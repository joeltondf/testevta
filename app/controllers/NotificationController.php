<?php
/**
 * NotificationController.php
 *
 * Controller responsável por gerenciar notificações via API JSON.
 */

class NotificationController
{
    private $pdo;

    /**
     * @param PDO $pdo Conexão PDO com o banco de dados.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna a lista de notificações do usuário autenticado com paginação.
     */
    public function getNotifications(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT, ['options' => ['default' => 20, 'min_range' => 1]]);
        $unreadOnly = filter_input(INPUT_GET, 'unread_only', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);

        // Limite máximo para evitar uso excessivo.
        $perPage = min($perPage, 100);
        $offset = ($page - 1) * $perPage;

        try {
            $baseWhere = 'WHERE n.usuario_id = :user_id';
            $params = ['user_id' => $userId];

            if ($unreadOnly === 1) {
                $baseWhere .= ' AND n.lida = 0';
            }

            $countSql = "SELECT COUNT(*) FROM notificacoes n {$baseWhere}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalCount = (int) $countStmt->fetchColumn();

            $sql = "SELECT n.id, n.tipo_alerta, n.mensagem, n.link, n.lida, n.priority, n.data_criacao, 
                           u.nome AS remetente_nome
                    FROM notificacoes n
                    LEFT JOIN users u ON n.remetente_id = u.id
                    {$baseWhere}
                    ORDER BY n.priority DESC, n.data_criacao DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue(':' . $param, $value, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['relative_time'] = $this->formatRelativeTime($row['data_criacao']);
                $notifications[] = $row;
            }

            $totalPages = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 0;
            $unreadCount = $this->getUnreadCountForUser($userId, false);

            $this->respondWithJson([
                'success' => true,
                'notifications' => $notifications,
                'total_count' => $totalCount,
                'unread_count' => $unreadCount,
                'current_page' => $page,
                'total_pages' => $totalPages,
            ]);
        } catch (Throwable $exception) {
            error_log('Erro ao buscar notificações: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao buscar notificações.'], 500);
        }
    }

    /**
     * Marca notificações como lidas.
     */
    public function markAsRead(): void
    {
        if (!$this->isRequestMethod('POST')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $payload = $this->getJsonPayload();
        if ($payload === null) {
            return;
        }

        try {
            if (isset($payload['action']) && $payload['action'] === 'all') {
                $stmt = $this->pdo->prepare('UPDATE notificacoes SET lida = 1, read_at = NOW() WHERE usuario_id = :user_id AND lida = 0');
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $updatedCount = $stmt->rowCount();
            } elseif (isset($payload['notification_id'])) {
                $notificationId = (int) $payload['notification_id'];
                if ($notificationId <= 0) {
                    $this->respondWithJson(['success' => false, 'message' => 'ID de notificação inválido.'], 400);
                }

                $stmt = $this->pdo->prepare('UPDATE notificacoes SET lida = 1, read_at = NOW() WHERE id = :id AND usuario_id = :user_id');
                $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $updatedCount = $stmt->rowCount();
            } else {
                $this->respondWithJson(['success' => false, 'message' => 'Requisição inválida.'], 400);
            }

            $this->clearUnreadCountCache($userId);
            $this->respondWithJson(['success' => true, 'updated_count' => $updatedCount]);
        } catch (Throwable $exception) {
            error_log('Erro ao marcar notificações como lidas: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao atualizar notificações.'], 500);
        }
    }

    /**
     * Retorna a contagem de notificações não lidas utilizando cache.
     */
    public function getUnreadCount(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        try {
            $count = $this->getUnreadCountForUser($userId, true);
            $this->respondWithJson(['success' => true, 'count' => $count]);
        } catch (Throwable $exception) {
            error_log('Erro ao obter contagem de notificações não lidas: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao obter contagem de notificações.'], 500);
        }
    }

    /**
     * Remove uma notificação lida do usuário autenticado.
     */
    public function deleteNotification(): void
    {
        if (!$this->isRequestMethod('DELETE')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $payload = $this->getJsonPayload();
        if ($payload === null) {
            return;
        }

        if (empty($payload['notification_id'])) {
            $this->respondWithJson(['success' => false, 'message' => 'ID da notificação é obrigatório.'], 400);
        }

        $notificationId = (int) $payload['notification_id'];
        if ($notificationId <= 0) {
            $this->respondWithJson(['success' => false, 'message' => 'ID de notificação inválido.'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT lida FROM notificacoes WHERE id = :id AND usuario_id = :user_id');
            $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $this->respondWithJson(['success' => false, 'message' => 'Notificação não encontrada.'], 404);
            }

            if ((int) $notification['lida'] !== 1) {
                $this->respondWithJson([
                    'success' => false,
                    'message' => 'Apenas notificações lidas podem ser deletadas',
                ], 403);
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM notificacoes WHERE id = :id AND usuario_id = :user_id AND lida = 1');
            $deleteStmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
            $deleteStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $deleteStmt->execute();

            $this->clearUnreadCountCache($userId);
            $this->respondWithJson(['success' => true]);
        } catch (Throwable $exception) {
            error_log('Erro ao deletar notificação: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao deletar notificação.'], 500);
        }
    }

    /**
     * Marca uma notificação como resolvida.
     */
    public function markAsResolved(): void
    {
        if (!$this->isRequestMethod('POST')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $payload = $this->getJsonPayload();
        if ($payload === null || empty($payload['notification_id'])) {
            $this->respondWithJson(['success' => false, 'message' => 'ID da notificação é obrigatório.'], 400);
        }

        $notificationId = (int) $payload['notification_id'];
        if ($notificationId <= 0) {
            $this->respondWithJson(['success' => false, 'message' => 'ID de notificação inválido.'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('UPDATE notificacoes SET resolvido = 1, data_resolucao = NOW() WHERE id = :id AND usuario_id = :user_id');
            $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $this->respondWithJson(['success' => false, 'message' => 'Notificação não encontrada.'], 404);
            }

            $this->respondWithJson(['success' => true]);
        } catch (Throwable $exception) {
            error_log('Erro ao marcar notificação como resolvida: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao atualizar notificação.'], 500);
        }
    }

    /**
     * Retorna notificações filtradas por tipo de alerta.
     */
    public function getNotificationsByType(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $tipoAlerta = isset($_GET['tipo_alerta']) ? trim((string) $_GET['tipo_alerta']) : '';
        if ($tipoAlerta === '') {
            $this->respondWithJson(['success' => false, 'message' => 'Parâmetro tipo_alerta é obrigatório.'], 400);
        }

        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1]]);
        $limit = min($limit, 100);

        try {
            $sql = 'SELECT id, tipo_alerta, mensagem, link, lida, priority, data_criacao
                    FROM notificacoes
                    WHERE usuario_id = :user_id AND tipo_alerta = :tipo_alerta
                    ORDER BY data_criacao DESC
                    LIMIT :limit';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['relative_time'] = $this->formatRelativeTime($row['data_criacao']);
                $notifications[] = $row;
            }

            $this->respondWithJson(['success' => true, 'notifications' => $notifications]);
        } catch (Throwable $exception) {
            error_log('Erro ao buscar notificações por tipo: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao buscar notificações.'], 500);
        }
    }

    /**
     * Retorna novas notificações após determinado timestamp.
     */
    public function poll(): void
    {
        if (!$this->isRequestMethod('GET')) {
            $this->respondWithJson(['success' => false, 'message' => 'Método não permitido.'], 405);
        }

        $userId = $this->requireAuthenticatedUser();
        if ($userId === null) {
            return;
        }

        $lastCheckParam = isset($_GET['last_check']) ? trim((string) $_GET['last_check']) : '';
        if ($lastCheckParam === '') {
            $this->respondWithJson(['success' => false, 'message' => 'Parâmetro last_check é obrigatório.'], 400);
        }

        $lastCheck = DateTime::createFromFormat(DateTime::ATOM, $lastCheckParam);
        if (!$lastCheck) {
            $this->respondWithJson(['success' => false, 'message' => 'Formato de data inválido. Use ISO 8601.'], 400);
        }

        try {
            $sql = 'SELECT id, tipo_alerta, mensagem, link, lida, priority, data_criacao
                    FROM notificacoes
                    WHERE usuario_id = :user_id AND data_criacao > :last_check
                    ORDER BY data_criacao DESC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':last_check', $lastCheck->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();

            $newNotifications = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['relative_time'] = $this->formatRelativeTime($row['data_criacao']);
                $newNotifications[] = $row;
            }

            $totalUnread = $this->getUnreadCountForUser($userId, false);

            $this->respondWithJson([
                'success' => true,
                'new_notifications' => $newNotifications,
                'new_count' => count($newNotifications),
                'total_unread' => $totalUnread,
            ]);
        } catch (Throwable $exception) {
            error_log('Erro no polling de notificações: ' . $exception->getMessage());
            $this->respondWithJson(['success' => false, 'message' => 'Erro ao buscar notificações.'], 500);
        }
    }

    /**
     * Formata uma data para uma string relativa no formato "há X tempo".
     */
    private function formatRelativeTime($datetime): string
    {
        try {
            $date = new DateTime($datetime);
        } catch (Exception $exception) {
            return '';
        }

        $now = new DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return 'há ' . $diff->y . ' ' . ($diff->y === 1 ? 'ano' : 'anos');
        }
        if ($diff->m > 0) {
            return 'há ' . $diff->m . ' ' . ($diff->m === 1 ? 'mês' : 'meses');
        }
        if ($diff->d > 0) {
            return 'há ' . $diff->d . ' ' . ($diff->d === 1 ? 'dia' : 'dias');
        }
        if ($diff->h > 0) {
            return 'há ' . $diff->h . ' ' . ($diff->h === 1 ? 'hora' : 'horas');
        }
        if ($diff->i > 0) {
            return 'há ' . $diff->i . ' ' . ($diff->i === 1 ? 'minuto' : 'minutos');
        }

        return 'há poucos segundos';
    }

    /**
     * Obtém a contagem de notificações não lidas para o usuário.
     *
     * @param int  $userId   ID do usuário.
     * @param bool $useCache Define se deve utilizar cache.
     */
    private function getUnreadCountForUser(int $userId, bool $useCache): int
    {
        $cacheTtl = 10;
        $cacheKey = 'notifications_unread_count_' . $userId;

        if ($useCache) {
            if (function_exists('apcu_fetch')) {
                $success = false;
                $cached = apcu_fetch($cacheKey, $success);
                if ($success) {
                    return (int) $cached;
                }
            } elseif (isset($_SESSION['notifications_unread_cache']) && isset($_SESSION['notifications_unread_cache'][$userId])) {
                $cacheData = $_SESSION['notifications_unread_cache'][$userId];
                if (is_array($cacheData) && isset($cacheData['timestamp'], $cacheData['count']) && (time() - $cacheData['timestamp']) <= $cacheTtl) {
                    return (int) $cacheData['count'];
                }
            }
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :user_id AND lida = 0');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        if ($useCache) {
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, $count, $cacheTtl);
            } else {
                if (!isset($_SESSION['notifications_unread_cache']) || !is_array($_SESSION['notifications_unread_cache'])) {
                    $_SESSION['notifications_unread_cache'] = [];
                }
                $_SESSION['notifications_unread_cache'][$userId] = [
                    'count' => $count,
                    'timestamp' => time(),
                ];
            }
        }

        return $count;
    }

    /**
     * Remove o cache de notificações não lidas do usuário.
     */
    private function clearUnreadCountCache(int $userId): void
    {
        $cacheKey = 'notifications_unread_count_' . $userId;
        if (function_exists('apcu_delete')) {
            apcu_delete($cacheKey);
        }

        if (isset($_SESSION['notifications_unread_cache']) && isset($_SESSION['notifications_unread_cache'][$userId])) {
            unset($_SESSION['notifications_unread_cache'][$userId]);
        }
    }

    /**
     * Valida se o método HTTP da requisição corresponde ao esperado.
     */
    private function isRequestMethod(string $expectedMethod): bool
    {
        return $_SERVER['REQUEST_METHOD'] === $expectedMethod;
    }

    /**
     * Garante que o usuário esteja autenticado e retorna seu ID.
     */
    private function requireAuthenticatedUser(): ?int
    {
        if (empty($_SESSION['user_id'])) {
            $this->respondWithJson(['success' => false, 'message' => 'Não autorizado.'], 401);
            return null;
        }

        return (int) $_SESSION['user_id'];
    }

    /**
     * Lê o payload JSON da requisição.
     */
    private function getJsonPayload(): ?array
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->respondWithJson(['success' => false, 'message' => 'JSON inválido.'], 400);
            return null;
        }

        if (!is_array($data)) {
            $this->respondWithJson(['success' => false, 'message' => 'Payload inválido.'], 400);
            return null;
        }

        return $data;
    }

    /**
     * Envia uma resposta JSON e encerra a execução.
     */
    private function respondWithJson(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}
