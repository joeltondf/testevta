<?php
/**
 * @file /app/controllers/AuthController.php
 * @description Controller responsável por todo o fluxo de autenticação:
 * exibir formulário, processar login e fazer logout.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../config.php';

class AuthController
{
    private $userModel;

    /**
     * Construtor da classe.
     * Inicia o model de User para ser usado pelos outros métodos.
     *
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        // CORREÇÃO 1: O construtor agora APENAS inicializa o userModel.
        $this->userModel = new User($pdo);
    }

    /**
     * Exibe o formulário de login.
     */
    public function showLoginForm()
    {
        require_once __DIR__ . '/../views/auth/login_form.php';
    }

    /**
     * Processa a tentativa de login do utilizador.
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'];
            $senha = $_POST['senha'];

            // CORREÇÃO 2: Renomeado de findByEmail para getByEmail.
            // Agora $this->userModel existe e a chamada do método está correta.
            $user = $this->userModel->getByEmail($email);

            if ($user && password_verify($senha, $user['senha'])) {
                // Inicia a sessão se não estiver iniciada
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome_completo'];
                $_SESSION['user_perfil'] = $user['perfil'];

                // ===== INÍCIO DA ALTERAÇÃO =====
                // Lógica de redirecionamento por perfil
                if ($user['perfil'] === 'vendedor') {
                    header('Location: ' . APP_URL . '/dashboard_vendedor.php');
                } elseif ($user['perfil'] === 'sdr') {
                    header('Location: ' . APP_URL . '/sdr_dashboard.php');
                } elseif ($user['perfil'] === 'cliente') {
                    header('Location: ' . APP_URL . '/portal_cliente.php');
                } else {
                    header('Location: ' . APP_URL . '/dashboard.php');
                }
                exit();
                
            } else {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['error_message'] = 'Email ou senha inválidos.';
                header('Location: ' . APP_URL . '/login.php');
                exit();
            }
        }
    }

    /**
     * Processa o logout do utilizador.
     */
    public function logout()
    {
        // Garante que a sessão está ativa antes de manipulá-la
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        session_destroy();

        // Inicia uma nova sessão para passar a mensagem de sucesso
        session_start();
        $_SESSION['success_message'] = 'Você saiu com sucesso.';
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}
