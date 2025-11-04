<?php
/**
 * @file /app/controllers/VendedoresController.php
 * @description Controller para gerir as requisições da entidade 'Vendedor'.
 * Orquestra as ações de CRUD, que envolvem operações transacionais com o model User.
 */

require_once __DIR__ . '/../models/Vendedor.php';

class VendedoresController
{
    private $vendedorModel;
    private $pdo;

    /**
     * Construtor da classe.
     * Inicia o model de Vendedor.
     *
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->vendedorModel = new Vendedor($this->pdo);
    }

    // =======================================================================
    // AÇÕES CRUD PARA VENDEDORES
    // =======================================================================

    /**
     * Exibe a página com a lista de todos os vendedores.
     */
    public function index()
    {
        $pageTitle = "Gestão de Vendedores";
        $vendedores = $this->vendedorModel->getAll();
        
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedores/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe o formulário de criação de um novo vendedor.
     */
    public function create()
    {
        $pageTitle = "Cadastrar Novo Vendedor";
        $vendedor = null; // Nenhum dado para pré-popular o formulário

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedores/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário e armazena o novo vendedor.
     * A lógica complexa de criar um User e um Vendedor numa transação
     * é encapsulada no VendedorModel.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validação simples para garantir que a senha foi enviada
            if (empty($_POST['senha'])) {
                $_SESSION['error_message'] = "O campo senha é obrigatório para novos vendedores.";
                header('Location: vendedores.php?action=create');
                exit();
            }
            
            if ($this->vendedorModel->create($_POST)) {
                $_SESSION['success_message'] = "Vendedor cadastrado com sucesso!";
            } else {
                // A mensagem de erro específica (ex: email duplicado)
                // já foi definida dentro do model, então apenas redirecionamos.
                header('Location: vendedores.php?action=create');
                exit();
            }
            
            header('Location: vendedores.php');
            exit();
        }
    }

    /**
     * Exibe o formulário de edição para um vendedor existente.
     * @param int $id O ID do vendedor a ser editado.
     */
    public function edit($id)
    {
        $vendedor = $this->vendedorModel->getById($id);
        
        if (!$vendedor) {
            $_SESSION['error_message'] = "Vendedor não encontrado.";
            header('Location: vendedores.php');
            exit();
        }
        
        $pageTitle = "Editar Vendedor: " . htmlspecialchars($vendedor['nome_completo']);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedores/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário de edição e atualiza o vendedor.
     * @param int $id O ID do vendedor a ser atualizado.
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->vendedorModel->update($id, $_POST)) {
                $_SESSION['success_message'] = "Vendedor atualizado com sucesso!";
            } else {
                // A mensagem de erro já foi definida no Model
                $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Ocorreu um erro ao atualizar o vendedor.";
            }

            header('Location: vendedores.php');
            exit();
        }
    }

    /**
     * Deleta um vendedor.
     * @param int $id O ID do vendedor a ser deletado.
     * @note A lógica no model associado pode ter regras específicas sobre
     * a exclusão (ex: manter o User associado).
     */
    public function delete($id)
    {
        if ($this->vendedorModel->delete($id)) {
            $_SESSION['success_message'] = "Vendedor apagado com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao apagar o vendedor.";
        }
        
        header('Location: vendedores.php');
        exit();
    }
}