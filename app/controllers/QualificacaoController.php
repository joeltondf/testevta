<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../services/LeadDistributor.php';

class QualificacaoController
{
    private $pdo;
    private $prospectionModel;
    private $userModel;
    private Cliente $clienteModel;
    private Processo $processoModel;
    private LeadDistributor $leadDistributor;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->processoModel = new Processo($pdo);
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

    public function getQualificationMetrics(?int $sdrId = null, ?int $vendorId = null): array
    {
        $conditions = [];
        $params = [];

        if ($sdrId !== null) {
            $conditions[] = 'pq.sdrId = :sdrId';
            $params[':sdrId'] = $sdrId;
        }

        if ($vendorId !== null) {
            $conditions[] = 'p.responsavel_id = :vendorId';
            $params[':vendorId'] = $vendorId;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    SUM(CASE WHEN pq.decision = 'qualificado' THEN 1 ELSE 0 END) AS qualified,
                    SUM(CASE WHEN pq.decision = 'descartado' THEN 1 ELSE 0 END) AS discarded,
                    SUM(CASE WHEN p.status = 'Convertido' THEN 1 ELSE 0 END) AS converted
                FROM prospeccao_qualificacoes pq
                INNER JOIN prospeccoes p ON p.id = pq.prospeccaoId
                $whereSql";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'qualified' => (int)($row['qualified'] ?? 0),
            'discarded' => (int)($row['discarded'] ?? 0),
            'converted' => (int)($row['converted'] ?? 0)
        ];
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

                $conversionSummary = $this->convertQualifiedLead($lead, $vendorId, (int) ($qualificationPayload['sdrId'] ?? 0));
                $convertedLead = $lead;
                $convertedLead['cliente_id'] = $conversionSummary['clienteId'];

                if ($meetingDateTime instanceof \DateTimeImmutable) {
                    $this->createMeeting($convertedLead, $vendorId, $meetingTitle, $meetingDateTime, $meetingLink, $meetingNotes);
                }

                $this->prospectionModel->updateLeadStatus($prospeccaoId, 'Convertido');
                $this->prospectionModel->logInteraction(
                    $prospeccaoId,
                    (int) ($_SESSION['user_id'] ?? 0),
                    sprintf(
                        'Lead convertido automaticamente em cliente #%d com processo #%d.',
                        $conversionSummary['clienteId'] ?? 0,
                        $conversionSummary['processoId'] ?? 0
                    ),
                    'log_sistema'
                );
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

    private function convertQualifiedLead(array $lead, int $vendorId, int $sdrId): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            throw new \RuntimeException('Lead inválido para conversão automática.');
        }

        $clientId = (int) ($lead['cliente_id'] ?? 0);
        $clientCreated = false;

        if ($clientId <= 0) {
            $clientPayload = $this->buildClientPayloadFromLead($lead);
            $newClientId = $this->clienteModel->create($clientPayload);

            if (!is_numeric($newClientId) || (int) $newClientId <= 0) {
                throw new \RuntimeException('Não foi possível criar o cliente a partir da prospecção qualificada.');
            }

            $clientId = (int) $newClientId;
            $clientCreated = true;

            $updateStmt = $this->pdo->prepare('UPDATE prospeccoes SET cliente_id = :clientId WHERE id = :leadId');
            $updateStmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);
            $updateStmt->bindValue(':leadId', $leadId, PDO::PARAM_INT);
            $updateStmt->execute();
        } else {
            $this->clienteModel->promoteProspectToClient($clientId);
        }

        $processTitle = $this->buildProcessTitle($lead, $clientId);
        $processData = [
            'cliente_id' => $clientId,
            'colaborador_id' => $sdrId > 0 ? $sdrId : $vendorId,
            'vendedor_id' => $vendorId,
            'titulo' => $processTitle,
            'valor_proposto' => $this->extractLeadValue($lead),
            'status_processo' => 'Orçamento Pendente',
        ];

        $processId = $this->processoModel->createFromProspeccao($processData);
        if (!$processId) {
            throw new \RuntimeException('Não foi possível gerar o processo para o cliente qualificado.');
        }

        return [
            'clienteId' => $clientId,
            'processoId' => $processId,
            'clientCreated' => $clientCreated,
        ];
    }

    private function buildClientPayloadFromLead(array $lead): array
    {
        $leadName = trim((string) ($lead['nome_prospecto'] ?? ''));
        $clientName = trim((string) ($lead['cliente_nome'] ?? ''));
        $finalName = $clientName !== '' ? $clientName : ($leadName !== '' ? $leadName : 'Cliente sem nome');

        return [
            'nome_cliente' => $finalName,
            'nome_responsavel' => $leadName !== '' ? $leadName : null,
            'email' => $lead['email'] ?? null,
            'telefone' => $lead['telefone'] ?? null,
            'tipo_pessoa' => 'Jurídica',
            'tipo_assessoria' => $lead['leadCategory'] ?? null,
            'prazo_legalizacao_dias' => null,
        ];
    }

    private function buildProcessTitle(array $lead, int $clientId): string
    {
        $title = trim((string) ($lead['nome_prospecto'] ?? ''));
        if ($title !== '') {
            return 'Orçamento - ' . $title;
        }

        $clientName = trim((string) ($lead['cliente_nome'] ?? ''));
        if ($clientName !== '') {
            return 'Orçamento - ' . $clientName;
        }

        return 'Orçamento para cliente #' . $clientId;
    }

    private function extractLeadValue(array $lead): float
    {
        $rawValue = $lead['valor_proposto'] ?? null;

        if ($rawValue === null || $rawValue === '') {
            return 0.0;
        }

        return is_numeric($rawValue) ? (float) $rawValue : 0.0;
    }
}
