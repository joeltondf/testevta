<?php

declare(strict_types=1);

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

if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via CLI');
}

$pdo = $pdo ?? null;

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Conexão PDO não disponível.\n");
    exit(1);
}

$logDir = $rootDir . '/logs';
$logFile = $logDir . '/cron_feedback_request.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

try {
    $query = "SELECT
                lh.id,
                lh.prospeccao_id,
                lh.vendor_id,
                lh.sdr_id,
                lh.status,
                lh.created_at,
                p.nome_prospecto
            FROM lead_handoffs lh
            LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
            WHERE lh.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND DATE_SUB(NOW(), INTERVAL 6 DAY)
              AND lh.quality_score IS NULL
              AND lh.status IN ('accepted', 'completed', 'lost')";

    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $handoffs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $notified = 0;
    $notifiedIds = [];

    foreach ($handoffs as $handoff) {
        if (NotificationService::notifyFeedbackRequest($handoff) !== false) {
            $notified++;
            $notifiedIds[] = (int) ($handoff['id'] ?? 0);
        }
    }

    $metadata = json_encode(
        [
            'handoff_ids' => $notifiedIds,
            'total_notified' => $notified,
            'timestamp' => date(DATE_ATOM),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $auditStmt = $pdo->prepare(
        "INSERT INTO audit_logs (action, metadata, created_at) VALUES (:action, :metadata, NOW())"
    );
    $auditStmt->bindValue(':action', 'feedback_request_sent');
    $auditStmt->bindValue(':metadata', $metadata);
    $auditStmt->execute();

    $logMessage = sprintf(
        '[%s] Feedback solicitado para %d handoffs',
        date('Y-m-d H:i:s'),
        $notified
    );

    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);

    echo $logMessage . PHP_EOL;
} catch (Throwable $exception) {
    $errorMessage = sprintf(
        '[%s] Erro ao solicitar feedback: %s',
        date('Y-m-d H:i:s'),
        $exception->getMessage()
    );
    file_put_contents($logFile, $errorMessage . PHP_EOL, FILE_APPEND);
    fwrite(STDERR, $errorMessage . PHP_EOL);

    exit(1);
}
