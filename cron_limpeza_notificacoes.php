<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/services/NotificationCleanupService.php';

if (!isset($pdo)) {
    throw new RuntimeException('Conexão com o banco de dados não encontrada.');
}

$service = new NotificationCleanupService($pdo);
$summary = $service->cleanup();

echo sprintf(
    "%d notificações resolvidas removidas com mais de %d dias.\n",
    $summary['removed'],
    $summary['retention_days']
);
