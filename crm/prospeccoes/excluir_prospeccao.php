<?php
// Arquivo: crm/prospeccoes/excluir_prospeccao.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Notificacao.php';

// 1. Segurança: Verifica se o usuário tem o perfil correto
if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
    die("Acesso negado. Você não tem permissão para realizar esta ação.");
}

// 2. Valida se a requisição é POST e se o ID foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    die("ID inválido.");
}

try {
    $notificationLink = '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id;
    $notificacaoModel = new Notificacao($pdo);

    $stmt = $pdo->prepare("DELETE FROM prospeccoes WHERE id = ?");
    $stmt->execute([$prospeccao_id]);

    $notificacaoModel->resolverPorReferencia('prospeccao_exclusao', (int)$prospeccao_id);

    if ($stmt->rowCount() === 0) {
        $_SESSION['success_message'] = 'Os alertas desta prospecção foram removidos. Ela já havia sido excluída.';
    } else {
        $_SESSION['success_message'] = 'Prospecção excluída com sucesso e alertas removidos.';
    }

    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;

} catch (PDOException $e) {
    die("Erro ao excluir a prospecção: " . $e->getMessage());
}
?>