<?php
/**
 * @file /app/models/Tradutor.php
 * @description Model responsável pela gestão dos dados dos tradutores.
 * Realiza as operações de Criar, Ler, Atualizar e Deletar (CRUD).
 */

class Tradutor
{
    private $pdo;

    /**
     * Construtor da classe Tradutor.
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
     * Cria um novo tradutor na base de dados.
     *
     * @param array $data Dados do tradutor (nome, email, telefone, especialidade, ativo).
     * @return bool Retorna 'true' em caso de sucesso na execução, 'false' em caso de falha.
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO tradutores (nome_tradutor, email, telefone, especialidade_idioma, ativo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['nome_tradutor'],
            $data['email'] ?? null,
            $data['telefone'] ?? null,
            $data['especialidade_idioma'] ?? null,
            isset($data['ativo']) ? 1 : 0
        ]);
    }

    /**
     * Busca um único tradutor pelo seu ID.
     *
     * @param int $id O ID do tradutor a ser buscado.
     * @return array|false Retorna um array associativo com os dados do tradutor ou 'false' se não for encontrado.
     */
    public function getById(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tradutores WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os tradutores ATIVOS, ordenados por nome.
     *
     * @return array Retorna um array de tradutores ativos.
     */
    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM tradutores WHERE ativo = TRUE ORDER BY nome_tradutor ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza um tradutor existente na base de dados.
     *
     * @param int $id O ID do tradutor a ser atualizado.
     * @param array $data Os novos dados do tradutor.
     * @return bool Retorna 'true' em caso de sucesso na execução, 'false' em caso de falha.
     */
    public function update(int $id, array $data)
    {
        $sql = "UPDATE tradutores SET nome_tradutor = ?, email = ?, telefone = ?, especialidade_idioma = ?, ativo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['nome_tradutor'],
            $data['email'] ?? null,
            $data['telefone'] ?? null,
            $data['especialidade_idioma'] ?? null,
            isset($data['ativo']) ? 1 : 0,
            $id
        ]);
    }

    /**
     * Realiza um "soft delete" de um tradutor, alterando seu status para inativo.
     * O registo não é removido fisicamente da base de dados.
     *
     * @param int $id O ID do tradutor a ser desativado.
     * @return bool Retorna 'true' em caso de sucesso na execução, 'false' em caso de falha.
     */
    public function delete(int $id)
    {
        $sql = "UPDATE tradutores SET ativo = FALSE WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
}