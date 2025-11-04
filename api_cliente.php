<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once __DIR__ . '/app/models/Cliente.php';

// Medida de segurança para garantir que o usuário está logado
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$cliente_id = $_GET['id'] ?? null;
if (!$cliente_id) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente não fornecido.']);
    exit;
}

try {
    $clienteModel = new Cliente($pdo);
    $cliente = $clienteModel->getById($cliente_id);

    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado.']);
        exit;
    }

    $servicos_mensalista = [];
    // Verifica a coluna correta 'tipo_assessoria' e chama o método correto 'getServicosMensalista'
    if (isset($cliente['tipo_assessoria']) && $cliente['tipo_assessoria'] === 'Mensalista') {
        $servicos_mensalista = $clienteModel->getServicosMensalista($cliente_id);
    }

    echo json_encode([
        'success' => true,
        'tipo_assessoria' => $cliente['tipo_assessoria'] ?? 'À vista',
        'servicos' => $servicos_mensalista
    ]);

} catch (Exception $e) {
    // Log do erro para depuração no servidor
    error_log("Erro na api_cliente.php: " . $e->getMessage());
    // Mensagem de erro genérica para o usuário
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao buscar os dados do cliente.']);
}