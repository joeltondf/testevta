<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/services/VendorRecommendationEngine.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não suportado. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso não autorizado.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST ?? [];
}

$tipoServico = trim((string) ($payload['tipo_servico'] ?? ''));
$urgency = strtolower(trim((string) ($payload['urgency'] ?? 'media')));

if ($urgency === '') {
    $urgency = 'media';
}

try {
    $engine = new VendorRecommendationEngine($pdo);
    $scoredVendors = $engine->recommendVendors([
        'tipo_servico' => $tipoServico,
        'urgency' => $urgency,
    ], 0);

    $topRecommendations = array_slice($scoredVendors, 0, 3);

    $response = [
        'success' => true,
        'recommendations' => array_map(static function (array $vendor): array {
            return [
                'vendor_id' => $vendor['vendor_id'],
                'name' => $vendor['name'],
                'score' => $vendor['score'],
                'reason' => $vendor['reason'],
                'current_load' => $vendor['current_load'],
                'badge' => $vendor['badge'] ?? null,
                'max_concurrent_leads' => $vendor['max_concurrent_leads'] ?? 0,
                'details' => [
                    'specialty_score' => $vendor['specialty_score'] ?? 0,
                    'workload_score' => $vendor['workload_score'] ?? 0,
                    'conversion_rate' => $vendor['conversion_rate'] ?? 0,
                    'response_minutes' => $vendor['response_minutes'] ?? null,
                    'average_rating' => $vendor['average_rating'] ?? 0,
                ],
            ];
        }, $topRecommendations),
        'vendors' => array_map(static function (array $vendor): array {
            return [
                'id' => $vendor['vendor_id'],
                'name' => $vendor['name'],
                'score' => $vendor['score'],
                'current_load' => $vendor['current_load'],
                'max_concurrent_leads' => $vendor['max_concurrent_leads'] ?? 0,
            ];
        }, $scoredVendors),
        'requested' => [
            'tipo_servico' => $tipoServico,
            'urgency' => $urgency,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Vendor recommendation error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível gerar recomendações no momento.',
    ], JSON_UNESCAPED_UNICODE);
}
