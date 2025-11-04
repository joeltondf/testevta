<?php
/**
 * @file /app/models/Configuracao.php
 * @description Model para gerir as configurações gerais da aplicação.
 */

class Configuracao
{
    private $pdo;

    /**
     * Construtor da classe.
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca o valor de uma configuração pela sua chave.
     * @param string $chave A chave da configuração.
     * @return string|null O valor da configuração ou nulo se não encontrada.
     */
    public function get($chave)
    {
        $stmt = $this->pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result !== false ? $result : null;
    }

    /**
     * Define ou atualiza o valor de uma configuração.
     * @param string $chave A chave da configuração.
     * @param string $valor O valor a ser salvo.
     * @return bool True em caso de sucesso, false em caso de falha.
     */
    public function save($chave, $valor)
    {
        // Usa "INSERT ... ON DUPLICATE KEY UPDATE" para criar ou atualizar.
        $stmt = $this->pdo->prepare("
            INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        return $stmt->execute([$chave, $valor, $valor]);
    }

    /**
     * Obtém todas as configurações da base de dados.
     * O nome do método foi ajustado para `getAll()` para ser compatível com o AdminController.
     * @return array Um array associativo no formato ['chave' => 'valor'].
     */
    public function getAll()
    {
        $settings = [];
        $stmt = $this->pdo->query("SELECT chave, valor FROM configuracoes");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['chave']] = $row['valor'];
        }
        return $settings;
    }
    
    /**
     * Alias para o método get() para garantir compatibilidade com código antigo.
     */
    public function getSetting($chave)
    {
        return $this->get($chave);
    }

    /**
     * Alias para o método save() para garantir compatibilidade com código antigo.
     */
    public function saveSetting($chave, $valor)
    {
        return $this->save($chave, $valor);
    }
}