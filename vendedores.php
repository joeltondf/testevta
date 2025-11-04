<?php
// /vendedores.php

// Inicia a sessão para podermos usar as mensagens de sucesso/erro
session_start();

require_once 'config.php';
require_once 'app/core/auth_check.php';
require_once 'app/controllers/VendedoresController.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia', 'supervisor']);

// Cria uma instância do controlador, passando a conexão com o banco de dados
$controller = new VendedoresController($pdo);

// Define a ação com base no parâmetro GET, com 'index' como padrão
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
        if ($id) {
            $controller->edit($id);
        } else {
            // Redireciona se o ID não for fornecido
            header('Location: vendedores.php');
            exit();
        }
        break;
    case 'update':
        if ($id) {
            $controller->update($id);
        } else {
            // Redireciona se o ID não for fornecido
            header('Location: vendedores.php');
            exit();
        }
        break;
    case 'delete':
        if ($id) {
            $controller->delete($id);
        } else {
            // Redireciona se o ID não for fornecido
            header('Location: vendedores.php');
            exit();
        }
        break;
    case 'index':
    default:
        $controller->index();
        break;
}
?>