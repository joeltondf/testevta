<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/LeadHandoff.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
if (file_exists(__DIR__ . '/../services/NotificationService.php')) {
    require_once __DIR__ . '/../services/NotificationService.php';
}

class LeadHandoffController
{
    private PDO $pdo;
    private LeadHandoff $leadHandoffModel;
    private Prospeccao $prospeccaoModel;
    private User $userModel;

    /**
     * @var NotificationService|null
     */
    private $notificationService = null;

    /**
     * LeadHandoffController constructor.
     *
     * @param PDO $pdo Active PDO connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->leadHandoffModel = new LeadHandoff($pdo);
        $this->prospeccaoModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        if (class_exists('NotificationService')) {
            $this->notificationService = new NotificationService($pdo);
        }
    }

    /**
     * Transfers a lead from an SDR to a vendor.
     *
     * @return void
     */
    public function transferLead(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $payload = $this->decodeJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $errors = [];
            $prospeccaoId = $this->filterInt($payload['prospeccao_id'] ?? null);
            $vendorId = $this->filterInt($payload['vendor_id'] ?? null);
            $handoffNotes = $this->sanitizeString($payload['handoff_notes'] ?? '');
            $urgency = $this->sanitizeString($payload['urgency'] ?? '');
            $urgency = strtolower($urgency);

            if ($prospeccaoId <= 0) {
                $errors['prospeccao_id'] = 'ID de prospecção inválido.';
            }

            if ($vendorId <= 0) {
                $errors['vendor_id'] = 'ID de vendedor inválido.';
            }

            if (mb_strlen($handoffNotes) < 50) {
                $errors['handoff_notes'] = 'As notas devem ter ao menos 50 caracteres.';
            }

            $allowedUrgencies = ['alta', 'media', 'baixa'];
            if ($urgency === '' || !in_array($urgency, $allowedUrgencies, true)) {
                $errors['urgency'] = 'Urgência inválida.';
            }

            if (!empty($errors)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $errors,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $lead = $this->prospeccaoModel->getById($prospeccaoId);
            if (!$lead) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Lead não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($lead['sdrId'] ?? 0) !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $vendor = $this->userModel->findActiveVendorById($vendorId);
            if ($vendor === null) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => ['vendor_id' => 'Vendedor não encontrado ou inativo.'],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = $this->leadHandoffModel->create([
                'prospeccao_id' => $prospeccaoId,
                'sdr_id' => $currentUserId,
                'vendor_id' => $vendorId,
                'handoff_notes' => $handoffNotes,
                'urgency' => $urgency,
            ]);

            if ($handoffId === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Não foi possível transferir o lead.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$this->prospeccaoModel->updateResponsavelAndStatus($prospeccaoId, $vendorId, 'Transferido')) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Falha ao atualizar a prospecção.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->notify('notifyLeadTransferred', [$handoffId, $prospeccaoId, $currentUserId, $vendorId]);
            $this->addTimelineEntry($prospeccaoId, $currentUserId, 'transferred', [
                'vendor_id' => $vendorId,
                'urgency' => $urgency,
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'handoff_id' => $handoffId,
                'message' => 'Lead transferido com sucesso.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::transferLead error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Accepts a transferred lead.
     *
     * @return void
     */
    public function acceptLead(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'vendedor') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $payload = $this->decodeJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = $this->filterInt($payload['handoff_id'] ?? null);
            if ($handoffId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoff = $this->leadHandoffModel->getHandoffDetails($handoffId);
            if ($handoff === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Handoff não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($handoff['vendor_id'] ?? 0) !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (($handoff['status'] ?? '') !== 'pending') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Handoff não está pendente.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$this->leadHandoffModel->accept($handoffId, $currentUserId)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Não foi possível aceitar o lead.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->notify('notifyLeadAccepted', [$handoffId, (int) ($handoff['sdr_id'] ?? 0), $currentUserId]);
            $this->addTimelineEntry((int) $handoff['prospeccao_id'], $currentUserId, 'accepted');

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Lead aceito com sucesso.'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::acceptLead error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Rejects a transferred lead with a reason.
     *
     * @return void
     */
    public function rejectLead(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'vendedor') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $payload = $this->decodeJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = $this->filterInt($payload['handoff_id'] ?? null);
            $reason = $this->sanitizeString($payload['rejection_reason'] ?? '');

            if ($handoffId <= 0 || $reason === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoff = $this->leadHandoffModel->getHandoffDetails($handoffId);
            if ($handoff === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Handoff não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($handoff['vendor_id'] ?? 0) !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (($handoff['status'] ?? '') !== 'pending') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Handoff não está pendente.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$this->leadHandoffModel->reject($handoffId, $currentUserId, $reason)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Não foi possível rejeitar o lead.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $prospeccaoId = (int) ($handoff['prospeccao_id'] ?? 0);
            if ($prospeccaoId > 0) {
                $this->prospeccaoModel->updateResponsavelAndStatus($prospeccaoId, null, 'Ativo');
            }

            $this->notify('notifyLeadRejected', [$handoffId, (int) ($handoff['sdr_id'] ?? 0), $currentUserId, $reason]);
            $this->addTimelineEntry($prospeccaoId, $currentUserId, 'rejected', ['reason' => $reason]);

            http_response_code(200);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::rejectLead error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Marks the first contact for a handoff.
     *
     * @return void
     */
    public function markFirstContact(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'vendedor') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $payload = $this->decodeJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = $this->filterInt($payload['handoff_id'] ?? null);
            if ($handoffId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoff = $this->leadHandoffModel->getHandoffDetails($handoffId);
            if ($handoff === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Handoff não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($handoff['vendor_id'] ?? 0) !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$this->leadHandoffModel->markFirstContact($handoffId, $currentUserId)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Não foi possível marcar o primeiro contato.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->notify('notifyFirstContact', [$handoffId, (int) ($handoff['sdr_id'] ?? 0), $currentUserId]);
            $this->addTimelineEntry((int) $handoff['prospeccao_id'], $currentUserId, 'first_contact');

            http_response_code(200);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::markFirstContact error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Submits feedback for a handoff.
     *
     * @return void
     */
    public function submitFeedback(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'vendedor') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $payload = $this->decodeJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = $this->filterInt($payload['handoff_id'] ?? null);
            $qualityScore = $this->filterInt($payload['quality_score'] ?? null);
            $comments = $this->sanitizeString($payload['comments'] ?? ($payload['feedback_text'] ?? ''));

            if ($handoffId <= 0 || $qualityScore < 1 || $qualityScore > 5) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoff = $this->leadHandoffModel->getHandoffDetails($handoffId);
            if ($handoff === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Handoff não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($handoff['vendor_id'] ?? 0) !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $checkboxKeys = ['info_correct', 'icp_match', 'engaged', 'expectations'];
            $feedbackPayload = [
                'version' => 1,
                'comments' => $comments,
            ];

            foreach ($checkboxKeys as $key) {
                $feedbackPayload[$key] = $this->normalizeFeedbackBoolean($payload[$key] ?? false);
            }

            $feedbackJson = json_encode($feedbackPayload, JSON_UNESCAPED_UNICODE);
            if ($feedbackJson === false) {
                $feedbackJson = $comments;
            }

            if (!$this->leadHandoffModel->addFeedback($handoffId, $currentUserId, $qualityScore, $feedbackJson)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Não foi possível registrar o feedback.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            http_response_code(200);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::submitFeedback error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Retrieves pending handoffs for the logged vendor.
     *
     * @return void
     */
    public function getPendingHandoffs(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'vendedor') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            $handoffs = $this->leadHandoffModel->getPendingForVendor($currentUserId);

            http_response_code(200);
            echo json_encode(['success' => true, 'handoffs' => $handoffs], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::getPendingHandoffs error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Retrieves handoffs created by the logged SDR.
     *
     * @return void
     */
    public function getMyHandoffs(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'sdr') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $status = null;
            if (isset($_GET['status'])) {
                $statusValue = $this->sanitizeString($_GET['status']);
                $status = $statusValue !== '' ? $statusValue : null;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            $handoffs = $this->leadHandoffModel->getHandoffsBySDR($currentUserId, $status);

            http_response_code(200);
            echo json_encode(['success' => true, 'handoffs' => $handoffs], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::getMyHandoffs error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Retrieves detailed handoff information ensuring the user has access.
     *
     * @return void
     */
    public function getHandoffDetails(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            $userProfile = $_SESSION['user_perfil'] ?? '';
            if (!in_array($userProfile, ['sdr', 'vendedor'], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoffId = isset($_GET['handoff_id']) ? $this->filterInt($_GET['handoff_id']) : 0;
            if ($handoffId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $handoff = $this->leadHandoffModel->getHandoffDetails($handoffId);
            if ($handoff === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Handoff não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            $isOwner = ($userProfile === 'sdr' && (int) ($handoff['sdr_id'] ?? 0) === $currentUserId)
                || ($userProfile === 'vendedor' && (int) ($handoff['vendor_id'] ?? 0) === $currentUserId);

            if (!$isOwner) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'handoff' => $handoff], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::getHandoffDetails error: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Decodes the JSON payload from the request body.
     *
     * @return array|null
     */
    private function decodeJsonPayload(): ?array
    {
        $rawInput = file_get_contents('php://input');
        if ($rawInput === false || $rawInput === '') {
            return [];
        }

        $decoded = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Sanitizes string input values.
     *
     * @param mixed $value Raw value provided by the request.
     *
     * @return string
     */
    private function sanitizeString($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $sanitized = trim(strip_tags($value));

        return filter_var($sanitized, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES) ?? '';
    }

    /**
     * Filters integer values ensuring a numeric result.
     *
     * @param mixed $value Raw value provided by the request.
     *
     * @return int
     */
    private function filterInt($value): int
    {
        if ($value === null) {
            return 0;
        }

        return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    }

    private function normalizeFeedbackBoolean($value): bool
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

    /**
     * Dispatches notification calls if the service is available.
     *
     * @param string $method    Notification method to call.
     * @param array  $arguments Arguments forwarded to the notification method.
     *
     * @return void
     */
    private function notify(string $method, array $arguments = []): void
    {
        if ($this->notificationService === null || !method_exists($this->notificationService, $method)) {
            return;
        }

        try {
            call_user_func_array([$this->notificationService, $method], $arguments);
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::notify error: ' . $exception->getMessage());
        }
    }

    /**
     * Adds a record into the prospection timeline.
     *
     * @param int   $prospeccaoId Lead identifier.
     * @param int   $userId       User responsible for the action.
     * @param string $action      Timeline action code.
     * @param array $metadata     Optional contextual information.
     *
     * @return void
     */
    private function addTimelineEntry(int $prospeccaoId, int $userId, string $action, array $metadata = []): void
    {
        if ($prospeccaoId <= 0 || $userId <= 0) {
            return;
        }

        try {
            $sql = 'INSERT INTO prospeccao_timeline (prospeccao_id, user_id, action, metadata, created_at)
                    VALUES (:prospeccao_id, :user_id, :action, :metadata, NOW())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':metadata', json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: '{}', PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('LeadHandoffController::addTimelineEntry error: ' . $exception->getMessage());
        }
    }
}
