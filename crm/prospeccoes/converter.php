<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/services/ProspectionConversionService.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';
require_once __DIR__ . '/../../app/models/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit();
}

$prospectionId = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
if (!$prospectionId) {
    $_SESSION['error_message'] = 'Prospecção inválida.';
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userProfile = $_SESSION['user_perfil'] ?? '';

if ($userId <= 0) {
    $_SESSION['error_message'] = 'Sessão expirada. Faça login novamente.';
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$managerProfiles = ['admin', 'gerencia', 'supervisor'];
$authorizedManagerId = null;

if (in_array($userProfile, $managerProfiles, true)) {
    $authorizedManagerId = $userId;
} elseif ($userProfile === 'sdr') {
    $authorizationToken = $_POST['authorization_token'] ?? '';
    if ($authorizationToken === '') {
        $_SESSION['error_message'] = 'Informe as credenciais de um gestor para concluir a conversão.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
        exit();
    }

    $currentTime = time();
    if (isset($_SESSION['manager_authorization_tokens']) && is_array($_SESSION['manager_authorization_tokens'])) {
        foreach ($_SESSION['manager_authorization_tokens'] as $tokenValue => $tokenData) {
            if (!is_array($tokenData) || ($tokenData['expires_at'] ?? 0) < $currentTime) {
                unset($_SESSION['manager_authorization_tokens'][$tokenValue]);
            }
        }
        if (empty($_SESSION['manager_authorization_tokens'])) {
            unset($_SESSION['manager_authorization_tokens']);
        }
    }

    if (!isset($_SESSION['manager_authorization_tokens'][$authorizationToken])) {
        $_SESSION['error_message'] = 'A autorização do gestor expirou. Valide novamente para converter.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
        exit();
    }

    $authorizationData = $_SESSION['manager_authorization_tokens'][$authorizationToken];
    if (!is_array($authorizationData) || ($authorizationData['expires_at'] ?? 0) < $currentTime) {
        unset($_SESSION['manager_authorization_tokens'][$authorizationToken]);
        $_SESSION['error_message'] = 'A autorização do gestor expirou. Valide novamente para converter.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
        exit();
    }

    if (($authorizationData['context'] ?? '') !== 'prospection_conversion') {
        unset($_SESSION['manager_authorization_tokens'][$authorizationToken]);
        $_SESSION['error_message'] = 'A autorização informada não é válida para conversão.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
        exit();
    }

    $authorizedManagerId = (int) ($authorizationData['manager_id'] ?? 0);
    if ($authorizedManagerId <= 0) {
        unset($_SESSION['manager_authorization_tokens'][$authorizationToken]);
        $_SESSION['error_message'] = 'Não foi possível confirmar o gestor autorizado.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
        exit();
    }

    unset($_SESSION['manager_authorization_tokens'][$authorizationToken]);
} else {
    $_SESSION['error_message'] = 'Você não tem permissão para converter esta prospecção.';
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
    exit();
}

$conversionService = new ProspectionConversionService($pdo);

try {
    $redirectUrl = $conversionService->convert($prospectionId, $userId, $authorizedManagerId);
    $_SESSION['success_message'] = 'Prospecção convertida com sucesso.';
    header('Location: ' . $redirectUrl);
    exit();
} catch (Throwable $exception) {
    $_SESSION['error_message'] = 'Não foi possível converter a prospecção: ' . $exception->getMessage();
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
    exit();
}
