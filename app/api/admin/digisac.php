<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/models/Configuracao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'master'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$configModel = new Configuracao($pdo);

switch ($action) {
    case 'test-connection':
        $token = trim((string)($input['api_token'] ?? ''));
        if ($token === '') {
            echo json_encode(['success' => false, 'error' => 'Informe o token da API']);
            exit;
        }

        $apiUrl = $configModel->get('digisac_api_url');
        if (!is_string($apiUrl) || trim($apiUrl) === '') {
            $apiUrl = 'https://api.digisac.app';
        }
        $endpoint = rtrim($apiUrl, '/') . '/v1/account';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200) {
            echo json_encode(['success' => true]);
        } else {
            if ($error === '' && $response !== false) {
                $error = 'Token inválido ou erro na API';
            }
            echo json_encode([
                'success' => false,
                'error' => $error !== '' ? $error : 'Token inválido ou erro na API',
            ]);
        }
        exit;

    case 'save-config':
        $current = $configModel->getAll();

        $enabled = isset($input['enabled']) && $input['enabled'] !== '' ? 1 : 0;
        $departmentId = trim((string)($input['department_id'] ?? ''));
        $token = trim((string)($input['api_token'] ?? ''));

        $checkboxes = [
            'send_on_urgent_lead' => 'digisac_send_on_urgent_lead',
            'send_on_sla_warning' => 'digisac_send_on_sla_warning',
            'send_on_sla_overdue' => 'digisac_send_on_sla_overdue',
            'send_on_converted' => 'digisac_send_on_converted',
            'send_on_feedback_request' => 'digisac_send_on_feedback_request',
        ];

        $valuesToPersist = [
            'digisac_enabled' => $enabled,
            'digisac_default_department_id' => $departmentId,
        ];

        foreach ($checkboxes as $inputKey => $configKey) {
            $valuesToPersist[$configKey] = isset($input[$inputKey]) && $input[$inputKey] !== '' ? 1 : 0;
        }

        $existingToken = $current['digisac_api_token'] ?? '';
        if ($enabled === 1 && $token === '' && ($existingToken === '' || $existingToken === null)) {
            echo json_encode(['success' => false, 'error' => 'Informe o token da API para habilitar a integração.']);
            exit;
        }

        $changes = [];
        foreach ($valuesToPersist as $key => $value) {
            $previous = $current[$key] ?? null;
            if ((string) $previous !== (string) $value) {
                $changes[$key] = ['old' => $previous, 'new' => $value];
                $configModel->save($key, (string) $value);
            }
        }

        if ($token !== '') {
            $configModel->save('digisac_api_token', $token);
            $changes['digisac_api_token'] = ['old' => $existingToken !== '' ? '[defined]' : null, 'new' => '[updated]'];
        }

        if (!array_key_exists('digisac_api_url', $current) || trim((string)($current['digisac_api_url'] ?? '')) === '') {
            $configModel->save('digisac_api_url', 'https://api.digisac.app');
        }

        if (!empty($changes)) {
            try {
                $auditStmt = $pdo->prepare(
                    'INSERT INTO audit_logs (action, metadata, created_at) VALUES (:action, :metadata, :created_at)'
                );
                $metadata = [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'changes' => $changes,
                ];
                $auditStmt->bindValue(':action', 'digisac_config_update');
                $auditStmt->bindValue(':metadata', json_encode($metadata, JSON_UNESCAPED_UNICODE));
                $auditStmt->bindValue(':created_at', date('Y-m-d H:i:s'));
                $auditStmt->execute();
            } catch (Throwable $exception) {
                error_log('Digisac config audit log error: ' . $exception->getMessage());
            }
        }

        echo json_encode(['success' => true]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        exit;
}
