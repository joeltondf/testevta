<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Configuracao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$userModel = new User($pdo);
$authorizedProfiles = ['admin', 'gerencia', 'supervisor'];

$allowedContexts = ['vendor_delegation', 'prospection_conversion'];
$validationMode = $_POST['validation_mode'] ?? 'manager_login';
$authorizationContext = $_POST['authorization_context'] ?? 'vendor_delegation';
if (!in_array($authorizationContext, $allowedContexts, true)) {
    $authorizationContext = 'vendor_delegation';
}

$login = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$managementPassword = $_POST['management_password'] ?? '';

$configModel = new Configuracao($pdo);

if ($validationMode === 'management_password') {
    $managementPassword = trim($managementPassword);
    if ($managementPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Informe a senha da gerência.']);
        exit;
    }

    $storedHash = $configModel->get('prospection_management_password_hash');
    if (!$storedHash) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nenhuma senha de gerência está configurada.']);
        exit;
    }

    if (!password_verify($managementPassword, $storedHash)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Senha da gerência incorreta.']);
        exit;
    }

    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($currentUserId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sessão inválida para autorizar esta ação.']);
        exit;
    }

    $managerUser = $userModel->getById($currentUserId);
    if (!$managerUser || !in_array($managerUser['perfil'], $authorizedProfiles, true) || (int) ($managerUser['ativo'] ?? 1) !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'A senha da gerência só pode ser usada por gestores ativos.']);
        exit;
    }
} else {
    if ($login === false || $login === null || trim($login) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Informe um e-mail válido.']);
        exit;
    }

    if ($password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Informe a senha do gestor.']);
        exit;
    }

    $managerUser = $userModel->getByEmail($login);
    if (!$managerUser || !in_array($managerUser['perfil'], $authorizedProfiles, true) || (int) ($managerUser['ativo'] ?? 1) !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas para o gestor informado.']);
        exit;
    }

    if (!password_verify($password, $managerUser['senha'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Senha incorreta.']);
        exit;
    }
}

if (!isset($_SESSION['manager_authorization_tokens']) || !is_array($_SESSION['manager_authorization_tokens'])) {
    $_SESSION['manager_authorization_tokens'] = [];
}

$now = time();
foreach ($_SESSION['manager_authorization_tokens'] as $tokenValue => $tokenData) {
    if (!is_array($tokenData) || ($tokenData['expires_at'] ?? 0) < $now) {
        unset($_SESSION['manager_authorization_tokens'][$tokenValue]);
    }
}

$token = bin2hex(random_bytes(16));
$_SESSION['manager_authorization_tokens'][$token] = [
    'manager_id' => (int) $managerUser['id'],
    'context' => $authorizationContext,
    'created_at' => $now,
    'expires_at' => $now + 300,
];

echo json_encode([
    'success' => true,
    'token' => $token,
    'managerName' => $managerUser['nome_completo'] ?? '',
    'context' => $authorizationContext,
]);
