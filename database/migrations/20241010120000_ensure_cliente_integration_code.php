<?php

declare(strict_types=1);

class EnsureClienteIntegrationCodeMigration
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
            $this->addIndexIfMissing();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addColumnIfMissing(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info('clientes')");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if (strcasecmp($column['name'], 'codigo_cliente_integracao') === 0) {
                    return;
                }
            }
            $this->pdo->exec('ALTER TABLE clientes ADD COLUMN codigo_cliente_integracao VARCHAR(60) NULL');
            return;
        }

        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM clientes LIKE :column');
        $stmt->execute(['column' => 'codigo_cliente_integracao']);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec('ALTER TABLE clientes ADD COLUMN codigo_cliente_integracao VARCHAR(60) NULL AFTER id');
        }
    }

    private function addIndexIfMissing(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA index_list('clientes')");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($indexes as $index) {
                if (strcasecmp($index['name'], 'idx_clientes_codigo_integracao') === 0) {
                    return;
                }
            }
            $this->pdo->exec('CREATE INDEX idx_clientes_codigo_integracao ON clientes (codigo_cliente_integracao)');
            return;
        }

        $stmt = $this->pdo->prepare('SHOW INDEX FROM clientes WHERE Key_name = :index');
        $stmt->execute(['index' => 'idx_clientes_codigo_integracao']);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec('CREATE INDEX idx_clientes_codigo_integracao ON clientes (codigo_cliente_integracao)');
        }
    }
}

