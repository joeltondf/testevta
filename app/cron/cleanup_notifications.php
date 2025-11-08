<?php
// Agendamento sugerido (domingo às 02h): 0 2 * * 0 php /caminho/para/cleanup_notifications.php
// Caminhos necessários
$rootDir = dirname(__DIR__, 2);
$configPath = $rootDir . '/config.php';

if (!file_exists($configPath)) {
    fwrite(STDERR, "Arquivo de configuração não encontrado em {$configPath}." . PHP_EOL);
    exit(1);
}

require_once $configPath;

// Permitir execução somente via CLI
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via CLI');
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Conexão PDO não disponível." . PHP_EOL);
    exit(1);
}

$options = getopt('', ['backup::']);
$shouldBackup = array_key_exists('backup', $options);
$backupDirectory = $rootDir . '/backups';

if (
    $shouldBackup
    && is_string($options['backup'])
    && trim($options['backup']) !== ''
) {
    $backupDirectory = rtrim($options['backup'], "/\\");
}

$logDir = $rootDir . '/logs';
$logFile = $logDir . '/cron_cleanup_notifications.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

try {
    if ($shouldBackup) {
        if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0775, true) && !is_dir($backupDirectory)) {
            throw new RuntimeException("Não foi possível criar o diretório de backup em {$backupDirectory}");
        }

        backupTable($pdo, 'notificacoes', $backupDirectory);
        backupTable($pdo, 'audit_logs', $backupDirectory);
    }

    $pdo->beginTransaction();

    $cleanupNotifications = $pdo->prepare(
        'DELETE FROM notificacoes WHERE lida = 1 AND data_criacao < DATE_SUB(NOW(), INTERVAL 90 DAY)'
    );
    $cleanupNotifications->execute();
    $notificationsDeleted = $cleanupNotifications->rowCount();

    $cleanupAuditLogs = $pdo->prepare(
        'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)'
    );
    $cleanupAuditLogs->execute();
    $auditLogsDeleted = $cleanupAuditLogs->rowCount();

    $pdo->commit();

    $timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
    $message = sprintf(
        '[%s] Limpeza concluída. Notificações removidas: %d | Logs removidos: %d',
        $timestamp,
        $notificationsDeleted,
        $auditLogsDeleted
    );

    file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
    echo $message . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
    $errorMessage = sprintf('[%s] Erro na limpeza de notificações: %s', $timestamp, $exception->getMessage());
    file_put_contents($logFile, $errorMessage . PHP_EOL, FILE_APPEND);
    fwrite(STDERR, $errorMessage . PHP_EOL);

    exit(1);
}

/**
 * Realiza o backup dos registros de uma tabela antes da remoção.
 */
function backupTable(PDO $pdo, string $tableName, string $backupDirectory): void
{
    $escapedTable = str_replace('`', '``', $tableName);
    $stmt = $pdo->query(sprintf('SELECT * FROM `%s`', $escapedTable));
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fileName = sprintf(
        '%s/%s_%s.json',
        rtrim($backupDirectory, '/\\'),
        $tableName,
        (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Ymd_His')
    );

    file_put_contents(
        $fileName,
        json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}
