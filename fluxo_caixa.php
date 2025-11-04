<?php
session_start();
require_once 'config.php';
require_once 'app/core/auth_check.php';
require_once 'app/controllers/FluxoCaixaController.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'financeiro', 'gerencia']);

$controller = new FluxoCaixaController($pdo);
$action = $_GET['action'] ?? 'index';

$method = $_SERVER['REQUEST_METHOD']; // Captura o método: GET, POST, etc.

switch ($action) {
    case 'store':
        if ($method === 'POST') {
            $controller->store();
        }
        break;

case 'delete':
        // A ação de deletar só executa se o método for POST
        if ($method === 'POST') {
            // A lógica de exclusão que estava no controller vem para cá
            if (!empty($_POST['id'])) {
                // Passamos o ID recebido via POST para o método delete
                if ($controller->delete($_POST['id'])) {
                    $_SESSION['message'] = "Lançamento excluído com sucesso.";
                } else {
                    $_SESSION['error'] = "Erro ao excluir o lançamento.";
                }
            } else {
                $_SESSION['error'] = "ID para exclusão não foi fornecido.";
            }
            // Redireciona de volta para a página principal após a tentativa de exclusão
            header("Location: fluxo_caixa.php");
            exit();
        }
        break;
    case 'delete':
        $controller->delete();
        break;

    default:
        $controller->index();
        break;
}