<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/models/Configuracao.php';
require_once __DIR__ . '/../app/services/OmieService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$term = trim((string) ($_GET['termo'] ?? ''));
$uf = isset($_GET['uf']) ? strtoupper(substr(trim((string) $_GET['uf']), 0, 2)) : null;

if (mb_strlen($term) < 3) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe pelo menos três caracteres para pesquisar.']);
    exit;
}

try {
    $configModel = new Configuracao($pdo);
    $service = new OmieService($configModel, $pdo);
    $cities = $service->pesquisarCidades($term, $uf);

    $payload = [
        'success' => true,
        'cidades' => array_map(static fn (OmieCidade $cidade) => $cidade->toArray(), $cities),
    ];

    if (empty($payload['cidades'])) {
        $payload['message'] = 'Nenhuma cidade encontrada.';
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Erro ao pesquisar cidades na Omie: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível consultar a Omie no momento. Tente novamente.',
    ], JSON_UNESCAPED_UNICODE);
}

