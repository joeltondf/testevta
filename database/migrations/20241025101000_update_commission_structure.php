<?php

declare(strict_types=1);

class UpdateCommissionStructureMigration
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
            $this->addTipoComissaoColumn();
            $this->ensureCommissionIndexes();
            $this->seedSdrCommissionSetting();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addTipoComissaoColumn(): void
    {
        if ($this->columnExists('comissoes', 'tipo_comissao')) {
            return;
        }

        $this->pdo->exec("ALTER TABLE comissoes ADD COLUMN tipo_comissao ENUM('vendedor','sdr') NOT NULL DEFAULT 'vendedor' AFTER status_comissao");
        $this->pdo->exec("UPDATE comissoes SET tipo_comissao = 'vendedor' WHERE tipo_comissao IS NULL");
    }

    private function ensureCommissionIndexes(): void
    {
        $indexExists = $this->indexExists('comissoes', 'uniq_comissoes_venda_tipo');

        if (!$indexExists) {
            $this->pdo->exec("ALTER TABLE comissoes ADD UNIQUE INDEX uniq_comissoes_venda_tipo (venda_id, tipo_comissao)");
        }
    }

    private function seedSdrCommissionSetting(): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM configuracoes_comissao WHERE tipo_regra = :tipo");
        $stmt->execute([':tipo' => 'percentual_sdr']);

        if ((int) $stmt->fetchColumn() === 0) {
            $insert = $this->pdo->prepare(
                "INSERT INTO configuracoes_comissao (tipo_regra, valor, ativo) VALUES (:tipo, :valor, 1)"
            );
            $insert->execute([
                ':tipo' => 'percentual_sdr',
                ':valor' => 0.5,
            ]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute([':column' => $column]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :index");
        $stmt->execute([':index' => $index]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
