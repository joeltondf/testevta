<?php
/**
 * @file /app/controllers/TradutoresController.php
 * @description Controller para gerir as requisições da entidade 'Tradutor'.
 * Orquestra as ações de listar, criar, editar e desativar tradutores.
 */

require_once __DIR__ . '/../models/Tradutor.php';

class TradutoresController
{
    private $tradutorModel;

    /**
     * Construtor da classe.
     * Inicia o model de Tradutor.
     *
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        $this->tradutorModel = new Tradutor($pdo);
    }

    // =======================================================================
    // AÇÕES CRUD PARA TRADUTORES
    // =======================================================================

    /**
     * Exibe a página com a lista de todos os tradutores ativos.
     */
    public function index()
    {
        $pageTitle = "Gestão de Tradutores";
        $tradutores = $this->tradutorModel->getAll();
        
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tradutores/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe o formulário de criação de um novo tradutor.
     */
    public function create()
    {
        $pageTitle = "Cadastrar Novo Tradutor";
        $tradutor = null; // Nenhuma informação de tradutor para pré-popular o form

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tradutores/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário e armazena o novo tradutor.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            // Garante que o valor do 'ativo' seja 1 (se marcado) ou 0 (se não marcado)
            $data['ativo'] = isset($data['ativo']) ? 1 : 0;
            
            $this->tradutorModel->create($data);
            
            $_SESSION['success_message'] = "Tradutor cadastrado com sucesso!";
            header('Location: tradutores.php');
            exit();
        }
    }

    /**
     * Exibe o formulário de edição para um tradutor existente.
     * @param int $id O ID do tradutor a ser editado.
     */
    public function edit($id)
    {
        // CORREÇÃO: Padronizado para getById, conforme o Model organizado.
        $tradutor = $this->tradutorModel->getById($id);
        
        if (!$tradutor) {
            $_SESSION['error_message'] = "Tradutor não encontrado.";
            header('Location: tradutores.php');
            exit();
        }
        
        $pageTitle = "Editar Tradutor";

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tradutores/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa os dados do formulário de edição e atualiza o tradutor.
     * @param int $id O ID do tradutor a ser atualizado.
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $data['ativo'] = isset($data['ativo']) ? 1 : 0;

            $this->tradutorModel->update($id, $data);

            $_SESSION['success_message'] = "Tradutor atualizado com sucesso!";
            header('Location: tradutores.php');
            exit();
        }
    }

    /**
     * Desativa um tradutor (soft delete).
     * @param int $id O ID do tradutor a ser desativado.
     */
    public function delete($id)
    {
        $this->tradutorModel->delete($id);
        $_SESSION['success_message'] = "Tradutor desativado com sucesso!";
        header('Location: tradutores.php');
        exit();
    }
}