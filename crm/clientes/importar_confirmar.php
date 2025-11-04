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
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$token = $_POST['token'] ?? '';
$batch = $_SESSION['lead_import_batches'][$token] ?? null;

if (!$batch || ($batch['uploader_id'] ?? null) !== ($_SESSION['user_id'] ?? null)) {
    $_SESSION['error_message'] = 'Importação não encontrada ou expirada.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$rows = $batch['rows'] ?? [];
$assignedOwnerId = $batch['assigned_owner_id'] ?? null;
$skippedCount = (int) ($batch['skipped'] ?? 0);
$errorRows = $batch['errors'] ?? [];

$actions = $_POST['actions'] ?? [];
$rowsToInsert = [];
$discardedCount = 0;
$duplicateCount = 0;

foreach ($rows as $rowKey => $row) {
    $isDuplicate = $row['duplicate'] !== null;
    if ($isDuplicate) {
        $duplicateCount++;
        $action = $actions[$rowKey] ?? 'keep';
        if (!in_array($action, ['keep', 'import', 'discard'], true)) {
            $action = 'keep';
        }

        if ($action === 'discard') {
            $discardedCount++;
            continue;
        }

        if ($action === 'import') {
            $rowsToInsert[] = $row;
        }

        continue;
    }

    $rowsToInsert[] = $row;
}

try {
    $createdCount = insertImportedProspects($pdo, $rowsToInsert, $assignedOwnerId);
} catch (Throwable $exception) {
    error_log('Erro ao confirmar importação: ' . $exception->getMessage());
    $_SESSION['error_message'] = 'Não foi possível concluir a importação. Tente novamente.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

unset($_SESSION['lead_import_batches'][$token]);

$_SESSION['import_summary'] = [
    'created' => $createdCount,
    'skipped' => $skippedCount,
    'duplicates' => $duplicateCount,
    'discarded' => $discardedCount,
    'errors' => $errorRows,
];

if ($createdCount > 0) {
    $_SESSION['success_message'] = "Importação concluída: {$createdCount} lead(s) criado(s).";
} else {
    $_SESSION['success_message'] = 'Processo concluído sem novos leads adicionados.';
}

header('Location: ' . APP_URL . '/crm/clientes/lista.php');
exit();
