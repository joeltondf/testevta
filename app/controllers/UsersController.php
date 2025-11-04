<?php
/**
 * @file /app/controllers/UsersController.php
 * @description Controller para gerir as requisições da entidade 'User' (Utilizador).
 * Orquestra as ações de listar, criar, editar e apagar utilizadores.
 */

require_once __DIR__ . '/../models/User.php';

class UsersController
{
    private $userModel;

    /**
     * Construtor da classe.
     * Inicia o model de User.
     *
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        $this->userModel = new User($pdo);
    }

    // =======================================================================
    // AÇÕES CRUD PARA UTILIZADORES
    // =======================================================================

    /**
     * Exibe a página com a lista de todos os utilizadores.
     */
    public function index()
    {
        $pageTitle = "Gestão de Utilizadores";
        $users = $this->userModel->getAll();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe o formulário de criação de um novo utilizador.
     */
    public function create()
    {
        $pageTitle = "Cadastrar Novo Utilizador";
        $user = null; // Nenhum dado para pré-popular o formulário

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário e armazena o novo utilizador.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validação simples para garantir que a senha foi enviada
            if (empty($_POST['senha'])) {
                $_SESSION['error_message'] = "O campo senha é obrigatório para novos utilizadores.";
                header('Location: users.php?action=create');
                exit();
            }

            $perfil = $this->sanitizePerfil($_POST['perfil'] ?? null);

            if ($perfil === null) {
                $_SESSION['error_message'] = "Perfil selecionado é inválido.";
                header('Location: users.php?action=create');
                exit();
            }

            $userId = $this->userModel->create($_POST['nome_completo'], $_POST['email'], $_POST['senha'], $perfil);
            
            if ($userId) {
                $_SESSION['success_message'] = "Utilizador criado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao criar utilizador. O email pode já existir.";
            }

            header('Location: users.php');
            exit();
        }
    }

    /**
     * Exibe o formulário de edição para um utilizador existente.
     * @param int $id O ID do utilizador a ser editado.
     */
    public function edit($id)
    {
        // CORREÇÃO: Padronizado para getById, conforme o Model organizado.
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            $_SESSION['error_message'] = "Utilizador não encontrado.";
            header('Location: users.php');
            exit();
        }
        
        $pageTitle = "Editar Utilizador";

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário de edição e atualiza o utilizador.
     * @param int $id O ID do utilizador a ser atualizado.
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $perfil = $this->sanitizePerfil($data['perfil'] ?? null);
            if ($perfil === null) {
                $_SESSION['error_message'] = "Perfil selecionado é inválido.";
                header('Location: users.php?action=edit&id=' . $id);
                exit();
            }
            $data['perfil'] = $perfil;
            $data['ativo'] = isset($data['ativo']) ? 1 : 0;
            
            $passwordUpdated = false;
            $detailsUpdated = false;

            // --- LÓGICA PARA ATUALIZAR A SENHA ---
            // Verifica se o campo de nova senha foi preenchido.
            if (!empty($data['nova_senha'])) {
                // Verifica se a confirmação da senha corresponde.
                if ($data['nova_senha'] !== $data['confirmar_senha']) {
                    $_SESSION['error_message'] = "Erro: A nova senha e a confirmação não correspondem.";
                    header('Location: users.php?action=edit&id=' . $id);
                    exit();
                }

                // Se tudo estiver correto, atualiza a senha.
                if ($this->userModel->updatePassword($id, $data['nova_senha'])) {
                    $passwordUpdated = true;
                } else {
                    $_SESSION['error_message'] = "Ocorreu um erro ao tentar atualizar a senha.";
                    header('Location: users.php?action=edit&id=' . $id);
                    exit();
                }
            }

            // --- ATUALIZA OS OUTROS DADOS DO UTILIZADOR ---
            if ($this->userModel->update($id, $data)) {
                $detailsUpdated = true;
            }

            // --- MENSAGEM FINAL ---
            if ($detailsUpdated || $passwordUpdated) {
                $_SESSION['success_message'] = "Utilizador atualizado com sucesso!";
            } else {
                // Caso nada tenha sido alterado ou tenha ocorrido um erro no update dos detalhes
                $_SESSION['error_message'] = "Nenhuma alteração foi realizada ou ocorreu um erro ao atualizar os dados.";
            }

            header('Location: users.php');
            exit();
        }
    }


    /**
     * Deleta um utilizador.
     * Inclui uma verificação de segurança para impedir que um utilizador se auto-delete.
     * @param int $id O ID do utilizador a ser deletado.
     */
    public function delete($id)
    {
        // Regra de segurança: impede que o utilizador logado apague a sua própria conta.
        if ($id == $_SESSION['user_id']) {
            $_SESSION['error_message'] = "Não é possível apagar o seu próprio utilizador.";
            header('Location: users.php');
            exit();
        }

        if ($this->userModel->delete($id)) {
            $_SESSION['success_message'] = "Utilizador apagado com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao apagar o utilizador. Verifique se não é um vendedor ou se tem processos associados.";
        }

        header('Location: users.php');
        exit();
    }

    private function sanitizePerfil(?string $perfil): ?string
    {
        $perfil = is_string($perfil) ? trim($perfil) : '';

        if ($perfil === '') {
            return null;
        }

        return in_array($perfil, $this->getAllowedProfiles(), true) ? $perfil : null;
    }

    private function getAllowedProfiles(): array
    {
        return [
            'master',
            'admin',
            'gerencia',
            'supervisor',
            'financeiro',
            'sdr',
            'vendedor',
            'colaborador',
            'cliente'
        ];
    }
}
