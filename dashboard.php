<?php
// /dashboard.php (na pasta raiz do projeto)

// 1. Carrega as configurações (sessão e BD)
require_once __DIR__ . '/config.php';

// 2. Executa o verificador de segurança. Se não estiver autenticado, redireciona para o login.
require_once __DIR__ . '/app/core/auth_check.php';

// Lógica para marcar notificação como lida
if (
    isset($_GET['action'], $_GET['id'])
    && in_array($_GET['action'], ['mark_notification_read', 'delete_notification'], true)
) {
    require_once __DIR__ . '/app/models/Notificacao.php';
    $notificacaoModel = new Notificacao($pdo);

    $notification_id = (int)$_GET['id'];

    if ($notificacaoModel->marcarComoLida($notification_id, (int)($_SESSION['user_id'] ?? 0))) {
        $_SESSION['success_message'] = "Notificação marcada como lida.";
    } else {
        $_SESSION['error_message'] = "Não foi possível atualizar a notificação.";
    }

    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    header("Location: " . $redirect_url);
    exit;
}


// 3. Carrega o controlador do dashboard
require_once __DIR__ . '/app/controllers/DashboardController.php';

// 4. Cria uma instância do controlador
$dashboardController = new DashboardController($pdo);

// 5. Chama o método para renderizar a página
$dashboardController->index();
?>
