<?php
class CategoriaFinanceira
{
    public const SERVICE_TYPES = ['Nenhum', 'Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros'];
    public const DEFAULT_SERVICE_TYPE = 'Nenhum';

    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function getAll($include_inactive = false) {
        $sql = "SELECT * FROM categorias_financeiras";
        if (!$include_inactive) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY grupo_principal, nome_categoria";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categorias_financeiras WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $sql = "INSERT INTO categorias_financeiras (nome_categoria, tipo_lancamento, grupo_principal, valor_padrao, servico_tipo, bloquear_valor_minimo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nome_categoria'], 
            $data['tipo_lancamento'], 
            $data['grupo_principal'],
            ($data['tipo_lancamento'] === 'RECEITA') ? ($data['valor_padrao'] ?? null) : null,
            ($data['tipo_lancamento'] === 'RECEITA')
                ? $this->normalizeServiceType($data['servico_tipo'] ?? null)
                : self::DEFAULT_SERVICE_TYPE,
            ($data['tipo_lancamento'] === 'RECEITA') ? (isset($data['bloquear_valor_minimo']) ? 1 : 0) : 0
        ]);
    }

    public function update($id, $data) {
        $sql = "UPDATE categorias_financeiras SET nome_categoria = ?, tipo_lancamento = ?, grupo_principal = ?, ativo = ?, valor_padrao = ?, servico_tipo = ?, bloquear_valor_minimo = ?, eh_produto_orcamento = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nome_categoria'],
            $data['tipo_lancamento'],
            $data['grupo_principal'],
            $data['ativo'],
            ($data['tipo_lancamento'] === 'RECEITA') ? ($data['valor_padrao'] ?? null) : null,
            ($data['tipo_lancamento'] === 'RECEITA')
                ? $this->normalizeServiceType($data['servico_tipo'] ?? null)
                : self::DEFAULT_SERVICE_TYPE,
            ($data['tipo_lancamento'] === 'RECEITA') ? (isset($data['bloquear_valor_minimo']) ? 1 : 0) : 0,
            ($data['tipo_lancamento'] === 'RECEITA') ? (isset($data['eh_produto_orcamento']) ? 1 : 0) : 0, // Adicionado
            $id
        ]);
    }

    // Não vamos deletar de verdade, apenas desativar para manter o histórico.
    public function delete($id) {
        $sql = "UPDATE categorias_financeiras SET ativo = 0 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function deleteProdutoOrcamento($id) {
        $sql = "DELETE FROM categorias_financeiras WHERE id = ? AND eh_produto_orcamento = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
    public function reactivate($id) {
        $sql = "UPDATE categorias_financeiras SET ativo = 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function getGruposPrincipais() {
        $sql = "SELECT DISTINCT grupo_principal FROM categorias_financeiras WHERE ativo = 1 ORDER BY grupo_principal ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Renomeia um grupo principal, atualizando todas as categorias associadas.
     */
    public function renameGrupo($oldName, $newName) {
        $sql = "UPDATE categorias_financeiras SET grupo_principal = ? WHERE grupo_principal = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$newName, $oldName]);
    }

    /**
     * Exclui um grupo principal e todas as categorias associadas a ele.
     */
    public function deleteGrupo($groupName) {
        $sql = "DELETE FROM categorias_financeiras WHERE grupo_principal = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$groupName]);
    }

    /**
     * Exclui uma categoria permanentemente do banco de dados.
     * ATENÇÃO: Esta ação é irreversível.
     */
    public function deletePermanente($id) {
        // Adicionar verificação se a categoria está em uso antes de excluir
        $stmt_check = $this->pdo->prepare("SELECT COUNT(*) FROM lancamentos_financeiros WHERE categoria_id = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            // Impede a exclusão se a categoria já foi usada em algum lançamento
            return false;
        }

        // Se não estiver em uso, prossegue com a exclusão
        $sql = "DELETE FROM categorias_financeiras WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Busca todas as categorias de receita ativas que estão vinculadas a um tipo de serviço.
     * @param string $tipo_servico 'Tradução', 'CRC', 'Apostilamento', 'Postagem' ou 'Outros'.
     * @return array A lista de categorias de receita.
     */
    public function getReceitasPorServico($tipo_servico) {
        $sql = "SELECT id, nome_categoria, valor_padrao, bloquear_valor_minimo 
                FROM categorias_financeiras 
                WHERE tipo_lancamento = 'RECEITA' AND servico_tipo = ? AND ativo = 1 
                ORDER BY nome_categoria";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->normalizeServiceType($tipo_servico)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma categoria de receita pelo seu nome.
     * @param string $nome O nome exato da categoria.
     * @return array|false
     */
    public function findReceitaByNome($nome) {
        $stmt = $this->pdo->prepare("SELECT * FROM categorias_financeiras WHERE nome_categoria = ? AND tipo_lancamento = 'RECEITA' LIMIT 1");
        $stmt->execute([$nome]);
        return $stmt->fetch();
    }

    public function findByServiceType(string $serviceType, bool $includeInactive = false)
    {
        $sql = "SELECT * FROM categorias_financeiras WHERE servico_tipo = ? AND tipo_lancamento = 'RECEITA' AND eh_produto_orcamento = 0";
        if (!$includeInactive) {
            $sql .= " AND ativo = 1";
        }

        $sql .= " ORDER BY id ASC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$serviceType]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // NOVO MÉTODO: Busca apenas os produtos de orçamento.
    public function getProdutosOrcamento(bool $includeInactive = false) {
        $selectFields = ['cf.*'];

        $selectFields[] = $this->buildOmieCodigoExpression();

        $selectFields = array_merge($selectFields, [
            $this->selectColumnOrNull('omie_produtos', 'codigo_produto', 'omie_codigo_produto'),
            $this->selectColumnOrNull('omie_produtos', 'codigo_integracao', 'omie_codigo_integracao'),
            $this->selectColumnOrNull('omie_produtos', 'ncm', 'omie_ncm'),
            $this->selectColumnOrNull('omie_produtos', 'unidade', 'omie_unidade'),
            $this->selectColumnOrNull('omie_produtos', 'cfop', 'omie_cfop'),
            $this->selectColumnOrNull('omie_produtos', 'codigo_servico_municipal', 'omie_codigo_servico_municipal'),
            $this->selectColumnOrNull('omie_produtos', 'valor_unitario', 'omie_valor_unitario')
        ]);

        $selectColumns = implode(",\n                ", $selectFields);

        $whereClauses = ['cf.eh_produto_orcamento = 1'];
        if (!$includeInactive) {
            $whereClauses[] = 'cf.ativo = 1';
        }

        $where = implode(' AND ', $whereClauses);

        $sql = <<<SQL
            SELECT
                {$selectColumns}
            FROM categorias_financeiras cf
            LEFT JOIN omie_produtos op
                ON op.id = (
                    SELECT op_inner.id
                    FROM omie_produtos op_inner
                    WHERE op_inner.local_produto_id = cf.id
                    ORDER BY op_inner.id DESC
                    LIMIT 1
                )
            WHERE {$where}
            ORDER BY cf.nome_categoria
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // NOVO MÉTODO: Cria um produto de orçamento com campos específicos.
    public function createProdutoOrcamento($data) {
        $sql = "INSERT INTO categorias_financeiras (nome_categoria, tipo_lancamento, grupo_principal, valor_padrao, servico_tipo, bloquear_valor_minimo, eh_produto_orcamento, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        $success = $stmt->execute([
            $data['nome_categoria'],
            'RECEITA',
            $data['grupo_principal'] ?? 'Produtos e Serviços',
            $data['valor_padrao'] ?? null,
            $this->normalizeServiceType($data['servico_tipo'] ?? null),
            isset($data['bloquear_valor_minimo']) ? 1 : 0,
            1,
            $data['ativo'] ?? 1
        ]);

        return $success ? (int)$this->pdo->lastInsertId() : false;
    }

    // NOVO MÉTODO: Atualiza um produto de orçamento.
    public function updateProdutoOrcamento($id, $data) {
        $sql = "UPDATE categorias_financeiras SET nome_categoria = ?, valor_padrao = ?, servico_tipo = ?, bloquear_valor_minimo = ?, ativo = ? WHERE id = ? AND eh_produto_orcamento = 1";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nome_categoria'],
            $data['valor_padrao'] ?? null,
            $this->normalizeServiceType($data['servico_tipo'] ?? null),
            isset($data['bloquear_valor_minimo']) ? 1 : 0,
            $data['ativo'],
            $id
        ]);
    }
    public function getCategoriasFinanceiras($show_inactive = false) {
        $sql = "SELECT * FROM categorias_financeiras WHERE eh_produto_orcamento = 0";
        if (!$show_inactive) {
            $sql .= " AND ativo = 1";
        }
        $sql .= " ORDER BY grupo_principal, nome_categoria";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setActiveStatus($id, $status) {
        $sql = "UPDATE categorias_financeiras SET ativo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([(int)$status, $id]);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $safeTable = str_replace('`', '``', $table);
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE :column");
        $stmt->execute(['column' => $column]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function buildOmieCodigoExpression(): string
    {
        $hasCodigo = $this->tableHasColumn('omie_produtos', 'codigo');
        $hasCodigoProduto = $this->tableHasColumn('omie_produtos', 'codigo_produto');
        $hasCodigoIntegracao = $this->tableHasColumn('omie_produtos', 'codigo_integracao');

        if ($hasCodigo) {
            return 'op.codigo AS omie_codigo';
        }

        $availableColumns = [];

        if ($hasCodigoProduto) {
            $availableColumns[] = 'op.codigo_produto';
        }

        if ($hasCodigoIntegracao) {
            $availableColumns[] = 'op.codigo_integracao';
        }

        if (!$availableColumns) {
            return 'NULL AS omie_codigo';
        }

        if (count($availableColumns) === 1) {
            return $availableColumns[0] . ' AS omie_codigo';
        }

        return 'COALESCE(' . implode(', ', $availableColumns) . ') AS omie_codigo';
    }

    private function selectColumnOrNull(string $table, string $column, string $alias): string
    {
        if ($this->tableHasColumn($table, $column)) {
            return "op.{$column} AS {$alias}";
        }

        return "NULL AS {$alias}";
    }

    private function normalizeServiceType(?string $serviceType): string
    {
        if (!is_string($serviceType)) {
            return self::DEFAULT_SERVICE_TYPE;
        }

        $normalized = trim($serviceType);

        return in_array($normalized, self::SERVICE_TYPES, true)
            ? $normalized
            : self::DEFAULT_SERVICE_TYPE;
    }
}
