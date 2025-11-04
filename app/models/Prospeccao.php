<?php
// /app/models/Prospeccao.php

class Prospeccao
{
    private $pdo;
    private const ALLOWED_PAYMENT_PROFILES = ['mensalista', 'avista'];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function logInteraction(int $prospectionId, int $userId, string $note, string $type = 'nota'): void
    {
        $sql = 'INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo)
                VALUES (:prospectionId, :userId, :note, :type)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prospectionId', $prospectionId, PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function hasActiveProspectionForClient(int $clientId): bool
    {
        $sql = "SELECT COUNT(*)
                FROM prospeccoes
                WHERE cliente_id = :clientId
                  AND status NOT IN ('Descartado', 'Convertido', 'Cliente Ativo', 'Inativo')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findLatestProspectionByClient(int $clientId): ?array
    {
        $sql = "SELECT p.id, p.responsavel_id, u.nome_completo AS vendor_name
                FROM prospeccoes p
                LEFT JOIN users u ON p.responsavel_id = u.id
                WHERE p.cliente_id = :clientId
                ORDER BY p.data_prospeccao DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function resolveOwnerColumn(string $column): string
    {
        $allowedColumns = ['responsavel_id', 'sdrId'];

        return in_array($column, $allowedColumns, true) ? $column : 'responsavel_id';
    }

    private function normalizePaymentProfile(?string $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($profile), 'UTF-8');

        return in_array($normalized, self::ALLOWED_PAYMENT_PROFILES, true) ? $normalized : null;
    }

    /**
     * Busca estatísticas de prospecção para um responsável específico.
     * @param int $responsavel_id O ID do usuário (vendedor).
     * @return array
     */
    public function getStatsByResponsavel(int $responsavel_id, string $ownerColumn = 'responsavel_id'): array
    {
        $ownerColumn = $this->resolveOwnerColumn($ownerColumn);
        $stats = [
            'novos_leads_mes' => 0,
            'reunioes_agendadas_mes' => 0,
            'taxa_conversao' => 0
        ];

        // Novos leads no mês atual
        $stmt_leads = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE {$ownerColumn} = :id AND MONTH(data_prospeccao) = MONTH(CURDATE()) AND YEAR(data_prospeccao) = YEAR(CURDATE())");
        $stmt_leads->execute(['id' => $responsavel_id]);
        $stats['novos_leads_mes'] = $stmt_leads->fetchColumn();

        // Reuniões agendadas no mês atual
        $stmt_reunioes = $this->pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE usuario_id = :id AND MONTH(data_inicio) = MONTH(CURDATE()) AND YEAR(data_inicio) = YEAR(CURDATE())");
        $stmt_reunioes->execute(['id' => $responsavel_id]);
        $stats['reunioes_agendadas_mes'] = $stmt_reunioes->fetchColumn();

        // Taxa de conversão (total)
        $stmt_total = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE {$ownerColumn} = :id");
        $stmt_total->execute(['id' => $responsavel_id]);
        $total_prospeccoes = $stmt_total->fetchColumn();

        $stmt_convertidos = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE {$ownerColumn} = :id AND status = 'Convertido'");
        $stmt_convertidos->execute(['id' => $responsavel_id]);
        $total_convertidos = $stmt_convertidos->fetchColumn();

        if ($total_prospeccoes > 0) {
            $stats['taxa_conversao'] = ($total_convertidos / $total_prospeccoes) * 100;
        }

        return $stats;
    }

    /**
     * Conta o número de prospecções em cada status para o funil de vendas.
     * @param int $responsavel_id
     * @return array
     */
    public function getProspeccoesCountByStatus(int $responsavel_id, string $ownerColumn = 'responsavel_id'): array
    {
        $ownerColumn = $this->resolveOwnerColumn($ownerColumn);
        $sql = "SELECT status, COUNT(*) as total
                FROM prospeccoes
                WHERE {$ownerColumn} = :id
                AND status NOT IN ('Convertido', 'Descartado', 'Inativo', 'Pausa')
                GROUP BY status";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $responsavel_id]);

        // Retorna um array no formato ['Status' => total]
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Busca os próximos agendamentos de um usuário.
     * @param int $responsavel_id
     * @param int $limit
     * @return array
     */
    public function getProximosAgendamentos(int $ownerId, int $limit = 5, string $ownerType = 'usuario'): array
    {
        $limit = max(1, $limit);

        if ($ownerType === 'sdr') {
            $sql = "SELECT a.id,
                           a.titulo,
                           a.status,
                           a.data_inicio,
                           a.data_fim,
                           a.prospeccao_id,
                           c.nome_cliente,
                           u.nome_completo AS responsavel_nome
                    FROM agendamentos a
                    INNER JOIN prospeccoes p ON a.prospeccao_id = p.id
                    LEFT JOIN clientes c ON a.cliente_id = c.id
                    LEFT JOIN users u ON a.usuario_id = u.id
                    WHERE p.sdrId = :id
                      AND a.data_inicio >= NOW()
                    ORDER BY a.data_inicio ASC
                    LIMIT :limit";
        } else {
            $sql = "SELECT a.id,
                           a.titulo,
                           a.status,
                           a.data_inicio,
                           a.data_fim,
                           a.prospeccao_id,
                           c.nome_cliente,
                           u.nome_completo AS responsavel_nome
                    FROM agendamentos a
                    LEFT JOIN clientes c ON a.cliente_id = c.id
                    LEFT JOIN users u ON a.usuario_id = u.id
                    WHERE a.usuario_id = :id
                      AND a.data_inicio >= NOW()
                    ORDER BY a.data_inicio ASC
                    LIMIT :limit";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $ownerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CONTA o número de prospecções que precisam de retorno.
     */
    public function countProspectsForReturn($days, $excluded_statuses, $user_id, $user_perfil)
    {
        $sql_conditions = "status NOT IN (" . str_repeat('?,', count($excluded_statuses) - 1) . "?) AND data_ultima_atualizacao <= NOW() - INTERVAL ? DAY";
        $params = array_merge($excluded_statuses, [$days]);

        if (in_array($user_perfil, ['vendedor', 'sdr'], true) && $user_id) {
            $ownerColumn = $user_perfil === 'sdr' ? 'sdrId' : 'responsavel_id';
            $sql_conditions .= " AND {$ownerColumn} = ?";
            $params[] = $user_id;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE " . $sql_conditions);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * LISTA as prospecções mais urgentes que precisam de retorno.
     */
    public function getUrgentProspectsForReturn($limit, $days, $excluded_statuses, $user_id, $user_perfil)
    {
        $sql_conditions = "p.status NOT IN (" . str_repeat('?,', count($excluded_statuses) - 1) . "?) AND p.data_ultima_atualizacao <= NOW() - INTERVAL ? DAY";
        $params = array_merge($excluded_statuses, [$days]);

        if (in_array($user_perfil, ['vendedor', 'sdr'], true) && $user_id) {
            $ownerColumn = $user_perfil === 'sdr' ? 'p.sdrId' : 'p.responsavel_id';
            $sql_conditions .= " AND {$ownerColumn} = ?";
            $params[] = $user_id;
        }

        $sql = "SELECT p.id, p.nome_prospecto, DATEDIFF(NOW(), p.data_ultima_atualizacao) as dias_sem_contato
                FROM prospeccoes p
                WHERE " . $sql_conditions . "
                ORDER BY p.data_ultima_atualizacao ASC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);

        // Bind parameters with correct types
        $i = 1;
        foreach ($excluded_statuses as $status) {
            $stmt->bindValue($i++, $status, PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, (int)$days, PDO::PARAM_INT);
        if (in_array($user_perfil, ['vendedor', 'sdr'], true) && $user_id) {
            $stmt->bindValue($i++, (int)$user_id, PDO::PARAM_INT);
        }
        $stmt->bindValue($i, (int)$limit, PDO::PARAM_INT); // Explicitly cast limit to an integer

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os usuários do sistema para preencher filtros e selects.
     * @return array
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT id, nome_completo
                FROM users
                WHERE perfil IN ('admin', 'gerencia', 'supervisor', 'vendedor')
                ORDER BY nome_completo ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar todos os usuários: " . $e->getMessage());
            return [];
        }
    }

    public function getById(int $id)
    {
        $sql = "SELECT p.*, c.nome_cliente AS cliente_nome
                FROM prospeccoes p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                WHERE p.id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function getKanbanLeads(
        array $statuses,
        ?int $responsavelId = null,
        bool $onlyUnassigned = false,
        string $ownerColumn = 'responsavel_id',
        ?string $paymentProfile = null
    ): array
    {
        if (empty($statuses)) {
            return [];
        }

        $ownerColumn = $this->resolveOwnerColumn($ownerColumn);
        $ownerJoinColumn = $ownerColumn === 'sdrId' ? 'sdrId' : 'responsavel_id';
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $normalizedProfile = $this->normalizePaymentProfile($paymentProfile);

        $sql = "SELECT
                    p.id,
                    p.nome_prospecto,
                    p.valor_proposto,
                    p.status,
                    p.responsavel_id,
                    p.sdrId,
                    p.perfil_pagamento,
                    p.data_ultima_atualizacao,
                    c.nome_cliente,
                    u.nome_completo AS responsavel_nome
                FROM prospeccoes p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN users u ON p.{$ownerJoinColumn} = u.id
                WHERE p.status IN ($placeholders)";

        if ($onlyUnassigned) {
            $sql .= " AND (p.responsavel_id IS NULL OR p.responsavel_id = 0)";
        } elseif ($responsavelId !== null) {
            $sql .= " AND p.{$ownerColumn} = ?";
            $params[] = $responsavelId;
        }

        if ($normalizedProfile !== null) {
            $sql .= " AND p.perfil_pagamento = ?";
            $params[] = $normalizedProfile;
        }

        $sql .= " ORDER BY p.data_ultima_atualizacao DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function getKanbanOwners(array $statuses): array
    {
        if (empty($statuses)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        $sql = "SELECT DISTINCT u.id, u.nome_completo
                FROM prospeccoes p
                INNER JOIN users u ON p.responsavel_id = u.id
                WHERE p.status IN ($placeholders)
                ORDER BY u.nome_completo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($statuses);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeadDistributionForSdr(int $sdrId): array
    {
        $sql = "SELECT COALESCE(p.responsavel_id, 0) AS vendorId,
                       COALESCE(u.nome_completo, 'Aguardando vendedor') AS vendorName,
                       COUNT(*) AS total
                FROM prospeccoes p
                LEFT JOIN users u ON p.responsavel_id = u.id
                WHERE p.sdrId = :sdrId
                GROUP BY COALESCE(p.responsavel_id, 0), COALESCE(u.nome_completo, 'Aguardando vendedor')
                ORDER BY (p.responsavel_id IS NULL OR p.responsavel_id = 0) ASC, vendorName ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sdrId', $sdrId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSdrVendorConversionRates(int $sdrId): array
    {
        $sql = "SELECT
                    COALESCE(u.id, 0) AS vendorId,
                    COALESCE(u.nome_completo, 'Aguardando vendedor') AS vendorName,
                    COUNT(*) AS totalLeads,
                    SUM(CASE WHEN p.status = 'Convertido' THEN 1 ELSE 0 END) AS convertedLeads
                FROM prospeccoes p
                LEFT JOIN users u ON p.responsavel_id = u.id
                WHERE p.sdrId = :sdrId
                GROUP BY COALESCE(u.id, 0), COALESCE(u.nome_completo, 'Aguardando vendedor')
                ORDER BY vendorName ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sdrId', $sdrId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSdrWorkSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $conditions = ['p.sdrId IS NOT NULL'];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_prospeccao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_prospeccao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    u.id AS sdrId,
                    u.nome_completo AS sdrName,
                    COUNT(*) AS totalLeads,
                    SUM(CASE WHEN p.responsavel_id IS NOT NULL AND p.responsavel_id <> 0 THEN 1 ELSE 0 END) AS assignedLeads,
                    SUM(CASE WHEN p.status = 'Convertido' THEN 1 ELSE 0 END) AS convertedLeads,
                    SUM(COALESCE(p.valor_proposto, 0)) AS totalBudget,
                    SUM(CASE WHEN p.status = 'Convertido' THEN COALESCE(p.valor_proposto, 0) ELSE 0 END) AS convertedBudget,
                    SUM(COALESCE(ag.totalAppointments, 0)) AS totalAppointments
                FROM prospeccoes p
                INNER JOIN users u ON p.sdrId = u.id
                LEFT JOIN (
                    SELECT prospeccao_id, COUNT(*) AS totalAppointments
                    FROM agendamentos
                    GROUP BY prospeccao_id
                ) ag ON ag.prospeccao_id = p.id
                $whereSql
                GROUP BY u.id, u.nome_completo
                ORDER BY u.nome_completo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $totalLeads = (int) ($row['totalLeads'] ?? 0);
            $convertedLeads = (int) ($row['convertedLeads'] ?? 0);
            $row['conversionRate'] = $totalLeads > 0 ? ($convertedLeads / $totalLeads) * 100 : 0;
        }

        unset($row);

        return $rows;
    }

    public function getSdrVendorConversionSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $conditions = ['p.sdrId IS NOT NULL'];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_prospeccao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_prospeccao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    COALESCE(v.id, 0) AS vendorId,
                    COALESCE(u.id, 0) AS userId,
                    COALESCE(u.nome_completo, 'Sem vendedor') AS vendorName,
                    COUNT(*) AS totalLeads,
                    SUM(CASE WHEN p.status = 'Convertido' THEN 1 ELSE 0 END) AS convertedLeads,
                    SUM(COALESCE(p.valor_proposto, 0)) AS totalBudget,
                    SUM(CASE WHEN p.status = 'Convertido' THEN COALESCE(p.valor_proposto, 0) ELSE 0 END) AS convertedBudget
                FROM prospeccoes p
                LEFT JOIN vendedores v ON v.user_id = p.responsavel_id
                LEFT JOIN users u ON u.id = p.responsavel_id
                $whereSql
                GROUP BY COALESCE(v.id, 0), COALESCE(u.id, 0), COALESCE(u.nome_completo, 'Sem vendedor')
                ORDER BY convertedLeads DESC, vendorName ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $totalLeads = (int) ($row['totalLeads'] ?? 0);
            $convertedLeads = (int) ($row['convertedLeads'] ?? 0);
            $row['conversionRate'] = $totalLeads > 0 ? ($convertedLeads / $totalLeads) * 100 : 0;
        }

        unset($row);

        return $rows;
    }

    public function registerLeadDistribution(int $leadId, int $vendorId, ?int $sdrId = null): void
    {
        $sql = "INSERT INTO distribuicao_leads (prospeccaoId, sdrId, vendedorId, createdAt)
                VALUES (:leadId, :sdrId, :vendorId, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
        if ($sdrId !== null) {
            $stmt->bindValue(':sdrId', $sdrId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':sdrId', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function assignLeadToVendor(int $leadId, ?int $vendorId, ?int $sdrId = null, bool $useTransaction = true): bool
    {
        if ($useTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $setClauses = ['responsavel_id = :vendorId', 'data_ultima_atualizacao = NOW()'];
            $params = [':leadId' => $leadId];

            if ($vendorId !== null) {
                $params[':vendorId'] = $vendorId;
            }

            if ($sdrId !== null) {
                $setClauses[] = 'sdrId = :sdrId';
                $params[':sdrId'] = $sdrId;
            }

            $sql = 'UPDATE prospeccoes SET ' . implode(', ', $setClauses) . ' WHERE id = :leadId';
            $stmt = $this->pdo->prepare($sql);

            if ($vendorId === null) {
                $stmt->bindValue(':vendorId', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
            }

            foreach ($params as $key => $value) {
                if ($key === ':vendorId') {
                    continue;
                }

                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }

            $stmt->execute();

            if ($vendorId !== null) {
                $this->registerLeadDistribution($leadId, $vendorId, $sdrId);
            }

            if ($useTransaction) {
                $this->pdo->commit();
            }

            return true;
        } catch (PDOException $exception) {
            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    public function createLeadFromKanban(array $payload): array
    {
        $companyName = trim((string)($payload['companyName'] ?? ''));
        $contactName = trim((string)($payload['contactName'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $channel = trim((string)($payload['channel'] ?? 'Outro'));
        $status = trim((string)($payload['status'] ?? ''));
        $leadCategory = 'Entrada';
        $vendorId = $payload['vendorId'] ?? null;
        $sdrId = $payload['sdrId'] ?? null;

        if ($companyName === '') {
            throw new InvalidArgumentException('company_name_required');
        }

        if ($status === '') {
            throw new InvalidArgumentException('status_required');
        }

        $normalizedEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $normalizedPhone = $phone !== '' ? preg_replace('/\s+/', ' ', $phone) : null;

        $prospectName = $contactName !== '' ? $contactName : $companyName;

        $vendorParam = null;
        if ($vendorId !== null) {
            $vendorParam = (int)$vendorId > 0 ? (int)$vendorId : null;
        }

        $sdrParam = null;
        if ($sdrId !== null) {
            $sdrParam = (int)$sdrId > 0 ? (int)$sdrId : null;
        }

        $this->pdo->beginTransaction();

        try {
            $clientSql = 'INSERT INTO clientes (
                    nome_cliente,
                    nome_responsavel,
                    email,
                    telefone,
                    canal_origem,
                    categoria,
                    is_prospect,
                    crmOwnerId
                ) VALUES (
                    :company_name,
                    :contact_name,
                    :email,
                    :phone,
                    :channel,
                    :category,
                    1,
                    :owner_id
                )';

            $clientStmt = $this->pdo->prepare($clientSql);
            $clientStmt->bindValue(':company_name', $companyName, PDO::PARAM_STR);
            $clientStmt->bindValue(':contact_name', $contactName !== '' ? $contactName : null, PDO::PARAM_STR);
            $clientStmt->bindValue(':email', $normalizedEmail, $normalizedEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $clientStmt->bindValue(':phone', $normalizedPhone, $normalizedPhone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $clientStmt->bindValue(':channel', $channel !== '' ? $channel : 'Outro', PDO::PARAM_STR);
            $clientStmt->bindValue(':category', $leadCategory, PDO::PARAM_STR);
            if ($vendorParam !== null) {
                $clientStmt->bindValue(':owner_id', $vendorParam, PDO::PARAM_INT);
            } else {
                $clientStmt->bindValue(':owner_id', null, PDO::PARAM_NULL);
            }
            $clientStmt->execute();

            $clientId = (int)$this->pdo->lastInsertId();

            $prospectionSql = 'INSERT INTO prospeccoes (
                    cliente_id,
                    nome_prospecto,
                    data_prospeccao,
                    responsavel_id,
                    sdrId,
                    feedback_inicial,
                    valor_proposto,
                    status,
                    leadCategory,
                    perfil_pagamento,
                    data_ultima_atualizacao
                ) VALUES (
                    :client_id,
                    :prospect_name,
                    NOW(),
                    :vendor_id,
                    :sdr_id,
                    :feedback,
                    :deal_value,
                    :status,
                    :lead_category,
                    NULL,
                    NOW()
                )';

            $prospectionStmt = $this->pdo->prepare($prospectionSql);
            $prospectionStmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
            $prospectionStmt->bindValue(':prospect_name', $prospectName, PDO::PARAM_STR);
            if ($vendorParam !== null) {
                $prospectionStmt->bindValue(':vendor_id', $vendorParam, PDO::PARAM_INT);
            } else {
                $prospectionStmt->bindValue(':vendor_id', null, PDO::PARAM_NULL);
            }
            if ($sdrParam !== null) {
                $prospectionStmt->bindValue(':sdr_id', $sdrParam, PDO::PARAM_INT);
            } else {
                $prospectionStmt->bindValue(':sdr_id', null, PDO::PARAM_NULL);
            }
            $prospectionStmt->bindValue(':feedback', '', PDO::PARAM_STR);
            $prospectionStmt->bindValue(':deal_value', 0, PDO::PARAM_INT);
            $prospectionStmt->bindValue(':status', $status, PDO::PARAM_STR);
            $prospectionStmt->bindValue(':lead_category', $leadCategory, PDO::PARAM_STR);
            $prospectionStmt->execute();

            $prospectionId = (int)$this->pdo->lastInsertId();

            if ($vendorParam !== null && $sdrParam !== null) {
                $this->registerLeadDistribution($prospectionId, $vendorParam, $sdrParam);
            }

            $this->logInteraction(
                $prospectionId,
                $sdrParam ?? 0,
                'Lead criado diretamente no Kanban do SDR.',
                'sistema'
            );

            $this->pdo->commit();

            return [
                'clientId' => $clientId,
                'prospectionId' => $prospectionId,
            ];
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveSellers(): array
    {
        $sql = "SELECT id, nome_completo
                FROM users
                WHERE perfil = 'vendedor' AND (ativo = 1 OR ativo IS NULL)
                ORDER BY nome_completo ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Erro ao buscar vendedores ativos: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * @param array<string> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function getLeadsOutsideKanban(
        array $statuses,
        ?int $responsavelId = null,
        bool $onlyUnassigned = false,
        string $ownerColumn = 'responsavel_id',
        ?string $paymentProfile = null
    ): array
    {
        if (empty($statuses)) {
            return [];
        }

        $ownerColumn = $this->resolveOwnerColumn($ownerColumn);
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $normalizedProfile = $this->normalizePaymentProfile($paymentProfile);

        $sql = "SELECT
                    p.id,
                    p.nome_prospecto,
                    p.responsavel_id,
                    p.sdrId,
                    p.status,
                    p.data_ultima_atualizacao,
                    p.perfil_pagamento,
                    u.nome_completo AS responsavel_nome
                FROM prospeccoes p
                LEFT JOIN users u ON p.{$ownerColumn} = u.id
                WHERE (p.status IS NULL OR p.status = '' OR p.status NOT IN ($placeholders))";

        if ($onlyUnassigned) {
            $sql .= " AND (p.responsavel_id IS NULL OR p.responsavel_id = 0)";
        } elseif ($responsavelId !== null) {
            $sql .= " AND p.{$ownerColumn} = ?";
            $params[] = $responsavelId;
        }

        if ($normalizedProfile !== null) {
            $sql .= " AND p.perfil_pagamento = ?";
            $params[] = $normalizedProfile;
        }

        $sql .= " ORDER BY p.data_ultima_atualizacao DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string> $statuses
     */
    public function hasUnassignedKanbanLeads(array $statuses, ?string $paymentProfile = null): bool
    {
        if (empty($statuses)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $normalizedProfile = $this->normalizePaymentProfile($paymentProfile);

        $sql = "SELECT COUNT(*)
                FROM prospeccoes p
                WHERE p.status IN ($placeholders)
                  AND (p.responsavel_id IS NULL OR p.responsavel_id = 0)";

        $params = $statuses;

        if ($normalizedProfile !== null) {
            $sql .= " AND p.perfil_pagamento = ?";
            $params[] = $normalizedProfile;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function updateLeadStatus(int $leadId, string $newStatus): bool
    {
        $sql = "UPDATE prospeccoes
                SET status = :status,
                    data_ultima_atualizacao = NOW()
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':status' => $newStatus,
            ':id' => $leadId
        ]);
    }

    public function saveQualification(int $prospeccaoId, array $data): int
    {
        $sql = "INSERT INTO prospeccao_qualificacoes (
                    prospeccaoId,
                    sdrId,
                    fitIcp,
                    budget,
                    authority,
                    timing,
                    decision,
                    notes,
                    createdAt
                ) VALUES (
                    :prospeccaoId,
                    :sdrId,
                    :fitIcp,
                    :budget,
                    :authority,
                    :timing,
                    :decision,
                    :notes,
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':prospeccaoId' => $prospeccaoId,
            ':sdrId' => $data['sdrId'],
            ':fitIcp' => $data['fitIcp'],
            ':budget' => $data['budget'],
            ':authority' => $data['authority'],
            ':timing' => $data['timing'],
            ':decision' => $data['decision'],
            ':notes' => $data['notes'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateResponsavelAndStatus(int $prospeccaoId, ?int $responsavelId, string $status): bool
    {
        $sql = "UPDATE prospeccoes
                SET responsavel_id = :responsavel_id,
                    status = :status,
                    data_ultima_atualizacao = NOW()
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        if ($responsavelId === null) {
            $stmt->bindValue(':responsavel_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':responsavel_id', $responsavelId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', $prospeccaoId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getLeadsWithoutResponsible(): array
    {
        $sql = "SELECT id, sdrId
                FROM prospeccoes
                WHERE responsavel_id IS NULL OR responsavel_id = 0
                ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeadsDistributedCount(?int $sdrId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM distribuicao_leads';
        $params = [];

        if ($sdrId !== null) {
            $sql .= ' WHERE sdrId = :sdrId';
            $params[':sdrId'] = $sdrId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAppointmentsCount(?int $sdrId = null): int
    {
        if ($sdrId === null) {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM agendamentos');
            return (int) $stmt->fetchColumn();
        }

        $sql = 'SELECT COUNT(*)
                FROM agendamentos a
                INNER JOIN prospeccoes p ON a.prospeccao_id = p.id
                WHERE p.sdrId = :sdrId';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sdrId', $sdrId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSdrLeads(int $sdrId): array
    {
        $sql = "SELECT p.id,
                       p.nome_prospecto,
                       p.status,
                       p.data_ultima_atualizacao,
                       COALESCE(u.nome_completo, 'Aguardando vendedor') AS vendor_name,
                       c.nome_cliente,
                       p.valor_proposto,
                       p.cliente_id,
                       p.responsavel_id
                FROM prospeccoes p
                LEFT JOIN users u ON p.responsavel_id = u.id
                LEFT JOIN clientes c ON p.cliente_id = c.id
                WHERE p.sdrId = :sdrId
                ORDER BY p.data_ultima_atualizacao DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sdrId', $sdrId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVendorLeads(int $vendorId): array
    {
        $sql = "SELECT p.id,
                       p.nome_prospecto,
                       p.status,
                       p.data_ultima_atualizacao,
                       c.nome_cliente,
                       p.cliente_id,
                       p.responsavel_id
                FROM prospeccoes p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                WHERE p.responsavel_id = :vendorId
                ORDER BY p.data_ultima_atualizacao DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNextLeadForVendor(int $vendorId): ?array
    {
        $sql = "SELECT p.id,
                       p.nome_prospecto,
                       p.status,
                       p.data_ultima_atualizacao,
                       c.nome_cliente,
                       COALESCE(dl.lastAssignedAt, p.data_ultima_atualizacao) AS distributed_at
                FROM prospeccoes p
                LEFT JOIN (
                    SELECT prospeccaoId, MAX(createdAt) AS lastAssignedAt
                    FROM distribuicao_leads
                    GROUP BY prospeccaoId
                ) dl ON dl.prospeccaoId = p.id
                LEFT JOIN clientes c ON p.cliente_id = c.id
                WHERE p.responsavel_id = :vendorId
                  AND (p.status IS NULL OR p.status NOT IN ('Descartado', 'Convertido', 'Inativo'))
                ORDER BY dl.lastAssignedAt IS NULL DESC,
                         distributed_at ASC,
                         p.id ASC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->execute();

        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead !== false ? $lead : null;
    }

}
