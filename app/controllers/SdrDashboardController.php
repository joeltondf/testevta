<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/LeadHandoff.php';
require_once __DIR__ . '/../services/SdrKanbanConfigService.php';

class SdrDashboardController
{
    private $pdo;
    private $prospectionModel;
    private $userModel;
    private LeadHandoff $leadHandoffModel;
    private SdrKanbanConfigService $kanbanConfigService;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->leadHandoffModel = new LeadHandoff($pdo);
        $this->kanbanConfigService = new SdrKanbanConfigService($pdo);
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit();
        }

        $kanbanStatuses = $this->kanbanConfigService->getColumns();
        $stats = $this->buildDashboardStats($userId);
        $statusCounts = $this->prospectionModel->getProspeccoesCountByStatus($userId, 'sdrId');
        $excludedStatuses = ['Qualificado', 'Descartado', 'Convertido'];
        $urgentLeads = $this->prospectionModel->getUrgentProspectsForReturn(10, 3, $excludedStatuses, $userId, 'sdr');
        $upcomingMeetings = $this->prospectionModel->getProximosAgendamentos($userId, 5, 'sdr');
        $kanbanLeads = $this->prospectionModel->getKanbanLeads($kanbanStatuses, $userId, false, 'sdrId');
        $activeVendors = $this->userModel->getActiveVendors();
        $vendorDistribution = $this->prospectionModel->getLeadDistributionForSdr($userId);
        $vendorConversionRates = $this->prospectionModel->getSdrVendorConversionRates($userId);

        $assignedLeadCount = 0;
        $unassignedLeadCount = 0;

        $hasUnassignedRow = false;

        foreach ($vendorDistribution as $distributionRow) {
            $total = (int) ($distributionRow['total'] ?? 0);
            $vendorId = (int) ($distributionRow['vendorId'] ?? 0);

            if ($vendorId > 0) {
                $assignedLeadCount += $total;
            } else {
                $unassignedLeadCount += $total;
                $hasUnassignedRow = true;
            }
        }

        if (!$hasUnassignedRow) {
            $vendorDistribution[] = [
                'vendorId' => 0,
                'vendorName' => 'Aguardando vendedor',
                'total' => 0
            ];
        }

        $leadsByStatus = [];
        foreach ($kanbanStatuses as $status) {
            $leadsByStatus[$status] = [];
        }
        foreach ($kanbanLeads as $lead) {
            $status = $lead['status'] ?? '';
            if (!isset($leadsByStatus[$status])) {
                $leadsByStatus[$status] = [];
            }
            $leadsByStatus[$status][] = $lead;
        }

        $pageTitle = 'Painel SDR';
        $totalLeads = array_sum($statusCounts);
        $kanbanEditUrl = APP_URL . '/sdr_dashboard.php?action=update_columns';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/sdr_dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function myFeedbacks(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit();
        }

        $feedbackRecords = $this->leadHandoffModel->getFeedbackForSdr($userId);
        $totalFeedbacks = count($feedbackRecords);
        $scoreSum = 0;
        $scoreDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        $flagLabels = [
            'info_correct' => 'Informações corretas',
            'icp_match' => 'Lead no perfil (ICP)',
            'engaged' => 'Lead engajado',
            'expectations' => 'Expectativas alinhadas',
        ];

        $flagCounts = array_fill_keys(array_keys($flagLabels), 0);
        $recentFeedbacks = [];

        foreach ($feedbackRecords as $record) {
            $score = (int) ($record['quality_score'] ?? 0);
            if ($score >= 1 && $score <= 5) {
                $scoreDistribution[$score]++;
                $scoreSum += $score;
            }

            $rawFeedback = $record['vendor_feedback'] ?? '';
            $decodedFeedback = null;
            if (is_string($rawFeedback) && $rawFeedback !== '') {
                $decoded = json_decode($rawFeedback, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $decodedFeedback = $decoded;
                }
            }

            $comment = '';
            if (is_array($decodedFeedback) && array_key_exists('comments', $decodedFeedback)) {
                $comment = (string) $decodedFeedback['comments'];
            } elseif (is_string($rawFeedback)) {
                $comment = (string) $rawFeedback;
            }
            $comment = trim($comment);

            $flags = [];
            foreach ($flagLabels as $key => $label) {
                $flagValue = false;
                if (is_array($decodedFeedback)) {
                    if (array_key_exists($key, $decodedFeedback)) {
                        $flagValue = $decodedFeedback[$key];
                    } elseif (isset($decodedFeedback['flags']) && is_array($decodedFeedback['flags']) && array_key_exists($key, $decodedFeedback['flags'])) {
                        $flagValue = $decodedFeedback['flags'][$key];
                    }
                }

                $flagBool = $this->normalizeFeedbackFlag($flagValue);
                $flags[$key] = $flagBool;
                if ($flagBool) {
                    $flagCounts[$key]++;
                }
            }

            $recentFeedbacks[] = [
                'id' => (int) ($record['id'] ?? 0),
                'lead_name' => $record['nome_prospecto'] ?? 'Lead',
                'vendor_name' => $record['vendor_name'] ?? 'Vendedor',
                'score' => $score,
                'comments' => $comment,
                'flags' => $flags,
                'submitted_at' => $record['updated_at'] ?? $record['accepted_at'] ?? $record['created_at'] ?? null,
            ];
        }

        usort($recentFeedbacks, static function (array $a, array $b): int {
            $timeA = isset($a['submitted_at']) ? strtotime((string) $a['submitted_at']) : false;
            $timeB = isset($b['submitted_at']) ? strtotime((string) $b['submitted_at']) : false;

            $timeA = $timeA !== false ? $timeA : PHP_INT_MIN;
            $timeB = $timeB !== false ? $timeB : PHP_INT_MIN;

            return $timeB <=> $timeA;
        });

        $recentFeedbacks = array_slice($recentFeedbacks, 0, 10);
        $averageScore = $totalFeedbacks > 0 ? round($scoreSum / $totalFeedbacks, 2) : null;

        $scoreDistributionPercent = [];
        foreach ($scoreDistribution as $score => $count) {
            $scoreDistributionPercent[$score] = $totalFeedbacks > 0
                ? round(($count / $totalFeedbacks) * 100)
                : 0;
        }

        $flagPercentages = [];
        foreach ($flagCounts as $key => $count) {
            $flagPercentages[$key] = $totalFeedbacks > 0
                ? round(($count / $totalFeedbacks) * 100)
                : 0;
        }

        $feedbackSummary = [
            'total' => $totalFeedbacks,
            'average' => $averageScore,
            'score_distribution' => $scoreDistribution,
            'score_distribution_percent' => $scoreDistributionPercent,
        ];

        $feedbackPatterns = [
            'labels' => $flagLabels,
            'counts' => $flagCounts,
            'percentages' => $flagPercentages,
        ];

        $pageTitle = 'Feedbacks Recebidos';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/sdr_dashboard/my_feedbacks.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function assignLeadOwner(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $leadId = isset($payload['leadId']) ? (int) $payload['leadId'] : 0;
        $vendorId = isset($payload['vendorId']) && $payload['vendorId'] !== ''
            ? (int) $payload['vendorId']
            : null;

        if ($leadId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Lead inválido.']);
            return;
        }

        $lead = $this->prospectionModel->getById($leadId);
        if (!$lead || (int) ($lead['sdrId'] ?? 0) !== $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lead não encontrado.']);
            return;
        }

        if ($vendorId !== null) {
            $vendor = $this->userModel->findActiveVendorById($vendorId);
            if ($vendor === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Vendedor não localizado.']);
                return;
            }
        }

        $currentVendor = (int) ($lead['responsavel_id'] ?? 0);
        if (($vendorId ?? 0) === $currentVendor) {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração necessária.']);
            return;
        }

        if (!$this->prospectionModel->assignLeadToVendor($leadId, $vendorId, $userId)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o responsável.']);
            return;
        }

        $distribution = $this->prospectionModel->getLeadDistributionForSdr($userId);

        $assignedCount = 0;
        $unassignedCount = 0;
        $vendorName = 'Aguardando vendedor';
        $hasUnassigned = false;

        foreach ($distribution as $row) {
            $rowVendorId = (int) ($row['vendorId'] ?? 0);
            $total = (int) ($row['total'] ?? 0);

            if ($rowVendorId > 0) {
                $assignedCount += $total;
            } else {
                $unassignedCount += $total;
                $hasUnassigned = true;
            }

            if ($rowVendorId === ($vendorId ?? 0)) {
                $vendorName = (string) ($row['vendorName'] ?? $vendorName);
            }
        }

        if (!$hasUnassigned) {
            $distribution[] = [
                'vendorId' => 0,
                'vendorName' => 'Aguardando vendedor',
                'total' => 0
            ];
        }

        echo json_encode([
            'success' => true,
            'vendorId' => $vendorId,
            'vendorName' => $vendorName,
            'assignedLeadCount' => $assignedCount,
            'unassignedLeadCount' => $unassignedCount,
            'distribution' => $distribution
        ]);
    }

    public function createLead(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        $companyName = trim((string)($payload['companyName'] ?? ''));
        $contactName = trim((string)($payload['contactName'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $channel = trim((string)($payload['channel'] ?? 'Outro'));
        $status = trim((string)($payload['status'] ?? ''));
        $vendorId = isset($payload['vendorId']) && $payload['vendorId'] !== ''
            ? (int)$payload['vendorId']
            : null;

        $kanbanStatuses = $this->kanbanConfigService->getColumns();

        if ($companyName === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Informe o nome do lead.']);
            return;
        }

        if ($status === '' || !in_array($status, $kanbanStatuses, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Selecione um status válido.']);
            return;
        }

        $allowedChannels = [
            'Call',
            'LinkedIn',
            'Instagram',
            'Whatsapp',
            'Indicação Cliente',
            'Indicação Cartório',
            'Website',
            'Bitrix',
            'Evento',
            'Outro'
        ];

        if (!in_array($channel, $allowedChannels, true)) {
            $channel = 'Outro';
        }

        if ($vendorId !== null) {
            if ($vendorId <= 0) {
                $vendorId = null;
            } else {
                $vendor = $this->userModel->findActiveVendorById($vendorId);
                if ($vendor === null) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Vendedor não localizado.']);
                    return;
                }
            }
        }

        try {
            $creationResult = $this->prospectionModel->createLeadFromKanban([
                'companyName' => $companyName,
                'contactName' => $contactName,
                'email' => $email,
                'phone' => $phone,
                'channel' => $channel,
                'status' => $status,
                'vendorId' => $vendorId,
                'sdrId' => $userId,
            ]);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Não foi possível criar o lead.']);
            return;
        } catch (Throwable $exception) {
            error_log('Erro ao criar lead no Kanban: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno ao criar o lead.']);
            return;
        }

        $lead = $this->prospectionModel->getById((int)$creationResult['prospectionId']);

        if (!$lead) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lead criado, mas não foi possível carregar os dados.']);
            return;
        }

        $vendorName = 'Aguardando vendedor';
        $vendorLabelId = (int)($lead['responsavel_id'] ?? 0);
        if ($vendorLabelId > 0) {
            $vendorData = $this->userModel->findActiveVendorById($vendorLabelId);
            if ($vendorData !== null) {
                $vendorName = (string)($vendorData['nome_completo'] ?? $vendorName);
            }
        }

        $distribution = $this->prospectionModel->getLeadDistributionForSdr($userId);

        $assignedCount = 0;
        $unassignedCount = 0;
        $hasUnassigned = false;

        foreach ($distribution as $row) {
            $rowVendorId = (int)($row['vendorId'] ?? 0);
            $total = (int)($row['total'] ?? 0);

            if ($rowVendorId > 0) {
                $assignedCount += $total;
            } else {
                $unassignedCount += $total;
                $hasUnassigned = true;
            }
        }

        if (!$hasUnassigned) {
            $distribution[] = [
                'vendorId' => 0,
                'vendorName' => 'Aguardando vendedor',
                'total' => 0,
            ];
        }

        echo json_encode([
            'success' => true,
            'lead' => [
                'id' => (int)$lead['id'],
                'prospectName' => (string)($lead['nome_prospecto'] ?? $companyName),
                'clientName' => (string)($lead['cliente_nome'] ?? $companyName),
                'status' => $status,
                'vendorId' => $vendorLabelId,
                'vendorName' => $vendorName,
                'updatedAt' => $lead['data_ultima_atualizacao'] ?? null,
            ],
            'assignedLeadCount' => $assignedCount,
            'unassignedLeadCount' => $unassignedCount,
            'distribution' => $distribution,
        ]);
    }

    public function updateLeadStatus(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $leadId = (int) ($payload['leadId'] ?? 0);
        $newStatus = isset($payload['newStatus']) ? trim($payload['newStatus']) : '';

        $allowedStatuses = $this->kanbanConfigService->getColumns();
        $allowedStatuses = array_unique(array_merge($allowedStatuses, ['Descartado', 'Convertido']));

        if ($leadId <= 0 || $newStatus === '' || !in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            return;
        }

        $lead = $this->prospectionModel->getById($leadId);
        if (!$lead || (int) ($lead['sdrId'] ?? 0) !== $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lead não encontrado.']);
            return;
        }

        if (!$this->prospectionModel->updateLeadStatus($leadId, $newStatus)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o status.']);
            return;
        }

        echo json_encode(['success' => true]);
    }

    public function listarLeads(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userProfile = $_SESSION['user_perfil'] ?? '';

        if ($userId <= 0 || $userProfile !== 'sdr') {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit();
        }

        $leads = $this->prospectionModel->getSdrLeads($userId);
        $pageTitle = 'Meus Leads';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/sdr_dashboard/lista_leads.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function updateKanbanColumns(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Apenas SDRs podem atualizar o Kanban.']);
            return;
        }

        $columns = $_POST['columns'] ?? [];
        if (!is_array($columns)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Formato inválido.']);
            return;
        }

        try {
            $this->kanbanConfigService->saveColumns($columns);
            echo json_encode([
                'success' => true,
                'columns' => $this->kanbanConfigService->getColumns()
            ]);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
        }
    }

    private function buildDashboardStats(int $sdrId): array
    {
        $distributed = $this->prospectionModel->getLeadsDistributedCount($sdrId);
        $appointments = $this->prospectionModel->getAppointmentsCount($sdrId);
        $rate = $distributed > 0 ? ($appointments / $distributed) * 100 : 0.0;

        return [
            'leadsDistribuidos' => $distributed,
            'agendamentosRealizados' => $appointments,
            'taxaAgendamento' => $rate
        ];
    }

    private function normalizeFeedbackFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'sim', 'yes', 'on'], true);
        }

        return false;
    }
}
