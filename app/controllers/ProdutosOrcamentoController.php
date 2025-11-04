<?php
// app/controllers/ProdutosOrcamentoController.php

require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../services/OmieSyncService.php';

class ProdutosOrcamentoController {
    private $pdo;
    private $categoriaModel;
    private $configModel;
    private $omieSyncService;
    private const SERVICE_TYPES = ['Nenhum', 'Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros'];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->categoriaModel = new CategoriaFinanceira($pdo);
        $this->configModel = new Configuracao($pdo);
        $this->omieSyncService = null;
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

    private function getOmieSyncService(): OmieSyncService
    {
        if ($this->omieSyncService === null) {
            $this->omieSyncService = new OmieSyncService($this->pdo, $this->configModel);
        }

        return $this->omieSyncService;
    }

    private function normalizeServiceType($value): string
    {
        if (!is_string($value)) {
            return 'Nenhum';
        }

        $candidate = trim($value);
        return in_array($candidate, self::SERVICE_TYPES, true) ? $candidate : 'Nenhum';
    }
    
    protected function render($view, $data = []) {
        extract($data);
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function index() {
        $this->auth_check();
        $produtos = $this->categoriaModel->getProdutosOrcamento(true);
        $this->render('produtos_orcamento/index', ['produtos' => $produtos]);
    }

    public function store() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Valores fixos para produtos de orçamento
            $_POST['tipo_lancamento'] = 'RECEITA';
            $_POST['eh_produto_orcamento'] = 1;
            $_POST['grupo_principal'] = 'Produtos e Serviços'; // Grupo padrão
            $_POST['servico_tipo'] = $this->normalizeServiceType($_POST['servico_tipo'] ?? 'Nenhum');
            $_POST['ativo'] = $_POST['ativo'] ?? 1;
            $_POST['unidade'] = $_POST['unidade'] ?? 'UN';
            $_POST['ncm'] = $_POST['ncm'] ?? '0000.00.00';
            $_POST['cfop'] = $_POST['cfop'] ?? null;
            $_POST['codigo_servico_municipal'] = $_POST['codigo_servico_municipal'] ?? null;

            $novoId = $this->categoriaModel->createProdutoOrcamento($_POST);

            if ($novoId) {
                $sincronizar = !empty($_POST['sincronizar_omie']);

                try {
                    if ($sincronizar) {
                        $resultado = $this->getOmieSyncService()->syncLocalProduct((int)$novoId, $_POST);
                        $codigoOmie = $resultado['codigo_produto'] ?? 'N/D';
                        $_SESSION['success_message'] = 'Produto de Orçamento criado e sincronizado com a Omie (código ' . $codigoOmie . ').';
                    } else {
                        $this->getOmieSyncService()->persistLocalProductMetadata((int)$novoId, $_POST);
                        $_SESSION['success_message'] = 'Produto de Orçamento criado com sucesso!';
                    }
                } catch (Exception $exception) {
                    $_SESSION['warning_message'] = 'Produto criado, mas a sincronização com a Omie falhou: ' . $exception->getMessage();
                }
            } else {
                $_SESSION['error_message'] = 'Erro ao criar o produto.';
            }
            header('Location: produtos_orcamento.php');
            exit();
        }
    }

    public function update($id) {
        $this->auth_check();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            $_SESSION['error_message'] = 'Produto inválido selecionado para edição.';
            header('Location: produtos_orcamento.php');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['servico_tipo'] = $this->normalizeServiceType($_POST['servico_tipo'] ?? 'Nenhum');
            $_POST['ativo'] = $_POST['ativo'] ?? 1;
            $_POST['unidade'] = $_POST['unidade'] ?? 'UN';
            $_POST['ncm'] = $_POST['ncm'] ?? '0000.00.00';
            $_POST['cfop'] = $_POST['cfop'] ?? null;
            $_POST['codigo_servico_municipal'] = $_POST['codigo_servico_municipal'] ?? null;

            $atualizado = $this->categoriaModel->updateProdutoOrcamento($id, $_POST);

            if ($atualizado) {
                $sincronizar = !empty($_POST['sincronizar_omie']);

                try {
                    if ($sincronizar) {
                        $resultado = $this->getOmieSyncService()->syncLocalProduct((int)$id, $_POST);
                        $codigoOmie = $resultado['codigo_produto'] ?? 'N/D';
                        $_SESSION['success_message'] = 'Produto de Orçamento atualizado e sincronizado com a Omie (código ' . $codigoOmie . ').';
                    } else {
                        $this->getOmieSyncService()->persistLocalProductMetadata((int)$id, $_POST);
                        $_SESSION['success_message'] = 'Produto de Orçamento atualizado com sucesso!';
                    }
                } catch (Exception $exception) {
                    $_SESSION['warning_message'] = 'Produto atualizado, mas a sincronização com a Omie falhou: ' . $exception->getMessage();
                }
            } else {
                $_SESSION['error_message'] = 'Erro ao atualizar o produto.';
            }
            header('Location: produtos_orcamento.php');
            exit();
        }
    }

    public function delete($id) {
        $this->auth_check();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            $_SESSION['error_message'] = 'Produto inválido selecionado para exclusão.';
            header('Location: produtos_orcamento.php');
            exit();
        }

        $warningMessages = [];

        try {
            $this->getOmieSyncService()->deleteProduct($id);
        } catch (Exception $exception) {
            $warningMessages[] = 'Falha ao excluir o produto na Omie: ' . $exception->getMessage();
        }

        if ($this->categoriaModel->deleteProdutoOrcamento($id)) {
            $_SESSION['success_message'] = 'Produto de Orçamento excluído com sucesso!';
            if (!empty($warningMessages)) {
                $_SESSION['warning_message'] = implode(' ', $warningMessages);
            }
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir o produto.';
            if (!empty($warningMessages)) {
                $_SESSION['warning_message'] = implode(' ', $warningMessages);
            }
        }
        header('Location: produtos_orcamento.php');
        exit();
    }
}