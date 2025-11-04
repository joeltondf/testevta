<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php';
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Notificacao.php';

// Garante que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$controller = new ProcessosController($pdo);

// Ação padrão agora é exibir o painel de notificações
$action = $_GET['action'] ?? 'painelNotificacoes';

switch ($action) {
    case 'markRead':
        $notificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($notificationId) {
            $notificacaoModel = new Notificacao($pdo);
            if ($notificacaoModel->marcarComoLida($notificationId, (int)($_SESSION['user_id'] ?? 0))) {
                $_SESSION['success_message'] = 'Notificação marcada como lida.';
            } else {
                $_SESSION['error_message'] = 'Não foi possível atualizar a notificação.';
            }
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/notificacoes.php'));
        exit;
    case 'aprovar':
        // Mantemos a lógica de aprovação, caso ainda seja usada
        $controller->aprovarOrcamento();
        break;
    case 'recusar':
        // Mantemos a lógica de recusa, caso ainda seja usada
        $controller->recusarOrcamento();
        break;
    default:
        $controller->painelNotificacoes();
        break;
}