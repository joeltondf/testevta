<?php

declare(strict_types=1);

class OmieProduto
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $descricao = $this->normalizeString($data['descricao'] ?? '');
        if ($descricao === null) {
            throw new InvalidArgumentException('A descrição do produto é obrigatória para o cadastro na tabela omie_produtos.');
        }

        $hasLocalProductId = array_key_exists('local_produto_id', $data);
        $hasValorUnitario = array_key_exists('valor_unitario', $data);
        $hasAtivo = array_key_exists('ativo', $data);

        $localProductId = $hasLocalProductId
            ? $this->normalizeLocalProductId($data['local_produto_id'])
            : null;
        $codigo = $this->normalizeProductCode($data['codigo'] ?? null);
        $codigoProduto = $this->normalizeString($data['codigo_produto'] ?? null);
        $codigoIntegracao = $this->normalizeString($data['codigo_integracao'] ?? null);
        $cfop = $this->normalizeString($data['cfop'] ?? null);
        $codigoServicoMunicipal = $this->normalizeMunicipalCode($data['codigo_servico_municipal'] ?? null);
        $ncm = $this->normalizeNcm($data['ncm'] ?? null);
        $unidade = $this->normalizeString($data['unidade'] ?? null);
        $valorUnitario = $hasValorUnitario
            ? $this->normalizeDecimal($data['valor_unitario'])
            : null;
        $ativo = $hasAtivo ? (int)$data['ativo'] : null;

        $existing = null;
        if ($localProductId !== null) {
            $existing = $this->findByLocalProductId($localProductId);
        }

        if ($existing === null) {
            $existing = $this->resolveExistingProduct($localProductId, $codigoProduto, $codigoIntegracao, $codigo);
        }

        $persistenceData = $this->buildPersistencePayload([
            'descricao' => $descricao,
            'codigo' => $codigo,
            'codigo_produto' => $codigoProduto,
            'codigo_integracao' => $codigoIntegracao,
            'cfop' => $cfop,
            'codigo_servico_municipal' => $codigoServicoMunicipal,
            'ncm' => $ncm,
            'unidade' => $unidade,
            'valor_unitario' => $valorUnitario,
            'local_produto_id' => $localProductId,
            'ativo' => $ativo,
        ], $existing);

        if ($localProductId !== null) {
            $this->updateOrCreate(
                ['local_produto_id' => $localProductId],
                $persistenceData,
                $existing
            );

            return;
        }

        $this->persistRecord($persistenceData, $existing ? (int)$existing['id'] : null);
    }

    public function upsertFromOmieResponse(int $categoriaId, object $omieResponse): void
    {
        $this->upsert([
            'local_produto_id' => $categoriaId,
            'descricao' => $omieResponse->descricao ?? '',
            'codigo_produto' => $omieResponse->codigo ?? null,
            'codigo_integracao' => $omieResponse->codigoIntegracao ?? null,
            'unidade' => 'UN',
            'ncm' => $omieResponse->ncm ?? '00000000',
            'valor_unitario' => $omieResponse->valorUnitario ?? 0,
            'ativo' => true,
        ]);
    }

    public function findByLocalProductId(int $localProductId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE local_produto_id = :id LIMIT 1');
        $stmt->execute([':id' => $localProductId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deactivateByLocalProductId(int $localProductId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE omie_produtos SET ativo = 0 WHERE local_produto_id = :id');
        $stmt->execute([':id' => $localProductId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByLocalProductId(int $localProductId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM omie_produtos WHERE local_produto_id = :id');
        $stmt->execute([':id' => $localProductId]);

        return $stmt->rowCount() > 0;
    }

    public function findByCodigoProduto(string $codigoProduto): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo_produto = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigoProduto]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCodigoIntegracao(string $codigoIntegracao): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo_integracao = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigoIntegracao]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_produtos ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateById(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $descricao = $this->normalizeString($data['descricao'] ?? ($existing['descricao'] ?? ''));
        if ($descricao === null) {
            throw new InvalidArgumentException('A descrição do produto é obrigatória para o cadastro na tabela omie_produtos.');
        }

        $payload = $this->buildPersistencePayload([
            'descricao' => $descricao,
            'codigo' => $this->normalizeProductCode($data['codigo'] ?? null),
            'codigo_produto' => $this->normalizeString($data['codigo_produto'] ?? null),
            'codigo_integracao' => $this->normalizeString($data['codigo_integracao'] ?? null),
            'cfop' => $this->normalizeString($data['cfop'] ?? null),
            'codigo_servico_municipal' => $this->normalizeMunicipalCode($data['codigo_servico_municipal'] ?? null),
            'ncm' => $this->normalizeNcm($data['ncm'] ?? null),
            'unidade' => $this->normalizeString($data['unidade'] ?? null),
            'valor_unitario' => array_key_exists('valor_unitario', $data)
                ? $this->normalizeDecimal($data['valor_unitario'])
                : null,
            'local_produto_id' => $this->normalizeLocalProductId($data['local_produto_id'] ?? $existing['local_produto_id'] ?? null),
            'ativo' => isset($data['ativo']) ? (int)$data['ativo'] : null,
        ], $existing);

        $this->persistRecord($payload, $id);

        return true;
    }

    private function resolveExistingProduct(
        ?int $localProductId,
        ?string $codigoProduto,
        ?string $codigoIntegracao,
        ?string $codigo
    ): ?array {
        if ($localProductId !== null) {
            $record = $this->findByLocalProductId($localProductId);
            if ($record) {
                return $record;
            }
        }

        if ($codigoProduto !== null) {
            $record = $this->findByCodigoProduto($codigoProduto);
            if ($record) {
                return $record;
            }
        }

        if ($codigoIntegracao !== null) {
            $record = $this->findByCodigoIntegracao($codigoIntegracao);
            if ($record) {
                return $record;
            }
        }

        if ($codigo !== null) {
            return $this->findByCodigo($codigo);
        }

        return null;
    }

    private function normalizeMunicipalCode($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 10);
    }

    private function normalizeNcm($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_float($value)) {
            $value = (string)(int)$value;
        } elseif (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, 8);
    }

    private function normalizeProductCode($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 50);
    }

    private function normalizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float)$value, 4, '.', '');
        }

        $normalized = str_replace(['R$', ' '], '', (string)$value);
        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (strpos($normalized, ',') !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? number_format((float)$normalized, 4, '.', '') : null;
    }

    private function normalizeLocalProductId($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $id = $value;
        } elseif (is_numeric($value)) {
            $id = (int)$value;
        } else {
            return null;
        }

        return $id > 0 ? $id : null;
    }

    private function buildPersistencePayload(array $normalizedData, ?array $existing): array
    {
        $descricao = $normalizedData['descricao'] ?? null;
        if ($descricao === null) {
            throw new InvalidArgumentException('A descrição do produto é obrigatória para o cadastro na tabela omie_produtos.');
        }

        $payload = [
            'descricao' => $descricao,
            'codigo' => $normalizedData['codigo'] ?? null,
            'codigo_produto' => $normalizedData['codigo_produto'] ?? null,
            'codigo_integracao' => $normalizedData['codigo_integracao'] ?? null,
            'cfop' => $normalizedData['cfop'] ?? null,
            'codigo_servico_municipal' => $normalizedData['codigo_servico_municipal'] ?? null,
            'ncm' => $normalizedData['ncm'] ?? null,
            'unidade' => $normalizedData['unidade'] ?? null,
            'valor_unitario' => $normalizedData['valor_unitario'] ?? null,
            'local_produto_id' => $normalizedData['local_produto_id'] ?? null,
            'ativo' => $normalizedData['ativo'],
        ];

        if ($existing !== null) {
            $payload['local_produto_id'] = $payload['local_produto_id']
                ?? $this->normalizeLocalProductId($existing['local_produto_id'] ?? null);
            $payload['codigo'] = $payload['codigo'] ?? $this->normalizeProductCode($existing['codigo'] ?? null);
            $payload['codigo_produto'] = $payload['codigo_produto']
                ?? $this->normalizeString($existing['codigo_produto'] ?? null);
            $payload['codigo_integracao'] = $payload['codigo_integracao']
                ?? $this->normalizeString($existing['codigo_integracao'] ?? null);
            $payload['cfop'] = $payload['cfop'] ?? $this->normalizeString($existing['cfop'] ?? null);
            $payload['codigo_servico_municipal'] = $payload['codigo_servico_municipal']
                ?? $this->normalizeMunicipalCode($existing['codigo_servico_municipal'] ?? null);
            $payload['ncm'] = $payload['ncm'] ?? $this->normalizeNcm($existing['ncm'] ?? null);
            $payload['unidade'] = $payload['unidade'] ?? $this->normalizeString($existing['unidade'] ?? null);

            if ($payload['valor_unitario'] === null && array_key_exists('valor_unitario', $existing)) {
                $payload['valor_unitario'] = $this->normalizeDecimal($existing['valor_unitario']);
            }

            if ($payload['ativo'] === null && array_key_exists('ativo', $existing)) {
                $payload['ativo'] = (int)$existing['ativo'];
            }
        }

        if ($payload['ativo'] === null) {
            $payload['ativo'] = 1;
        }

        return $payload;
    }

    private function updateOrCreate(array $criteria, array $values, ?array $existing): void
    {
        if (!array_key_exists('local_produto_id', $criteria)) {
            throw new InvalidArgumentException('O campo local_produto_id é obrigatório para updateOrCreate.');
        }

        $localProductId = $this->normalizeLocalProductId($criteria['local_produto_id']);
        if ($localProductId === null) {
            throw new InvalidArgumentException('O campo local_produto_id é obrigatório para updateOrCreate.');
        }

        $currentRecord = $existing;
        if ($currentRecord === null || (int)($currentRecord['local_produto_id'] ?? 0) !== $localProductId) {
            $currentRecord = $this->findByLocalProductId($localProductId);
        }

        $values['local_produto_id'] = $localProductId;

        $this->persistRecord($values, $currentRecord ? (int)$currentRecord['id'] : null);
    }

    private function persistRecord(array $values, ?int $id): void
    {
        $sql = $id === null
            ? <<<SQL
                INSERT INTO omie_produtos (
                    descricao,
                    codigo,
                    codigo_produto,
                    codigo_integracao,
                    cfop,
                    codigo_servico_municipal,
                    ncm,
                    unidade,
                    valor_unitario,
                    local_produto_id,
                    ativo
                ) VALUES (
                    :descricao,
                    :codigo,
                    :codigo_produto,
                    :codigo_integracao,
                    :cfop,
                    :codigo_servico_municipal,
                    :ncm,
                    :unidade,
                    :valor_unitario,
                    :local_produto_id,
                    :ativo
                )
            SQL
            : <<<SQL
                UPDATE omie_produtos
                SET descricao = :descricao,
                    codigo = :codigo,
                    codigo_produto = :codigo_produto,
                    codigo_integracao = :codigo_integracao,
                    cfop = :cfop,
                    codigo_servico_municipal = :codigo_servico_municipal,
                    ncm = :ncm,
                    unidade = :unidade,
                    valor_unitario = :valor_unitario,
                    local_produto_id = :local_produto_id,
                    ativo = :ativo
                WHERE id = :id
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':descricao' => $values['descricao'],
            ':codigo' => $values['codigo'],
            ':codigo_produto' => $values['codigo_produto'],
            ':codigo_integracao' => $values['codigo_integracao'],
            ':cfop' => $values['cfop'],
            ':codigo_servico_municipal' => $values['codigo_servico_municipal'],
            ':ncm' => $values['ncm'],
            ':unidade' => $values['unidade'],
            ':valor_unitario' => $values['valor_unitario'],
            ':local_produto_id' => $values['local_produto_id'],
            ':ativo' => $values['ativo'],
        ];

        if ($id !== null) {
            $params[':id'] = $id;
        }

        $stmt->execute($params);
    }
}
