<?php
/**
 * @file /app/models/Documento.php
 * @description Model para gerir os dados da entidade 'Documento'.
 * Documentos são tipicamente associados a um 'Processo'.
 * Esta classe fornece a estrutura básica de CRUD para os documentos.
 */

class Documento
{
    public const SERVICE_CATEGORIES = ['Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros'];
    private const LEGACY_CATEGORIES = ['N/A'];
    public const DEFAULT_CATEGORY = 'Outros';

    private $pdo;

    private function normalizeDecimalValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float)$value, 2);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(["R$", "\xc2\xa0", ' '], '', $value);
        $normalized = trim((string)preg_replace('/[^0-9,.-]/u', '', $normalized));

        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }

        $hasComma = strpos($normalized, ',') !== false;
        $hasDot = strpos($normalized, '.') !== false;

        if ($hasComma && $hasDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float)$normalized, 2);
    }

    private function prepareDocumentPayload(array $data, bool $forUpdate = false): array
    {
        $category = isset($data['categoria']) ? trim((string)$data['categoria']) : '';
        $category = $category === '' ? 'N/A' : $category;

        $type = isset($data['tipo_documento']) ? trim((string)$data['tipo_documento']) : '';
        if ($type === '') {
            throw new InvalidArgumentException('Tipo do documento é obrigatório.');
        }

        $name = isset($data['nome_documento']) ? trim((string)$data['nome_documento']) : '';
        $name = $name === '' ? null : $name;

        $quantity = isset($data['quantidade']) ? (int)$data['quantidade'] : 1;
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $value = $this->normalizeDecimalValue($data['valor_unitario'] ?? null);
        if ($value === null) {
            throw new InvalidArgumentException('Valor do documento é obrigatório.');
        }

        if (strcasecmp($category, 'Outros') === 0) {
            if ($name === null) {
                throw new InvalidArgumentException('Nome do documento é obrigatório para a categoria Outros.');
            }

            if ($value <= 0) {
                throw new InvalidArgumentException('Valor do documento deve ser maior que zero para a categoria Outros.');
            }
        }

        $payload = [
            'categoria' => $category,
            'tipo_documento' => $type,
            'nome_documento' => $name,
            'quantidade' => $quantity,
            'valor_unitario' => number_format($value, 2, '.', ''),
        ];

        if (!$forUpdate) {
            if (!isset($data['processo_id'])) {
                throw new InvalidArgumentException('processo_id é obrigatório.');
            }

            $payload['processo_id'] = (int)$data['processo_id'];
        }

        return $payload;
    }

    /**
     * Construtor da classe Documento.
     *
     * @param PDO $pdo Uma instância do objeto PDO para a conexão com a base de dados.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // =======================================================================
    // MÉTODOS CRUD (Create, Read, Update, Delete)
    // =======================================================================

    /**
     * Cria um novo documento associado a um processo.
     *
     * @param array $data Dados do documento (ex: processo_id, tipo_documento, quantidade, etc.).
     * @return int|false Retorna o ID do novo documento ou 'false' em caso de erro.
     */
    public function create(array $data)
    {
        try {
            $payload = $this->prepareDocumentPayload($data, false);
            $sql = "INSERT INTO documentos (processo_id, categoria, tipo_documento, nome_documento, quantidade, valor_unitario)
                    VALUES (:processo_id, :categoria, :tipo_documento, :nome_documento, :quantidade, :valor_unitario)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':processo_id' => $payload['processo_id'],
                ':categoria' => $payload['categoria'],
                ':tipo_documento' => $payload['tipo_documento'],
                ':nome_documento' => $payload['nome_documento'],
                ':quantidade' => $payload['quantidade'],
                ':valor_unitario' => $payload['valor_unitario'],
            ]);

            return $this->pdo->lastInsertId();
        } catch (InvalidArgumentException $exception) {
            error_log('Validação de documento falhou: ' . $exception->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Erro ao criar documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca um documento específico pelo seu ID.
     *
     * @param int $id O ID do documento.
     * @return array|false Um array com os dados do documento ou 'false' se não for encontrado.
     */
    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar documento por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todos os documentos de um processo específico.
     *
     * @param int $processoId O ID do processo.
     * @return array Uma lista de documentos associados ao processo. Retorna array vazio em caso de erro.
     */
    public function getByProcessoId($processoId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE processo_id = ? ORDER BY id ASC");
            $stmt->execute([$processoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar documentos por ID do processo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualiza os dados de um documento.
     *
     * @param int $id O ID do documento a ser atualizado.
     * @param array $data Os novos dados do documento.
     * @return bool 'true' em caso de sucesso, 'false' em caso de erro.
     */
    public function update(int $id, array $data)
    {
        try {
            $payload = $this->prepareDocumentPayload($data, true);
            $sql = "UPDATE documentos SET
                        categoria = :categoria,
                        tipo_documento = :tipo_documento,
                        nome_documento = :nome_documento,
                        quantidade = :quantidade,
                        valor_unitario = :valor_unitario 
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                ':id'               => $id,
                ':categoria' => $payload['categoria'],
                ':tipo_documento' => $payload['tipo_documento'],
                ':nome_documento' => $payload['nome_documento'],
                ':quantidade' => $payload['quantidade'],
                ':valor_unitario' => $payload['valor_unitario'],
            ]);
        } catch (InvalidArgumentException $exception) {
            error_log('Validação de documento falhou: ' . $exception->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Erro ao atualizar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deleta um documento do banco de dados.
     *
     * @param int $id O ID do documento a ser deletado.
     * @return bool 'true' em caso de sucesso, 'false' em caso de erro.
     */
    public function delete($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM documentos WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erro ao deletar documento: " . $e->getMessage());
            return false;
        }
    }
    
        

    private function normalizeCategoria($categoria): string
    {
        if (!is_string($categoria)) {
            return self::DEFAULT_CATEGORY;
        }

        $normalized = trim($categoria);

        if ($normalized === '') {
            return self::DEFAULT_CATEGORY;
        }

        if (in_array($normalized, self::SERVICE_CATEGORIES, true)) {
            return $normalized;
        }

        if (in_array($normalized, self::LEGACY_CATEGORIES, true)) {
            return self::DEFAULT_CATEGORY;
        }

        return self::DEFAULT_CATEGORY;
    }
}
