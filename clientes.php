<?php
// /clientes.php (CORRIGIDO)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/ClientesController.php';
require_once __DIR__ . '/app/core/access_control.php';

$controller = new ClientesController($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// ===================================================================
// LÓGICA DE PERMISSÃO CORRIGIDA
// ===================================================================
// Verifica a permissão baseado na AÇÃO específica, em vez de bloquear o arquivo inteiro.
switch ($action) {
    case 'edit':
    case 'update':
        // Apenas 'edit' e 'update' são permitidos para vendedores no fluxo da prospecção.
        require_permission(['admin', 'gerencia', 'supervisor', 'vendedor']);
        break;

    default:
        // Todas as outras ações (listar, criar, deletar) continuam restritas.
        require_permission(['admin', 'gerencia', 'supervisor', 'colaborador']);
        break;
}
// ===================================================================

// O roteamento continua o mesmo
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
        $controller->update();
        break;
    case 'delete':
        $controller->delete($id);
        break;
    case 'get_json':
        $controller->getClienteJson();
        break;
    
    case 'index':
    default:
        $controller->index();
        break;
}
?>