<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/SdrKanbanConfigService.php';
require_once __DIR__ . '/LeadDistributor.php';

class DashboardKanbanService
{
    private Prospeccao $prospectionModel;
    private User $userModel;
    private SdrKanbanConfigService $sdrKanbanConfig;
    private LeadDistributor $leadDistributor;

    public function __construct(PDO $pdo)
    {
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->sdrKanbanConfig = new SdrKanbanConfigService($pdo);
        $this->leadDistributor = new LeadDistributor($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSdrBoard(int $sdrId, array $filters = []): array
    {
        $availableStatuses = $this->sdrKanbanConfig->getColumns();
        $selectedStatuses = $this->resolveStatuses($filters, $availableStatuses);

        $onlyUnassigned = !empty($filters['only_unassigned']);
        $selectedVendorId = isset($filters['vendor_id']) && (int)$filters['vendor_id'] > 0
            ? (int)$filters['vendor_id']
            : null;
        $paymentProfile = $this->sanitizePaymentProfile($filters['payment_profile'] ?? null);

        $kanbanLeads = $this->prospectionModel->getKanbanLeads(
            $selectedStatuses,
            $onlyUnassigned ? null : $sdrId,
            $onlyUnassigned,
            'sdrId',
            $paymentProfile,
            $sdrId,
            $selectedVendorId
        );

        $leadsByStatus = $this->groupLeadsByStatus($selectedStatuses, $kanbanLeads);
        $distribution = $this->prospectionModel->getLeadDistributionForSdr($sdrId);

        return [
            'available_statuses' => $availableStatuses,
            'selected_statuses' => $selectedStatuses,
            'leads_by_status' => $leadsByStatus,
            'filters' => [
                'selected_vendor_id' => $selectedVendorId,
                'selected_payment_profile' => $paymentProfile,
                'only_unassigned' => $onlyUnassigned,
                'status_options' => $availableStatuses,
                'vendor_options' => $distribution,
                'payment_profiles' => $this->prospectionModel->getAllowedPaymentProfiles(),
            ],
            'raw_leads' => $kanbanLeads,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildVendorBoard(int $vendorUserId, array $filters = []): array
    {
        $availableStatuses = $this->prospectionModel->getActiveKanbanStatuses($vendorUserId, 'responsavel_id');
        if (empty($availableStatuses)) {
            $availableStatuses = $this->sdrKanbanConfig->getColumns();
        }
        $selectedStatuses = $this->resolveStatuses($filters, $availableStatuses);

        $onlyUnassigned = !empty($filters['only_unassigned']);
        $selectedSdrId = isset($filters['sdr_id']) && (int)$filters['sdr_id'] > 0
            ? (int)$filters['sdr_id']
            : null;
        $paymentProfile = $this->sanitizePaymentProfile($filters['payment_profile'] ?? null);

        $kanbanLeads = $this->prospectionModel->getKanbanLeads(
            $selectedStatuses,
            $onlyUnassigned ? null : $vendorUserId,
            $onlyUnassigned,
            'responsavel_id',
            $paymentProfile,
            $selectedSdrId,
            $onlyUnassigned ? null : $vendorUserId
        );

        $leadsByStatus = $this->groupLeadsByStatus($selectedStatuses, $kanbanLeads);
        $sdrDirectory = $this->prospectionModel->getSdrDirectoryForVendor($vendorUserId);

        return [
            'available_statuses' => $availableStatuses,
            'selected_statuses' => $selectedStatuses,
            'leads_by_status' => $leadsByStatus,
            'filters' => [
                'selected_sdr_id' => $selectedSdrId,
                'selected_payment_profile' => $paymentProfile,
                'only_unassigned' => $onlyUnassigned,
                'status_options' => $availableStatuses,
                'sdr_options' => $sdrDirectory,
                'payment_profiles' => $this->prospectionModel->getAllowedPaymentProfiles(),
            ],
            'raw_leads' => $kanbanLeads,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildManagerBoard(array $filters = []): array
    {
        $availableStatuses = $this->prospectionModel->getActiveKanbanStatuses();
        if (empty($availableStatuses)) {
            $availableStatuses = $this->sdrKanbanConfig->getColumns();
        }
        $selectedStatuses = $this->resolveStatuses($filters, $availableStatuses);

        $onlyUnassigned = !empty($filters['only_unassigned']);
        $selectedVendorId = isset($filters['vendor_id']) && (int)$filters['vendor_id'] > 0
            ? (int)$filters['vendor_id']
            : null;
        $selectedSdrId = isset($filters['sdr_id']) && (int)$filters['sdr_id'] > 0
            ? (int)$filters['sdr_id']
            : null;
        $paymentProfile = $this->sanitizePaymentProfile($filters['payment_profile'] ?? null);

        $kanbanLeads = $this->prospectionModel->getKanbanLeads(
            $selectedStatuses,
            $selectedVendorId,
            $onlyUnassigned,
            'responsavel_id',
            $paymentProfile,
            $selectedSdrId,
            $selectedVendorId
        );

        $leadsByStatus = $this->groupLeadsByStatus($selectedStatuses, $kanbanLeads);

        return [
            'available_statuses' => $availableStatuses,
            'selected_statuses' => $selectedStatuses,
            'leads_by_status' => $leadsByStatus,
            'filters' => [
                'selected_vendor_id' => $selectedVendorId,
                'selected_sdr_id' => $selectedSdrId,
                'selected_payment_profile' => $paymentProfile,
                'only_unassigned' => $onlyUnassigned,
                'status_options' => $availableStatuses,
                'vendor_options' => $this->userModel->getActiveVendors(),
                'sdr_options' => $this->userModel->getActiveSdrs(),
                'payment_profiles' => $this->prospectionModel->getAllowedPaymentProfiles(),
            ],
            'raw_leads' => $kanbanLeads,
            'queue_preview' => $this->leadDistributor->getPendingQueue(),
        ];
    }

    /**
     * @param array<int, string> $statuses
     * @param array<int, array<string, mixed>> $leads
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupLeadsByStatus(array $statuses, array $leads): array
    {
        $grouped = [];
        foreach ($statuses as $status) {
            $grouped[$status] = [];
        }

        foreach ($leads as $lead) {
            $status = $lead['status'] ?? '';
            if (!isset($grouped[$status])) {
                $grouped[$status] = [];
            }
            $grouped[$status][] = $lead;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string> $availableStatuses
     * @return array<int, string>
     */
    private function resolveStatuses(array $filters, array $availableStatuses): array
    {
        $selected = $filters['statuses'] ?? $filters['kanban_status'] ?? null;
        if ($selected === null) {
            return $availableStatuses;
        }

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        $normalized = [];
        foreach ($selected as $status) {
            $name = trim((string) $status);
            if ($name === '') {
                continue;
            }
            if (in_array($name, $availableStatuses, true)) {
                $normalized[] = $name;
            }
        }

        return !empty($normalized) ? $normalized : $availableStatuses;
    }

    private function sanitizePaymentProfile($profile): ?string
    {
        if ($profile === null || $profile === '') {
            return null;
        }

        $allowed = $this->prospectionModel->getAllowedPaymentProfiles();
        foreach ($allowed as $allowedProfile) {
            if (strcasecmp($allowedProfile, (string) $profile) === 0) {
                return $allowedProfile;
            }
        }

        return null;
    }
}
