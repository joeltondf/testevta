<?php
// /app/models/LancamentoFinanceiro.php

class LancamentoFinanceiro {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($data) {
        // O nome da coluna no INSERT está 'tipo', mas no banco é 'tipo_lancamento'. Vamos corrigir isso.
        $sql = "INSERT INTO lancamentos_financeiros (descricao, valor, data_vencimento, tipo_lancamento, categoria_id, cliente_id, processo_id, status, eh_agregado, itens_agregados_ids, data_lancamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['descricao'],
            $data['valor'],
            $data['data_vencimento'],
            $data['tipo'], // O 'tipo' do formulário corresponde ao 'tipo_lancamento' do banco
            $data['categoria_id'],
            $data['cliente_id'] ?? null,
            $data['processo_id'] ?? null,
            $data['status'] ?? 'Pendente',
            $data['eh_agregado'] ?? 0,
            $data['itens_agregados_ids'] ?? null,
            $data['data_lancamento'] ?? date('Y-m-d H:i:s')
        ]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM lancamentos_financeiros WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM lancamentos_financeiros WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAllPaginated($page = 1, $perPage = 20, $search = '', $filters = []) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT lf.*, cf.nome_categoria 
                FROM lancamentos_financeiros lf
                LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id
                WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        $sql .= " ORDER BY lf.data_vencimento DESC, lf.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll($search = '', $filters = []) {
        $params = [];
        $sql = "SELECT COUNT(lf.id) 
                FROM lancamentos_financeiros lf
                LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id
                WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getTotals($search = '', $filters = []) {
        $params = [];
        $sql = "SELECT 
                    SUM(CASE WHEN tipo_lancamento = 'RECEITA' THEN valor ELSE 0 END) as receitas, -- CORRIGIDO AQUI
                    SUM(CASE WHEN tipo_lancamento = 'DESPESA' THEN valor ELSE 0 END) as despesas -- CORRIGIDO AQUI
                FROM lancamentos_financeiros lf
                WHERE 1=1";

        if (!empty($search)) {
            $sql = str_replace('FROM lancamentos_financeiros lf', 'FROM lancamentos_financeiros lf LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id', $sql);
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}