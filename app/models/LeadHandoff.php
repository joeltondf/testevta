<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/Model.php')) {
    require_once __DIR__ . '/Model.php';
}

/**
 * Lead handoff management model.
 */
class LeadHandoff extends Model
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * LeadHandoff constructor.
     *
     * @param PDO $pdo Active PDO connection instance.
     */
    public function __construct(PDO $pdo)
    {
        try {
            $this->pdo = $pdo;
        } catch (Throwable $exception) {
            error_log('LeadHandoff::__construct error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Creates a new lead handoff entry.
     *
     * @param array $data Associative array with prospeccao_id, sdr_id, vendor_id, handoff_notes and urgency.
     *
     * @return int|false The identifier of the created handoff or false on failure.
     */
    public function create(array $data)
    {
        try {
            $requiredFields = ['prospeccao_id', 'sdr_id', 'vendor_id', 'handoff_notes', 'urgency'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException('Missing required field: ' . $field);
                }
            }

            $allowedUrgencies = ['alta' => 24, 'media' => 48, 'baixa' => 72];
            $urgency = strtolower((string) $data['urgency']);
            if (!array_key_exists($urgency, $allowedUrgencies)) {
                throw new InvalidArgumentException('Invalid urgency provided.');
            }

            $now = new DateTimeImmutable('now');
            $slaDeadline = $now->modify(sprintf('+%d hours', $allowedUrgencies[$urgency]));

            $sql = 'INSERT INTO lead_handoffs (
                        prospeccao_id,
                        sdr_id,
                        vendor_id,
                        handoff_notes,
                        urgency,
                        status,
                        sla_deadline,
                        created_at,
                        updated_at
                    ) VALUES (
                        :prospeccao_id,
                        :sdr_id,
                        :vendor_id,
                        :handoff_notes,
                        :urgency,
                        :status,
                        :sla_deadline,
                        NOW(),
                        NOW()
                    )';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':prospeccao_id', (int) $data['prospeccao_id'], PDO::PARAM_INT);
            $stmt->bindValue(':sdr_id', (int) $data['sdr_id'], PDO::PARAM_INT);
            $stmt->bindValue(':vendor_id', (int) $data['vendor_id'], PDO::PARAM_INT);
            $stmt->bindValue(':handoff_notes', (string) $data['handoff_notes'], PDO::PARAM_STR);
            $stmt->bindValue(':urgency', $urgency, PDO::PARAM_STR);
            $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
            $stmt->bindValue(':sla_deadline', $slaDeadline->format('Y-m-d H:i:s'), PDO::PARAM_STR);

            if (!$stmt->execute()) {
                return false;
            }

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $exception) {
            error_log('LeadHandoff::create error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Accepts a lead handoff for the specified vendor.
     *
     * @param int $handoffId Identifier of the handoff.
     * @param int $vendorId  Identifier of the vendor.
     *
     * @return bool True on success, false otherwise.
     */
    public function accept(int $handoffId, int $vendorId): bool
    {
        try {
            if ($handoffId <= 0 || $vendorId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers provided.');
            }

            if (!$this->isVendorAuthorized($handoffId, $vendorId)) {
                return false;
            }

            $sql = 'UPDATE lead_handoffs
                    SET status = :status,
                        accepted_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':status', 'accepted', PDO::PARAM_STR);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Throwable $exception) {
            error_log('LeadHandoff::accept error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Rejects a lead handoff for the specified vendor with a reason.
     *
     * @param int    $handoffId Identifier of the handoff.
     * @param int    $vendorId  Identifier of the vendor.
     * @param string $reason    Reason for rejection.
     *
     * @return bool True on success, false otherwise.
     */
    public function reject(int $handoffId, int $vendorId, string $reason): bool
    {
        try {
            if ($handoffId <= 0 || $vendorId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers provided.');
            }

            if (trim($reason) === '') {
                throw new InvalidArgumentException('Rejection reason cannot be empty.');
            }

            if (!$this->isVendorAuthorized($handoffId, $vendorId)) {
                return false;
            }

            $sql = 'UPDATE lead_handoffs
                    SET status = :status,
                        rejection_reason = :reason,
                        updated_at = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':status', 'rejected', PDO::PARAM_STR);
            $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Throwable $exception) {
            error_log('LeadHandoff::reject error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Marks the first contact timestamp for the given handoff.
     *
     * @param int $handoffId Identifier of the handoff.
     * @param int $vendorId  Identifier of the vendor.
     *
     * @return bool True on success, false otherwise.
     */
    public function markFirstContact(int $handoffId, int $vendorId): bool
    {
        try {
            if ($handoffId <= 0 || $vendorId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers provided.');
            }

            if (!$this->isVendorAuthorized($handoffId, $vendorId)) {
                return false;
            }

            $sql = 'UPDATE lead_handoffs
                    SET first_contact_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Throwable $exception) {
            error_log('LeadHandoff::markFirstContact error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Adds vendor feedback and quality score to a handoff.
     *
     * @param int    $handoffId   Identifier of the handoff.
     * @param int    $vendorId    Identifier of the vendor.
     * @param int    $qualityScore Quality score between 1 and 5.
     * @param string $feedback     Feedback text provided by the vendor.
     *
     * @return bool True on success, false otherwise.
     */
    public function addFeedback(int $handoffId, int $vendorId, int $qualityScore, string $feedback): bool
    {
        try {
            if ($handoffId <= 0 || $vendorId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers provided.');
            }

            if ($qualityScore < 1 || $qualityScore > 5) {
                throw new InvalidArgumentException('Quality score must be between 1 and 5.');
            }

            if (!$this->isVendorAuthorized($handoffId, $vendorId)) {
                return false;
            }

            $sql = 'UPDATE lead_handoffs
                    SET quality_score = :quality_score,
                        vendor_feedback = :vendor_feedback,
                        updated_at = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':quality_score', $qualityScore, PDO::PARAM_INT);
            $stmt->bindValue(':vendor_feedback', $feedback, PDO::PARAM_STR);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Throwable $exception) {
            error_log('LeadHandoff::addFeedback error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Retrieves pending handoffs for a given vendor.
     *
     * @param int $vendorId Identifier of the vendor.
     *
     * @return array Pending handoffs ordered by urgency and SLA deadline.
     */
    public function getPendingForVendor(int $vendorId): array
    {
        $results = [];

        try {
            if ($vendorId <= 0) {
                throw new InvalidArgumentException('Invalid vendor identifier provided.');
            }

            $sql = "SELECT
                        lh.id,
                        lh.prospeccao_id,
                        lh.handoff_notes,
                        lh.urgency,
                        lh.sla_deadline,
                        lh.created_at,
                        p.nome_prospecto
                    FROM lead_handoffs lh
                    INNER JOIN prospeccoes p ON p.id = lh.prospeccao_id
                    WHERE lh.vendor_id = :vendor_id
                      AND lh.status = 'pending'
                    ORDER BY FIELD(lh.urgency, 'alta', 'media', 'baixa'), lh.sla_deadline ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('LeadHandoff::getPendingForVendor error: ' . $exception->getMessage());
        }

        return $results;
    }

    /**
     * Retrieves handoffs whose SLA has been exceeded without a first contact.
     *
     * @return array Overdue handoff records.
     */
    public function getOverdueSLA(): array
    {
        $results = [];

        try {
            $sql = "SELECT
                        lh.id,
                        lh.prospeccao_id,
                        lh.vendor_id,
                        lh.sdr_id,
                        lh.sla_deadline,
                        lh.accepted_at,
                        p.nome_prospecto,
                        vendor.nome_completo AS vendor_nome
                    FROM lead_handoffs lh
                    LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
                    LEFT JOIN users vendor ON vendor.id = lh.vendor_id
                    WHERE lh.sla_deadline < NOW()
                      AND lh.first_contact_at IS NULL
                      AND lh.status = 'accepted'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('LeadHandoff::getOverdueSLA error: ' . $exception->getMessage());
        }

        return $results;
    }

    /**
     * Retrieves detailed handoff information with related SDR and vendor data.
     *
     * @param int $handoffId Identifier of the handoff.
     *
     * @return array|null Associative array with handoff details or null if not found.
     */
    public function getHandoffDetails(int $handoffId): ?array
    {
        try {
            if ($handoffId <= 0) {
                throw new InvalidArgumentException('Invalid handoff identifier provided.');
            }

            $sql = "SELECT
                        lh.*,
                        p.nome_prospecto,
                        sdr.nome_completo AS sdr_nome,
                        vendor.nome_completo AS vendor_nome
                    FROM lead_handoffs lh
                    LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
                    LEFT JOIN users sdr ON sdr.id = lh.sdr_id
                    LEFT JOIN users vendor ON vendor.id = lh.vendor_id
                    WHERE lh.id = :id
                    LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row !== false ? $row : null;
        } catch (Throwable $exception) {
            error_log('LeadHandoff::getHandoffDetails error: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Retrieves handoffs assigned to a specific SDR optionally filtered by status.
     *
     * @param int         $sdrId  Identifier of the SDR.
     * @param string|null $status Optional status filter.
     *
     * @return array List of handoffs matching the criteria.
     */
    public function getHandoffsBySDR(int $sdrId, ?string $status = null): array
    {
        $results = [];

        try {
            if ($sdrId <= 0) {
                throw new InvalidArgumentException('Invalid SDR identifier provided.');
            }

            $sql = "SELECT
                        lh.id,
                        lh.status,
                        lh.urgency,
                        lh.created_at,
                        lh.updated_at,
                        lh.sla_deadline,
                        lh.first_contact_at,
                        p.nome_prospecto,
                        vendor.nome_completo AS vendor_nome
                    FROM lead_handoffs lh
                    LEFT JOIN prospeccoes p ON p.id = lh.prospeccao_id
                    LEFT JOIN users vendor ON vendor.id = lh.vendor_id
                    WHERE lh.sdr_id = :sdr_id";

            $params = [':sdr_id' => $sdrId];

            if ($status !== null && $status !== '') {
                $sql .= ' AND lh.status = :status';
                $params[':status'] = $status;
            }

            $sql .= ' ORDER BY lh.created_at DESC';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => $value) {
                $type = $param === ':status' ? PDO::PARAM_STR : PDO::PARAM_INT;
                $stmt->bindValue($param, $value, $type);
            }
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('LeadHandoff::getHandoffsBySDR error: ' . $exception->getMessage());
        }

        return $results;
    }

    /**
     * Validates that the vendor is authorized to update the handoff.
     *
     * @param int $handoffId Identifier of the handoff.
     * @param int $vendorId  Identifier of the vendor.
     *
     * @return bool True if the vendor can update the handoff, false otherwise.
     */
    private function isVendorAuthorized(int $handoffId, int $vendorId): bool
    {
        try {
            $sql = 'SELECT vendor_id FROM lead_handoffs WHERE id = :id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $handoffId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row !== false && (int) $row['vendor_id'] === $vendorId;
        } catch (Throwable $exception) {
            error_log('LeadHandoff::isVendorAuthorized error: ' . $exception->getMessage());

            return false;
        }
    }
}
