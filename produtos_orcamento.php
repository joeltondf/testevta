<?php
// produtos_orcamento.php

require_once 'config.php';
require_once __DIR__ . '/app/controllers/ProdutosOrcamentoController.php';

$controller = new ProdutosOrcamentoController($pdo);

$action = $_GET['action'] ?? 'index';
if (!is_string($action)) {
    $action = 'index';
}

$id = null;
if (isset($_GET['id'])) {
    $filteredId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($filteredId !== false && $filteredId > 0) {
        $id = $filteredId;
    }
}

switch ($action) {
    case 'store':
        $controller->store();
        break;
    case 'update':
        $controller->update($id);
        break;
    case 'delete':
        $controller->delete($id);
        break;
    default:
        $controller->index();
        break;
}