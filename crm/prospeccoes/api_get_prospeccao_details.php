<?php
// Arquivo: crm/prospeccoes/api_get_prospeccao_details.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

header('Content-Type: application/json');

$prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$prospeccao_id) {
    echo json_encode(['success' => false, 'message' => 'ID da prospecção inválido.']);
    exit;
}

try {
    // Correção 1: Consulta principal com nomes de tabelas e colunas corretos
    $stmt_prospect = $pdo->prepare("
        SELECT p.*, c.nome_cliente, u.nome_completo as responsavel_nome
        FROM prospeccoes p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN users u ON p.responsavel_id = u.id
        WHERE p.id = ?
    ");
    $stmt_prospect->execute([$prospeccao_id]);
    $prospect = $stmt_prospect->fetch(PDO::FETCH_ASSOC);

    if (!$prospect) {
        echo json_encode(['success' => false, 'message' => 'Prospecção não encontrada.']);
        exit;
    }

    // Correção 2: Consulta de interações com nomes de tabelas e colunas corretos
    $stmt_interacoes = $pdo->prepare("
        SELECT i.observacao, i.data_interacao, i.tipo, u.nome_completo as usuario_nome
        FROM interacoes i
        LEFT JOIN users u ON i.usuario_id = u.id
        WHERE i.prospeccao_id = ?
        ORDER BY i.data_interacao DESC
        LIMIT 5
    ");
    $stmt_interacoes->execute([$prospeccao_id]);
    $interacoes = $stmt_interacoes->fetchAll(PDO::FETCH_ASSOC);

    // Combina tudo em uma única resposta
    $response = [
        'success' => true,
        'prospect' => $prospect,
        'interacoes' => $interacoes
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Erro na API de detalhes da prospecção: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados.']);
}
?>