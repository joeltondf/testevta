<?php
// Arquivo: crm/clientes/salvar.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
require_once __DIR__ . '/../../app/utils/DatabaseSchemaInspector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $currentUserPerfil = $_SESSION['user_perfil'] ?? '';
    
    // Recebe os dados com os nomes corretos do formulário
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nome_cliente = trim($_POST['nome_cliente']);
    $nome_responsavel = trim($_POST['nome_responsavel']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $telefoneDdiInput = trim($_POST['telefone_ddi'] ?? '');
    $canal_origem = trim($_POST['canal_origem']);
    $leadCategory = 'Entrada';
    $redirectUrl = $id ? APP_URL . "/crm/clientes/editar_cliente.php?id=$id" : APP_URL . "/crm/clientes/novo.php";

    // Validação
    if (empty($nome_cliente) || empty($canal_origem)) {
        $_SESSION['error_message'] = "Nome do Lead e Canal de Origem são obrigatórios.";
        header('Location: ' . $redirectUrl);
        exit();
    }

    $telefoneDdi = null;
    $telefoneDdd = null;
    $telefoneNumero = null;

    try {
        if ($telefone !== '') {
            $parts = extractPhoneParts($telefone);
            $telefoneDdd = $parts['ddd'] ?? null;
            $telefoneNumero = $parts['phone'] ?? null;
            $telefoneDdi = $telefoneDdiInput !== '' ? normalizeDDI($telefoneDdiInput) : '55';

            $telefone = $telefoneDdi . ($telefoneDdd ?? '') . ($telefoneNumero ?? '');
        } else {
            $telefone = null;
        }
    } catch (InvalidArgumentException $exception) {
        $_SESSION['error_message'] = $exception->getMessage();
        header('Location: ' . $redirectUrl);
        exit();
    }

    if ($telefone === null) {
        $telefoneDdi = null;
        $telefoneDdd = null;
        $telefoneNumero = null;
    }

    $phoneColumnAvailability = [
        'ddi' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi'),
        'ddd' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd'),
        'numero' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero'),
    ];

    try {
        if ($id) {
            $stmtCheckProspect = $pdo->prepare("SELECT is_prospect, crmOwnerId, categoria FROM clientes WHERE id = :id");
            $stmtCheckProspect->execute([':id' => $id]);
            $clienteExistente = $stmtCheckProspect->fetch(PDO::FETCH_ASSOC);

            if (!$clienteExistente) {
                $_SESSION['error_message'] = "Lead não encontrado.";
                header('Location: ' . APP_URL . '/crm/clientes/lista.php');
                exit();
            }

            if ($currentUserPerfil === 'vendedor' && (int)($clienteExistente['crmOwnerId'] ?? 0) !== $currentUserId) {
                $_SESSION['error_message'] = "Você não tem permissão para alterar este lead.";
                header('Location: ' . APP_URL . '/crm/clientes/lista.php');
                exit();
            }

            if (!empty($clienteExistente['categoria'])) {
                $leadCategory = $clienteExistente['categoria'];
            }

            if ((int) ($clienteExistente['is_prospect'] ?? 0) !== 1) {
                $_SESSION['error_message'] = "Este lead já foi convertido em cliente e deve ser gerenciado pelo sistema principal.";
                header('Location: ' . APP_URL . '/crm/clientes/lista.php');
                exit();
            }

            $sql = "UPDATE clientes SET nome_cliente = :nome_cliente, nome_responsavel = :nome_responsavel, email = :email, telefone = :telefone";

            if ($phoneColumnAvailability['ddi']) {
                $sql .= ", telefone_ddi = :telefone_ddi";
            }

            if ($phoneColumnAvailability['ddd']) {
                $sql .= ", telefone_ddd = :telefone_ddd";
            }

            if ($phoneColumnAvailability['numero']) {
                $sql .= ", telefone_numero = :telefone_numero";
            }

            $sql .= ", canal_origem = :canal_origem, categoria = :categoria, is_prospect = :is_prospect WHERE id = :id";

            $params = [
                ':nome_cliente' => $nome_cliente,
                ':nome_responsavel' => $nome_responsavel,
                ':email' => $email,
                ':telefone' => $telefone,
                ':canal_origem' => $canal_origem,
                ':categoria' => $leadCategory,
                ':is_prospect' => 1,
                ':id' => $id
            ];

            if ($phoneColumnAvailability['ddi']) {
                $params[':telefone_ddi'] = $telefoneDdi;
            }

            if ($phoneColumnAvailability['ddd']) {
                $params[':telefone_ddd'] = $telefoneDdd;
            }

            if ($phoneColumnAvailability['numero']) {
                $params[':telefone_numero'] = $telefoneNumero;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['success_message'] = "Lead atualizado com sucesso!";
            $redirect_location = APP_URL . '/crm/clientes/lista.php';

        } else {
            $columns = [
                'nome_cliente',
                'nome_responsavel',
                'email',
                'telefone',
                'canal_origem',
                'categoria',
                'is_prospect',
                'crmOwnerId',
            ];

            $placeholders = [
                ':nome_cliente',
                ':nome_responsavel',
                ':email',
                ':telefone',
                ':canal_origem',
                ':categoria',
                ':is_prospect',
                ':crm_owner_id',
            ];

            $params = [
                ':nome_cliente' => $nome_cliente,
                ':nome_responsavel' => $nome_responsavel,
                ':email' => $email,
                ':telefone' => $telefone,
                ':canal_origem' => $canal_origem,
                ':categoria' => $leadCategory,
                ':is_prospect' => 1,
                ':crm_owner_id' => $currentUserId,
            ];

            if ($phoneColumnAvailability['ddi']) {
                $columns[] = 'telefone_ddi';
                $placeholders[] = ':telefone_ddi';
                $params[':telefone_ddi'] = $telefoneDdi;
            }

            if ($phoneColumnAvailability['ddd']) {
                $columns[] = 'telefone_ddd';
                $placeholders[] = ':telefone_ddd';
                $params[':telefone_ddd'] = $telefoneDdd;
            }

            if ($phoneColumnAvailability['numero']) {
                $columns[] = 'telefone_numero';
                $placeholders[] = ':telefone_numero';
                $params[':telefone_numero'] = $telefoneNumero;
            }

            $sql = 'INSERT INTO clientes (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $novo_cliente_id = $pdo->lastInsertId();
            $_SESSION['success_message'] = "Lead criado com sucesso! Agora cadastre a prospecção.";

            $redirectBase = $_POST['redirect_url'] ?? '';
            if (empty($redirectBase) || strpos($redirectBase, APP_URL) !== 0) {
                $redirectBase = APP_URL . '/crm/prospeccoes/nova.php';
            }

            $separator = strpos($redirectBase, '?') === false ? '?' : '&';
            $redirect_location = $redirectBase . $separator . 'cliente_id=' . $novo_cliente_id;
        }

        header('Location: ' . $redirect_location);
        exit;

    } catch (PDOException $e) {
        die("Erro ao salvar lead: " . $e->getMessage());
    }

} else {
    // Se a requisição não for POST, redireciona para a lista
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}
?>