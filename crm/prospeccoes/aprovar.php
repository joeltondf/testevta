<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';
require_once __DIR__ . '/../../app/services/ProspectionConversionService.php';

$prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userProfile = $_SESSION['user_perfil'] ?? '';
$managerProfiles = ['admin', 'gerencia', 'supervisor'];

if ($userId <= 0 || !in_array($userProfile, $managerProfiles, true)) {
    $_SESSION['error_message'] = 'Apenas perfis de gestão podem converter a prospecção diretamente.';
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
    exit();
}

try {
    $conversionService = new ProspectionConversionService($pdo);
    $redirectUrl = $conversionService->convert($prospeccao_id, $userId, $userId);
    $_SESSION['success_message'] = 'Prospecção convertida com sucesso.';
    header('Location: ' . $redirectUrl);
    exit();

} catch (PDOException $e) {
    die("Erro ao aprovar prospecção: " . $e->getMessage());
} catch (Throwable $exception) {
    $_SESSION['error_message'] = 'Não foi possível converter a prospecção: ' . $exception->getMessage();
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
    exit();
}
?>