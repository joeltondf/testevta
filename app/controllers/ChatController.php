<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/SdrVendorMessage.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/Notificacao.php';
require_once __DIR__ . '/../services/NotificationService.php';

/**
 * Controlador responsável por gerenciar as interações do chat entre SDRs e vendedores.
 */
class ChatController
{
    private const MESSAGE_LIMIT_PER_REQUEST = 50;
    private const RATE_LIMIT_MAX_MESSAGES = 100;
    private const RATE_LIMIT_WINDOW_SECONDS = 3600; // 1 hora
    private const MAX_MESSAGE_LENGTH = 5000;
    private const MAX_UPLOAD_SIZE = 5242880; // 5 MB
    private const UPLOAD_BASE_DIR = __DIR__ . '/../../uploads/chat';

    private PDO $pdo;
    private SdrVendorMessage $messageModel;
    private Prospeccao $prospeccaoModel;
    private Notificacao $notificationModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->messageModel = new SdrVendorMessage($pdo);
        $this->prospeccaoModel = new Prospeccao($pdo);
        $this->notificationModel = new Notificacao($pdo);
    }

    /**
     * Envia uma nova mensagem entre SDR e vendedor.
     */
    public function sendMessage(): void
    {
        if (!$this->isRequestMethod('POST')) {
            $this->respondMethodNotAllowed(['POST']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $payload = $this->getJsonPayload();

        $prospeccaoId = $this->filterInt($payload['prospeccao_id'] ?? null);
        $recipientId = $this->filterInt($payload['recipient_id'] ?? null);
        $rawMessage = isset($payload['message']) ? trim((string) $payload['message']) : '';
        $isUrgent = $this->normalizeBoolean($payload['is_urgent'] ?? false);

        if ($prospeccaoId <= 0 || $recipientId <= 0) {
            $this->respondJson(['success' => false, 'message' => 'Dados inválidos.'], 400);
        }

        if ($rawMessage === '') {
            $this->respondJson(['success' => false, 'message' => 'A mensagem não pode estar vazia.'], 400);
        }

        if (mb_strlen($rawMessage, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            $this->respondJson([
                'success' => false,
                'message' => 'A mensagem excede o limite de caracteres permitido.'
            ], 400);
        }

        $lead = $this->fetchLead($prospeccaoId);

        if (!$this->userCanAccessLead($currentUser['id'], $currentUser['perfil'], $lead)) {
            $this->respondJson(['success' => false, 'message' => 'Acesso negado ao lead.'], 403);
        }

        if (!$this->isRecipientValid($lead, $recipientId, $currentUser['id'])) {
            $this->respondJson(['success' => false, 'message' => 'Destinatário inválido para esta conversa.'], 400);
        }

        if (!$this->checkRateLimit($currentUser['id'])) {
            $this->respondJson([
                'success' => false,
                'message' => 'Limite de mensagens excedido. Tente novamente em alguns minutos.'
            ], 429);
        }

        $sanitizedMessage = htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8');

        try {
            $messageId = $this->messageModel->send(
                $prospeccaoId,
                $currentUser['id'],
                $recipientId,
                $sanitizedMessage,
                $isUrgent
            );

            if (!$messageId) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'Não foi possível enviar a mensagem.'
                ], 500);
            }

            $timestamp = $this->fetchMessageTimestamp($messageId);

            if (!$this->isUserOnline($recipientId)) {
                $this->notifyRecipient($lead, $currentUser['id'], $recipientId, $isUrgent);
            }

            $this->respondJson([
                'success' => true,
                'message_id' => $messageId,
                'timestamp' => $timestamp
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::sendMessage error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Ocorreu um erro ao enviar a mensagem.'
            ], 500);
        }
    }

    /**
     * Recupera a conversa relacionada a uma prospecção.
     */
    public function getConversation(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondMethodNotAllowed(['GET']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $prospeccaoId = $this->filterInt($_GET['prospeccao_id'] ?? null);
        $lastMessageId = isset($_GET['last_message_id']) ? $this->filterInt($_GET['last_message_id']) : null;

        if ($prospeccaoId <= 0) {
            $this->respondJson(['success' => false, 'message' => 'Prospecção inválida.'], 400);
        }

        $lead = $this->fetchLead($prospeccaoId);

        if (!$this->userCanAccessLead($currentUser['id'], $currentUser['perfil'], $lead)) {
            $this->respondJson(['success' => false, 'message' => 'Acesso negado ao lead.'], 403);
        }

        try {
            if ($lastMessageId !== null && $lastMessageId > 0) {
                $messages = $this->fetchMessagesAfterId($prospeccaoId, $currentUser['id'], $lastMessageId);
                $hasMore = false;
            } else {
                $limit = self::MESSAGE_LIMIT_PER_REQUEST + 1;
                $messages = $this->messageModel->getConversation($prospeccaoId, $currentUser['id'], $limit);
                $hasMore = count($messages) > self::MESSAGE_LIMIT_PER_REQUEST;

                if ($hasMore) {
                    $messages = array_slice($messages, -self::MESSAGE_LIMIT_PER_REQUEST, self::MESSAGE_LIMIT_PER_REQUEST);
                }
            }

            $formattedMessages = array_map(
                fn(array $message) => $this->formatMessage($message, $currentUser['id']),
                $messages
            );

            $markedCount = $this->messageModel->markConversationAsRead($prospeccaoId, $currentUser['id']);
            if ($markedCount > 0) {
                $this->resetUnreadCache($currentUser['id']);
            }

            $this->respondJson([
                'success' => true,
                'messages' => $formattedMessages,
                'has_more' => $hasMore
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::getConversation error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível carregar a conversa.'
            ], 500);
        }
    }

    /**
     * Marca mensagens como lidas.
     */
    public function markAsRead(): void
    {
        if (!$this->isRequestMethod('POST')) {
            $this->respondMethodNotAllowed(['POST']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $payload = $this->getJsonPayload();

        $messageId = isset($payload['message_id']) ? $this->filterInt($payload['message_id']) : null;
        $prospeccaoId = isset($payload['prospeccao_id']) ? $this->filterInt($payload['prospeccao_id']) : null;

        if ($messageId === null && $prospeccaoId === null) {
            $this->respondJson([
                'success' => false,
                'message' => 'Informe a mensagem ou a prospecção.'
            ], 400);
        }

        try {
            if ($messageId !== null && $messageId > 0) {
                $marked = $this->messageModel->markAsRead($messageId, $currentUser['id']);
                $count = $marked ? 1 : 0;
            } else {
                if ($prospeccaoId === null || $prospeccaoId <= 0) {
                    $this->respondJson(['success' => false, 'message' => 'Prospecção inválida.'], 400);
                }

                $lead = $this->fetchLead($prospeccaoId);
                if (!$this->userCanAccessLead($currentUser['id'], $currentUser['perfil'], $lead)) {
                    $this->respondJson(['success' => false, 'message' => 'Acesso negado ao lead.'], 403);
                }

                $count = $this->messageModel->markConversationAsRead($prospeccaoId, $currentUser['id']);
            }

            if ($count > 0) {
                $this->resetUnreadCache($currentUser['id']);
            }

            $this->respondJson([
                'success' => true,
                'marked_count' => $count
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::markAsRead error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível atualizar o status de leitura.'
            ], 500);
        }
    }

    /**
     * Obtém a contagem de mensagens não lidas do usuário.
     */
    public function getUnreadCount(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondMethodNotAllowed(['GET']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);

        try {
            $count = $this->messageModel->getUnreadCount($currentUser['id']);
            $this->respondJson([
                'success' => true,
                'unread_count' => $count
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::getUnreadCount error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível obter a contagem.'
            ], 500);
        }
    }

    /**
     * Retorna as conversas recentes do usuário.
     */
    public function getRecentConversations(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondMethodNotAllowed(['GET']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $limit = $this->filterInt($_GET['limit'] ?? self::MESSAGE_LIMIT_PER_REQUEST);
        $limit = $limit > 0 ? min($limit, 50) : self::MESSAGE_LIMIT_PER_REQUEST;

        try {
            $conversations = $this->messageModel->getRecentConversations($currentUser['id'], $limit);
            $this->respondJson([
                'success' => true,
                'conversations' => $conversations
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::getRecentConversations error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível carregar as conversas.'
            ], 500);
        }
    }

    /**
     * Remove uma mensagem enviada pelo usuário.
     */
    public function deleteMessage(): void
    {
        if (!$this->isRequestMethod('DELETE')) {
            $this->respondMethodNotAllowed(['DELETE']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $payload = $this->getJsonPayload();
        $messageId = $this->filterInt($payload['message_id'] ?? null);

        if ($messageId <= 0) {
            $this->respondJson(['success' => false, 'message' => 'Mensagem inválida.'], 400);
        }

        try {
            $message = $this->fetchMessageMetadata($messageId);

            if (!$message) {
                $this->respondJson(['success' => false, 'message' => 'Mensagem não encontrada.'], 404);
            }

            if ((int) $message['sender_id'] !== $currentUser['id']) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'Não é possível deletar',
                ], 403);
            }

            $createdAt = new DateTimeImmutable($message['created_at']);
            $diffSeconds = (new DateTimeImmutable())->getTimestamp() - $createdAt->getTimestamp();

            if ($diffSeconds > 300) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'Não é possível deletar',
                ], 403);
            }

            if (!$this->messageModel->deleteMessage($messageId, $currentUser['id'])) {
                $this->respondJson([
                    'success' => false,
                    'message' => 'Não foi possível deletar a mensagem.'
                ], 500);
            }

            $this->respondJson(['success' => true]);
        } catch (Throwable $exception) {
            error_log('ChatController::deleteMessage error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível remover a mensagem.'
            ], 500);
        }
    }

    /**
     * Realiza o upload de um arquivo anexado à conversa.
     */
    public function uploadFile(): void
    {
        if (!$this->isRequestMethod('POST')) {
            $this->respondMethodNotAllowed(['POST']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);

        $prospeccaoId = $this->filterInt($_POST['prospeccao_id'] ?? null);
        if ($prospeccaoId <= 0) {
            $this->respondJson(['success' => false, 'message' => 'Prospecção inválida.'], 400);
        }

        if (!isset($_FILES['file'])) {
            $this->respondJson(['success' => false, 'message' => 'Nenhum arquivo enviado.'], 400);
        }

        $file = $_FILES['file'];

        if (!isset($file['error']) || is_array($file['error'])) {
            $this->respondJson(['success' => false, 'message' => 'Upload inválido.'], 400);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->respondJson(['success' => false, 'message' => 'Falha no upload do arquivo.'], 400);
        }

        if ((int) $file['size'] > self::MAX_UPLOAD_SIZE) {
            $this->respondJson([
                'success' => false,
                'message' => 'O arquivo excede o tamanho máximo permitido (5MB).'
            ], 400);
        }

        $lead = $this->fetchLead($prospeccaoId);
        if (!$this->userCanAccessLead($currentUser['id'], $currentUser['perfil'], $lead)) {
            $this->respondJson(['success' => false, 'message' => 'Acesso negado ao lead.'], 403);
        }

        $recipientId = $this->resolveRecipientFromLead($lead, $currentUser['id']);
        if ($recipientId <= 0) {
            $this->respondJson(['success' => false, 'message' => 'Não foi possível determinar o destinatário.'], 400);
        }

        $safeFilename = $this->sanitizeFileName($file['name'] ?? 'anexo');
        $extension = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];

        if (!in_array($extension, $allowedExtensions, true)) {
            $this->respondJson(['success' => false, 'message' => 'Tipo de arquivo não permitido.'], 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'text/plain'
        ];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->respondJson(['success' => false, 'message' => 'Tipo MIME não suportado.'], 400);
        }

        $targetDir = self::UPLOAD_BASE_DIR . '/' . $prospeccaoId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $this->respondJson(['success' => false, 'message' => 'Não foi possível criar o diretório de upload.'], 500);
        }

        $uniquePrefix = (new DateTimeImmutable())->format('YmdHis');
        $randomSuffix = bin2hex(random_bytes(4));
        $storedFileName = $uniquePrefix . '_' . $randomSuffix . '_' . $safeFilename;
        $storedFilePath = $targetDir . '/' . $storedFileName;

        if (!move_uploaded_file($file['tmp_name'], $storedFilePath)) {
            $this->respondJson(['success' => false, 'message' => 'Não foi possível salvar o arquivo.'], 500);
        }

        $relativePath = 'uploads/chat/' . $prospeccaoId . '/' . $storedFileName;
        $fileUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/' . $relativePath;

        $attachmentMessage = sprintf('[Arquivo anexado: %s]', $safeFilename);
        $sanitizedMessage = htmlspecialchars($attachmentMessage, ENT_QUOTES, 'UTF-8');

        try {
            $messageId = $this->messageModel->send(
                $prospeccaoId,
                $currentUser['id'],
                $recipientId,
                $sanitizedMessage,
                false,
                $fileUrl
            );

            if (!$messageId) {
                @unlink($storedFilePath);
                $this->respondJson([
                    'success' => false,
                    'message' => 'Não foi possível registrar a mensagem do arquivo.'
                ], 500);
            }

            if (!$this->isUserOnline($recipientId)) {
                $this->notifyRecipient($lead, $currentUser['id'], $recipientId, false);
            }

            $this->respondJson([
                'success' => true,
                'file_url' => $fileUrl,
                'message_id' => $messageId
            ]);
        } catch (Throwable $exception) {
            if (is_file($storedFilePath)) {
                @unlink($storedFilePath);
            }

            error_log('ChatController::uploadFile error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Ocorreu um erro ao anexar o arquivo.'
            ], 500);
        }
    }

    /**
     * Endpoint de polling para obter atualizações rápidas.
     */
    public function poll(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondMethodNotAllowed(['GET']);
        }

        $currentUser = $this->requireAuthenticatedUser(['sdr', 'vendedor']);
        $lastPollParam = isset($_GET['last_poll_time']) ? trim((string) $_GET['last_poll_time']) : null;
        $lastPollDate = $this->parseDateTime($lastPollParam);

        try {
            $newMessagesCount = $this->countNewMessages($currentUser['id'], $lastPollDate);
            $newNotificationsCount = $this->countNewNotifications($currentUser, $lastPollDate);
            $recentMessages = $this->fetchRecentMessages($currentUser['id']);

            $this->respondJson([
                'success' => true,
                'new_messages_count' => $newMessagesCount,
                'new_notifications_count' => $newNotificationsCount,
                'recent_messages' => $recentMessages,
                'updates' => []
            ]);
        } catch (Throwable $exception) {
            error_log('ChatController::poll error: ' . $exception->getMessage());
            $this->respondJson([
                'success' => false,
                'message' => 'Não foi possível obter as atualizações.'
            ], 500);
        }
    }

    /**
     * Valida se o request method é o esperado.
     */
    private function isRequestMethod(string $expected): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === strtoupper($expected);
    }

    /**
     * Responde com o status 405 quando o método HTTP é inválido.
     *
     * @param array<int, string> $allowedMethods
     */
    private function respondMethodNotAllowed(array $allowedMethods): void
    {
        header('Allow: ' . implode(', ', $allowedMethods));
        $this->respondJson([
            'success' => false,
            'message' => 'Método não permitido.'
        ], 405);
    }

    /**
     * Garante que o usuário esteja autenticado e autorizado.
     *
     * @param array<int, string> $allowedRoles
     *
     * @return array{id:int, perfil:string}
     */
    private function requireAuthenticatedUser(array $allowedRoles): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $perfil = (string) ($_SESSION['user_perfil'] ?? '');

        if ($userId <= 0 || !in_array($perfil, $allowedRoles, true)) {
            $this->respondJson([
                'success' => false,
                'message' => 'Não autenticado ou sem permissão.'
            ], 401);
        }

        return ['id' => $userId, 'perfil' => $perfil];
    }

    /**
     * Obtém e valida o payload JSON da requisição.
     *
     * @return array<string, mixed>
     */
    private function getJsonPayload(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            // Permite outros content-types para compatibilidade, porém tenta decodificar o corpo.
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: 'null', true);

        if (!is_array($data)) {
            $this->respondJson(['success' => false, 'message' => 'JSON inválido.'], 400);
        }

        return $data;
    }

    /**
     * Busca os dados da prospecção e garante o retorno de array.
     *
     * @return array<string, mixed>
     */
    private function fetchLead(int $prospeccaoId): array
    {
        $lead = $this->prospeccaoModel->getById($prospeccaoId);
        if (!is_array($lead)) {
            $this->respondJson(['success' => false, 'message' => 'Lead não encontrado.'], 404);
        }

        return $lead;
    }

    /**
     * Verifica se o usuário atual pode interagir com o lead.
     */
    private function userCanAccessLead(int $userId, string $perfil, array $lead): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sdrId = (int) ($lead['sdrId'] ?? 0);
        $vendorId = (int) ($lead['responsavel_id'] ?? 0);

        if ($perfil === 'sdr') {
            return $userId === $sdrId;
        }

        if ($perfil === 'vendedor') {
            return $userId === $vendorId;
        }

        return false;
    }

    /**
     * Determina se o destinatário é válido para a conversa.
     */
    private function isRecipientValid(array $lead, int $recipientId, int $currentUserId): bool
    {
        if ($recipientId <= 0 || $recipientId === $currentUserId) {
            return false;
        }

        $validIds = array_filter([
            (int) ($lead['sdrId'] ?? 0),
            (int) ($lead['responsavel_id'] ?? 0)
        ]);

        return in_array($recipientId, $validIds, true);
    }

    /**
     * Reseta caches relacionados a notificações não lidas.
     */
    private function resetUnreadCache(int $userId): void
    {
        if (function_exists('apcu_delete')) {
            @apcu_delete('chat_unread_' . $userId);
        }
    }

    /**
     * Retorna mensagens enviadas após determinado ID.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchMessagesAfterId(int $prospeccaoId, int $userId, int $lastMessageId): array
    {
        $sql = 'SELECT m.id, m.message, m.sender_id, sender.nome_completo AS sender_name, m.recipient_id,
                       m.is_read, m.is_urgent, m.attachment_url, m.created_at
                FROM sdr_vendor_messages m
                INNER JOIN users sender ON sender.id = m.sender_id
                WHERE m.prospeccao_id = :prospeccao_id
                  AND (m.sender_id = :user_id OR m.recipient_id = :user_id)
                  AND m.id > :last_id
                ORDER BY m.created_at ASC, m.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':last_id', $lastMessageId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Formata a mensagem para retorno JSON.
     *
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function formatMessage(array $message, int $currentUserId): array
    {
        return [
            'id' => (int) ($message['id'] ?? 0),
            'sender_id' => (int) ($message['sender_id'] ?? 0),
            'sender_name' => (string) ($message['sender_name'] ?? ''),
            'message' => (string) ($message['message'] ?? ''),
            'created_at' => (string) ($message['created_at'] ?? ''),
            'is_urgent' => (bool) ($message['is_urgent'] ?? false),
            'is_mine' => ((int) ($message['sender_id'] ?? 0)) === $currentUserId,
            'attachment_url' => $message['attachment_url'] ?? null,
        ];
    }

    /**
     * Recupera metadados de uma mensagem específica.
     *
     * @return array<string, mixed>|false
     */
    private function fetchMessageMetadata(int $messageId)
    {
        $stmt = $this->pdo->prepare('SELECT id, sender_id, created_at FROM sdr_vendor_messages WHERE id = :id');
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém o timestamp de criação da mensagem.
     */
    private function fetchMessageTimestamp(int $messageId): string
    {
        $stmt = $this->pdo->prepare('SELECT created_at FROM sdr_vendor_messages WHERE id = :id');
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
        $stmt->execute();

        $timestamp = $stmt->fetchColumn();

        return $timestamp !== false ? (string) $timestamp : (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    /**
     * Aplica rate limiting por usuário.
     */
    private function checkRateLimit(int $userId): bool
    {
        $now = time();
        $key = 'chat_rate_' . $userId;

        if (function_exists('apcu_fetch')) {
            $data = apcu_fetch($key);

            if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
                apcu_store($key, ['count' => 1, 'reset' => $now + self::RATE_LIMIT_WINDOW_SECONDS], self::RATE_LIMIT_WINDOW_SECONDS);
                return true;
            }

            if (($data['count'] ?? 0) >= self::RATE_LIMIT_MAX_MESSAGES) {
                return false;
            }

            $data['count'] = ($data['count'] ?? 0) + 1;
            apcu_store($key, $data, $data['reset'] - $now);

            return true;
        }

        if (!isset($_SESSION['chat_rate'])) {
            $_SESSION['chat_rate'] = [];
        }

        $rateData = $_SESSION['chat_rate'][$key] ?? ['count' => 0, 'reset' => $now + self::RATE_LIMIT_WINDOW_SECONDS];

        if ($rateData['reset'] <= $now) {
            $rateData = ['count' => 1, 'reset' => $now + self::RATE_LIMIT_WINDOW_SECONDS];
        } elseif ($rateData['count'] >= self::RATE_LIMIT_MAX_MESSAGES) {
            $_SESSION['chat_rate'][$key] = $rateData;
            return false;
        } else {
            $rateData['count']++;
        }

        $_SESSION['chat_rate'][$key] = $rateData;

        return true;
    }

    /**
     * Determina se um usuário está online com base em caches.
     */
    private function isUserOnline(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (function_exists('apcu_exists') && apcu_exists('user_online_' . $userId)) {
            return true;
        }

        if (!empty($_SESSION['online_users']) && is_array($_SESSION['online_users'])) {
            $lastActivity = (int) ($_SESSION['online_users'][$userId] ?? 0);
            if ($lastActivity > 0 && ($lastActivity + 300) >= time()) {
                return true;
            }
        }

        return $userId === (int) ($_SESSION['user_id'] ?? 0);
    }

    /**
     * Dispara a notificação de nova mensagem ao destinatário.
     */
    private function notifyRecipient(array $lead, int $senderId, int $recipientId, bool $isUrgent): void
    {
        $payload = [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'prospeccao_id' => (int) ($lead['id'] ?? 0),
            'is_urgent' => $isUrgent ? 'urgent' : 'normal'
        ];

        $prospeccaoNome = (string) ($lead['nome_prospecto'] ?? ($lead['cliente_nome'] ?? 'Lead'));
        NotificationService::notifyNewMessage($payload, $prospeccaoNome);
    }

    /**
     * Converte diferentes tipos em booleano.
     *
     * @param mixed $value
     */
    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitiza um nome de arquivo para evitar path traversal.
     */
    private function sanitizeFileName(string $filename): string
    {
        $basename = basename($filename);
        $basename = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $basename) ?? 'arquivo';
        return $basename !== '' ? $basename : 'arquivo';
    }

    /**
     * Determina o destinatário com base no lead.
     */
    private function resolveRecipientFromLead(array $lead, int $currentUserId): int
    {
        $sdrId = (int) ($lead['sdrId'] ?? 0);
        $vendorId = (int) ($lead['responsavel_id'] ?? 0);

        if ($currentUserId === $sdrId) {
            return $vendorId;
        }

        if ($currentUserId === $vendorId) {
            return $sdrId;
        }

        return 0;
    }

    /**
     * Faz o parse seguro de data/hora.
     */
    private function parseDateTime(?string $datetime): ?DateTimeImmutable
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($datetime);
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * Conta novas mensagens desde o último polling.
     */
    private function countNewMessages(int $userId, ?DateTimeImmutable $lastPoll): int
    {
        if ($lastPoll instanceof DateTimeImmutable) {
            $sql = 'SELECT COUNT(*) FROM sdr_vendor_messages WHERE recipient_id = :user_id AND created_at > :last_poll';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':last_poll', $lastPoll->format('Y-m-d H:i:s'));
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        }

        return $this->messageModel->getUnreadCount($userId);
    }

    /**
     * Determina o grupo de notificações com base no perfil.
     */
    private function resolveNotificationGroup(string $perfil): string
    {
        if ($perfil === 'sdr') {
            return 'sdr';
        }

        if ($perfil === 'vendedor') {
            return 'vendedor';
        }

        return 'gerencia';
    }

    /**
     * Conta novas notificações desde o último polling.
     *
     * @param array{id:int, perfil:string} $currentUser
     */
    private function countNewNotifications(array $currentUser, ?DateTimeImmutable $lastPoll): int
    {
        $grupo = $this->resolveNotificationGroup($currentUser['perfil']);

        if ($lastPoll instanceof DateTimeImmutable) {
            $sql = 'SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :user_id AND grupo_destino = :grupo AND data_criacao > :last_poll';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $currentUser['id'], PDO::PARAM_INT);
            $stmt->bindValue(':grupo', $grupo, PDO::PARAM_STR);
            $stmt->bindValue(':last_poll', $lastPoll->format('Y-m-d H:i:s'));
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        }

        return $this->notificationModel->countNaoLidas($currentUser['id'], $grupo);
    }

    /**
     * Obtém as mensagens mais recentes recebidas pelo usuário.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecentMessages(int $userId): array
    {
        $sql = 'SELECT m.id, m.message, m.created_at, m.sender_id, u.nome_completo AS sender_name, m.prospeccao_id
                FROM sdr_vendor_messages m
                INNER JOIN users u ON u.id = m.sender_id
                WHERE m.recipient_id = :user_id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 5';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'message' => (string) ($row['message'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'sender_id' => (int) ($row['sender_id'] ?? 0),
                'sender_name' => (string) ($row['sender_name'] ?? ''),
                'prospeccao_id' => (int) ($row['prospeccao_id'] ?? 0)
            ];
        }, $rows);
    }

    /**
     * Converte valores em inteiros seguros.
     *
     * @param mixed $value
     */
    private function filterInt($value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * Responde em JSON e encerra a execução.
     *
     * @param array<string, mixed> $data
     */
    private function respondJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
