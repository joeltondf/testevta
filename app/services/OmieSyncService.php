<?php

declare(strict_types=1);


require_once __DIR__ . '/OmieService.php';
require_once __DIR__ . '/../models/OmieProduto.php';

class OmieSyncService
{
    private PDO $pdo;
    private Configuracao $configModel;
    private OmieService $omieService;
    private OmieProduto $omieProdutoModel;

    public function __construct(PDO $pdo, Configuracao $configModel)
    {
        $this->pdo = $pdo;
        $this->configModel = $configModel;
        $this->omieService = new OmieService($configModel);
        $this->omieProdutoModel = new OmieProduto($pdo);
    }

    public function syncSupportTables(): array
    {
        return [
            'produtos' => $this->syncProdutos(),
        ];
    }

    public function persistLocalProductMetadata(int $localProductId, array $data): void
    {
        $this->omieProdutoModel->upsert([
            'local_produto_id' => $localProductId,
            'descricao' => $data['nome_categoria'] ?? $data['descricao'] ?? '',
            'valor_unitario' => $data['valor_padrao'] ?? $data['valor_unitario'] ?? null,
            'codigo' => $this->normalizeProductCode($data['codigo'] ?? null),
            'codigo_integracao' => $data['codigo_integracao'] ?? null,
            'ncm' => $this->normalizeNcm($data['ncm'] ?? null),
            'unidade' => $data['unidade'] ?? null,
            'cfop' => $data['cfop'] ?? null,
            'codigo_servico_municipal' => $this->normalizeMunicipalServiceCode($data['codigo_servico_municipal'] ?? null),
            'ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 1,
        ]);
    }

    public function syncLocalProduct(int $localProductId, array $data): array
    {
        $this->persistLocalProductMetadata($localProductId, $data);
        $localRecord = $this->omieProdutoModel->findByLocalProductId($localProductId);

        if (!$localRecord) {
            throw new Exception('Não foi possível carregar os metadados locais do produto para sincronização.');
        }

        $codigoIntegracao = $this->normalizeString($localRecord['codigo_integracao'] ?? null)
            ?? $this->normalizeString($data['codigo_integracao'] ?? null)
            ?? $this->generateIntegrationCode($localProductId);

        $codigo = $this->normalizeProductCode($localRecord['codigo'] ?? null)
            ?? $this->normalizeProductCode($data['codigo'] ?? null)
            ?? $this->normalizeProductCode($codigoIntegracao)
            ?? $this->normalizeProductCode($this->generateProductCode($localProductId));

        $valorUnitario = $localRecord['valor_unitario']
            ?? $this->normalizeCurrency($data['valor_padrao'] ?? null)
            ?? '0';

        $payload = [
            'codigo_produto_integracao' => $codigoIntegracao,
            'codigo' => $codigo,
            'descricao' => $localRecord['descricao'],
            'valor_unitario' => (float)$valorUnitario,
            'unidade' => $localRecord['unidade'] ?? $this->normalizeString($data['unidade'] ?? 'UN') ?? 'UN',
        ];

        $ncm = $localRecord['ncm'] ?? $this->normalizeNcm($data['ncm'] ?? null);
        if ($ncm) {
            $payload['ncm'] = $ncm;
        }

        $cfop = $localRecord['cfop'] ?? $this->normalizeString($data['cfop'] ?? null);
        if ($cfop) {
            $payload['cfop'] = $cfop;
        }

        $codigoProdutoExistente = $this->normalizeString($localRecord['codigo_produto'] ?? null);
        if ($codigoProdutoExistente) {
            $payload['codigo_produto'] = $codigoProdutoExistente;
        }

        try {
            $response = $codigoProdutoExistente
                ? $this->omieService->alterarProduto($payload)
                : $this->omieService->incluirProduto($payload);
        } catch (Exception $exception) {
            throw $this->wrapOmieSyncException($exception, $ncm);
        }

        $codigoProdutoOmie = $this->resolveCodigoProdutoOmie(
            $response,
            $codigoIntegracao,
            $codigoProdutoExistente
        );

        $inputMunicipalCode = $this->normalizeMunicipalServiceCode(
            $data['codigo_servico_municipal'] ?? ($localRecord['codigo_servico_municipal'] ?? null)
        );
        $municipalCode = $inputMunicipalCode;

        if ($municipalCode === null && $codigoProdutoOmie !== null) {
            $municipalCode = $this->fetchMunicipalServiceCode($codigoProdutoOmie)
                ?? ($localRecord['codigo_servico_municipal'] ?? null);
        }
   

        $this->omieProdutoModel->upsert([
            'local_produto_id' => $localProductId,
            'descricao' => $localRecord['descricao'],
            'valor_unitario' => $valorUnitario,
            'unidade' => $payload['unidade'],
            'ncm' => $payload['ncm'] ?? null,
            'cfop' => $payload['cfop'] ?? null,
            'codigo' => $codigo,
            'codigo_integracao' => $codigoIntegracao,
            'codigo_produto' => $codigoProdutoOmie,
            'codigo_servico_municipal' => $this->normalizeMunicipalServiceCode($municipalCode),
            'ativo' => 1,
        ]);

        return [
            'codigo_produto' => $codigoProdutoOmie,
            'codigo_integracao' => $codigoIntegracao,
            'codigo' => $codigo,
            'operation' => $codigoProdutoExistente ? 'updated' : 'created',
        ];
    }

    public function deactivateLocalProduct(int $localProductId): void
    {
        $this->omieProdutoModel->deactivateByLocalProductId($localProductId);
    }

    public function removeLocalProduct(int $localProductId): void
    {
        $this->omieProdutoModel->deleteByLocalProductId($localProductId);
    }

    public function syncProdutos(): array
    {
        $items = $this->omieService->listarProdutos(['apenas_importado_api' => 'N']);
        $codes = [];

        foreach ($items as $item) {
            $codigoProduto = $this->extractCodigo($item, ['codigo_produto', 'cCodigo', 'nCodigo']);
            $descricao = $this->extractValue($item, ['descricao', 'cDescricao', 'nome']);
            if ($descricao === null && $codigoProduto === null) {
                continue;
            }

            $codigoIntegracao = $this->extractValue($item, ['codigo_produto_integracao', 'codigo_integracao']);
            $codigoInterno = $this->extractValue($item, ['codigo']);
            $cfop = $this->extractValue($item, ['cfop', 'codigo_cfop']);
            $ncm = $this->extractValue($item, ['ncm', 'cNCM']);
            $unidade = $this->extractValue($item, ['unidade', 'cUnidade']);
            $valorUnitario = $this->extractValue($item, ['valor_unitario', 'valor_unitario_cadastro', 'valor_venda']);
            $localId = $this->extractNumericValue($item, ['codigo_local_estoque', 'codigo_local']);
            $existingRecord = $codigoProduto !== null
                ? $this->omieProdutoModel->findByCodigoProduto((string)$codigoProduto)
                : null;
            $municipalCode = null;

            if ($codigoProduto !== null) {
                $municipalCode = $this->fetchMunicipalServiceCode((string)$codigoProduto);
                if ($municipalCode === null && $existingRecord) {
                    $municipalCode = $existingRecord['codigo_servico_municipal'] ?? null;
                }
            }

            $this->omieProdutoModel->upsert([
                'codigo_produto' => $codigoProduto,
                'codigo_integracao' => $codigoIntegracao,
                'descricao' => $descricao ?? (string)$codigoProduto,
                'cfop' => $cfop,
                'codigo' => $this->normalizeProductCode($codigoInterno),
                'codigo_servico_municipal' => $this->normalizeMunicipalServiceCode($municipalCode),
                'ncm' => $ncm,
                'unidade' => $unidade,
                'valor_unitario' => $valorUnitario,
                'local_produto_id' => $localId,
                'ativo' => $this->isActive($item) ? 1 : 0,
            ]);

            if ($codigoProduto !== null) {
                $codes[] = (string)$codigoProduto;
            }
        }

        $this->markMissingRecords('omie_produtos', 'codigo_produto', $codes);

        return ['total' => count($items)];
    }

    private function extractCodigo(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && $item[$key] !== '') {
                return (string)$item[$key];
            }
        }

        return null;
    }

    private function extractValue(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!isset($item[$key])) {
                continue;
            }

            $value = $item[$key];
            if (is_array($value)) {
                continue;
            }

            $trimmed = trim((string)$value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function extractNumericValue(array $item, array $keys): ?int
    {
        $value = $this->extractValue($item, $keys);
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (int)$value;
    }

    private function isActive(array $item): bool
    {
        foreach (['inativo', 'cInativo'] as $key) {
            if (!isset($item[$key])) {
                continue;
            }

            $flag = $item[$key];
            if (is_bool($flag)) {
                return !$flag;
            }

            $normalized = strtoupper(trim((string)$flag));
            if (in_array($normalized, ['S', 'Y', '1'], true)) {
                return false;
            }
        }

        return true;
    }

    private function markMissingRecords(string $table, string $column, array $identifiers): void
    {
        $identifiers = array_values(array_filter(array_unique(array_map('strval', $identifiers)), static function ($value) {
            return $value !== '';
        }));

        if (empty($identifiers)) {
            $this->pdo->exec("UPDATE {$table} SET ativo = 0");
            return;
        }

        $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
        $sql = "UPDATE {$table} SET ativo = 0 WHERE {$column} NOT IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($identifiers);
    }

    private function resolveCodigoProdutoOmie(array $response, string $codigoIntegracao, ?string $codigoProdutoExistente): string
    {
        $codigoDireto = $this->extractCodigoProduto($response);
        if ($codigoDireto !== null) {
            return $codigoDireto;
        }

        $codigoRegistrado = $this->normalizeString($codigoProdutoExistente);
        if ($codigoRegistrado !== null) {
            return $codigoRegistrado;
        }

        $codigoIntegracaoNormalizado = $this->normalizeString($codigoIntegracao);
        if ($codigoIntegracaoNormalizado === null) {
            throw new Exception('Produto sincronizado na Omie, mas nenhum código foi retornado. Verifique o cadastro e tente novamente.');
        }

        $codigoConsultado = $this->fetchCodigoProdutoFromOmie($codigoIntegracaoNormalizado);
        if ($codigoConsultado === null) {
            throw new Exception('Produto sincronizado na Omie, mas não foi possível recuperar o código do item. Verifique o cadastro na Omie e tente novamente.');
        }

        return $codigoConsultado;
    }

    private function fetchCodigoProdutoFromOmie(string $codigoIntegracao): ?string
    {
        try {
            $detalhes = $this->omieService->consultarProdutoPorIntegracao($codigoIntegracao);
        } catch (Exception $exception) {
            throw new Exception(
                'Produto sincronizado na Omie, mas não foi possível recuperar o código do item: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $this->extractCodigoProduto($detalhes);
    }

    private function extractCodigoProduto(array $payload): ?string
    {
        foreach (['codigo_produto', 'codigo_produto_omie'] as $key) {
            if (array_key_exists($key, $payload)) {
                $codigo = $this->normalizeString($payload[$key]);
                if ($codigo !== null) {
                    return $codigo;
                }
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $codigo = $this->extractCodigoProduto($value);
                if ($codigo !== null) {
                    return $codigo;
                }
            }
        }

        return null;
    }

    private function fetchMunicipalServiceCode(string $codigoProduto): ?string
    {
        $codigo = $this->normalizeString($codigoProduto);
        if ($codigo === null) {
            return null;
        }

        try {
            $serviceDetails = $this->omieService->consultarServicoPorCodigo($codigo);
            $code = $this->extractMunicipalServiceCode($serviceDetails);
            if ($code !== null) {
                return $code;
            }
        } catch (Exception $exception) {
            error_log('Omie Sync: falha ao consultar serviço ' . $codigo . ': ' . $exception->getMessage());
        }

        try {
            $response = $this->omieService->listarCadastroServico([
                'pagina' => 1,
                'registros_por_pagina' => 1,
                'codigo_servico' => $codigo,
            ]);
            $code = $this->extractMunicipalServiceCode($response);
            if ($code !== null) {
                return $code;
            }
        } catch (Exception $exception) {
            error_log('Omie Sync: falha ao listar cadastro do serviço ' . $codigo . ': ' . $exception->getMessage());
        }

        try {
            $details = $this->omieService->consultarProdutoPorCodigo($codigo);
            $code = $this->extractMunicipalServiceCode($details);
            if ($code !== null) {
                return $code;
            }
        } catch (Exception $exception) {
            error_log('Omie Sync: falha ao consultar detalhes do serviço ' . $codigo . ': ' . $exception->getMessage());
        }

        error_log('Omie Sync: serviço ' . $codigo . ' não retornou código municipal (cCodServMun).');

        return null;
    }

    public function deleteProduct(int $localProductId): void
    {
        $metadata = $this->omieProdutoModel->findByLocalProductId($localProductId);
        if ($metadata) {
            $codigoProduto = $this->normalizeString($metadata['codigo_produto'] ?? null);
            $codigoIntegracao = $this->normalizeString($metadata['codigo_integracao'] ?? null);

            if ($codigoProduto !== null || $codigoIntegracao !== null) {
                $payload = $codigoProduto !== null
                    ? ['codigo_produto' => $codigoProduto]
                    : ['codigo_produto_integracao' => $codigoIntegracao];

                $caughtException = null;

                try {
                    $this->omieService->excluirProduto($payload);
                } catch (Exception $exception) {
                    if (!$this->isNotFoundError($exception->getMessage())) {
                        $caughtException = new Exception(
                            'Falha ao excluir o produto na Omie (código: ' . ($codigoProduto ?? $codigoIntegracao ?? 'indefinido') . '): ' . $exception->getMessage(),
                            0,
                            $exception
                        );
                    }
                } finally {
                    $this->removeLocalProduct($localProductId);
                }

                if ($caughtException !== null) {
                    throw $caughtException;
                }

                return;
            }
        }

        $this->removeLocalProduct($localProductId);
    }

    private function wrapOmieSyncException(Exception $exception, ?string $ncm = null): Exception
    {
        $message = $exception->getMessage();

        if ($this->isNcmNotRegisteredError($message)) {
            if ($ncm !== null) {
                $formattedNcm = $this->formatNcmForMessage($ncm);
                $ncmPhrase = 'o NCM "' . $formattedNcm . '"';
            } else {
                $ncmPhrase = 'o NCM informado';
            }

            return new Exception(
                'A Omie recusou ' . $ncmPhrase . '. Cadastre esse NCM na Omie ou deixe o campo em branco e tente novamente.',
                0,
                $exception
            );
        }

        return new Exception('Falha ao sincronizar o produto com a Omie: ' . $message, 0, $exception);
    }

    private function isNotFoundError(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return strpos($normalized, 'não encontrado') !== false
            || strpos($normalized, 'nao encontrado') !== false
            || strpos($normalized, 'não foi localizado') !== false
            || strpos($normalized, 'nao foi localizado') !== false
            || strpos($normalized, 'não foi encontrada') !== false
            || strpos($normalized, 'registro inexistente') !== false
            || strpos($normalized, 'registro nao localizado') !== false;
    }

    private function isNcmNotRegisteredError(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return strpos($normalized, 'ncm') !== false
            && (strpos($normalized, 'não cadas') !== false || strpos($normalized, 'nao cadas') !== false);
    }

    private function normalizeNcm($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_float($value)) {
            $value = (string)(int)$value;
        } elseif (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, 8);
    }

    private function formatNcmForMessage(string $ncm): string
    {
        $digits = preg_replace('/\D+/', '', $ncm);
        if (strlen($digits) === 8) {
            return substr($digits, 0, 4) . '.' . substr($digits, 4, 2) . '.' . substr($digits, 6, 2);
        }

        return $ncm;
    }

    private function extractMunicipalServiceCode($payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        if (array_key_exists('cCodServMun', $payload)) {
            $code = $this->normalizeMunicipalServiceCode($payload['cCodServMun']);
            if ($code !== null) {
                return $code;
            }
        }

        foreach ($payload as $value) {
            $code = $this->extractMunicipalServiceCode($value);
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    private function generateIntegrationCode(int $localProductId): string
    {
        return 'LP-' . str_pad((string)$localProductId, 6, '0', STR_PAD_LEFT);
    }

    private function generateProductCode(int $localProductId): string
    {
        return 'PRD-' . str_pad((string)$localProductId, 6, '0', STR_PAD_LEFT);
    }

    private function normalizeMunicipalServiceCode($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_float($value)) {
            $value = (string)(int)$value;
        } elseif (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits !== '') {
            return substr($digits, 0, 10);
        }

        return mb_substr($trimmed, 0, 10);
    }

    private function normalizeProductCode($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 50);
    }


    private function normalizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeCurrency($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float)$value, 4, '.', '');
        }

        $normalized = str_replace(['R$', ' '], '', (string)$value);
        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (strpos($normalized, ',') !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? number_format((float)$normalized, 4, '.', '') : null;
    }
}
