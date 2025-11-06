<?php

declare(strict_types=1);

 

class OmieEtapaFaturamento
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO omie_etapas_faturamento (codigo, descricao, ativo)
            VALUES (:codigo, :descricao, :ativo)
            ON DUPLICATE KEY UPDATE
                descricao = VALUES(descricao),
                ativo = VALUES(ativo)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $data['codigo'],
            ':descricao' => $data['descricao'],
            ':ativo' => $data['ativo'],
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_etapas_faturamento ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_etapas_faturamento WHERE ativo = 1 ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_etapas_faturamento WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateById(int $id, array $data): bool
    {
        $sql = 'UPDATE omie_etapas_faturamento SET descricao = :descricao, ativo = :ativo WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':descricao' => $data['descricao'],
            ':ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 0,
            ':id' => $id,
        ]);
    }
}
