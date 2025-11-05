<?php

declare(strict_types=1);

class NotificationCleanupService
{
    private PDO $pdo;
    private int $defaultRetentionDays = 30;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Remove notificações resolvidas que excederam o período de retenção.
     *
     * @return array{removed:int,retention_days:int}
     */
    public function cleanup(?int $retentionDays = null): array
    {
        $days = $retentionDays ?? (int) ($_ENV['NOTIFICATION_RETENTION_DAYS'] ?? getenv('NOTIFICATION_RETENTION_DAYS') ?? $this->defaultRetentionDays);
        $days = $days > 0 ? $days : $this->defaultRetentionDays;

        $sql = 'DELETE FROM notificacoes
                WHERE resolvido = 1
                  AND data_criacao < DATE_SUB(NOW(), INTERVAL :days DAY)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':days', $days, PDO::PARAM_INT);
        $statement->execute();

        return [
            'removed' => $statement->rowCount(),
            'retention_days' => $days,
        ];
    }
}
