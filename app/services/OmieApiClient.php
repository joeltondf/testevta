<?php

declare(strict_types=1);


class OmieApiClient
{
    private const BASE_API_URL = 'https://app.omie.com.br/api/v1';

    /**
     * Executa uma chamada simples para a API da Omie.
     *
     * @param string $appKey    Chave da aplicação registrada na Omie.
     * @param string $appSecret Segredo da aplicação registrada na Omie.
     * @param string $endpoint  Caminho do endpoint (ex.: /geral/clientes/).
     * @param string $call      Nome do método a ser executado (ex.: ListarClientes).
     * @param array  $params    Parâmetros específicos do método.
     *
     * @return array Resposta decodificada da API.
     */
    public static function call(string $appKey, string $appSecret, string $endpoint, string $call, array $params = []): array
    {
        if ($appKey === '' || $appSecret === '') {
            throw new InvalidArgumentException('As credenciais da API Omie (app_key e app_secret) são obrigatórias.');
        }

        $payload = [
            'call' => $call,
            'app_key' => $appKey,
            'app_secret' => $appSecret,
            'param' => [empty($params) ? new stdClass() : $params],
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('Falha ao codificar o payload para a API Omie: ' . json_last_error_msg());
        }

        $url = self::buildUrl($endpoint);
        self::log('request', [
            'endpoint' => $endpoint,
            'call' => $call,
            'payload' => self::sanitizePayloadForLog($payload),
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload),
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('Erro de comunicação com a API Omie: ' . $curlError);
        }

        if ($response === false) {
            throw new RuntimeException('A API Omie retornou uma resposta vazia.');
        }

        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Não foi possível interpretar a resposta da API Omie: ' . json_last_error_msg());
        }

        self::log('response', [
            'endpoint' => $endpoint,
            'call' => $call,
            'status' => $httpCode,
            'body' => self::truncateForLog($decodedResponse),
        ]);

        if ($httpCode >= 400 || (is_array($decodedResponse) && isset($decodedResponse['faultstring']))) {
            $message = is_array($decodedResponse) ? ($decodedResponse['faultstring'] ?? $response) : $response;
            throw new RuntimeException("Erro na API Omie (HTTP {$httpCode}): {$message}");
        }

        return is_array($decodedResponse) ? $decodedResponse : [];
    }

    /**
     * Realiza chamadas paginadas para a API, retornando todos os registros encontrados.
     *
     * @param string $endpoint Caminho do endpoint (ex.: /geral/clientes/).
     * @param string $call     Nome do método a ser executado.
     * @param array  $dataKeys Possíveis chaves na resposta que armazenam a lista desejada.
     * @param array  $params   Parâmetros adicionais enviados à API.
     *
     * @return array Lista completa agregada a partir de todas as páginas.
     */
    public static function fetchAll(
        string $appKey,
        string $appSecret,
        string $endpoint,
        string $call,
        array $dataKeys,
        array $params = []
    ): array {
        $currentPage = max(1, (int)($params['pagina'] ?? 1));
        $perPage = max(1, (int)($params['registros_por_pagina'] ?? 500));
        $allItems = [];
        $hasMore = true;

        while ($hasMore) {
            $pageParams = $params;
            $pageParams['pagina'] = $currentPage;
            $pageParams['registros_por_pagina'] = $perPage;

            $response = self::call($appKey, $appSecret, $endpoint, $call, $pageParams);
            $items = self::extractDataList($response, $dataKeys);
            if (!empty($items)) {
                $allItems = array_merge($allItems, $items);
            }

            $responsePage = isset($response['pagina']) ? (int)$response['pagina'] : $currentPage;
            $totalPages = isset($response['total_de_paginas']) ? (int)$response['total_de_paginas'] : $responsePage;
            $hasMore = $responsePage < $totalPages;

            if (!$hasMore && isset($response['proxima_pagina'])) {
                $hasMore = self::hasNextPage($response['proxima_pagina']);
            }

            if ($hasMore) {
                $currentPage = $responsePage + 1;
            }
        }

        return $allItems;
    }

    private static function buildUrl(string $endpoint): string
    {
        $base = rtrim(self::BASE_API_URL, '/');
        $path = '/' . ltrim($endpoint, '/');
        return $base . $path;
    }

    private static function extractDataList(array $response, array $dataKeys): array
    {
        foreach ($dataKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        foreach ($response as $value) {
            if (self::isSequentialArrayOfArrays($value)) {
                return array_values($value);
            }
        }

        return [];
    }

    private static function hasNextPage($nextPageFlag): bool
    {
        if (is_numeric($nextPageFlag)) {
            return (int)$nextPageFlag > 0;
        }

        if (is_string($nextPageFlag)) {
            $normalized = strtoupper(trim($nextPageFlag));
            return $normalized === 'S' || $normalized === 'Y';
        }

        if (is_bool($nextPageFlag)) {
            return $nextPageFlag;
        }

        return false;
    }

    private static function isSequentialArrayOfArrays($value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        $keys = array_keys($value);
        if ($keys !== range(0, count($value) - 1)) {
            return false;
        }

        return is_array($value[0]);
    }

    private static function log(string $type, array $context): void
    {
        if (defined('OMIE_DISABLE_LOGS') && OMIE_DISABLE_LOGS) {
            return;
        }

        $message = sprintf('[OmieApi:%s] %s', strtoupper($type), json_encode($context, JSON_UNESCAPED_UNICODE));
        error_log($message);
    }

    private static function sanitizePayloadForLog(array $payload): array
    {
        $sanitized = $payload;
        unset($sanitized['app_key'], $sanitized['app_secret']);

        if (isset($sanitized['param']) && is_array($sanitized['param'])) {
            $sanitized['param'] = array_map(static function ($item) {
                if (is_array($item)) {
                    $copy = $item;
                    unset($copy['app_key'], $copy['app_secret']);
                    return $copy;
                }
                return $item;
            }, $sanitized['param']);
        }

        return $sanitized;
    }

    private static function truncateForLog($data, int $limit = 2000): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '[unloggable]';
        }

        if (strlen($json) > $limit) {
            return substr($json, 0, $limit) . '... (truncated)';
        }

        return $json;
    }
}
