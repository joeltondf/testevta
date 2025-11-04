<?php
// app/services/AutomacaoKanbanService.php

require_once __DIR__ . '/../models/AutomacaoModel.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/EmailService.php';

class AutomacaoKanbanService
{
    private PDO $pdo;
    private AutomacaoModel $automacaoModel;
    private Prospeccao $prospeccaoModel;
    private Cliente $clienteModel;
    private ?EmailService $emailService = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->automacaoModel = new AutomacaoModel($pdo);
        $this->prospeccaoModel = new Prospeccao($pdo);
        $this->clienteModel = new Cliente($pdo);
    }

    public function handleStatusChange(int $leadId, string $newStatus, ?int $userId = null): void
    {
        $campanhas = $this->automacaoModel->getCampanhasAtivasPorStatus($newStatus);

        if (empty($campanhas)) {
            return;
        }

        $lead = $this->prospeccaoModel->getById($leadId);

        if (!$lead || empty($lead['cliente_id'])) {
            return;
        }

        $cliente = $this->clienteModel->getById((int)$lead['cliente_id']);

        if (!$cliente) {
            return;
        }

        foreach ($campanhas as $campanha) {
            $digisacScheduled = $this->scheduleDigisacMessage($campanha, $cliente);
            $emailSent = $this->sendEmailIfAvailable($campanha, $cliente, $lead, $newStatus);

            if ($digisacScheduled || $emailSent) {
                $this->registerInteraction($leadId, $campanha, $newStatus, $userId, $digisacScheduled, $emailSent);
            }
        }
    }

    private function scheduleDigisacMessage(array $campaign, array $client): bool
    {
        if (empty($campaign['digisac_template_id']) || empty($campaign['digisac_conexao_id'])) {
            return false;
        }

        if (empty($client['telefone'])) {
            return false;
        }

        $campaignId = (int)$campaign['id'];
        $clientId = (int)$client['id'];
        $interval = (int)($campaign['intervalo_reenvio_dias'] ?? 0);

        if ($this->automacaoModel->verificarEnvioRecente($campaignId, $clientId, $interval)) {
            return false;
        }

        if ($this->automacaoModel->verificarSeEstaNaFila($campaignId, $clientId)) {
            return false;
        }

        return $this->automacaoModel->adicionarNaFila($campaignId, $clientId, $client['telefone']);
    }

    private function sendEmailIfAvailable(array $campaign, array $client, array $lead, string $stage): bool
    {
        if (empty($client['email'])) {
            return false;
        }

        $subjectTemplate = $campaign['email_assunto'] ?? '';
        $headerTemplate = $campaign['email_cabecalho'] ?? '';
        $bodyTemplate = $campaign['email_corpo'] ?? '';

        if (trim($subjectTemplate) === '' || trim($bodyTemplate) === '') {
            return false;
        }

        try {
            $emailService = $this->getEmailService();
        } catch (Exception $exception) {
            error_log('Erro ao carregar serviço de e-mail: ' . $exception->getMessage());
            return false;
        }

        if (!$emailService) {
            return false;
        }

        $placeholders = $this->buildPlaceholders($campaign, $client, $lead, $stage);
        $subject = $this->replacePlaceholders($subjectTemplate, $placeholders);
        $header = $this->replacePlaceholders($headerTemplate, $placeholders);
        $body = $this->replacePlaceholders($bodyTemplate, $placeholders);

        $finalBody = trim($header) !== '' ? $header . $body : $body;

        try {
            return $emailService->sendEmail($client['email'], $subject, $finalBody);
        } catch (Exception $exception) {
            error_log('Erro ao enviar e-mail de automação: ' . $exception->getMessage());
            return false;
        }
    }

    private function getEmailService(): ?EmailService
    {
        if ($this->emailService instanceof EmailService) {
            return $this->emailService;
        }

        try {
            $this->emailService = new EmailService($this->pdo);
        } catch (Exception $exception) {
            error_log('Erro ao instanciar EmailService: ' . $exception->getMessage());
            $this->emailService = null;
        }

        return $this->emailService;
    }

    private function buildPlaceholders(array $campaign, array $client, array $lead, string $stage): array
    {
        $leadValue = isset($lead['valor_proposto']) ? number_format((float)$lead['valor_proposto'], 2, ',', '.') : '0,00';

        return [
            '{{clientName}}' => $client['nome_cliente'] ?? '',
            '{{clientContact}}' => $client['nome_responsavel'] ?? '',
            '{{clientPhone}}' => $client['telefone'] ?? '',
            '{{clientEmail}}' => $client['email'] ?? '',
            '{{leadName}}' => $lead['nome_prospecto'] ?? '',
            '{{leadValue}}' => $leadValue,
            '{{stageName}}' => $stage,
            '{{campaignName}}' => $campaign['nome_campanha'] ?? '',
        ];
    }

    private function replacePlaceholders(string $template, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    private function registerInteraction(
        int $leadId,
        array $campaign,
        string $stage,
        ?int $userId,
        bool $digisacScheduled,
        bool $emailSent
    ): void {
        $actions = [];

        if ($digisacScheduled) {
            $actions[] = 'mensagem Digisac agendada';
        }

        if ($emailSent) {
            $actions[] = 'e-mail enviado';
        }

        if (empty($actions)) {
            $actions[] = 'nenhuma ação executada';
        }

        $description = sprintf(
            "Automação '%s' para o estágio '%s' executada (%s).",
            $campaign['nome_campanha'] ?? 'Campanha sem nome',
            $stage,
            implode(' e ', $actions)
        );

        $stmt = $this->pdo->prepare(
            "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, 'log_sistema')"
        );

        $stmt->execute([$leadId, $userId, $description]);
    }
}
