<?php

declare(strict_types=1);

class UpdateNotificacoesStructureMigration
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
            $this->addColumnIfNotExists('notificacoes', 'tipo_alerta', "VARCHAR(60) NOT NULL DEFAULT 'processo_generico' AFTER link");
            $this->addColumnIfNotExists('notificacoes', 'referencia_id', 'INT UNSIGNED DEFAULT NULL AFTER tipo_alerta');
            $this->addColumnIfNotExists('notificacoes', 'grupo_destino', "VARCHAR(30) NOT NULL DEFAULT 'gerencia' AFTER referencia_id");
            $this->addColumnIfNotExists('notificacoes', 'resolvido', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER lida');

            $this->addIndexIfNotExists('notificacoes', 'idx_notificacoes_tipo_alerta', 'INDEX idx_notificacoes_tipo_alerta (tipo_alerta)');
            $this->addIndexIfNotExists('notificacoes', 'idx_notificacoes_referencia', 'INDEX idx_notificacoes_referencia (referencia_id)');
            $this->addIndexIfNotExists('notificacoes', 'idx_notificacoes_grupo_destino', 'INDEX idx_notificacoes_grupo_destino (grupo_destino)');
            $this->addIndexIfNotExists('notificacoes', 'idx_notificacoes_resolvido', 'INDEX idx_notificacoes_resolvido (resolvido)');
            $this->addUniqueIndexIfNotExists('notificacoes', 'uniq_notificacoes_evento', 'UNIQUE INDEX uniq_notificacoes_evento (usuario_id, tipo_alerta, referencia_id)');

            $this->hydrateNewColumns();

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function hydrateNewColumns(): void
    {
        $this->pdo->exec(<<<SQL
            UPDATE notificacoes
            SET resolvido = 0
            WHERE resolvido IS NULL
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE notificacoes
            SET tipo_alerta = CASE
                WHEN tipo_alerta IS NULL OR tipo_alerta = '' OR tipo_alerta = 'processo_generico' THEN
                    CASE
                        WHEN link LIKE '%crm/prospeccoes%' THEN 'prospeccao_generica'
                        WHEN link LIKE '%processos.php%' THEN 'processo_generico'
                        ELSE 'notificacao_generica'
                    END
                ELSE tipo_alerta
            END
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE notificacoes
            SET referencia_id = CAST(SUBSTRING_INDEX(link, 'id=', -1) AS UNSIGNED)
            WHERE (referencia_id IS NULL OR referencia_id = 0)
              AND link IS NOT NULL
              AND link LIKE '%id=%'
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE notificacoes n
            LEFT JOIN users u ON u.id = n.usuario_id
            SET n.grupo_destino = CASE
                WHEN u.perfil = 'vendedor' THEN 'vendedor'
                ELSE 'gerencia'
            END
            WHERE n.grupo_destino IS NULL
               OR n.grupo_destino = ''
        SQL);
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute(['column' => $column]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $definition): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :indexName");
        $stmt->execute(['indexName' => $indexName]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD {$definition}");
        }
    }

    private function addUniqueIndexIfNotExists(string $table, string $indexName, string $definition): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :indexName");
        $stmt->execute(['indexName' => $indexName]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD {$definition}");
        }
    }
}
