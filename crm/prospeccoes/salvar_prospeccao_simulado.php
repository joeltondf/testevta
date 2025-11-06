<?php
// Arquivo: crm/prospeccoes/salvar.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $nome_prospecto = trim($_POST['nome_prospecto']);
    $valor_proposto = filter_input(INPUT_POST, 'valor_proposto', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $feedback_inicial = trim($_POST['feedback_inicial']);
    $status = trim($_POST['status']);
    
    $responsavel_id = $_SESSION['user_id']; 
    $data_prospeccao = date('Y-m-d H:i:s');

    if (empty($cliente_id) || empty($nome_prospecto) || !isset($_POST['valor_proposto']) || empty($feedback_inicial) || empty($status)) {
        // Correção: Redirecionamento absoluto
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php?error=campos_obrigatorios');
        exit();
    }
    
    try {
        $sql = "INSERT INTO prospeccoes 
                    (cliente_id, nome_prospecto, data_prospeccao, responsavel_id, feedback_inicial, valor_proposto, status) 
                VALUES 
                    (:cliente_id, :nome_prospecto, :data_prospeccao, :responsavel_id, :feedback_inicial, :valor_proposto, :status)";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':cliente_id' => $cliente_id,
            ':nome_prospecto' => $nome_prospecto,
            ':data_prospeccao' => $data_prospeccao,
            ':responsavel_id' => $responsavel_id,
            ':feedback_inicial' => $feedback_inicial,
            ':valor_proposto' => $valor_proposto,
            ':status' => $status
        ]);

        $prospeccao_id = $pdo->lastInsertId();

        // Gera e salva o ID em texto (PROS-0001)
        $id_texto = "PROS-" . str_pad($prospeccao_id, 4, '0', STR_PAD_LEFT);
        $stmt_update = $pdo->prepare("UPDATE prospeccoes SET id_texto = :id_texto WHERE id = :id");
        $stmt_update->execute([':id_texto' => $id_texto, ':id' => $prospeccao_id]);

        // Salva a observação inicial como a primeira interação
        if (!empty($feedback_inicial)) {
            $sql_interacao = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao) VALUES (:prospeccao_id, :usuario_id, :observacao)";
            $stmt_interacao = $pdo->prepare($sql_interacao);
            $stmt_interacao->execute([
                ':prospeccao_id' => $prospeccao_id,
                ':usuario_id' => $_SESSION['user_id'],
                ':observacao' => "Interação inicial: " . $feedback_inicial
            ]);
        }
        
        // Correção: Redirecionamento absoluto
        header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php?success=prospeccao_criada');
        exit();

    } catch (PDOException $e) { 
        die("Erro ao salvar prospecção: " . $e->getMessage()); 
    }
} else {
    // Correção: Redirecionamento absoluto
    header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
    exit();
}
?>