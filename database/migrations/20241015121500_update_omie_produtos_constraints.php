<?php

declare(strict_types=1);

class UpdateOmieProdutosConstraintsMigration
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
            $this->removeDuplicates('codigo_produto');
            $this->removeDuplicates('codigo_integracao');
            $this->removeDuplicates('local_produto_id');

            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $this->ensureSqliteSchema();
            } else {
                $this->ensureMysqlSchema();
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function ensureMysqlSchema(): void
    {
        $this->ensureIdColumn();
        $this->ensureColumnType('codigo_produto', 'VARCHAR(50)');
        $this->ensureColumnType('codigo_integracao', 'VARCHAR(60)');

        $this->ensureUniqueIndex('omie_produtos', 'idx_omie_produtos_codigo', '(`codigo_produto`)');
        $this->ensureUniqueIndex('omie_produtos', 'idx_omie_produtos_integracao', '(`codigo_integracao`)');
        $this->ensureUniqueIndex('omie_produtos', 'idx_omie_produtos_local', '(`local_produto_id`)');
    }

    private function ensureSqliteSchema(): void
    {
        $this->ensureSqlitePrimaryKey();
        $this->ensureSqliteColumnType('codigo_produto', 'VARCHAR(50)');
        $this->ensureSqliteColumnType('codigo_integracao', 'VARCHAR(60)');

        $this->ensureSqliteUniqueIndex('idx_omie_produtos_codigo', '(codigo_produto)');
        $this->ensureSqliteUniqueIndex('idx_omie_produtos_integracao', '(codigo_integracao)');
        $this->ensureSqliteUniqueIndex('idx_omie_produtos_local', '(local_produto_id)');
    }

    private function removeDuplicates(string $column): void
    {
        if (!in_array($column, ['codigo_produto', 'codigo_integracao', 'local_produto_id'], true)) {
            return;
        }

        $columnExpression = $column;

        $sql = <<<SQL
            DELETE FROM omie_produtos
            WHERE id IN (
                SELECT id FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY {$columnExpression} ORDER BY updated_at DESC, id DESC) AS row_num
                    FROM omie_produtos
                    WHERE {$columnExpression} IS NOT NULL
                ) AS ranked
                WHERE ranked.row_num > 1
            )
        SQL;

        $this->pdo->exec($sql);
    }

    private function ensureIdColumn(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM omie_produtos LIKE 'id'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$column) {
            throw new RuntimeException('A coluna id não existe na tabela omie_produtos.');
        }

        $type = strtolower((string)($column['Type'] ?? ''));
        $extra = strtolower((string)($column['Extra'] ?? ''));
        $key = strtolower((string)($column['Key'] ?? ''));

        if (strpos($type, 'int') === false || strpos($type, 'unsigned') === false) {
            $this->pdo->exec('ALTER TABLE omie_produtos MODIFY id INT UNSIGNED NOT NULL');
            $type = 'int unsigned';
        }

        if (strpos($extra, 'auto_increment') === false) {
            $this->pdo->exec('ALTER TABLE omie_produtos MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if ($key !== 'pri') {
            $this->pdo->exec('ALTER TABLE omie_produtos ADD PRIMARY KEY (id)');
        }
    }

    private function ensureColumnType(string $column, string $expectedType): void
    {
        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM omie_produtos LIKE :column');
        $stmt->execute(['column' => $column]);
        $definition = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$definition) {
            return;
        }

        $type = strtoupper((string)($definition['Type'] ?? ''));
        if ($type !== strtoupper($expectedType)) {
            $this->pdo->exec("ALTER TABLE omie_produtos MODIFY {$column} {$expectedType} NULL");
        }
    }

    private function ensureUniqueIndex(string $table, string $indexName, string $columnsDefinition): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :index");
        $stmt->execute(['index' => $indexName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && (int)$existing['Non_unique'] === 0) {
            return;
        }

        if ($existing) {
            $this->pdo->exec("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }

        $this->pdo->exec("ALTER TABLE {$table} ADD UNIQUE INDEX {$indexName} {$columnsDefinition}");
    }

    private function ensureSqlitePrimaryKey(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info('omie_produtos')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            if (strcasecmp($column['name'], 'id') === 0) {
                $type = strtoupper($column['type'] ?? '');
                if ($column['pk'] === 1 && str_contains($type, 'INT')) {
                    return;
                }
            }
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS __omie_produtos_tmp AS SELECT * FROM omie_produtos');
        $this->pdo->exec('DROP TABLE omie_produtos');
        $this->pdo->exec(<<<SQL
            CREATE TABLE omie_produtos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                descricao TEXT NOT NULL,
                codigo TEXT NULL,
                codigo_produto TEXT NULL,
                codigo_integracao TEXT NULL,
                cfop TEXT NULL,
                codigo_servico_municipal TEXT NULL,
                ncm TEXT NULL,
                unidade TEXT NULL,
                valor_unitario TEXT NULL,
                local_produto_id INTEGER NULL,
                ativo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )
        SQL);
        $this->pdo->exec('INSERT INTO omie_produtos (id, descricao, codigo, codigo_produto, codigo_integracao, cfop, codigo_servico_municipal, ncm, unidade, valor_unitario, local_produto_id, ativo, created_at, updated_at) SELECT id, descricao, codigo, codigo_produto, codigo_integracao, cfop, codigo_servico_municipal, ncm, unidade, valor_unitario, local_produto_id, ativo, created_at, updated_at FROM __omie_produtos_tmp');
        $this->pdo->exec('DROP TABLE __omie_produtos_tmp');
    }

    private function ensureSqliteColumnType(string $column, string $expectedType): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info('omie_produtos')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $definition) {
            if (strcasecmp($definition['name'], $column) === 0) {
                $type = strtoupper($definition['type'] ?? '');
                if ($type === strtoupper($expectedType) || $type === 'TEXT') {
                    return;
                }
            }
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS __omie_produtos_tmp AS SELECT * FROM omie_produtos');
        $this->pdo->exec('DROP TABLE omie_produtos');
        $this->pdo->exec(<<<SQL
            CREATE TABLE omie_produtos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                descricao TEXT NOT NULL,
                codigo TEXT NULL,
                codigo_produto VARCHAR(50) NULL,
                codigo_integracao VARCHAR(60) NULL,
                cfop TEXT NULL,
                codigo_servico_municipal TEXT NULL,
                ncm TEXT NULL,
                unidade TEXT NULL,
                valor_unitario TEXT NULL,
                local_produto_id INTEGER NULL,
                ativo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )
        SQL);
        $this->pdo->exec('INSERT INTO omie_produtos (id, descricao, codigo, codigo_produto, codigo_integracao, cfop, codigo_servico_municipal, ncm, unidade, valor_unitario, local_produto_id, ativo, created_at, updated_at) SELECT id, descricao, codigo, codigo_produto, codigo_integracao, cfop, codigo_servico_municipal, ncm, unidade, valor_unitario, local_produto_id, ativo, created_at, updated_at FROM __omie_produtos_tmp');
        $this->pdo->exec('DROP TABLE __omie_produtos_tmp');
    }

    private function ensureSqliteUniqueIndex(string $indexName, string $columnsDefinition): void
    {
        $stmt = $this->pdo->query("PRAGMA index_list('omie_produtos')");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($indexes as $index) {
            if (strcasecmp($index['name'], $indexName) === 0 && (int)$index['unique'] === 1) {
                return;
            }

            if (strcasecmp($index['name'], $indexName) === 0) {
                $this->pdo->exec("DROP INDEX {$indexName}");
            }
        }

        $this->pdo->exec("CREATE UNIQUE INDEX {$indexName} ON omie_produtos {$columnsDefinition}");
    }
}
