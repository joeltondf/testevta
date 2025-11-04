<?php

declare(strict_types=1);


class PrepareOmieIntegrationMigration
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
            $this->createOmieEtapasFaturamentoTable();
            $this->createOmieCategoriasTable();
            $this->createOmieContasCorrentesTable();
            $this->createOmieCenariosFiscaisTable();
            $this->createOmieProdutosTable();

            $this->addColumnIfNotExists('omie_produtos', 'codigo', "VARCHAR(50) DEFAULT NULL AFTER descricao");
            $this->addColumnIfNotExists('omie_produtos', 'codigo_servico_municipal', "VARCHAR(10) DEFAULT NULL AFTER cfop");
            $this->addColumnIfNotExists('documentos', 'codigo_servico_municipal', "VARCHAR(10) DEFAULT NULL AFTER valor_unitario");

            $this->addColumnIfNotExists('categorias_financeiras', 'omie_codigo_categoria', "VARCHAR(50) DEFAULT NULL AFTER servico_tipo");
            $this->addIndexIfNotExists('categorias_financeiras', 'idx_cf_omie_codigo', 'INDEX idx_cf_omie_codigo (omie_codigo_categoria)');

            $this->addColumnIfNotExists('processos', 'codigo_pedido_integracao', "VARCHAR(50) DEFAULT NULL AFTER os_numero_conta_azul");
            $this->addColumnIfNotExists('processos', 'etapa_faturamento_codigo', "VARCHAR(10) DEFAULT NULL AFTER codigo_pedido_integracao");
            $this->addColumnIfNotExists('processos', 'codigo_categoria', "VARCHAR(50) DEFAULT NULL AFTER etapa_faturamento_codigo");
            $this->addColumnIfNotExists('processos', 'codigo_conta_corrente', "BIGINT DEFAULT NULL AFTER codigo_categoria");
            $this->addColumnIfNotExists('processos', 'codigo_cenario_fiscal', "INT DEFAULT NULL AFTER codigo_conta_corrente");
            $this->addIndexIfNotExists('processos', 'idx_processos_cod_pedido', 'INDEX idx_processos_cod_pedido (codigo_pedido_integracao)');
            $this->addIndexIfNotExists('processos', 'idx_processos_cod_conta_corrente', 'INDEX idx_processos_cod_conta_corrente (codigo_conta_corrente)');

            $this->addColumnIfNotExists('clientes', 'codigo_cliente_integracao', "VARCHAR(50) DEFAULT NULL AFTER omie_id");
            $this->addIndexIfNotExists('clientes', 'idx_clientes_cod_integracao', 'INDEX idx_clientes_cod_integracao (codigo_cliente_integracao)');

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function createOmieEtapasFaturamentoTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS omie_etapas_faturamento (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(30) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_omie_etapas_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }

    private function createOmieCategoriasTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS omie_categorias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_omie_categorias_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }

    private function createOmieContasCorrentesTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS omie_contas_correntes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nCodCC BIGINT UNSIGNED NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) DEFAULT NULL,
            banco VARCHAR(120) DEFAULT NULL,
            numero_agencia VARCHAR(50) DEFAULT NULL,
            numero_conta_corrente VARCHAR(50) DEFAULT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_omie_contas_ncodcc (nCodCC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }

    private function createOmieCenariosFiscaisTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS omie_cenarios_fiscais (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(30) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_omie_cenarios_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }

    private function createOmieProdutosTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS omie_produtos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            codigo VARCHAR(50) DEFAULT NULL,
            codigo_produto VARCHAR(50) DEFAULT NULL,
            codigo_integracao VARCHAR(60) DEFAULT NULL,
            cfop VARCHAR(20) DEFAULT NULL,
            ncm VARCHAR(20) DEFAULT NULL,
            unidade VARCHAR(10) DEFAULT NULL,
            valor_unitario DECIMAL(15,4) DEFAULT NULL,
            local_produto_id INT UNSIGNED DEFAULT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_omie_produtos_codigo (codigo_produto),
            UNIQUE KEY idx_omie_produtos_integracao (codigo_integracao),
            UNIQUE KEY idx_omie_produtos_local (local_produto_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
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
}
