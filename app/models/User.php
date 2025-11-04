<?php
/**
 * @file /app/models/User.php
 * @description Model para gerir os utilizadores (usuários) do sistema.
 * Responsável por operações de CRUD, autenticação e gestão de senhas.
 */

class User
{
    private $pdo;

    /**
     * Construtor da classe User.
     *
     * @param PDO $pdo Uma instância do objeto PDO para a conexão com a base de dados.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // =======================================================================
    // MÉTODOS CRUD E GESTÃO DE UTILIZADORES
    // =======================================================================

    /**
     * Cria um novo utilizador na base de dados com senha criptografada.
     *
     * @param string $nome_completo O nome completo do utilizador.
     * @param string $email O email do utilizador (usado para login).
     * @param string $senha A senha em texto plano (será hasheada antes de salvar).
     * @param string $perfil O perfil de acesso do utilizador (ex: 'admin', 'vendedor').
     * @return string|false Retorna o ID do novo utilizador em caso de sucesso, ou 'false' em caso de erro.
     */
    public function create(string $nome_completo, string $email, string $senha, string $perfil)
    {
        // Gera o hash da senha para armazenamento seguro.
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (nome_completo, email, senha, perfil) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            if ($stmt->execute([$nome_completo, $email, $senhaHash, $perfil])) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            // Pode ocorrer um erro se o email já for duplicado (se houver uma constraint UNIQUE).
            error_log("Erro ao criar utilizador: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um utilizador pelo seu ID, retornando dados seguros para exibição (sem senha).
     *
     * @param int $id O ID do utilizador.
     * @return array|false Retorna um array com os dados do utilizador ou 'false' se não for encontrado.
     */
    public function getById(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT id, nome_completo, email, perfil, ativo FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca um utilizador pelo seu email. Essencial para o processo de login.
     * Retorna todos os campos, incluindo a senha hasheada para verificação.
     *
     * @param string $email O email do utilizador.
     * @return array|false Retorna um array com todos os dados do utilizador ou 'false' se não for encontrado.
     */
    public function getByEmail(string $email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os utilizadores, retornando dados seguros para listagem (sem senhas).
     *
     * @return array Retorna um array de utilizadores.
     */
    public function getAll()
    {
        // Modificação: Adiciona a cláusula WHERE para excluir perfis 'cliente' e 'vendedor'.
        $sql = "SELECT id, nome_completo, email, perfil, ativo 
                FROM users 
                WHERE perfil NOT IN ('cliente', 'vendedor') 
                ORDER BY nome_completo ASC";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza os dados de um utilizador, exceto a senha.
     *
     * @param int $id O ID do utilizador a ser atualizado.
     * @param array $data Os novos dados (nome_completo, email, perfil, ativo).
     * @return bool Retorna 'true' em caso de sucesso, 'false' em caso de falha.
     */
    public function update(int $id, array $data)
    {
        $sql = "UPDATE users SET nome_completo = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['nome_completo'],
            $data['email'],
            $data['perfil'],
            $data['ativo'] ?? 1, // Por padrão, mantém/define o utilizador como ativo no update.
            $id
        ]);
    }
    
    /**
     * Atualiza apenas a senha de um utilizador específico, gerando um novo hash.
     *
     * @param int $id O ID do utilizador.
     * @param string $senha A nova senha em texto plano.
     * @return bool Retorna 'true' em caso de sucesso, 'false' em caso de falha.
     */
    public function updatePassword(int $id, string $senha)
    {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET senha = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$senhaHash, $id]);
    }

    /**
     * Deleta um utilizador permanentemente da base de dados.
     *
     * @param int $id O ID do utilizador a ser deletado.
     * @return bool Retorna 'true' em caso de sucesso, 'false' em caso de falha.
     */
    public function delete(int $id)
    {
        // Atenção: Esta ação é irreversível.
        // Adicionar verificações de dependência (ex: não deletar se for autor de processos) pode ser necessário.
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Busca os IDs de todos os usuários que pertencem a uma lista de perfis.
     * Essencial para notificar todos os administradores e gerentes.
     *
     * @param array $perfis Array de strings com os perfis (ex: ['admin', 'gerencia'])
     * @return array Array com os IDs dos usuários.
     */
    public function getIdsByPerfil(array $perfis): array
    {
        // Cria os placeholders (?) para a consulta SQL, ex: (?, ?)
        $placeholders = implode(',', array_fill(0, count($perfis), '?'));

        $sql = "SELECT id FROM users WHERE perfil IN ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($perfis);

        // Retorna um array simples de IDs, ex: [1, 5, 12]
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActiveVendors(): array
    {
        $sql = "SELECT id, nome_completo
                FROM users
                WHERE perfil = 'vendedor'
                  AND ativo = 1
                  AND id <> 17
                ORDER BY nome_completo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveVendorById(int $vendorId): ?array
    {
        $sql = "SELECT id, nome_completo
                FROM users
                WHERE id = :id
                  AND perfil = 'vendedor'
                  AND ativo = 1
                  AND id <> 17";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $vendorId, PDO::PARAM_INT);
        $stmt->execute();

        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $vendor !== false ? $vendor : null;
    }

    public function getActiveSdrs(): array
    {
        $sql = "SELECT id, nome_completo
                FROM users
                WHERE perfil = 'sdr' AND (ativo = 1 OR ativo IS NULL)
                ORDER BY nome_completo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveContactsByProfiles(array $profiles): array
    {
        if (empty($profiles)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($profiles), '?'));

        $sql = "SELECT id, nome_completo, email
                FROM users
                WHERE perfil IN ($placeholders)
                  AND (ativo = 1 OR ativo IS NULL)
                  AND email IS NOT NULL
                  AND email <> ''";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($profiles);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
