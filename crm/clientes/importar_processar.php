<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

function insertImportedProspects(PDO $pdo, array $rows, ?int $assignedOwnerId): int
{
    if (empty($rows)) {
        return 0;
    }

    $insertSql = 'INSERT INTO clientes (
            nome_cliente,
            nome_responsavel,
            email,
            telefone,
            canal_origem,
            categoria,
            is_prospect,
            crmOwnerId
        ) VALUES (
            :nome_cliente,
            :nome_responsavel,
            :email,
            :telefone,
            :canal_origem,
            :categoria,
            1,
            :crm_owner_id
        )';

    $insertStmt = $pdo->prepare($insertSql);
    $created = 0;

    $pdo->beginTransaction();

    try {
        foreach ($rows as $row) {
            $insertStmt->execute([
                ':nome_cliente' => $row['company_name'],
                ':nome_responsavel' => $row['contact_name'] !== '' ? $row['contact_name'] : null,
                ':email' => $row['email'] !== '' ? $row['email'] : null,
                ':telefone' => $row['phone'] !== '' ? $row['phone'] : null,
                ':canal_origem' => $row['channel'],
                ':categoria' => 'Entrada',
                ':crm_owner_id' => $assignedOwnerId,
            ]);
            $created++;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return $created;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';

if (!$currentUserId) {
    $_SESSION['error_message'] = 'Sessão expirada. Faça login novamente.';
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    $_SESSION['error_message'] = 'Nenhum arquivo foi enviado para importação.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$channelOptions = [
    'Call',
    'LinkedIn',
    'Instagram',
    'Whatsapp',
    'Indicação Cliente',
    'Indicação Cartório',
    'Website',
    'Bitrix',
    'Evento',
    'Outro'
];

$defaultChannel = $_POST['default_channel'] ?? 'Outro';
if (!in_array($defaultChannel, $channelOptions, true)) {
    $defaultChannel = 'Outro';
}

$delimiter = $_POST['delimiter'] ?? ';';
$allowedDelimiters = [';', ',', "\t"];
if (!in_array($delimiter, $allowedDelimiters, true)) {
    $delimiter = ';';
}

$hasHeader = isset($_POST['has_header']);

$assignedOwnerId = null;
if ($currentUserPerfil === 'vendedor') {
    $assignedOwnerId = $currentUserId;
} else {
    $assignedOwnerId = isset($_POST['assigned_owner']) && $_POST['assigned_owner'] !== ''
        ? (int) $_POST['assigned_owner']
        : null;

    if (!$assignedOwnerId) {
        $_SESSION['error_message'] = 'Selecione o vendedor responsável pelos leads importados.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }

    $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE id = :id AND perfil = 'vendedor' AND (ativo = 1 OR ativo IS NULL)");
    $stmtOwner->execute([':id' => $assignedOwnerId]);

    if (!$stmtOwner->fetchColumn()) {
        $_SESSION['error_message'] = 'Vendedor inválido selecionado. Tente novamente.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }
}

$filePath = $_FILES['csv_file']['tmp_name'];
$handle = fopen($filePath, 'r');

if (!$handle) {
    $_SESSION['error_message'] = 'Não foi possível abrir o arquivo enviado.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$skippedCount = 0;
$errorRows = [];
$rowNumber = 0;
$rows = [];
$duplicates = [];

try {
    if ($hasHeader) {
        fgetcsv($handle, 0, $delimiter);
    }

    $duplicateSql = "SELECT id, nome_cliente, email, telefone
                     FROM clientes
                     WHERE is_prospect = 1 AND (
                         (:email <> '' AND email = :email) OR
                         (:telefone <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') = :telefone) OR
                         (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))
                     )
                     LIMIT 1";
    $duplicateStmt = $pdo->prepare($duplicateSql);

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNumber++;

        $nomeCliente = trim($data[0] ?? '');
        $nomeResponsavel = trim($data[1] ?? '');
        $emailRaw = trim($data[2] ?? '');
        $telefoneRaw = trim($data[3] ?? '');

        if ($nomeCliente === '' && $emailRaw === '' && $telefoneRaw === '') {
            $skippedCount++;
            continue;
        }

        if ($nomeCliente === '') {
            $skippedCount++;
            $errorRows[] = "Linha {$rowNumber}: Nome do Lead é obrigatório.";
            continue;
        }

        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
        $telefoneDigits = preg_replace('/\D+/', '', $telefoneRaw);

        $duplicateStmt->execute([
            ':email' => $email,
            ':telefone' => $telefoneDigits,
            ':nome_cliente' => $nomeCliente,
        ]);

        $existingLead = $duplicateStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $rowData = [
            'row_index' => $rowNumber,
            'company_name' => $nomeCliente,
            'contact_name' => $nomeResponsavel,
            'email' => $email,
            'phone' => $telefoneRaw,
            'channel' => $defaultChannel,
            'duplicate' => $existingLead ? [
                'id' => (int) ($existingLead['id'] ?? 0),
                'name' => $existingLead['nome_cliente'] ?? '',
                'email' => $existingLead['email'] ?? '',
                'phone' => $existingLead['telefone'] ?? '',
            ] : null,
        ];

        $rows[] = $rowData;

        if ($existingLead) {
            $duplicates[] = $rowData;
        }
    }
} finally {
    fclose($handle);
}

$duplicateCount = count($duplicates);
$validRows = array_filter($rows, static fn(array $row) => $row['duplicate'] === null);

if ($duplicateCount === 0) {
    try {
        $createdCount = insertImportedProspects($pdo, array_values($validRows), $assignedOwnerId);
    } catch (Throwable $exception) {
        error_log('Erro na importação de leads: ' . $exception->getMessage());
        $_SESSION['error_message'] = 'Não foi possível concluir a importação. Tente novamente.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }

    $_SESSION['import_summary'] = [
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'duplicates' => 0,
        'discarded' => 0,
        'errors' => array_slice($errorRows, 0, 5),
    ];

    if ($createdCount > 0) {
        $_SESSION['success_message'] = "Importação concluída: {$createdCount} lead(s) criado(s).";
    } elseif (!empty($errorRows) || $skippedCount > 0) {
        $_SESSION['error_message'] = 'Nenhum lead foi importado. Verifique o arquivo e tente novamente.';
    }

    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}

$token = bin2hex(random_bytes(16));

$_SESSION['lead_import_batches'][$token] = [
    'rows' => $rows,
    'assigned_owner_id' => $assignedOwnerId,
    'default_channel' => $defaultChannel,
    'skipped' => $skippedCount,
    'errors' => array_slice($errorRows, 0, 5),
    'uploader_id' => $currentUserId,
    'created_at' => time(),
];

header('Location: ' . APP_URL . '/crm/clientes/importar_revisao.php?token=' . urlencode($token));
exit();
