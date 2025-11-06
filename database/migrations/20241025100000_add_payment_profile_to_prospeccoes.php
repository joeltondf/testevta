<?php

declare(strict_types=1);

class AddPaymentProfileToProspeccoesMigration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->addColumnIfMissing();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addColumnIfMissing(): void
    {
        $columnExists = $this->columnExists('prospeccoes', 'perfil_pagamento');

        if ($columnExists) {
            return;
        }

        $sql = "ALTER TABLE prospeccoes ADD COLUMN perfil_pagamento ENUM('mensalista','avista') NULL AFTER leadCategory";
        $this->pdo->exec($sql);
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute([':column' => $column]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
