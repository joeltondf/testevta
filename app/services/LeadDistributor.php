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

        try {
            $queue = $this->fetchQueueForUpdate();
            $queue = $this->syncQueueWithActiveVendors($queue, $activeVendors);

            if (empty($queue)) {
                throw new RuntimeException('A fila de distribuição está vazia.');
            }

            $nextVendorRow = array_shift($queue);
            $vendorId = (int) $nextVendorRow['vendor_id'];

            if (!$this->prospectionModel->assignLeadToVendor($leadId, $vendorId, $sdrId, false)) {
                throw new RuntimeException('Não foi possível atribuir o lead ao vendedor.');
            }

            $nextVendorRow['last_assigned_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $queue[] = $nextVendorRow;

            $this->persistQueue($queue);

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

        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $queue = $this->fetchQueueForUpdate();
            $queue = $this->syncQueueWithActiveVendors($queue, $activeVendors);
            $this->persistQueue($queue);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            if (empty($queue)) {
                return null;
            }

            $nextRow = $queue[0];
            $vendorId = (int) $nextRow['vendor_id'];
            $vendor = $activeVendors[$vendorId] ?? null;

            if ($vendor === null) {
                return null;
            }

            return [
                'vendorId' => $vendorId,
                'vendorName' => $vendor['nome_completo'] ?? 'Vendedor',
                'lastAssignedAt' => $nextRow['last_assigned_at'] ?? null
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, array{vendor_id:int,position:int,last_assigned_at:?string}>
     */
    private function fetchQueueForUpdate(): array
    {
        $this->ensureQueueTableExists();

        $sql = 'SELECT vendor_id, position, last_assigned_at
                FROM ' . self::QUEUE_TABLE . '
                ORDER BY position ASC
                FOR UPDATE';

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array{vendor_id:int,position:int,last_assigned_at:?string}> $queue
     * @param array<int, array<string, mixed>> $activeVendors
     * @return array<int, array{vendor_id:int,position:int,last_assigned_at:?string}>
     */
    private function syncQueueWithActiveVendors(array $queue, array $activeVendors): array
    {
        $queueVendorIds = array_map(static fn ($row) => (int) $row['vendor_id'], $queue);
        $activeVendorIds = array_map(static fn ($vendor) => (int) $vendor['id'], $activeVendors);

        $queue = array_values(array_filter($queue, static function ($row) use ($activeVendorIds) {
            return in_array((int) $row['vendor_id'], $activeVendorIds, true);
        }));

        foreach ($activeVendorIds as $vendorId) {
            if (!in_array($vendorId, $queueVendorIds, true)) {
                $queue[] = [
                    'vendor_id' => $vendorId,
                    'position' => count($queue) + 1,
                    'last_assigned_at' => null
                ];
            }
        }

        usort($queue, static function ($a, $b) {
            $aDate = $a['last_assigned_at'];
            $bDate = $b['last_assigned_at'];

            if ($aDate === $bDate) {
                return ((int) $a['position']) <=> ((int) $b['position']);
            }

            if ($aDate === null) {
                return -1;
            }

            if ($bDate === null) {
                return 1;
            }

            return strcmp($aDate, $bDate);
        });

        $queue = array_values($queue);

        foreach ($queue as $index => &$row) {
            $row['position'] = $index + 1;
        }

        return $queue;
    }

    /**
     * @param array<int, array{vendor_id:int,position:int,last_assigned_at:?string}> $queue
     */
    private function persistQueue(array $queue): void
    {
        $this->pdo->exec('DELETE FROM ' . self::QUEUE_TABLE);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::QUEUE_TABLE . ' (vendor_id, position, last_assigned_at)
             VALUES (:vendorId, :position, :lastAssignedAt)'
        );

        foreach ($queue as $index => $row) {
            $stmt->bindValue(':vendorId', (int) $row['vendor_id'], PDO::PARAM_INT);
            $stmt->bindValue(':position', $index + 1, PDO::PARAM_INT);

            if (!empty($row['last_assigned_at'])) {
                $stmt->bindValue(':lastAssignedAt', $row['last_assigned_at']);
            } else {
                $stmt->bindValue(':lastAssignedAt', null, PDO::PARAM_NULL);
            }

            $stmt->execute();
        }
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

    private function ensureQueueTableExists(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::QUEUE_TABLE . ' (
                    vendor_id INT NOT NULL PRIMARY KEY,
                    position INT NOT NULL,
                    last_assigned_at DATETIME NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_lead_distribution_queue_vendor
                        FOREIGN KEY (vendor_id) REFERENCES users(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->pdo->exec($sql);
    }
}
