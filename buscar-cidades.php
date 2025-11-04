<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/models/Configuracao.php';
require_once __DIR__ . '/app/services/OmieService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso não autorizado.'
    ]);
    exit;
}

$term = trim((string)($_GET['term'] ?? $_GET['q'] ?? $_GET['search'] ?? ''));
$uf = isset($_GET['uf']) ? strtoupper(substr(trim((string)$_GET['uf']), 0, 2)) : null;

if (mb_strlen($term) < 3) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Informe pelo menos três caracteres para pesquisar.'
    ]);
    exit;
}

try {
    $configModel = new Configuracao($pdo);
    $omieService = new OmieService($configModel, $pdo);
    $cities = $omieService->pesquisarCidades($term, $uf);

    $data = array_map(static function (OmieCidade $cidade): array {
        return [
            'code' => $cidade->codigo,
            'name' => $cidade->nome,
            'state' => $cidade->uf,
            'ibgeCode' => $cidade->codigoIbge,
            'siafiCode' => $cidade->codigoSiafi,
        ];
    }, $cities);

    echo json_encode([
        'success' => true,
        'cities' => $data,
        'count' => count($data)
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Erro ao buscar cidades na Omie: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível consultar a Omie no momento. Tente novamente.'
    ], JSON_UNESCAPED_UNICODE);
}
