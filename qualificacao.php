<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['sdr']);
require_once __DIR__ . '/app/controllers/QualificacaoController.php';

$controller = new QualificacaoController($pdo);
$action = $_GET['action'] ?? 'create';
$prospeccaoId = (int) ($_GET['id'] ?? 0);

if ($action === 'store') {
    if ($prospeccaoId <= 0) {
        $_SESSION['error_message'] = 'Prospecção inválida.';
        header('Location: ' . APP_URL . '/sdr_dashboard.php');
        exit();
    }
    $controller->store($prospeccaoId);
} else {
    if ($prospeccaoId <= 0) {
        $_SESSION['error_message'] = 'Prospecção inválida.';
        header('Location: ' . APP_URL . '/sdr_dashboard.php');
        exit();
    }
    $controller->create($prospeccaoId);
}
