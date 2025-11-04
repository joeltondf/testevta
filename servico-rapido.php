<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php';
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Cliente.php';
require_once __DIR__ . '/app/models/Notificacao.php';
require_once __DIR__ . '/app/models/Vendedor.php';


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$controller = new ProcessosController($pdo);
$action = $_GET['action'] ?? 'create';

switch ($action) {
    case 'create':
        // Chama a função para exibir o formulário de serviço rápido
        $controller->createServicoRapido();
        break;
    case 'store':
        // Agora chama o método STORE principal
        $controller->store();
        break;
    default:
        // Se nenhuma ação for especificada, exibe o formulário por padrão
        $controller->createServicoRapido();
        break;
}