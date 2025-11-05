<?php

declare(strict_types=1);


class OmieContaCorrente
{
    private const BASE_COLUMNS = 'id, nCodCC, nCodCC AS codigo, descricao, tipo, banco, numero_agencia, numero_conta_corrente, ativo, created_at, updated_at';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $codigo = $data['codigo'] ?? $data['nCodCC'] ?? null;
        $descricao = $data['descricao'] ?? null;

        $codigo = $codigo === null ? '' : trim((string)$codigo);
        $descricao = $descricao === null ? '' : trim((string)$descricao);
        $ativo = isset($data['ativo']) ? (int)$data['ativo'] : 1;

        if ($codigo === '' || $descricao === '') {
            throw new InvalidArgumentException('Código e descrição são obrigatórios para a conta corrente Omie.');
        }

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
            ':nCodCC' => $codigo,
            ':descricao' => $descricao,
            ':tipo' => $data['tipo'] ?? null,
            ':banco' => $data['banco'] ?? null,
            ':numero_agencia' => $data['numero_agencia'] ?? null,
            ':numero_conta_corrente' => $data['numero_conta_corrente'] ?? null,
            ':ativo' => $ativo,
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT ' . self::BASE_COLUMNS . ' FROM omie_contas_correntes ORDER BY descricao');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function getActiveOrdered(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::BASE_COLUMNS . ' FROM omie_contas_correntes WHERE ativo = 1 ORDER BY descricao'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::BASE_COLUMNS . ' FROM omie_contas_correntes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->normalizeRow($result) : null;
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
            ':descricao' => trim((string)($data['descricao'] ?? '')),
            ':tipo' => $data['tipo'] ?? null,
            ':banco' => $data['banco'] ?? null,
            ':numero_agencia' => $data['numero_agencia'] ?? null,
            ':numero_conta_corrente' => $data['numero_conta_corrente'] ?? null,
            ':ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 0,
            ':id' => $id,
        ]);
    }

    private function normalizeRow(array $row): array
    {
        if (!isset($row['codigo']) && isset($row['nCodCC'])) {
            $row['codigo'] = (string)$row['nCodCC'];
        }

        $row['ativo'] = isset($row['ativo']) ? (int)$row['ativo'] : 0;

        return $row;
    }
}
