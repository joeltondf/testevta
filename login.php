<?php
// /login.php (na pasta raiz do projeto)

// Carrega o ficheiro de configuração, que inicia a sessão e conecta à base de dados
require_once __DIR__ . '/config.php';

// Carrega o controlador que tem a lógica de autenticação
require_once __DIR__ . '/app/controllers/AuthController.php';

// Cria uma instância do controlador, passando a conexão PDO
$authController = new AuthController($pdo);

// Verifica qual ação o utilizador quer executar, com base no parâmetro na URL
// Ex: login.php?action=logout
// Se nenhuma ação for especificada, a ação por defeito é 'show' (mostrar o formulário)
$action = $_GET['action'] ?? 'show';

switch ($action) {
    case 'login':
        $authController->login();
        break;
    case 'logout':
        $authController->logout();
        break;
    case 'show':
    default:
        $authController->showLoginForm();
        break;
}
?>
