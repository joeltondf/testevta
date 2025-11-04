<?php
// Arquivo: vendas/prospeccoes/atualizar_status_kanban.php
// Este script atualiza o status de uma prospecção quando ela é movida no Kanban.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/services/AutomacaoKanbanService.php';
require_once __DIR__ . '/../../app/services/KanbanConfigService.php';

// Define o cabeçalho da resposta como JSON para comunicação com o JavaScript
header('Content-Type: application/json');

// Pega os dados brutos enviados pelo JavaScript
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

// Valida os dados recebidos
$prospeccao_id = filter_var($data->prospeccao_id ?? null, FILTER_VALIDATE_INT);
$novo_status = isset($data->novo_status) ? trim($data->novo_status) : null;
$user_id = $_SESSION['user_id'];

if (!$prospeccao_id || !$novo_status) {
    // Se os dados forem inválidos, retorna um erro
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$kanbanConfigService = new KanbanConfigService($pdo);
$allowedStatuses = $kanbanConfigService->getColumns();

if (!in_array($novo_status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Status informado não faz parte das colunas do Kanban.']);
    exit;
}

// Inicia uma transação para garantir que ambas as operações (update e log) funcionem
$pdo->beginTransaction();
try {
    // 1. Busca o status antigo para registrar no histórico
    $stmt_old = $pdo->prepare("SELECT status FROM prospeccoes WHERE id = ?");
    $stmt_old->execute([$prospeccao_id]);
    $status_antigo = $stmt_old->fetchColumn();

    // 2. Atualiza o status da prospecção na tabela principal
    $stmt_update = $pdo->prepare("UPDATE prospeccoes SET status = ?, data_ultima_atualizacao = NOW() WHERE id = ?");
    $stmt_update->execute([$novo_status, $prospeccao_id]);
    
    // 3. Registra a alteração no histórico de interações, se o status realmente mudou
    if ($status_antigo !== $novo_status) {
        $log_message = "Status alterado de '{$status_antigo}' para '{$novo_status}' através do Kanban.";
        $stmt_log = $pdo->prepare("INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, 'log_sistema')");
        $stmt_log->execute([$prospeccao_id, $user_id, $log_message]);
    }

    // Se tudo deu certo, confirma as alterações no banco
    $pdo->commit();

    try {
        $automationService = new AutomacaoKanbanService($pdo);
        $automationService->handleStatusChange($prospeccao_id, $novo_status, $user_id);
    } catch (Exception $automationException) {
        error_log('Erro ao acionar automação do Kanban: ' . $automationException->getMessage());
    }

    // Retorna uma resposta de sucesso
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Se algo deu errado, desfaz todas as alterações
    $pdo->rollBack();
    // Retorna uma resposta de erro com a mensagem
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status: ' . $e->getMessage()]);
}
