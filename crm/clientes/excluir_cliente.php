<?php
// Arquivo: crm/clientes/excluir_cliente.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

// 1. Segurança: Verifica se o usuário tem o perfil correto
if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
    die("Acesso negado. Você não tem permissão para realizar esta ação.");
}

// 2. Valida se a requisição é POST e se o ID foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit;
}

$cliente_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$cliente_id) {
    die("ID de lead inválido.");
}

// Inicia uma transação para garantir a integridade dos dados
$pdo->beginTransaction();

try {
    // 3. Lidar com dependências: "Orfanar" prospecções ligadas a este cliente
    $stmt_prospeccoes = $pdo->prepare("UPDATE prospeccoes SET cliente_id = NULL WHERE cliente_id = ?");
    $stmt_prospeccoes->execute([$cliente_id]);

    // 4. Excluir o cliente da tabela 'clientes'
    $stmt_delete = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt_delete->execute([$cliente_id]);

    // Se tudo deu certo, confirma as alterações no banco
    $pdo->commit();

    // 5. Redireciona de volta para a lista com mensagem de sucesso
    $_SESSION['success_message'] = "Lead excluído com sucesso.";
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit;

} catch (PDOException $e) {
    // Se qualquer operação falhar, desfaz todas as alterações
    $pdo->rollBack();
    die("Erro ao excluir o lead. Pode haver registros associados que impedem a exclusão. Erro: " . $e->getMessage());
}
?>