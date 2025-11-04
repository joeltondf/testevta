<?php
// /tradutores.php (na pasta raiz do projeto)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/TradutoresController.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'colaborador', 'gerencia', 'supervisor']);

$controller = new TradutoresController($pdo);

// Roteamento simples baseado no parâmetro 'action' da URL
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'create':
        $controller->create();
        break;
    case 'store':
        $controller->store();
        break;
    case 'edit':
        $controller->edit($id);
        break;
    case 'update':
        $controller->update($id);
        break;
    case 'delete':
        $controller->delete($id);
        break;
    case 'index':
    default:
        $controller->index();
        break;
}
?>
