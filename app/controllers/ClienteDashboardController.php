<?php
/**
 * @file /app/controllers/ClienteDashboardController.php
 * @description Controller responsável por montar e exibir a página do portal do cliente.
 */

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Cliente.php';

class ClienteDashboardController
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
     * Monta e renderiza a página do dashboard do cliente.
     */
    public function index()
    {
        // Instancia os models necessários
        $clienteModel = new Cliente($this->pdo);
        $processoModel = new Processo($this->pdo);

        // Busca o registro do cliente usando o ID do usuário da sessão
        $cliente = $clienteModel->getByUserId($_SESSION['user_id']);

        if (!$cliente) {
            // Se o usuário 'cliente' não está vinculado a um registro de cliente, exibe um erro.
            die("Erro de segurança: Seu usuário não está associado a nenhuma conta de cliente.");
        }

        // Com o ID do cliente, busca apenas os processos dele
        $processos = $processoModel->getProcessosByClienteId($cliente['id']);

        // Prepara o título da página
        $pageTitle = "Portal do Cliente";

        // Carrega o layout e a view específicos do cliente
        require_once __DIR__ . '/../views/layouts/header_cliente.php';
        require_once __DIR__ . '/../views/portal_cliente/dashboard.php';
        require_once __DIR__ . '/../views/layouts/footer_cliente.php';
    }
}