<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';

class LeadDistributor
{
    private const QUEUE_TABLE = 'lead_distribution_queue';

    private PDO $pdo;
    private Prospeccao $prospectionModel;
    private User $userModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * Garante que uma nova prospecção seja adicionada à fila de distribuição com status pendente.
     */
    public function enqueueProspection(int $leadId): void
    {
        $this->ensureQueueTableExists();

        $sql = 'INSERT INTO ' . self::QUEUE_TABLE . ' (prospeccao_id, tentativas, status)
                VALUES (:leadId, 0, "Pendente")
                ON DUPLICATE KEY UPDATE
                    status = "Pendente",
                    mensagem_erro = NULL,
                    atualizado_em = NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array{leadId:int,vendorId:int,vendorName:string}
     */
    public function distributeToNextSalesperson(int $leadId, ?int $sdrId = null): array
    {
        $activeVendors = $this->indexActiveVendors();
        if (empty($activeVendors)) {
            throw new RuntimeException('Nenhum vendedor ativo encontrado para distribuição.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        $vendorId = null;

        try {
            $this->enqueueProspection($leadId);
            $queueEntry = $this->lockQueueEntry($leadId);

            if ($queueEntry === null) {
                throw new RuntimeException('Não foi possível localizar a prospecção na fila de distribuição.');
            }

            $vendorId = $this->selectNextVendorId($activeVendors);

            if ($vendorId === null) {
                throw new RuntimeException('Nenhum vendedor disponível para receber o lead.');
            }

            if (!$this->prospectionModel->assignLeadToVendor($leadId, $vendorId, $sdrId, false)) {
                throw new RuntimeException('Não foi possível atribuir o lead ao vendedor.');
            }

            $this->prospectionModel->registerLeadDistribution($leadId, $vendorId, $sdrId);

            $this->markQueueAsDistributed($leadId, $vendorId);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'leadId' => $leadId,
                'vendorId' => $vendorId,
                'vendorName' => $activeVendors[$vendorId]['nome_completo'] ?? 'Vendedor'
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->markQueueAsError($leadId, $vendorId, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @return array<string, int>
     */
    public function distribuirLeads(): array
    {
        $leads = $this->prospectionModel->getLeadsWithoutResponsible();
        if (empty($leads)) {
            return [
                'leadsProcessados' => 0,
                'leadsDistribuidos' => 0
            ];
        }

        $processed = 0;
        $distributed = 0;

        foreach ($leads as $lead) {
            $processed++;

            try {
                $leadId = (int) $lead['id'];
                $sdrId = isset($lead['sdrId']) ? (int) $lead['sdrId'] : null;
                $this->distributeToNextSalesperson($leadId, $sdrId);
                $distributed++;
            } catch (Throwable $exception) {
                error_log('Erro ao distribuir lead #' . ($lead['id'] ?? 'desconhecido') . ': ' . $exception->getMessage());
            }
        }

        return [
            'leadsProcessados' => $processed,
            'leadsDistribuidos' => $distributed
        ];
    }

    public function previewNextSalesperson(): ?array
    {
        $activeVendors = $this->indexActiveVendors();
        if (empty($activeVendors)) {
            return null;
        }

        $vendorId = $this->selectNextVendorId($activeVendors);

        if ($vendorId === null) {
            return null;
        }

        $vendorStats = $this->fetchVendorDistributionStats(array_map('intval', array_keys($activeVendors)));
        $lastAssignedAt = $vendorStats[$vendorId]['last_assigned'] ?? null;

        return [
            'vendorId' => $vendorId,
            'vendorName' => $activeVendors[$vendorId]['nome_completo'] ?? 'Vendedor',
            'lastAssignedAt' => $lastAssignedAt,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexActiveVendors(): array
    {
        $vendors = $this->userModel->getActiveVendors();
        $indexed = [];

        foreach ($vendors as $vendor) {
            $indexed[(int) $vendor['id']] = $vendor;
        }

        return $indexed;
    }

    private function lockQueueEntry(int $leadId): ?array
    {
        $this->ensureQueueTableExists();

        $sql = 'SELECT prospeccao_id, tentativas, proximo_vendedor_id, status
                FROM ' . self::QUEUE_TABLE . '
                WHERE prospeccao_id = :leadId
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<int, array<string, mixed>> $activeVendors
     */
    private function selectNextVendorId(array $activeVendors): ?int
    {
        if (empty($activeVendors)) {
            return null;
        }

        $vendorIds = array_map(static fn ($vendor) => (int) $vendor['id'], $activeVendors);
        $stats = $this->fetchVendorDistributionStats($vendorIds);

        $selectedVendorId = null;
        $selectedCount = PHP_INT_MAX;
        $selectedLastAssigned = null;

        foreach ($vendorIds as $vendorId) {
            $vendorStats = $stats[$vendorId] ?? ['total' => 0, 'last_assigned' => null];
            $currentCount = (int) ($vendorStats['total'] ?? 0);
            $currentLastAssigned = $vendorStats['last_assigned'] ?? null;

            if ($selectedVendorId === null
                || $currentCount < $selectedCount
                || ($currentCount === $selectedCount && $this->isOlderAssignment($currentLastAssigned, $selectedLastAssigned))
            ) {
                $selectedVendorId = $vendorId;
                $selectedCount = $currentCount;
                $selectedLastAssigned = $currentLastAssigned;
            }
        }

        return $selectedVendorId;
    }

    /**
     * @param array<int> $vendorIds
     * @return array<int, array{total:int,last_assigned:?string}>
     */
    private function fetchVendorDistributionStats(array $vendorIds): array
    {
        if (empty($vendorIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));

        $sql = 'SELECT vendedorId, COUNT(*) AS total, MAX(createdAt) AS last_assigned
                FROM distribuicao_leads
                WHERE vendedorId IN (' . $placeholders . ')
                GROUP BY vendedorId';

        $stmt = $this->pdo->prepare($sql);
        foreach ($vendorIds as $index => $vendorId) {
            $stmt->bindValue($index + 1, $vendorId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vendorId = (int) ($row['vendedorId'] ?? 0);
            if ($vendorId <= 0) {
                continue;
            }

            $stats[$vendorId] = [
                'total' => (int) ($row['total'] ?? 0),
                'last_assigned' => $row['last_assigned'] ?? null,
            ];
        }

        return $stats;
    }

    private function isOlderAssignment(?string $current, ?string $selected): bool
    {
        if ($selected === null) {
            return true;
        }

        if ($current === null) {
            return true;
        }

        return strcmp($current, $selected) < 0;
    }

    private function markQueueAsDistributed(int $leadId, int $vendorId): void
    {
        $sql = 'UPDATE ' . self::QUEUE_TABLE . '
                SET tentativas = tentativas + 1,
                    proximo_vendedor_id = :vendorId,
                    status = "Distribuído",
                    mensagem_erro = NULL,
                    atualizado_em = NOW()
                WHERE prospeccao_id = :leadId';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $this->enqueueProspection($leadId);
            $stmt->execute();
        }
    }

    private function markQueueAsError(int $leadId, ?int $vendorId, string $errorMessage): void
    {
        $this->ensureQueueTableExists();

        $sql = 'UPDATE ' . self::QUEUE_TABLE . '
                SET tentativas = tentativas + 1,
                    status = "Erro",
                    mensagem_erro = :message,
                    atualizado_em = NOW(),
                    proximo_vendedor_id = :vendorId
                WHERE prospeccao_id = :leadId';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':message', mb_substr($errorMessage, 0, 1000), PDO::PARAM_STR);
        if ($vendorId !== null) {
            $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':vendorId', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $this->enqueueProspection($leadId);
            $stmt->execute();
        }
    }

    private function ensureQueueTableExists(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::QUEUE_TABLE . ' (
                    prospeccao_id INT NOT NULL PRIMARY KEY,
                    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
                    proximo_vendedor_id INT NULL,
                    status ENUM("Pendente", "Distribuído", "Erro") NOT NULL DEFAULT "Pendente",
                    mensagem_erro VARCHAR(1000) NULL,
                    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_lead_queue_prospeccao
                        FOREIGN KEY (prospeccao_id) REFERENCES prospeccoes(id)
                        ON DELETE CASCADE,
                    CONSTRAINT fk_lead_queue_vendor
                        FOREIGN KEY (proximo_vendedor_id) REFERENCES users(id)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->pdo->exec($sql);
    }
}
