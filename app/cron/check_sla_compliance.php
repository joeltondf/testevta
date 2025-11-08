<?php
// Imports obrigatórios
$configPath = __DIR__ . '/../config.php';
$notificationServicePath = __DIR__ . '/../app/services/NotificationService.php';
$leadHandoffPath = __DIR__ . '/../app/models/LeadHandoff.php';

$rootDir = dirname(__DIR__, 2);

if (!file_exists($configPath)) {
    $configPath = $rootDir . '/config.php';
}

if (!file_exists($notificationServicePath)) {
    $notificationServicePath = $rootDir . '/app/services/NotificationService.php';
}

if (!file_exists($leadHandoffPath)) {
    $leadHandoffPath = $rootDir . '/app/models/LeadHandoff.php';
}

require_once $configPath;
require_once $notificationServicePath;
require_once $leadHandoffPath;

// Prevenir execução via browser
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via CLI');
}

$logDir = $rootDir . '/logs';
$logFile = $logDir . '/cron_sla.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

$warningsSent = 0;
$warningNotifications = 0;
$overdueLeads = 0;
$overdueNotificationsSent = 0;
$pdo = $pdo ?? null;

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Conexão PDO não disponível.\n");
    exit(1);
}

try {
    $now = new DateTimeImmutable('now');

    $warningSql = "SELECT
            lh.id,
            lh.prospeccao_id,
            lh.vendor_id,
            lh.sdr_id,
            lh.sla_deadline,
            lh.status,
            lh.first_contact_at,
            lh.accepted_at,
            p.nome_prospecto,
            TIMESTAMPDIFF(MINUTE, :now, lh.sla_deadline) AS minutes_remaining
        FROM lead_handoffs lh
        LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
        WHERE lh.status = 'accepted'
          AND lh.first_contact_at IS NULL
          AND lh.sla_deadline BETWEEN :now AND DATE_ADD(:now, INTERVAL 2 HOUR)";

    $warningStmt = $pdo->prepare($warningSql);
    $warningStmt->bindValue(':now', $now->format('Y-m-d H:i:s'));
    $warningStmt->execute();

    $warningHandoffs = $warningStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($warningHandoffs as $handoff) {
        $handoff['sla_hours_remaining'] = isset($handoff['minutes_remaining'])
            ? max(0, round(((int) $handoff['minutes_remaining']) / 60, 1))
            : null;

        if (NotificationService::notifySLAWarning($handoff) !== false) {
            $warningsSent++;
            $warningNotifications++;
        }
    }

    $overdueSql = "SELECT
            lh.id,
            lh.prospeccao_id,
            lh.vendor_id,
            lh.sdr_id,
            lh.sla_deadline,
            lh.status,
            lh.first_contact_at,
            lh.accepted_at,
            p.nome_prospecto
        FROM lead_handoffs lh
        LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
        WHERE lh.status = 'accepted'
          AND lh.first_contact_at IS NULL
          AND lh.sla_deadline < :now";

    $overdueStmt = $pdo->prepare($overdueSql);
    $overdueStmt->bindValue(':now', $now->format('Y-m-d H:i:s'));
    $overdueStmt->execute();

    $overdueHandoffs = $overdueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($overdueHandoffs as $handoff) {
        $notificationIds = NotificationService::notifySLAOverdue($handoff);

        if (is_array($notificationIds) && $notificationIds !== []) {
            $overdueLeads++;
            $overdueNotificationsSent += count($notificationIds);
        }
    }

    $metadata = json_encode(
        [
            'warnings_sent' => $warningsSent,
            'warning_notifications_sent' => $warningNotifications,
            'overdue_leads' => $overdueLeads,
            'overdue_notifications_sent' => $overdueNotificationsSent,
            'timestamp' => date(DATE_ATOM),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $auditStmt = $pdo->prepare(
        "INSERT INTO audit_logs (action, metadata, created_at) VALUES (:action, :metadata, NOW())"
    );
    $auditStmt->bindValue(':action', 'sla_check_executed');
    $auditStmt->bindValue(':metadata', $metadata);
    $auditStmt->execute();

    $logMessage = sprintf(
        '[%s] Warnings enviados: %d | Alertas enviados: %d',
        $now->format('Y-m-d H:i:s'),
        $warningsSent,
        $overdueLeads
    );

    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);

    echo $logMessage . PHP_EOL;
} catch (Throwable $exception) {
    $errorMessage = sprintf(
        '[%s] Erro na verificação de SLA: %s',
        date('Y-m-d H:i:s'),
        $exception->getMessage()
    );
    file_put_contents($logFile, $errorMessage . PHP_EOL, FILE_APPEND);
    fwrite(STDERR, $errorMessage . PHP_EOL);

    exit(1);
}
