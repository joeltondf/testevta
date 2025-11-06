<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/LeadDistributor.php';

class QualificacaoController
{
    private $pdo;
    private $prospectionModel;
    private $userModel;
    private LeadDistributor $leadDistributor;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->leadDistributor = new LeadDistributor($pdo);
    }

    public function create(int $prospeccaoId): void
    {
        $this->ensureSdrAccess();

        $lead = $this->prospectionModel->getById($prospeccaoId);
        if (!$this->canAccessLead($lead)) {
            $_SESSION['error_message'] = 'Lead não encontrado ou inacessível.';
            header('Location: ' . APP_URL . '/sdr_dashboard.php');
            exit();
        }

        $nextVendor = null;

        try {
            $nextVendor = $this->leadDistributor->previewNextSalesperson();
        } catch (Throwable $exception) {
            error_log('Erro ao pré-visualizar vendedor: ' . $exception->getMessage());
        }
        $pageTitle = 'Qualificação de Lead';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/qualificacao/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function store(int $prospeccaoId): void
    {
        $this->ensureSdrAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/qualificacao.php?action=create&id=' . $prospeccaoId);
            exit();
        }

        $lead = $this->prospectionModel->getById($prospeccaoId);
        if (!$this->canAccessLead($lead)) {
            $_SESSION['error_message'] = 'Lead não encontrado ou inacessível.';
            header('Location: ' . APP_URL . '/sdr_dashboard.php');
            exit();
        }

        $fitIcp = trim($_POST['fit_icp'] ?? '');
        $budget = trim($_POST['budget'] ?? '');
        $authority = trim($_POST['authority'] ?? '');
        $timing = trim($_POST['timing'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $decision = $_POST['decision'] ?? '';

        if (!in_array($decision, ['qualificado', 'descartado'], true)) {
            $_SESSION['error_message'] = 'Selecione o resultado da qualificação.';
            header('Location: ' . APP_URL . '/qualificacao.php?action=create&id=' . $prospeccaoId);
            exit();
        }

        $qualificationPayload = [
            'sdrId' => (int) ($_SESSION['user_id'] ?? 0),
            'fitIcp' => $fitIcp,
            'budget' => $budget,
            'authority' => $authority,
            'timing' => $timing,
            'decision' => $decision,
            'notes' => $notes
        ];

        $this->pdo->beginTransaction();

        try {
            $this->prospectionModel->saveQualification($prospeccaoId, $qualificationPayload);

            if ($decision === 'qualificado') {
                $distribution = $this->leadDistributor->distributeToNextSalesperson(
                    $prospeccaoId,
                    (int) ($_SESSION['user_id'] ?? 0)
                );

                $vendorId = (int) ($distribution['vendorId'] ?? 0);
                if ($vendorId <= 0) {
                    throw new \RuntimeException('Não foi possível identificar o próximo vendedor na fila.');
                }

                $meetingTitle = trim($_POST['meeting_title'] ?? 'Reunião de Qualificação');
                $meetingDate = trim($_POST['meeting_date'] ?? '');
                $meetingTime = trim($_POST['meeting_time'] ?? '');
                $meetingLink = trim($_POST['meeting_link'] ?? '');
                $meetingNotes = trim($_POST['meeting_notes'] ?? '');
                $meetingDateTime = $this->buildDateTime($meetingDate, $meetingTime);

                $this->prospectionModel->updateLeadStatus($prospeccaoId, 'Qualificado');
                if ($meetingDateTime instanceof \DateTimeImmutable) {
                    $this->createMeeting($lead, $vendorId, $meetingTitle, $meetingDateTime, $meetingLink, $meetingNotes);
                }
            } else {
                $this->prospectionModel->updateResponsavelAndStatus($prospeccaoId, null, 'Descartado');
            }

            $this->pdo->commit();
            $_SESSION['success_message'] = 'Qualificação registrada com sucesso.';
            header('Location: ' . APP_URL . '/sdr_dashboard.php');
            exit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            error_log('Erro ao salvar qualificação: ' . $exception->getMessage());
            $_SESSION['error_message'] = $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Não foi possível concluir a qualificação.';
            header('Location: ' . APP_URL . '/qualificacao.php?action=create&id=' . $prospeccaoId);
            exit();
        }
    }

    private function ensureSdrAccess(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SESSION['user_perfil'] ?? '') !== 'sdr') {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit();
        }
    }

    private function canAccessLead(?array $lead): bool
    {
        if (!$lead) {
            return false;
        }

        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        return $currentUserId > 0 && (int) ($lead['sdrId'] ?? 0) === $currentUserId;
    }

    private function buildDateTime(string $date, string $time): ?\DateTimeImmutable
    {
        if ($date === '' || $time === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($date . ' ' . $time);
        } catch (\Exception $exception) {
            error_log('Data inválida na qualificação: ' . $exception->getMessage());
            return null;
        }
    }

    private function createMeeting(array $lead, int $vendorId, string $title, \DateTimeImmutable $startDate, string $link, string $notes): void
    {
        $clienteId = isset($lead['cliente_id']) ? (int) $lead['cliente_id'] : null;
        $prospeccaoId = (int) ($lead['id'] ?? 0);

        $sql = "INSERT INTO agendamentos (
                    titulo,
                    cliente_id,
                    prospeccao_id,
                    usuario_id,
                    data_inicio,
                    data_fim,
                    local_link,
                    observacoes,
                    status
                ) VALUES (
                    :titulo,
                    :cliente_id,
                    :prospeccao_id,
                    :usuario_id,
                    :data_inicio,
                    :data_fim,
                    :local_link,
                    :observacoes,
                    'Confirmado'
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':titulo', $title !== '' ? $title : 'Reunião de Qualificação', PDO::PARAM_STR);

        if ($clienteId > 0) {
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':cliente_id', null, PDO::PARAM_NULL);
        }

        if ($prospeccaoId > 0) {
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':prospeccao_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':usuario_id', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':data_inicio', $startDate->format('Y-m-d H:i:s'));
        $stmt->bindValue(':data_fim', $startDate->modify('+1 hour')->format('Y-m-d H:i:s'));

        if ($link !== '') {
            $stmt->bindValue(':local_link', $link, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':local_link', null, PDO::PARAM_NULL);
        }

        if ($notes !== '') {
            $stmt->bindValue(':observacoes', $notes, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':observacoes', null, PDO::PARAM_NULL);
        }

        $stmt->execute();
    }
}
