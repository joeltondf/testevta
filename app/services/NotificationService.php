<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../models/Model.php')) {
    require_once __DIR__ . '/../models/Model.php';
}


/**
 * Service layer responsible for orchestrating CRM notification creation.
 */
class NotificationService
{
    private const DEFAULT_GROUP = 'gerencia';
    private const VALID_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** @var PDO|null */
    private static ?PDO $pdo = null;

    /**
     * @var array<int, array{id:int,nome_completo:string|null,perfil:string|null}|false>
     */
    private static array $userCache = [];

    /**
     * Creates a notification indicating that a lead has been transferred to a vendor.
     *
     * @param array|object $handoff Lead handoff payload containing vendor_id, sdr_id, prospeccao_id, urgency and nome_prospecto.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyLeadTransferred($handoff)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing vendor_id or prospeccao_id for lead transfer notification.');
            }

            $urgency = strtolower((string) ($data['urgency'] ?? 'normal'));
            $priority = self::mapUrgencyToPriority($urgency);
            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'Lead');
            $formattedUrgency = ucfirst($urgency);

            $message = sprintf('🔔 Novo lead %s: %s', $formattedUrgency, $prospectName);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $vendorId,
                'remetente_id' => $sdrId,
                'tipo_alerta' => 'lead_received',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'vendedor',
                'mensagem' => $message,
                'link' => $link,
                'priority' => $priority,
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyLeadTransferred error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Creates a notification letting the SDR know that the vendor accepted the lead.
     *
     * @param array|object $handoff Lead handoff payload.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyLeadAccepted($handoff)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $sdrId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for lead accepted notification.');
            }

            $vendorName = self::sanitizeText($data['vendor_name'] ?? self::getUserName($vendorId) ?? 'O vendedor');
            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'o lead');

            $message = sprintf('✅ %s aceitou o lead %s', $vendorName, $prospectName);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $sdrId,
                'remetente_id' => $vendorId,
                'tipo_alerta' => 'lead_accepted',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'sdr',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'normal',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyLeadAccepted error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Notifies the SDR when a vendor rejects a lead.
     *
     * @param array|object $handoff Lead handoff payload.
     * @param string       $reason  Reason provided by the vendor.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyLeadRejected($handoff, string $reason)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $sdrId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for lead rejection notification.');
            }

            $vendorName = self::sanitizeText($data['vendor_name'] ?? self::getUserName($vendorId) ?? 'O vendedor');
            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'o lead');
            $reasonText = self::sanitizeText($reason !== '' ? $reason : 'Sem motivo informado');
            $message = sprintf('❌ %s recusou %s: %s', $vendorName, $prospectName, $reasonText);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $sdrId,
                'remetente_id' => $vendorId,
                'tipo_alerta' => 'lead_lost',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'sdr',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'high',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyLeadRejected error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Notifies the SDR when the vendor reports the first contact with the lead.
     *
     * @param array|object $handoff Lead handoff payload.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyFirstContact($handoff)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $sdrId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for first contact notification.');
            }

            $vendorName = self::sanitizeText($data['vendor_name'] ?? self::getUserName($vendorId) ?? 'O vendedor');
            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'o lead');
            $message = sprintf('📞 %s fez primeiro contato com %s', $vendorName, $prospectName);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $sdrId,
                'remetente_id' => $vendorId,
                'tipo_alerta' => 'lead_contacted',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'sdr',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'normal',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyFirstContact error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Notifies the SDR when a lead is converted.
     *
     * @param int         $prospeccaoId   Prospection identifier.
     * @param int         $sdrId          SDR identifier.
     * @param int|null    $vendorId       Vendor identifier.
     * @param string|null $prospeccaoNome Name of the prospection.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyLeadConverted(int $prospeccaoId, int $sdrId, ?int $vendorId, ?string $prospeccaoNome = null)
    {
        try {
            if ($prospeccaoId <= 0 || $sdrId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers for lead conversion notification.');
            }

            $prospectName = self::sanitizeText($prospeccaoNome ?? 'Lead');
            $message = sprintf('🎉 Lead convertido: %s', $prospectName);
            $link = self::buildProspectionLink($prospeccaoId);

            return self::createNotification([
                'usuario_id' => $sdrId,
                'remetente_id' => $vendorId,
                'tipo_alerta' => 'lead_converted',
                'referencia_id' => $prospeccaoId,
                'grupo_destino' => 'sdr',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'normal',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyLeadConverted error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Notifies the SDR when a lead is lost after qualification.
     *
     * @param int         $prospeccaoId   Prospection identifier.
     * @param int         $sdrId          SDR identifier.
     * @param int|null    $vendorId       Vendor identifier.
     * @param string|null $prospeccaoNome Name of the prospection.
     * @param string|null $reason         Reason for the loss.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyLeadLost(
        int $prospeccaoId,
        int $sdrId,
        ?int $vendorId,
        ?string $prospeccaoNome = null,
        ?string $reason = null
    ) {
        try {
            if ($prospeccaoId <= 0 || $sdrId <= 0) {
                throw new InvalidArgumentException('Invalid identifiers for lead lost notification.');
            }

            $prospectName = self::sanitizeText($prospeccaoNome ?? 'Lead');
            $reasonText = self::sanitizeText($reason ?? 'Sem justificativa informada');
            $message = sprintf('Lead perdido: %s - %s', $prospectName, $reasonText);
            $link = self::buildProspectionLink($prospeccaoId);

            return self::createNotification([
                'usuario_id' => $sdrId,
                'remetente_id' => $vendorId,
                'tipo_alerta' => 'lead_lost',
                'referencia_id' => $prospeccaoId,
                'grupo_destino' => 'sdr',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'normal',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyLeadLost error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Sends an SLA warning notification to the vendor.
     *
     * @param array|object $handoff Lead handoff payload.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifySLAWarning($handoff)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for SLA warning notification.');
            }

            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'Lead');
            $remainingHours = $data['sla_hours_remaining']
                ?? $data['hours_remaining']
                ?? $data['tempo_restante']
                ?? null;
            $hoursLabel = self::formatRemainingHours($remainingHours);
            $message = sprintf('⚠️ SLA do lead %s vence %s', $prospectName, $hoursLabel);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $vendorId,
                'remetente_id' => $sdrId,
                'tipo_alerta' => 'sla_warning',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'vendedor',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'urgent',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifySLAWarning error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Sends SLA overdue notifications to the vendor and the management team.
     *
     * @param array|object $handoff Lead handoff payload.
     *
     * @return int[] Array containing the identifiers of the generated notifications. Empty array on failure.
     */
    public static function notifySLAOverdue($handoff): array
    {
        $notificationIds = [];

        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for SLA overdue notification.');
            }

            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'Lead');
            $message = sprintf('🚨 SLA VENCIDO: %s', $prospectName);
            $link = self::buildProspectionLink($prospectionId);

            $vendorNotification = self::createNotification([
                'usuario_id' => $vendorId,
                'remetente_id' => $sdrId,
                'tipo_alerta' => 'sla_warning',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'vendedor',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'urgent',
            ]);

            if ($vendorNotification !== false) {
                $notificationIds[] = $vendorNotification;
            }

            $managerIds = self::getUserIdsByProfiles(['gerencia', 'supervisor']);

            foreach ($managerIds as $managerId) {
                $managerNotification = self::createNotification([
                    'usuario_id' => $managerId,
                    'remetente_id' => $vendorId,
                    'tipo_alerta' => 'sla_warning',
                    'referencia_id' => $prospectionId,
                    'grupo_destino' => 'gerencia',
                    'mensagem' => $message,
                    'link' => $link,
                    'priority' => 'urgent',
                ]);

                if ($managerNotification !== false) {
                    $notificationIds[] = $managerNotification;
                }
            }
        } catch (Throwable $exception) {
            error_log('NotificationService::notifySLAOverdue error: ' . $exception->getMessage());

            return [];
        }

        return $notificationIds;
    }

    /**
     * Creates a reminder notification asking the vendor to provide feedback on the lead qualification.
     *
     * @param array|object $handoff Lead handoff payload.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyFeedbackRequest($handoff)
    {
        try {
            $data = self::normalizePayload($handoff);
            $vendorId = self::extractPositiveInt($data, 'vendor_id');
            $sdrId = self::extractPositiveInt($data, 'sdr_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($vendorId === null || $prospectionId === null) {
                throw new InvalidArgumentException('Missing identifiers for feedback request notification.');
            }

            $prospectName = self::sanitizeText($data['nome_prospecto'] ?? 'Lead');
            $message = sprintf('💬 Por favor, avalie a qualificação do lead %s', $prospectName);
            $link = self::buildProspectionLink($prospectionId);

            return self::createNotification([
                'usuario_id' => $vendorId,
                'remetente_id' => $sdrId,
                'tipo_alerta' => 'feedback_requested',
                'referencia_id' => $prospectionId,
                'grupo_destino' => 'vendedor',
                'mensagem' => $message,
                'link' => $link,
                'priority' => 'normal',
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyFeedbackRequest error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Notifies the recipient about a new chat message related to a lead.
     *
     * @param array|object $messagePayload Message payload containing sender/recipient identifiers and urgency.
     * @param string       $prospeccaoNome Name of the prospection linked to the chat conversation.
     *
     * @return int|false Notification identifier on success, false otherwise.
     */
    public static function notifyNewMessage($messagePayload, string $prospeccaoNome)
    {
        try {
            $data = self::normalizePayload($messagePayload);
            $senderId = self::extractPositiveInt($data, 'sender_id');
            $recipientId = self::extractPositiveInt($data, 'recipient_id');
            $prospectionId = self::extractPositiveInt($data, 'prospeccao_id');

            if ($recipientId === null) {
                throw new InvalidArgumentException('Missing recipient_id for new message notification.');
            }

            $senderName = self::sanitizeText($data['sender_name'] ?? self::getUserName($senderId) ?? 'Um usuário');
            $prospectName = self::sanitizeText($prospeccaoNome);
            $priority = self::normalizePriority((string) ($data['is_urgent'] ?? 'normal'));
            $link = $prospectionId !== null ? self::buildProspectionLink($prospectionId) : null;
            $message = sprintf('%s enviou uma mensagem sobre %s', $senderName, $prospectName);

            return self::createNotification([
                'usuario_id' => $recipientId,
                'remetente_id' => $senderId,
                'tipo_alerta' => 'new_message',
                'referencia_id' => $prospectionId,
                'grupo_destino' => $data['grupo_destino'] ?? self::determineGroupByUserId($recipientId),
                'mensagem' => $message,
                'link' => $link,
                'priority' => $priority,
            ]);
        } catch (Throwable $exception) {
            error_log('NotificationService::notifyNewMessage error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Inserts a notification record into the database.
     *
     * @param array{
     *     usuario_id:int,
     *     tipo_alerta:string,
     *     mensagem:string,
     *     priority?:string,
     *     remetente_id?:int|null,
     *     referencia_id?:int|null,
     *     grupo_destino?:string|null,
     *     link?:string|null
     * } $data Structured notification payload ready for persistence.
     *
     * @return int|false Identifier of the stored notification or false on failure.
     */
    private static function createNotification(array $data)
    {
        try {
            $pdo = self::getPDO();

            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Unable to obtain PDO instance for notification creation.');
            }

            $usuarioId = (int) ($data['usuario_id'] ?? 0);
            $mensagem = self::sanitizeText($data['mensagem'] ?? '');
            $tipoAlerta = self::sanitizeText($data['tipo_alerta'] ?? '');

            if ($usuarioId <= 0 || $mensagem === '' || $tipoAlerta === '') {
                throw new InvalidArgumentException('Invalid notification payload.');
            }

            $remetenteId = isset($data['remetente_id']) ? (int) $data['remetente_id'] : null;
            if ($remetenteId !== null && $remetenteId <= 0) {
                $remetenteId = null;
            }

            $referenciaId = isset($data['referencia_id']) ? (int) $data['referencia_id'] : null;
            if ($referenciaId !== null && $referenciaId <= 0) {
                $referenciaId = null;
            }

            $grupoDestino = $data['grupo_destino'] ?? self::determineGroupByUserId($usuarioId) ?? self::DEFAULT_GROUP;
            $grupoDestino = $grupoDestino !== null ? self::sanitizeText($grupoDestino) : self::DEFAULT_GROUP;
            if ($grupoDestino === '') {
                $grupoDestino = self::DEFAULT_GROUP;
            }

            $link = isset($data['link']) ? trim((string) $data['link']) : null;
            if ($link === '') {
                $link = null;
            }

            $priority = self::normalizePriority($data['priority'] ?? 'normal');

            $sql = <<<SQL
                INSERT INTO notificacoes (
                    usuario_id,
                    remetente_id,
                    tipo_alerta,
                    referencia_id,
                    grupo_destino,
                    mensagem,
                    link,
                    priority,
                    lida,
                    resolvido,
                    read_at,
                    data_criacao
                ) VALUES (
                    :usuario_id,
                    :remetente_id,
                    :tipo_alerta,
                    :referencia_id,
                    :grupo_destino,
                    :mensagem,
                    :link,
                    :priority,
                    0,
                    0,
                    NULL,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    remetente_id = VALUES(remetente_id),
                    grupo_destino = VALUES(grupo_destino),
                    mensagem = VALUES(mensagem),
                    link = VALUES(link),
                    priority = VALUES(priority),
                    lida = 0,
                    resolvido = 0,
                    read_at = NULL,
                    data_resolucao = NULL,
                    data_criacao = NOW()
            SQL;

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':remetente_id', $remetenteId, $remetenteId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);

            if ($referenciaId === null) {
                $stmt->bindValue(':referencia_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            }

            $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);
            $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
            $stmt->bindValue(':link', $link, $link === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':priority', $priority, PDO::PARAM_STR);
            $stmt->execute();

            $notificationId = (int) $pdo->lastInsertId();
            if ($notificationId > 0) {
                return $notificationId;
            }

            $lookupSql = 'SELECT id FROM notificacoes WHERE usuario_id = :usuario_id AND tipo_alerta = :tipo_alerta';
            if ($referenciaId === null) {
                $lookupSql .= ' AND referencia_id IS NULL';
            } else {
                $lookupSql .= ' AND referencia_id = :lookup_referencia_id';
            }
            $lookupSql .= ' ORDER BY id DESC LIMIT 1';

            $lookupStmt = $pdo->prepare($lookupSql);
            $lookupStmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $lookupStmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
            if ($referenciaId !== null) {
                $lookupStmt->bindValue(':lookup_referencia_id', $referenciaId, PDO::PARAM_INT);
            }

            $lookupStmt->execute();
            $result = $lookupStmt->fetch(PDO::FETCH_ASSOC);

            return $result !== false ? (int) $result['id'] : false;
        } catch (Throwable $exception) {
            error_log('NotificationService::createNotification error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Obtains a reusable PDO instance.
     */
    private static function getPDO(): ?PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        try {
            if (class_exists('Model') && method_exists('Model', 'getPDO')) {
                $pdo = Model::getPDO();
                if ($pdo instanceof PDO) {
                    self::$pdo = $pdo;

                    return self::$pdo;
                }
            }
        } catch (Throwable $exception) {
            error_log('NotificationService::getPDO Model::getPDO error: ' . $exception->getMessage());
        }

        $globalPdo = $GLOBALS['pdo'] ?? null;
        if ($globalPdo instanceof PDO) {
            self::$pdo = $globalPdo;

            return self::$pdo;
        }

        $configPath = dirname(__DIR__, 2) . '/config.php';
        if (file_exists($configPath)) {
            try {
                require_once $configPath;

                if (isset($pdo) && $pdo instanceof PDO) {
                    self::$pdo = $pdo;

                    return self::$pdo;
                }

                $globalPdo = $GLOBALS['pdo'] ?? null;
                if ($globalPdo instanceof PDO) {
                    self::$pdo = $globalPdo;

                    return self::$pdo;
                }
            } catch (Throwable $exception) {
                error_log('NotificationService::getPDO config load error: ' . $exception->getMessage());
            }
        }

        return null;
    }

    /**
     * Normalizes the incoming payload to an associative array.
     *
     * @param mixed $payload Raw payload (array or stdClass).
     */
    private static function normalizePayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return get_object_vars($payload);
        }

        return [];
    }

    /**
     * Attempts to extract a positive integer from the provided payload.
     */
    private static function extractPositiveInt(array $payload, string $field): ?int
    {
        if (!array_key_exists($field, $payload)) {
            return null;
        }

        $value = filter_var($payload[$field], FILTER_VALIDATE_INT);

        if ($value === false || $value <= 0) {
            return null;
        }

        return $value;
    }

    /**
     * Maps urgency values to notification priorities.
     */
    private static function mapUrgencyToPriority(string $urgency): string
    {
        switch ($urgency) {
            case 'alta':
            case 'urgent':
            case 'urgente':
                return 'urgent';
            case 'media':
            case 'média':
            case 'high':
                return 'high';
            case 'baixa':
            case 'low':
                return 'normal';
            default:
                return 'normal';
        }
    }

    /**
     * Normalizes priority values.
     *
     * @param string $priority Incoming priority text.
     */
    private static function normalizePriority(string $priority): string
    {
        $normalized = strtolower(trim($priority));

        if ($normalized === 'true' || $normalized === '1') {
            $normalized = 'urgent';
        }

        if (!in_array($normalized, self::VALID_PRIORITIES, true)) {
            $normalized = 'normal';
        }

        return $normalized;
    }

    /**
     * Returns the formatted remaining hours for SLA warning.
     *
     * @param mixed $value Value representing the remaining hours.
     */
    private static function formatRemainingHours($value): string
    {
        if ($value === null || $value === '') {
            return 'em breve';
        }

        if (is_numeric($value)) {
            $hours = (float) $value;

            if ($hours <= 0) {
                return 'agora';
            }

            $rounded = (int) ceil($hours);

            return sprintf('em %d hora%s', $rounded, $rounded > 1 ? 's' : '');
        }

        $text = self::sanitizeText((string) $value);

        return $text !== '' ? $text : 'em breve';
    }

    /**
     * Builds the standard prospection detail link.
     */
    private static function buildProspectionLink(int $prospectionId): string
    {
        return sprintf('/crm/prospeccoes/detalhes.php?id=%d', $prospectionId);
    }

    /**
     * Sanitizes text values by trimming and stripping tags.
     */
    private static function sanitizeText(string $text): string
    {
        return trim(strip_tags($text));
    }

    /**
     * Retrieves a cached user name or fetches it from the database.
     */
    private static function getUserName(?int $userId): ?string
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        $userData = self::getUserData($userId);

        if (is_array($userData) && isset($userData['nome_completo'])) {
            return $userData['nome_completo'];
        }

        return null;
    }

    /**
     * Retrieves cached user information.
     */
    private static function getUserData(int $userId)
    {
        if (array_key_exists($userId, self::$userCache)) {
            return self::$userCache[$userId];
        }

        $pdo = self::getPDO();

        if (!$pdo instanceof PDO) {
            self::$userCache[$userId] = false;

            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT id, nome_completo, perfil FROM users WHERE id = :id');
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            self::$userCache[$userId] = $user !== false ? $user : false;
        } catch (Throwable $exception) {
            error_log('NotificationService::getUserData error: ' . $exception->getMessage());
            self::$userCache[$userId] = false;
        }

        return self::$userCache[$userId];
    }

    /**
     * Determines the appropriate notification group for a given user.
     */
    private static function determineGroupByUserId(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return self::DEFAULT_GROUP;
        }

        $userData = self::getUserData($userId);

        if (!is_array($userData) || empty($userData['perfil'])) {
            return self::DEFAULT_GROUP;
        }

        $perfil = strtolower($userData['perfil']);

        switch ($perfil) {
            case 'vendedor':
                return 'vendedor';
            case 'sdr':
                return 'sdr';
            case 'gerencia':
            case 'supervisor':
            case 'admin':
                return 'gerencia';
            default:
                return self::DEFAULT_GROUP;
        }
    }

    /**
     * Retrieves the identifiers of users by their profile names.
     *
     * @param string[] $profiles Profiles to fetch.
     *
     * @return int[]
     */
    private static function getUserIdsByProfiles(array $profiles): array
    {
        $profiles = array_values(array_filter(array_map('strval', $profiles), static function ($profile) {
            return $profile !== '';
        }));

        if (empty($profiles)) {
            return [];
        }

        $pdo = self::getPDO();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($profiles), '?'));

        try {
            $sql = 'SELECT id FROM users WHERE perfil IN (' . $placeholders . ') AND (ativo = 1 OR ativo IS NULL)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($profiles);

            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return array_map('intval', $ids);
        } catch (Throwable $exception) {
            error_log('NotificationService::getUserIdsByProfiles error: ' . $exception->getMessage());

            return [];
        }
    }
}
