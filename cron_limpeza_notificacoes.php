<?php
require_once __DIR__ . '/config.php';

if (!isset($pdo)) {
    throw new RuntimeException('Conexão com o banco de dados não encontrada.');
}

$defaultDays = 30;
$retentionDays = (int)($_ENV['NOTIFICATION_RETENTION_DAYS'] ?? getenv('NOTIFICATION_RETENTION_DAYS') ?? $defaultDays);
$retentionDays = $retentionDays > 0 ? $retentionDays : $defaultDays;

$sql = 'DELETE FROM notificacoes WHERE resolvido = 1 AND data_criacao < DATE_SUB(NOW(), INTERVAL :days DAY)';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);

if ($stmt->execute()) {
    $removed = $stmt->rowCount();
    echo sprintf("%d notificações resolvidas removidas com mais de %d dias.\n", $removed, $retentionDays);
} else {
    echo "Nenhum registro foi removido." . PHP_EOL;
}
