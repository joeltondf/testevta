<?php
// categorias.php

require_once 'config.php';
require_once __DIR__ . '/app/controllers/CategoriasController.php';

$controller = new CategoriasController($pdo);

// Define a ação e o ID a partir da URL
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Roteamento das ações para os métodos corretos do controller
switch ($action) {
    case 'store':
        $controller->store();
        break;
    case 'save':
        $controller->save();
        break;
    case 'update':
        $controller->update($id);
        break;
    case 'rename_group':
        $controller->renameGroup();
        break;
    case 'delete_group':
        $controller->deleteGroup();
        break;
    case 'deactivate': // Ação para desativar
        $controller->deactivate($id);
        break;
    case 'reactivate': // Ação para reativar
        $controller->reactivate($id);
        break;
    case 'delete_permanente': // Ação para excluir permanentemente
        $controller->delete_permanente($id);
        break;
    default:
        $controller->index();
        break;
}