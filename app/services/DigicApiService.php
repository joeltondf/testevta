<?php
// /app/services/DigicApiService.php

class DigicApiService
{
    /** @var string A URL base da API, configurada no painel de admin. */
    private $apiUrl;

    /** @var string O token de autorização da API. */
    private $token;

    /**
     * Construtor do serviço da API.
     * @param string $apiUrl A URL base da API Digisac (ex: https://fattoassessoria.digisac.me/api/v1).
     * @param string $token O token de autorização Bearer.
     */
    public function __construct(string $apiUrl, string $token)
    {
        // Garante que a URL não tenha uma barra no final para evitar erros.
        $this->apiUrl = rtrim($apiUrl, '/'); 
        $this->token = $token;
    }

    /**
     * Realiza uma requisição para a API Digisac.
     * @param string $method O método HTTP (GET, POST).
     * @param string $endpoint O endpoint da API (ex: /services).
     * @param array $queryParams Parâmetros para requisições GET.
     * @param array $postData Corpo da requisição para POST.
     * @return array|null A resposta da API decodificada ou nulo em caso de erro.
     */
    private function makeRequest(string $method, string $endpoint, array $queryParams = [], array $postData = [])
    {
        $url = $this->apiUrl . $endpoint;
        if ($method === 'GET' && !empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === 'POST' && !empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 400) {
            error_log("Erro na API Digisac ($endpoint): HTTP $http_code - " . $response);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Busca a lista de conexões (services) ativas na Digisac.
     * @return array Lista de conexões no formato ['id' => ..., 'name' => ...].
     */
    public function getConexoes(): array
    {
        $response = $this->makeRequest('GET', '/services', ['perPage' => 100]);
        $conexoesAtivas = [];

        if (isset($response['data'])) {
            foreach ($response['data'] as $conexao) {
                // Filtra para pegar apenas conexões que não estão arquivadas
                if (empty($conexao['archivedAt'])) {
                    $conexoesAtivas[] = [
                        'id' => $conexao['id'],
                        'name' => $conexao['name']
                    ];
                }
            }
        }
        return $conexoesAtivas;
    }

    /**
     * Busca a lista de templates de WhatsApp que estão aprovados.
     * @return array Lista de templates no formato ['id' => hsmId, 'name' => ...].
     */
    public function getTemplates(): array
    {
        $response = $this->makeRequest('GET', '/whatsapp-business-templates', ['perPage' => 200]);
        $templatesAprovados = [];

        if (isset($response['data'])) {
            foreach ($response['data'] as $template) {
                if (isset($template['status']) && $template['status'] === 'APPROVED') {
                    $templatesAprovados[] = [
                        'id' => $template['id'], // Este é o hsmId que você precisa
                        'name' => $template['name']
                    ];
                }
            }
        }
        return $templatesAprovados;
    }

    /**
     * Busca a lista de usuários ativos na conta Digisac.
     * @return array Uma lista de usuários com seu ID e nome.
     */
    public function getUsers(): array
    {
        $response = $this->makeRequest('GET', '/users', ['perPage' => 200]);
        $activeUsers = [];

        if (isset($response['data'])) {
            foreach ($response['data'] as $user) {
                // Listaremos apenas usuários que não estão arquivados
                if (empty($user['archivedAt'])) {
                    $activeUsers[] = [
                        'id' => $user['id'],
                        'name' => $user['name']
                    ];
                }
            }
        }
        return $activeUsers;
    }

    /**
     * Envia uma mensagem de template com a estrutura JSON exata do Postman.
     */
    public function sendMessageByNumber(string $number, string $serviceId, string $hsmId, array $params, ?string $userId = null): bool
    {
        $parametersPayload = [];
        foreach ($params as $paramValue) {
            $parametersPayload[] = ["type" => "text", "text" => (string)$paramValue];
        }

        $payload = [
            "type" => "chat",
            "number" => $number,
            "serviceId" => $serviceId,
            "hsmId" => $hsmId,
            "files" => [],
            "uploadingFiles" => false,
            "replyTo" => null,
            "parameters" => [
                [
                    "type" => "body",
                    "parameters" => $parametersPayload
                ]
            ],
            "file" => (object)[]
        ];

        // Adiciona o ID do usuário ao payload se ele for fornecido
        if ($userId) {
            $payload['userId'] = $userId;
        }
        
        $response = $this->makeRequest('POST', '/messages', [], $payload);
        return isset($response['id']);
    }
}