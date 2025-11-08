<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/models/Configuracao.php';
require_once __DIR__ . '/../app/services/DigisacService.php';

header('Content-Type: application/json');

try {
    $configModel = new Configuracao($pdo);
    $rawWhitelist = $configModel->get('digisac_allowed_ips');
    $allowedIps = ['xxx.xxx.xxx.xxx'];

    if (is_string($rawWhitelist) && trim($rawWhitelist) !== '') {
        $databaseIps = array_filter(array_map('trim', explode(',', $rawWhitelist)));
        if (!empty($databaseIps)) {
            $allowedIps = $databaseIps;
        }
    }

    $allowedIps[] = '127.0.0.1';
    $allowedIps[] = '::1';

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remoteIp === '' || !in_array($remoteIp, $allowedIps, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $service = new DigisacService($pdo);
    $service->handleWebhook($payload);

    echo json_encode(['success' => true]);
} catch (Throwable $exception) {
    error_log('Digisac webhook error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}
