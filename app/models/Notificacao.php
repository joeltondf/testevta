<?php
// /app/models/Notificacao.php

class Notificacao
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDropdownNotifications(
        int $userId,
        string $grupoDestino,
        int $limit = 15,
        string $sourceTimezone = 'UTC'
    ): array {
        return $this->fetchAlerts($userId, $grupoDestino, $limit, true, $sourceTimezone);
    }

    public function getAlertFeed(
        int $userId,
        string $grupoDestino,
        int $limit = 15,
        bool $onlyUnread = true,
        string $sourceTimezone = 'UTC'
    ): array {
        return [
            'total' => $this->countAlerts($userId, $grupoDestino, $onlyUnread),
            'notifications' => $this->fetchAlerts($userId, $grupoDestino, $limit, $onlyUnread, $sourceTimezone),
        ];
    }

    /**
     * Cria um novo registro de notificação no banco de dados.
     *
     * @param int $usuario_id ID do usuário que vai RECEBER a notificação.
     * @param int|null $remetente_id ID do usuário que GEROU a ação.
     * @param string $mensagem O texto da notificação.
     * @param string|null $link O link para onde o usuário será levado ao clicar.
     * @return bool
     */
    public function criar(
        int $usuarioId,
        ?int $remetenteId,
        string $mensagem,
        ?string $link,
        string $tipoAlerta,
        int $referenciaId,
        string $grupoDestino
    ): bool {
        if ($usuarioId <= 0 || $referenciaId <= 0) {
            return false;
        }

        $normalizedLink = $link !== null ? trim($link) : null;
        $tipoAlerta = trim($tipoAlerta) !== '' ? trim($tipoAlerta) : 'notificacao_generica';
        $grupoDestino = trim($grupoDestino) !== '' ? trim($grupoDestino) : 'gerencia';

        try {
            $driver = strtolower((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        } catch (PDOException $exception) {
            $driver = 'mysql';
        }

        $isMySql = strpos($driver, 'mysql') !== false;

        if ($isMySql) {
            $sql = <<<SQL
                INSERT INTO notificacoes (
                    usuario_id,
                    remetente_id,
                    mensagem,
                    link,
                    tipo_alerta,
                    referencia_id,
                    grupo_destino,
                    lida,
                    resolvido,
                    data_criacao
                ) VALUES (
                    :usuario_id,
                    :remetente_id,
                    :mensagem,
                    :link,
                    :tipo_alerta,
                    :referencia_id,
                    :grupo_destino,
                    0,
                    0,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    mensagem = VALUES(mensagem),
                    link = VALUES(link),
                    remetente_id = VALUES(remetente_id),
                    lida = 0,
                    resolvido = 0,
                    data_criacao = CURRENT_TIMESTAMP
            SQL;

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $stmt->bindValue(':remetente_id', $remetenteId, $remetenteId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
                $stmt->bindValue(':link', $normalizedLink, $normalizedLink === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
                $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
                $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);

                return $stmt->execute();
            } catch (PDOException $exception) {
                error_log('Erro ao criar notificação: ' . $exception->getMessage());
                return false;
            }
        }

        try {
            $this->pdo->beginTransaction();

            $deleteSql = 'DELETE FROM notificacoes WHERE usuario_id = :usuario_id AND tipo_alerta = :tipo_alerta AND referencia_id = :referencia_id';
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $deleteStmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
            $deleteStmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            $deleteStmt->execute();

            $insertSql = <<<SQL
                INSERT INTO notificacoes (
                    usuario_id,
                    remetente_id,
                    mensagem,
                    link,
                    tipo_alerta,
                    referencia_id,
                    grupo_destino,
                    lida,
                    resolvido,
                    data_criacao
                ) VALUES (
                    :usuario_id,
                    :remetente_id,
                    :mensagem,
                    :link,
                    :tipo_alerta,
                    :referencia_id,
                    :grupo_destino,
                    0,
                    0,
                    CURRENT_TIMESTAMP
                )
            SQL;

            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $insertStmt->bindValue(':remetente_id', $remetenteId, $remetenteId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insertStmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
            $insertStmt->bindValue(':link', $normalizedLink, $normalizedLink === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertStmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
            $insertStmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            $insertStmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);

            $result = $insertStmt->execute();
            $this->pdo->commit();

            return $result;
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            error_log('Erro ao criar notificação (modo compatibilidade): ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Busca as notificações mais recentes para um usuário específico.
     *
     * @param int $usuario_id
     * @param int $limit
     * @return array
     */
    public function getRecentes(
        int $usuarioId,
        string $grupoDestino,
        int $limit = 7,
        string $sourceTimezone = 'UTC'
    ): array {
        return $this->fetchAlerts($usuarioId, $grupoDestino, $limit, true, $sourceTimezone);
    }

    /**
     * Conta quantas notificações não lidas um usuário possui.
     *
     * @param int $usuario_id
     * @return int
     */
    public function countNaoLidas(int $usuarioId, string $grupoDestino): int
    {
        return $this->countAlerts($usuarioId, $grupoDestino, true);
    }

    /**
     * Exclui uma notificação específica pelo seu ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM notificacoes WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erro ao excluir notificação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui notificações com base no link, útil para limpar alertas de processos.
     *
     * @param string $link
     * @return bool
     */
    public function deleteByLink(string $link): bool
    {
        $links = $this->buildLinkVariants($link);
        if (empty($links)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($links), '?'));
        $sql = "UPDATE notificacoes SET resolvido = 1, lida = 1 WHERE link IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($links);
        } catch (PDOException $e) {
            error_log("Erro ao excluir notificação por link: " . $e->getMessage());
            return false;
        }
    }

    public function marcarComoLida(int $notificationId, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notificacoes SET lida = 1 WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function resolverPorReferencia(string $tipoAlerta, int $referenciaId): void
    {
        $tipoAlerta = trim($tipoAlerta);
        if ($tipoAlerta === '' || $referenciaId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE notificacoes SET resolvido = 1, lida = 1 WHERE tipo_alerta = :tipo_alerta AND referencia_id = :referencia_id'
        );
        $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
        $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function resolveGroupForProfile(string $perfil): string
    {
        return trim($perfil) === 'vendedor' ? 'vendedor' : 'gerencia';
    }

    private function countAlerts(int $usuarioId, string $grupoDestino, bool $onlyUnread): int
    {
        $grupoDestino = trim($grupoDestino) !== '' ? trim($grupoDestino) : 'gerencia';

        $conditions = [
            'usuario_id = :usuario_id',
            'grupo_destino = :grupo_destino',
            'resolvido = 0',
        ];

        if ($onlyUnread) {
            $conditions[] = 'lida = 0';
        }

        $sql = 'SELECT COUNT(id) FROM notificacoes WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    private function fetchAlerts(
        int $usuarioId,
        string $grupoDestino,
        int $limit,
        bool $onlyUnread,
        string $sourceTimezone
    ): array {
        $grupoDestino = trim($grupoDestino) !== '' ? trim($grupoDestino) : 'gerencia';
        $limit = max(1, $limit);

        $conditions = [
            'usuario_id = :usuario_id',
            'grupo_destino = :grupo_destino',
            'resolvido = 0',
        ];

        if ($onlyUnread) {
            $conditions[] = 'lida = 0';
        }

        $sql = 'SELECT * FROM notificacoes WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY data_criacao DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $notification) use ($sourceTimezone) {
            $notification['link'] = $this->normalizeNotificationLink($notification['link'] ?? null);

            $convertedDate = $this->convertToTimezone(
                $notification['data_criacao'] ?? null,
                'Y-m-d H:i:s',
                'America/Sao_Paulo',
                $sourceTimezone
            );
            $notification['data_criacao'] = $convertedDate;

            if ($convertedDate === '') {
                $notification['display_date'] = '';
            } else {
                try {
                    $displayDate = new \DateTime($convertedDate, new \DateTimeZone('America/Sao_Paulo'));
                    $notification['display_date'] = $displayDate->format('d/m/Y H:i');
                } catch (\Exception $exception) {
                    $notification['display_date'] = $convertedDate;
                }
            }

            return $notification;
        }, $notifications);
    }

    private function normalizeNotificationLink(?string $link): string
    {
        if ($link === null) {
            return '#';
        }

        $trimmedLink = trim($link);

        if ($trimmedLink === '') {
            return '#';
        }

        if (!defined('APP_URL')) {
            return $this->isAbsoluteUrl($trimmedLink)
                ? $trimmedLink
                : $this->ensureLeadingSlash($trimmedLink);
        }

        $baseUrl = rtrim((string)APP_URL, '/');

        if ($this->isAbsoluteUrl($trimmedLink)) {
            if (strpos($trimmedLink, $baseUrl) === 0) {
                $relative = substr($trimmedLink, strlen($baseUrl));
                return $this->ensureLeadingSlash($relative);
            }

            return $trimmedLink;
        }

        return $this->ensureLeadingSlash($trimmedLink);
    }

    private function convertToTimezone(?string $dateTime, string $format, string $targetTimezone, string $sourceTimezone): string
    {
        if ($dateTime === null || trim($dateTime) === '') {
            return '';
        }

        try {
            $date = new \DateTime($dateTime, new \DateTimeZone($sourceTimezone));
            $date->setTimezone(new \DateTimeZone($targetTimezone));

            return $date->format($format);
        } catch (\Exception $exception) {
            return $dateTime;
        }
    }

    private function buildLinkVariants(string $link): array
    {
        $trimmedLink = trim($link);
        if ($trimmedLink === '') {
            return [];
        }

        $variants = [$trimmedLink];

        $normalizedLink = $this->normalizeNotificationLink($trimmedLink);
        if ($normalizedLink !== '#' && $normalizedLink !== $trimmedLink) {
            $variants[] = $normalizedLink;
        }

        if (!defined('APP_URL')) {
            return $variants;
        }

        $baseUrl = rtrim((string)APP_URL, '/');
        $relativeFromAbsolute = $this->stripBaseUrl($trimmedLink, $baseUrl);

        if ($relativeFromAbsolute !== null) {
            $variants[] = $relativeFromAbsolute;
        } elseif (!$this->isAbsoluteUrl($trimmedLink)) {
            $relativePath = $this->ensureLeadingSlash($trimmedLink);
            $variants[] = $relativePath;
            $variants[] = $baseUrl . $relativePath;
        }

        return array_values(array_unique($variants));
    }

    private function stripBaseUrl(string $link, string $baseUrl): ?string
    {
        if (strpos($link, $baseUrl) !== 0) {
            return null;
        }

        $relative = substr($link, strlen($baseUrl));
        return $this->ensureLeadingSlash($relative);
    }

    private function ensureLeadingSlash(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private function isAbsoluteUrl(string $link): bool
    {
        return (bool)preg_match('/^https?:\/\//i', $link);
    }
}