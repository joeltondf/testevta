<?php

declare(strict_types=1);

/**
 * DigisacService integrates the CRM with the Digisac WhatsApp API.
 */
class DigisacService
{
    private const DEFAULT_API_URL = 'https://api.digisac.app';
    private const MESSAGE_ENDPOINT = '/v1/messages';
    private const RETRY_ATTEMPTS = 2;
    private const CURL_TIMEOUT = 10;

    /** @var PDO */
    private PDO $pdo;

    private string $apiUrl = self::DEFAULT_API_URL;

    private ?string $apiToken = null;

    private bool $enabled = false;

    private ?string $departmentId = null;

    /** @var array<string, bool> */
    private array $sendConfig = [
        'urgent_lead' => false,
        'sla_warning' => false,
        'sla_overdue' => false,
        'lead_converted' => false,
        'feedback_request' => false,
    ];

    /**
     * Constructor.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    /**
     * Reloads integration configuration from the database.
     */
    private function loadConfig(): void
    {
        $configKeys = [
            'digisac_api_url',
            'digisac_api_token',
            'digisac_enabled',
            'digisac_default_department_id',
            'digisac_send_on_urgent_lead',
            'digisac_send_on_sla_warning',
            'digisac_send_on_sla_overdue',
            'digisac_send_on_converted',
            'digisac_send_on_feedback_request',
        ];

        $placeholders = implode(',', array_fill(0, count($configKeys), '?'));
        $stmt = $this->pdo->prepare(sprintf('SELECT chave, valor FROM configuracoes WHERE chave IN (%s)', $placeholders));
        $stmt->execute($configKeys);

        $config = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['chave'])) {
                continue;
            }
            $config[$row['chave']] = $row['valor'] ?? null;
        }

        $apiUrl = trim((string)($config['digisac_api_url'] ?? ''));
        if ($apiUrl !== '') {
            $this->apiUrl = rtrim($apiUrl, '/');
        }

        $token = trim((string)($config['digisac_api_token'] ?? ''));
        $this->apiToken = $token !== '' ? $token : null;

        $this->enabled = (int)($config['digisac_enabled'] ?? 0) === 1;
        $department = trim((string)($config['digisac_default_department_id'] ?? ''));
        $this->departmentId = $department !== '' ? $department : null;

        $this->sendConfig['urgent_lead'] = array_key_exists('digisac_send_on_urgent_lead', $config)
            ? (int)$config['digisac_send_on_urgent_lead'] === 1
            : true;
        $this->sendConfig['sla_warning'] = array_key_exists('digisac_send_on_sla_warning', $config)
            ? (int)$config['digisac_send_on_sla_warning'] === 1
            : true;
        $this->sendConfig['sla_overdue'] = (int)($config['digisac_send_on_sla_overdue'] ?? 0) === 1;
        $this->sendConfig['lead_converted'] = (int)($config['digisac_send_on_converted'] ?? 0) === 1;
        $this->sendConfig['feedback_request'] = (int)($config['digisac_send_on_feedback_request'] ?? 0) === 1;
    }

    /**
     * Sends a WhatsApp text message through Digisac.
     *
     * @param string      $phoneNumber  Recipient phone number.
     * @param string      $messageText  Message body.
     * @param string|null $departmentId Optional Digisac department identifier.
     * @param array{user_id?:int|null,message_type?:string|null,prospeccao_id?:int|null} $context Additional logging context.
     *
     * @return array{success:bool,message_id?:string,error?:string,log_id?:int}
     */
    public function sendMessage(string $phoneNumber, string $messageText, ?string $departmentId = null, array $context = []): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Digisac integration is disabled.'];
        }

        if ($this->apiToken === null) {
            return ['success' => false, 'error' => 'Missing Digisac API token.'];
        }

        $formattedNumber = $this->formatPhoneNumber($phoneNumber);
        if ($formattedNumber === null) {
            return ['success' => false, 'error' => 'Invalid phone number provided.'];
        }

        $payload = [
            'number' => $formattedNumber,
            'body' => $messageText,
        ];

        $chosenDepartment = $departmentId ?? ($context['department_id'] ?? $this->departmentId);
        if ($chosenDepartment !== null && $chosenDepartment !== '') {
            $payload['department_id'] = $chosenDepartment;
        }

        $result = $this->performMessageRequest($payload);
        $status = $result['success'] ? 'sent' : 'failed';
        $messageId = $result['message_id'] ?? null;

        try {
            $logId = $this->logMessage(
                $context['user_id'] ?? null,
                $formattedNumber,
                $context['message_type'] ?? 'generic',
                $messageText,
                $context['prospeccao_id'] ?? null,
                $messageId,
                $status
            );
        } catch (Throwable $exception) {
            $logId = null;
            error_log('DigisacService::sendMessage log error: ' . $exception->getMessage());
        }

        $response = ['success' => $result['success']];
        if ($messageId !== null) {
            $response['message_id'] = $messageId;
        }
        if (!$result['success'] && isset($result['error'])) {
            $response['error'] = $result['error'];
        }
        if (isset($logId) && $logId !== null) {
            $response['log_id'] = $logId;
        }

        return $response;
    }

    /**
     * Sends an urgent lead WhatsApp notification to the vendor.
     *
     * @param int   $vendorId Vendor identifier.
     * @param array $leadData Lead payload (expects nome_prospecto, empresa, sdr_name, id).
     */
    public function sendUrgentLeadNotification(int $vendorId, array $leadData): array
    {
        if (!$this->sendConfig['urgent_lead']) {
            return ['success' => false, 'error' => 'Urgent lead WhatsApp notifications disabled.'];
        }

        $contact = $this->getUserContact($vendorId);
        if ($contact === null) {
            return ['success' => false, 'error' => 'Vendor not found or missing WhatsApp number.'];
        }

        if (!$contact['notifications_enabled']) {
            return ['success' => false, 'error' => 'Vendor opted out of WhatsApp notifications.'];
        }

        $leadName = $this->sanitizeLine($leadData['nome_prospecto'] ?? 'Lead');
        $company = $this->sanitizeLine($leadData['empresa'] ?? 'Empresa não informada');
        $sdrName = $this->sanitizeLine($leadData['sdr_name'] ?? 'SDR');
        $leadId = isset($leadData['id']) ? (int)$leadData['id'] : (int)($leadData['prospeccao_id'] ?? 0);
        $link = $this->buildProspectionLink($leadId);

        $message = "🚨 NOVO LEAD URGENTE\n\n";
        $message .= "Lead: {$leadName}\n";
        $message .= "Empresa: {$company}\n";
        $message .= "Qualificado por: {$sdrName}\n";
        $message .= "Score: Alta prioridade\n\n";
        $message .= "Prazo SLA: 24 horas\n\n";
        $message .= "Acesse: {$link}";

        return $this->sendMessage(
            $contact['number'],
            $message,
            null,
            [
                'user_id' => $vendorId,
                'message_type' => 'lead_urgent',
                'prospeccao_id' => $leadId > 0 ? $leadId : null,
            ]
        );
    }

    /**
     * Sends an SLA warning WhatsApp notification to the vendor.
     *
     * @param int      $vendorId
     * @param string   $leadName
     * @param int      $hoursRemaining
     * @param int|null $prospeccaoId
     */
    public function sendSLAWarning(int $vendorId, string $leadName, int $hoursRemaining, ?int $prospeccaoId = null): array
    {
        if (!$this->sendConfig['sla_warning']) {
            return ['success' => false, 'error' => 'SLA warning WhatsApp notifications disabled.'];
        }

        $contact = $this->getUserContact($vendorId);
        if ($contact === null || !$contact['notifications_enabled']) {
            return ['success' => false, 'error' => 'Vendor not eligible for SLA warning notifications.'];
        }

        $leadNameSafe = $this->sanitizeLine($leadName);
        $hoursLabel = max(0, $hoursRemaining) . 'h';
        $message = "⚠️ ALERTA DE SLA\n\n";
        $message .= "Lead: {$leadNameSafe}\n";
        $message .= "Tempo restante: {$hoursLabel}\n\n";
        $message .= "Ação necessária: Realizar primeiro contato urgente\n\n";
        $message .= 'Link: ' . $this->buildProspectionLink($prospeccaoId);

        return $this->sendMessage(
            $contact['number'],
            $message,
            null,
            [
                'user_id' => $vendorId,
                'message_type' => 'sla_warning',
                'prospeccao_id' => $prospeccaoId,
            ]
        );
    }

    /**
     * Sends an SLA overdue WhatsApp notification to the vendor.
     *
     * @param int      $vendorId
     * @param string   $leadName
     * @param int|null $prospeccaoId
     */
    public function sendSLAOverdue(int $vendorId, string $leadName, ?int $prospeccaoId = null): array
    {
        if (!$this->sendConfig['sla_overdue']) {
            return ['success' => false, 'error' => 'SLA overdue WhatsApp notifications disabled.'];
        }

        $contact = $this->getUserContact($vendorId);
        if ($contact === null || !$contact['notifications_enabled']) {
            return ['success' => false, 'error' => 'Vendor not eligible for SLA overdue notifications.'];
        }

        $leadNameSafe = $this->sanitizeLine($leadName);
        $message = "🚨 SLA VENCIDO\n\n";
        $message .= "Lead: {$leadNameSafe}\n";
        $message .= "Status: Primeiro contato ATRASADO\n\n";
        $message .= "Ação imediata necessária!\n\n";
        $message .= 'Link: ' . $this->buildProspectionLink($prospeccaoId);

        return $this->sendMessage(
            $contact['number'],
            $message,
            null,
            [
                'user_id' => $vendorId,
                'message_type' => 'sla_overdue',
                'prospeccao_id' => $prospeccaoId,
            ]
        );
    }

    /**
     * Sends a WhatsApp notification to the SDR when a lead is converted.
     */
    public function sendLeadConverted(int $sdrId, string $leadName, string $vendorName): array
    {
        if (!$this->sendConfig['lead_converted']) {
            return ['success' => false, 'error' => 'Lead conversion WhatsApp notifications disabled.'];
        }

        $contact = $this->getUserContact($sdrId);
        if ($contact === null || !$contact['notifications_enabled']) {
            return ['success' => false, 'error' => 'SDR not eligible for conversion notifications.'];
        }

        $leadSafe = $this->sanitizeLine($leadName);
        $vendorSafe = $this->sanitizeLine($vendorName);

        $message = "🎉 LEAD CONVERTIDO!\n\n";
        $message .= "Parabéns! O lead {$leadSafe} foi convertido.\n";
        $message .= "Vendedor: {$vendorSafe}\n\n";
        $message .= "Continue o ótimo trabalho!";

        return $this->sendMessage(
            $contact['number'],
            $message,
            null,
            [
                'user_id' => $sdrId,
                'message_type' => 'lead_converted',
            ]
        );
    }

    /**
     * Sends a WhatsApp feedback request to the vendor.
     */
    public function sendFeedbackRequest(int $vendorId, string $leadName): array
    {
        if (!$this->sendConfig['feedback_request']) {
            return ['success' => false, 'error' => 'Feedback WhatsApp notifications disabled.'];
        }

        $contact = $this->getUserContact($vendorId);
        if ($contact === null || !$contact['notifications_enabled']) {
            return ['success' => false, 'error' => 'Vendor not eligible for feedback notifications.'];
        }

        $leadSafe = $this->sanitizeLine($leadName);
        $message = "💬 SOLICITAÇÃO DE FEEDBACK\n\n";
        $message .= "Olá! Por favor, avalie a qualificação do lead:\n";
        $message .= "{$leadSafe}\n\n";
        $message .= "Seu feedback ajuda a melhorar nosso processo.\n\n";
        $message .= 'Avaliar: ' . $this->buildFeedbackLink();

        return $this->sendMessage(
            $contact['number'],
            $message,
            null,
            [
                'user_id' => $vendorId,
                'message_type' => 'feedback_request',
            ]
        );
    }

    /**
     * Formats a phone number to the pattern +55DDDNINE.
     */
    public function formatPhoneNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        if (strpos($digits, '55') === 0) {
            $national = substr($digits, 2);
        } else {
            $national = $digits;
            if (strlen($national) < 10 || strlen($national) > 11) {
                return null;
            }
            $digits = '55' . $national;
        }

        if (!preg_match('/^55\d{10,11}$/', $digits)) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * Inserts a log entry in digisac_messages_log.
     *
     * @param int|null    $userId
     * @param string      $phoneNumber
     * @param string|null $messageType
     * @param string      $messageText
     * @param int|null    $prospeccaoId
     * @param string|null $digisacMsgId
     * @param string      $status
     */
    public function logMessage(
        ?int $userId,
        string $phoneNumber,
        ?string $messageType,
        string $messageText,
        ?int $prospeccaoId,
        ?string $digisacMsgId,
        string $status
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO digisac_messages_log (user_id, phone_number, message_type, message_text, prospeccao_id, digisac_message_id, status, created_at)
             VALUES (:user_id, :phone_number, :message_type, :message_text, :prospeccao_id, :digisac_message_id, :status, :created_at)'
        );

        $stmt->bindValue(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':phone_number', $phoneNumber, PDO::PARAM_STR);
        $stmt->bindValue(':message_type', $messageType ?? 'generic', PDO::PARAM_STR);
        $stmt->bindValue(':message_text', $messageText, PDO::PARAM_STR);
        if ($prospeccaoId !== null && $prospeccaoId > 0) {
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':prospeccao_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':digisac_message_id', $digisacMsgId, $digisacMsgId !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $this->currentTimestamp(), PDO::PARAM_STR);
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Updates message delivery status based on Digisac callbacks.
     */
    public function updateMessageStatus(int $logId, string $status, ?string $timestamp = null): bool
    {
        $status = strtolower(trim($status));
        $allowedStatuses = ['sent', 'delivered', 'read', 'failed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'sent';
        }

        $sql = 'UPDATE digisac_messages_log SET status = :status';
        $params = [
            ':status' => $status,
            ':id' => $logId,
        ];

        $eventTimestamp = $timestamp !== null ? $timestamp : $this->currentTimestamp();

        if ($status === 'delivered') {
            $sql .= ', delivered_at = :event_ts';
            $params[':event_ts'] = $eventTimestamp;
        } elseif ($status === 'read') {
            $sql .= ', read_at = :event_ts';
            $params[':event_ts'] = $eventTimestamp;
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        return $stmt->execute();
    }

    /**
     * Processes Digisac webhook payloads updating local logs.
     *
     * @param array<string,mixed> $webhookData
     */
    public function handleWebhook(array $webhookData): void
    {
        $messageId = $webhookData['message_id'] ?? $webhookData['id'] ?? null;
        $status = $webhookData['status'] ?? null;
        $timestamp = $webhookData['timestamp'] ?? null;

        if ($messageId === null || $status === null) {
            error_log('DigisacService::handleWebhook missing message_id or status.');

            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM digisac_messages_log WHERE digisac_message_id = :message_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->bindValue(':message_id', $messageId, PDO::PARAM_STR);
        $stmt->execute();
        $logId = $stmt->fetchColumn();

        if ($logId === false) {
            error_log('DigisacService::handleWebhook log not found for message ' . $messageId);

            return;
        }

        $this->updateMessageStatus((int)$logId, (string)$status, $timestamp !== null ? (string)$timestamp : null);
    }

    /**
     * Executes the HTTP request with retry.
     *
     * @param array<string,mixed> $payload
     *
     * @return array{success:bool,message_id?:string,error?:string}
     */
    protected function performMessageRequest(array $payload): array
    {
        $url = rtrim($this->apiUrl, '/') . self::MESSAGE_ENDPOINT;
        $attempt = 0;
        $lastError = null;
        $messageId = null;

        while ($attempt < self::RETRY_ATTEMPTS) {
            $attempt++;
            $shouldRetry = false;
            try {
                $ch = curl_init($url);
                if ($ch === false) {
                    throw new RuntimeException('Unable to initialize cURL.');
                }

                $headers = [
                    'Authorization: Bearer ' . $this->apiToken,
                    'Content-Type: application/json',
                ];

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);

                $responseBody = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = curl_errno($ch);

                if ($responseBody === false) {
                    $lastError = curl_error($ch);
                    $timeoutErrors = [CURLE_OPERATION_TIMEOUTED];
                    if (defined('CURLE_OPERATION_TIMEDOUT')) {
                        $timeoutErrors[] = (int) constant('CURLE_OPERATION_TIMEDOUT');
                    }
                    if (in_array($curlErrno, $timeoutErrors, true)) {
                        $shouldRetry = true;
                    }
                } elseif ($httpCode >= 200 && $httpCode < 300) {
                    $decoded = json_decode((string)$responseBody, true);
                    if (is_array($decoded) && isset($decoded['id'])) {
                        $messageId = (string)$decoded['id'];
                        curl_close($ch);

                        return ['success' => true, 'message_id' => $messageId];
                    }
                    $lastError = 'Unexpected response from Digisac.';
                } else {
                    $lastError = sprintf('HTTP %d: %s', $httpCode, (string)$responseBody);
                    if ($httpCode >= 500 && $httpCode <= 599) {
                        $shouldRetry = true;
                    }
                }

                curl_close($ch);
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            }

            if (!$shouldRetry) {
                break;
            }
        }

        if ($lastError !== null) {
            error_log('DigisacService::performMessageRequest error: ' . $lastError);
        }

        return ['success' => false, 'error' => $lastError ?? 'Unknown error'];
    }

    /**
     * Retrieves WhatsApp contact info for a user.
     *
     * @return array{number:string,notifications_enabled:bool}|null
     */
    private function getUserContact(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT whatsapp_number, whatsapp_notifications_enabled FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $number = $row['whatsapp_number'] ?? '';
        if (!is_string($number) || trim($number) === '') {
            return null;
        }

        $formattedNumber = $this->formatPhoneNumber($number);
        if ($formattedNumber === null) {
            return null;
        }

        $notificationsEnabledRaw = $row['whatsapp_notifications_enabled'] ?? 1;
        $notificationsEnabled = (int)$notificationsEnabledRaw === 1;

        return [
            'number' => $formattedNumber,
            'notifications_enabled' => $notificationsEnabled,
        ];
    }

    private function sanitizeLine(string $value): string
    {
        return trim(strip_tags($value));
    }

    private function currentTimestamp(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    private function buildProspectionLink(?int $leadId): string
    {
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $path = '/crm/prospeccoes/detalhes.php';
        if ($leadId !== null && $leadId > 0) {
            return $baseUrl . $path . '?id=' . $leadId;
        }

        return $baseUrl . $path;
    }

    private function buildFeedbackLink(): string
    {
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';

        return $baseUrl . '/crm/prospeccoes/lista.php';
    }
}
