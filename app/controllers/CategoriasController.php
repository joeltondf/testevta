<?php
// app/controllers/CategoriasController.php

require_once __DIR__ . '/../models/CategoriaFinanceira.php';

class CategoriasController {
    private $pdo;
    private $categoriaModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->categoriaModel = new CategoriaFinanceira($pdo);
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function auth_check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }

    protected function render($view, $data = []) {
        extract($data);
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
        require_once __DIR__ . '/../views/' . $view . '.php';
    }

    public function index() {
        $this->auth_check();
        $show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
        $categorias = $this->categoriaModel->getCategoriasFinanceiras($show_inactive);
        $grupos_principais = $this->categoriaModel->getGruposPrincipais();
        $pageTitle = "Gerenciar Grupos Financeiros";
        
        $this->render('categorias/index', [
            'categorias' => $categorias,
            'grupos_principais' => $grupos_principais,
            'pageTitle' => $pageTitle,
            'show_inactive' => $show_inactive
        ]);
    }

    private function redirectWithMessage(bool $success, string $successMessage, string $errorMessage): void {
        if ($success) {
            $_SESSION['success_message'] = $successMessage;
        } else {
            $_SESSION['error_message'] = $errorMessage;
        }

        header('Location: categorias.php');
        exit();
    }

    public function store() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $success = $this->categoriaModel->create($_POST);
            $this->redirectWithMessage($success, 'Categoria criada com sucesso!', 'Erro ao criar a categoria.');
        }
    }

    public function update($id) {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $success = $this->categoriaModel->update($id, $_POST);
            $this->redirectWithMessage($success, 'Categoria atualizada com sucesso!', 'Erro ao atualizar a categoria.');
        }
    }

    public function save() {
        $this->auth_check();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: categorias.php');
            exit();
        }

        $id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';

        if ($id === '' || !ctype_digit($id)) {
            $this->store();
            return;
        }

        $this->update((int)$id);
    }

    public function renameGroup() {
        $this->auth_check();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            header('Location: categorias.php');
            exit();
        }

        $oldName = $this->sanitizeGroupName($_POST['old_name'] ?? '');
        $newName = $this->sanitizeGroupName($_POST['new_name'] ?? '');

        if ($oldName === '' || $newName === '') {
            $_SESSION['error_message'] = 'Informe o nome atual e o novo nome do grupo.';
            header('Location: categorias.php');
            exit();
        }

        if ($oldName === $newName) {
            $_SESSION['error_message'] = 'O novo nome deve ser diferente do atual.';
            header('Location: categorias.php');
            exit();
        }

        $success = $this->categoriaModel->renameGrupo($oldName, $newName);
        $this->redirectWithMessage($success, 'Grupo renomeado com sucesso!', 'Erro ao renomear o grupo.');
    }

    public function deleteGroup() {
        $this->auth_check();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: categorias.php');
            exit();
        }

        $groupName = $this->sanitizeGroupName($_POST['group_name'] ?? '');

        if ($groupName === '') {
            $_SESSION['error_message'] = 'Informe o grupo que deseja excluir.';
            header('Location: categorias.php');
            exit();
        }

        $success = $this->categoriaModel->deleteGrupo($groupName);
        $this->redirectWithMessage($success, 'Grupo excluído com sucesso!', 'Erro ao excluir o grupo. Verifique se ele não está em uso.');
    }

    private function sanitizeGroupName(string $value): string {
        return trim(strip_tags($value));

    }
    
    // Método para desativar (muda ativo para 0)
    public function deactivate($id) {
        $this->auth_check();
        if ($this->categoriaModel->setActiveStatus($id, 0)) {
            $_SESSION['success_message'] = 'Categoria desativada com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao desativar a categoria.';
        }
        header('Location: categorias.php');
        exit();
    }

    // Método para reativar (muda ativo para 1)
    public function reactivate($id) {
        $this->auth_check();
        if ($this->categoriaModel->setActiveStatus($id, 1)) {
            $_SESSION['success_message'] = 'Categoria ativada com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao ativar a categoria.';
        }
        header('Location: categorias.php');
        exit();
    }
    
    // Método para excluir permanentemente
    public function delete_permanente($id) {
        $this->auth_check();
        if ($this->categoriaModel->delete($id)) {
            $_SESSION['success_message'] = 'Categoria excluída permanentemente!';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir a categoria. Verifique se ela não está em uso.';
        }
        header('Location: categorias.php');
        exit();
    }
}