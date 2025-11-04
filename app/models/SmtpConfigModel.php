<?php
// app/models/SmtpConfigModel.php

class SmtpConfigModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtém as configurações de SMTP do banco de dados.
     * Sempre busca a configuração com id = 1.
     * @return array|null As configurações como um array associativo.
     */
    public function getSmtpConfig()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM smtp_config WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Salva as configurações de SMTP no banco de dados.
     * Sempre atualiza a configuração com id = 1.
     * @param array $data Um array com os dados a serem salvos.
     * @return bool True em caso de sucesso, false em caso de falha.
     */
    public function saveSmtpConfig($data)
    {
        // Lógica especial para a senha: não atualizar se o campo vier vazio
        $password = $this->getSmtpConfig()['smtp_pass'];
        if (!empty($data['smtp_pass'])) {
            // AQUI é o local ideal para criptografar a senha antes de salvar!
            // Ex: $password = encrypt_password($data['smtp_pass']);
            $password = $data['smtp_pass']; 
        }

        $stmt = $this->pdo->prepare("
            UPDATE smtp_config SET
                smtp_host = :smtp_host,
                smtp_port = :smtp_port,
                smtp_user = :smtp_user,
                smtp_pass = :smtp_pass,
                smtp_security = :smtp_security,
                smtp_from_email = :smtp_from_email,
                smtp_from_name = :smtp_from_name
            WHERE id = 1
        ");

        return $stmt->execute([
            ':smtp_host'       => $data['smtp_host'],
            ':smtp_port'       => $data['smtp_port'],
            ':smtp_user'       => $data['smtp_user'],
            ':smtp_pass'       => $password,
            ':smtp_security'   => $data['smtp_security'],
            ':smtp_from_email' => $data['smtp_from_email'],
            ':smtp_from_name'  => $data['smtp_from_name']
        ]);
    }
}