<?php
// /app/models/AutomacaoModel.php

class AutomacaoModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // =======================================================================
    // MÉTODOS DE GERENCIAMENTO (CRUD para o Painel de Admin)
    // =======================================================================

    /**
     * Busca todas as campanhas de automação para a listagem.
     * @return array
     */
    public function getAllCampanhas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM automacao_campanhas ORDER BY nome_campanha ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca uma única campanha pelo seu ID para edição.
     * @param int $id
     * @return mixed
     */
    public function getCampanhaById(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM automacao_campanhas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova campanha no banco de dados.
     * @param array $data
     * @return bool
     */
    public function createCampanha(array $data): bool
    {
        $sql = "INSERT INTO automacao_campanhas (nome_campanha, crm_gatilhos, digisac_conexao_id, digisac_template_id, mapeamento_parametros, digisac_user_id, email_assunto, email_cabecalho, email_corpo, intervalo_reenvio_dias, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nome_campanha'],
            $data['crm_gatilhos'],
            $data['digisac_conexao_id'],
            $data['digisac_template_id'],
            $data['mapeamento_parametros'] ?? '{}',
            $data['digisac_user_id'],
            $data['email_assunto'],
            $data['email_cabecalho'],
            $data['email_corpo'],
            $data['intervalo_reenvio_dias'],
            $data['ativo'] ?? 0
        ]);
    }

    /**
     * Atualiza uma campanha existente.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateCampanha(int $id, array $data): bool
    {
        $sql = "UPDATE automacao_campanhas SET nome_campanha = ?, crm_gatilhos = ?, digisac_conexao_id = ?, digisac_template_id = ?, mapeamento_parametros = ?, digisac_user_id = ?, email_assunto = ?, email_cabecalho = ?, email_corpo = ?, intervalo_reenvio_dias = ?, ativo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nome_campanha'],
            $data['crm_gatilhos'],
            $data['digisac_conexao_id'],
            $data['digisac_template_id'],
            $data['mapeamento_parametros'] ?? '{}',
            $data['digisac_user_id'],
            $data['email_assunto'],
            $data['email_cabecalho'],
            $data['email_corpo'],
            $data['intervalo_reenvio_dias'],
            $data['ativo'] ?? 0,
            $id
        ]);
    }

    /**
     * Exclui uma campanha.
     * @param int $id
     * @return bool
     */
    public function deleteCampanha(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM automacao_campanhas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // =======================================================================
    // MÉTODOS PARA O SCRIPT DE EXECUÇÃO (CRON JOB)
    // =======================================================================

    /**
     * Busca as campanhas que estão marcadas como ativas.
     * @return array
     */
    public function getCampanhasAtivas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM automacao_campanhas WHERE ativo = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca clientes elegíveis com base no STATUS DA PROSPECÇÃO (Kanban).
     * @param array $statusGatilho Lista de status do Kanban
     * @return array
     */
    public function getClientesPorStatusDeProspeccao(array $statusGatilho): array
    {
        if (empty($statusGatilho)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($statusGatilho), '?'));
        
        $sql = "SELECT DISTINCT cl.id, cl.nome_cliente, cl.nome_responsavel, cl.email, cl.telefone
                FROM clientes cl
                JOIN prospeccoes p ON cl.id = p.cliente_id
                WHERE p.status IN ($placeholders) AND cl.is_prospect = 1";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($statusGatilho);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Adiciona um cliente à fila de envio para uma campanha específica.
     * @param int $campanhaId
     * @param int $clienteId
     * @param string $numero
     * @return bool
     */
    public function adicionarNaFila(int $campanhaId, int $clienteId, string $numero): bool
    {
        $sql = "INSERT INTO automacao_fila_envio (campanha_id, cliente_id, numero_destino, data_agendamento) VALUES (?, ?, ?, CURDATE())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$campanhaId, $clienteId, $numero]);
    }

    /**
     * Conta quantos envios já foram feitos hoje.
     * @return int
     */
    public function contarEnviosDeHoje(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM automacao_envios_log WHERE DATE(data_envio) = CURDATE()");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Pega os próximos itens da fila para processamento, respeitando um limite.
     * @param int $limit
     * @return array
     */
    public function getItensDaFila(int $limit): array
    {
        $sql = "SELECT 
                    f.id as fila_id, f.numero_destino, 
                    c.id as campanha_id, c.digisac_conexao_id, c.digisac_template_id, c.mapeamento_parametros,
                    cl.id as cliente_id, cl.nome_cliente, cl.nome_responsavel, cl.email, cl.telefone 
                FROM automacao_fila_envio f
                JOIN automacao_campanhas c ON f.campanha_id = c.id
                JOIN clientes cl ON f.cliente_id = cl.id
                WHERE f.status_fila = 'pendente' 
                AND f.data_agendamento <= CURDATE()
                ORDER BY f.id ASC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove um item da fila (após ser processado).
     * @param int $filaId
     * @return bool
     */
    public function removerDaFila(int $filaId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM automacao_fila_envio WHERE id = ?");
        return $stmt->execute([$filaId]);
    }
    
    /**
     * Grava um registro no histórico de envios.
     * @param int $campanhaId
     * @param int $clienteId
     * @param string $status
     * @param string $detalhes
     * @return bool
     */
    public function logarEnvio(int $campanhaId, int $clienteId, string $status, string $detalhes = ''): bool
    {
        $sql = "INSERT INTO automacao_envios_log (campanha_id, cliente_id, status, detalhes) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$campanhaId, $clienteId, $status, $detalhes]);
    }

    /**
     * Verifica se um cliente já recebeu mensagem de uma campanha recentemente.
     * @param int $campanhaId
     * @param int $clienteId
     * @param int $intervaloDias
     * @return bool
     */
    public function verificarEnvioRecente(int $campanhaId, int $clienteId, int $intervaloDias): bool
    {
        $sql = "SELECT COUNT(*) FROM automacao_envios_log 
                WHERE campanha_id = ? AND cliente_id = ? AND data_envio >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$campanhaId, $clienteId, $intervaloDias]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica se um cliente já está na fila de envio pendente para uma campanha.
     * @param int $campanhaId
     * @param int $clienteId
     * @return bool
     */
    public function verificarSeEstaNaFila(int $campanhaId, int $clienteId): bool
    {
        $sql = "SELECT COUNT(*) FROM automacao_fila_envio WHERE campanha_id = ? AND cliente_id = ? AND status_fila = 'pendente'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$campanhaId, $clienteId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Retorna as campanhas ativas associadas a um status específico do Kanban.
     *
     * @param string $status
     * @return array
     */
    public function getCampanhasAtivasPorStatus(string $status): array
    {
        $stmt = $this->pdo->query("SELECT * FROM automacao_campanhas WHERE ativo = 1");
        $campanhasAtivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($campanhasAtivas)) {
            return [];
        }

        $campanhasFiltradas = [];

        foreach ($campanhasAtivas as $campanha) {
            $gatilhos = json_decode($campanha['crm_gatilhos'], true);

            if (!is_array($gatilhos)) {
                continue;
            }

            if (in_array($status, $gatilhos, true)) {
                $campanhasFiltradas[] = $campanha;
            }
        }

        return $campanhasFiltradas;
    }
}