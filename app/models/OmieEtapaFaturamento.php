<?php

declare(strict_types=1);


class OmieEtapaFaturamento
{
    private const BASE_COLUMNS = 'id, codigo, descricao, ativo, created_at, updated_at';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $codigo = isset($data['codigo']) ? trim((string)$data['codigo']) : '';
        $descricao = isset($data['descricao']) ? trim((string)$data['descricao']) : '';
        $ativo = isset($data['ativo']) ? (int)$data['ativo'] : 1;

        if ($codigo === '' || $descricao === '') {
            throw new InvalidArgumentException('Código e descrição são obrigatórios para a etapa de faturamento.');
        }

        $sql = <<<SQL
            INSERT INTO omie_etapas_faturamento (codigo, descricao, ativo)
            VALUES (:codigo, :descricao, :ativo)
            ON DUPLICATE KEY UPDATE
                descricao = VALUES(descricao),
                ativo = VALUES(ativo)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':descricao' => $descricao,
            ':ativo' => $ativo,
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT ' . self::BASE_COLUMNS . ' FROM omie_etapas_faturamento ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveOrdered(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::BASE_COLUMNS . ' FROM omie_etapas_faturamento WHERE ativo = 1 ORDER BY descricao'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::BASE_COLUMNS . ' FROM omie_etapas_faturamento WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function updateById(int $id, array $data): bool
    {
        $sql = 'UPDATE omie_etapas_faturamento SET descricao = :descricao, ativo = :ativo WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':descricao' => trim((string)($data['descricao'] ?? '')),
            ':ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 0,
            ':id' => $id,
        ]);
    }
}
