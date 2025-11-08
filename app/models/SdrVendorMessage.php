<?php

require_once __DIR__ . '/Model.php';

/**
 * Class SdrVendorMessage
 *
 * Responsável por gerir as mensagens trocadas entre SDRs e vendedores
 * relacionadas a uma prospecção específica no CRM.
 */
class SdrVendorMessage extends Model
{
    /**
     * SdrVendorMessage constructor.
     *
     * @param PDO $pdo Instância de conexão PDO.
     */
    public function __construct(PDO $pdo)
    {
        if (method_exists('Model', '__construct')) {
            parent::__construct($pdo);
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * Envia uma nova mensagem na conversa da prospecção.
     *
     * @param int         $prospeccaoId  ID da prospecção.
     * @param int         $senderId      ID do remetente.
     * @param int         $recipientId   ID do destinatário.
     * @param string      $message       Conteúdo da mensagem.
     * @param bool        $isUrgent      Define se a mensagem é urgente.
     * @param string|null $attachmentUrl URL de um anexo opcional.
     *
     * @return int|false Retorna o ID da mensagem criada ou false em caso de falha.
     */
    public function send(
        int $prospeccaoId,
        int $senderId,
        int $recipientId,
        string $message,
        bool $isUrgent = false,
        ?string $attachmentUrl = null
    ) {
        $message = trim(strip_tags($message));

        if ($prospeccaoId <= 0 || $senderId <= 0 || $recipientId <= 0) {
            return false;
        }

        if ($message === '') {
            return false;
        }

        if ($attachmentUrl !== null && !filter_var($attachmentUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $sql = 'INSERT INTO sdr_vendor_messages (
                    prospeccao_id,
                    sender_id,
                    recipient_id,
                    message,
                    is_read,
                    is_urgent,
                    attachment_url
                ) VALUES (:prospeccao_id, :sender_id, :recipient_id, :message, 0, :is_urgent, :attachment_url)';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
            $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
            $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':is_urgent', $isUrgent, PDO::PARAM_BOOL);
            $stmt->bindValue(':attachment_url', $attachmentUrl, $attachmentUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            if ($stmt->execute()) {
                return (int) $this->pdo->lastInsertId();
            }
        } catch (PDOException $exception) {
            error_log('Erro ao enviar mensagem: ' . $exception->getMessage());
        }

        return false;
    }

    /**
     * Obtém a conversa de um usuário dentro de uma prospecção.
     *
     * @param int $prospeccaoId ID da prospecção.
     * @param int $userId       ID do usuário logado.
     * @param int $limit        Limite de registros retornados.
     * @param int $offset       Deslocamento para paginação.
     *
     * @return array Lista de mensagens formatadas.
     */
    public function getConversation(int $prospeccaoId, int $userId, int $limit = 50, int $offset = 0): array
    {
        if ($prospeccaoId <= 0 || $userId <= 0) {
            return [];
        }

        $limit = max($limit, 1);
        $offset = max($offset, 0);

        $sql = 'SELECT
                    m.id,
                    m.message,
                    m.sender_id,
                    sender.nome_completo AS sender_name,
                    m.recipient_id,
                    recipient.nome_completo AS recipient_name,
                    m.is_read,
                    m.is_urgent,
                    m.attachment_url,
                    m.created_at
                FROM sdr_vendor_messages m
                INNER JOIN users sender ON sender.id = m.sender_id
                INNER JOIN users recipient ON recipient.id = m.recipient_id
                WHERE m.prospeccao_id = :prospeccao_id
                  AND (m.sender_id = :user_sender OR m.recipient_id = :user_recipient)
                ORDER BY m.created_at ASC, m.id ASC
                LIMIT :limit OFFSET :offset';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
            $stmt->bindValue(':user_sender', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_recipient', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as &$message) {
                $message['is_mine'] = ((int) $message['sender_id']) === $userId;
                $message['is_read'] = (bool) $message['is_read'];
                $message['is_urgent'] = (bool) $message['is_urgent'];
            }

            return $messages;
        } catch (PDOException $exception) {
            error_log('Erro ao obter conversa: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * Marca uma mensagem como lida pelo destinatário.
     *
     * @param int $messageId ID da mensagem.
     * @param int $userId    ID do usuário que está marcando como lida.
     *
     * @return bool Verdadeiro caso a atualização seja bem-sucedida.
     */
    public function markAsRead(int $messageId, int $userId): bool
    {
        if ($messageId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $checkStmt = $this->pdo->prepare('SELECT recipient_id FROM sdr_vendor_messages WHERE id = :id');
            $checkStmt->bindValue(':id', $messageId, PDO::PARAM_INT);
            $checkStmt->execute();
            $recipientId = $checkStmt->fetchColumn();

            if ((int) $recipientId !== $userId) {
                return false;
            }

            $updateStmt = $this->pdo->prepare('UPDATE sdr_vendor_messages SET is_read = 1 WHERE id = :id');
            $updateStmt->bindValue(':id', $messageId, PDO::PARAM_INT);

            return $updateStmt->execute();
        } catch (PDOException $exception) {
            error_log('Erro ao marcar mensagem como lida: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Marca todas as mensagens de uma conversa como lidas pelo destinatário.
     *
     * @param int $prospeccaoId ID da prospecção.
     * @param int $userId       ID do usuário destinatário.
     *
     * @return int Número de mensagens atualizadas.
     */
    public function markConversationAsRead(int $prospeccaoId, int $userId): int
    {
        if ($prospeccaoId <= 0 || $userId <= 0) {
            return 0;
        }

        $sql = 'UPDATE sdr_vendor_messages
                SET is_read = 1
                WHERE prospeccao_id = :prospeccao_id
                  AND recipient_id = :user_id
                  AND is_read = 0';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            error_log('Erro ao marcar conversa como lida: ' . $exception->getMessage());
            return 0;
        }
    }

    /**
     * Obtém a contagem total de mensagens não lidas para um usuário.
     *
     * @param int $userId ID do usuário.
     *
     * @return int Quantidade de mensagens não lidas.
     */
    public function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM sdr_vendor_messages WHERE recipient_id = :user_id AND is_read = 0';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            error_log('Erro ao obter contagem de mensagens não lidas: ' . $exception->getMessage());
            return 0;
        }
    }

    /**
     * Recupera as conversas mais recentes do usuário.
     *
     * @param int $userId ID do usuário logado.
     * @param int $limit  Número máximo de conversas retornadas.
     *
     * @return array Lista de conversas recentes com metadados.
     */
    public function getRecentConversations(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max($limit, 1);

        $sql = 'SELECT
                    m.prospeccao_id,
                    p.nome_prospecto AS prospeccao_nome,
                    m.message AS last_message,
                    m.created_at AS last_message_time,
                    COALESCE(unread.unread_count, 0) AS unread_count,
                    CASE WHEN m.sender_id = :user_main THEN m.recipient_id ELSE m.sender_id END AS other_user_id,
                    CASE WHEN m.sender_id = :user_main THEN recipient.nome_completo ELSE sender.nome_completo END AS other_user_name
                FROM sdr_vendor_messages m
                INNER JOIN prospeccoes p ON p.id = m.prospeccao_id
                INNER JOIN users sender ON sender.id = m.sender_id
                INNER JOIN users recipient ON recipient.id = m.recipient_id
                LEFT JOIN (
                    SELECT prospeccao_id, COUNT(*) AS unread_count
                    FROM sdr_vendor_messages
                    WHERE recipient_id = :user_unread AND is_read = 0
                    GROUP BY prospeccao_id
                ) unread ON unread.prospeccao_id = m.prospeccao_id
                WHERE (m.sender_id = :user_filter_sender OR m.recipient_id = :user_filter_recipient)
                  AND m.id = (
                        SELECT m2.id
                        FROM sdr_vendor_messages m2
                        WHERE m2.prospeccao_id = m.prospeccao_id
                          AND (m2.sender_id = :user_sub_sender OR m2.recipient_id = :user_sub_recipient)
                        ORDER BY m2.created_at DESC, m2.id DESC
                        LIMIT 1
                    )
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_main', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_unread', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_filter_sender', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_filter_recipient', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_sub_sender', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_sub_recipient', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($conversations as &$conversation) {
                $conversation['unread_count'] = (int) $conversation['unread_count'];
                $conversation['other_user_id'] = (int) $conversation['other_user_id'];
            }

            return $conversations;
        } catch (PDOException $exception) {
            error_log('Erro ao obter conversas recentes: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * Remove uma mensagem enviada pelo usuário, desde que recente.
     *
     * @param int $messageId ID da mensagem.
     * @param int $userId    ID do usuário solicitante.
     *
     * @return bool Verdadeiro em caso de sucesso.
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        if ($messageId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT sender_id, created_at FROM sdr_vendor_messages WHERE id = :id');
            $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
            $stmt->execute();
            $message = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$message || (int) $message['sender_id'] !== $userId) {
                return false;
            }

            $createdAt = new DateTime($message['created_at']);
            $now = new DateTime();
            $interval = $now->getTimestamp() - $createdAt->getTimestamp();

            if ($interval > 300) { // 5 minutos
                return false;
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM sdr_vendor_messages WHERE id = :id');
            $deleteStmt->bindValue(':id', $messageId, PDO::PARAM_INT);

            return $deleteStmt->execute();
        } catch (Exception $exception) {
            error_log('Erro ao deletar mensagem: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Obtém a contagem de mensagens não lidas agrupadas por prospecção.
     *
     * @param int $userId ID do usuário destinatário.
     *
     * @return array Associativo no formato [prospeccao_id => quantidade].
     */
    public function getUnreadByProspeccao(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = 'SELECT prospeccao_id, COUNT(*) AS unread_count
                FROM sdr_vendor_messages
                WHERE recipient_id = :user_id AND is_read = 0
                GROUP BY prospeccao_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $unreadByProspeccao = [];

            foreach ($results as $row) {
                $unreadByProspeccao[(int) $row['prospeccao_id']] = (int) $row['unread_count'];
            }

            return $unreadByProspeccao;
        } catch (PDOException $exception) {
            error_log('Erro ao obter mensagens não lidas por prospecção: ' . $exception->getMessage());
            return [];
        }
    }
}
