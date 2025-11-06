<?php

declare(strict_types=1);



class OmieContaCorrente
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO omie_contas_correntes (
                nCodCC,
                descricao,
                tipo,
                banco,
                numero_agencia,
                numero_conta_corrente,
                ativo
            ) VALUES (
                :nCodCC,
                :descricao,
                :tipo,
                :banco,
                :numero_agencia,
                :numero_conta_corrente,
                :ativo
            )
            ON DUPLICATE KEY UPDATE
                descricao = VALUES(descricao),
                tipo = VALUES(tipo),
                banco = VALUES(banco),
                numero_agencia = VALUES(numero_agencia),
                numero_conta_corrente = VALUES(numero_conta_corrente),
                ativo = VALUES(ativo)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nCodCC' => $data['nCodCC'],
            ':descricao' => $data['descricao'],
            ':tipo' => $data['tipo'],
            ':banco' => $data['banco'],
            ':numero_agencia' => $data['numero_agencia'],
            ':numero_conta_corrente' => $data['numero_conta_corrente'],
            ':ativo' => $data['ativo'],
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_contas_correntes ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_contas_correntes WHERE ativo = 1 ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_contas_correntes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateById(int $id, array $data): bool
    {
        $sql = <<<SQL
            UPDATE omie_contas_correntes
            SET descricao = :descricao,
                tipo = :tipo,
                banco = :banco,
                numero_agencia = :numero_agencia,
                numero_conta_corrente = :numero_conta_corrente,
                ativo = :ativo
            WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':descricao' => $data['descricao'],
            ':tipo' => $data['tipo'] ?? null,
            ':banco' => $data['banco'] ?? null,
            ':numero_agencia' => $data['numero_agencia'] ?? null,
            ':numero_conta_corrente' => $data['numero_conta_corrente'] ?? null,
            ':ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 0,
            ':id' => $id,
        ]);
    }
}
