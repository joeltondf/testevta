<?php
// /app/controllers/VendedorReportController.php

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Comissao.php'; // Model para comissões

class VendedorReportController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        $processoModel = new Processo($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);
        $comissaoModel = new Comissao($this->pdo);

        // Pega o vendedor logado
        $userId = $_SESSION['user_id'];
        $vendedor = $vendedorModel->getByUserId($userId);
        if (!$vendedor) { die("Vendedor não encontrado."); }
        $vendedorId = $vendedor['id'];

        // Filtros de data
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
        $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
        
        $filters = [
            'vendedor_id' => $vendedorId,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim
        ];

        // Busca os dados para o relatório
        $vendasDoPeriodo = $processoModel->getSalesByFilter($filters);
        $comissoes = $comissaoModel->getByVendedor($vendedorId); // Assumindo que este método existe

        // Prepara dados para o gráfico de vendas mensais
        $vendasMensais = [];
        foreach($vendasDoPeriodo as $venda) {
            $mes = date('Y-m', strtotime($venda['data_criacao']));
            if (!isset($vendasMensais[$mes])) {
                $vendasMensais[$mes] = 0;
            }
            $vendasMensais[$mes] += $venda['valor_total'];
        }
        ksort($vendasMensais); // Ordena por data
        
        $labels_vendas = json_encode(array_keys($vendasMensais));
        $valores_vendas = json_encode(array_values($vendasMensais));

        // Carrega a view
        $pageTitle = "Meu Relatório de Desempenho";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedores/relatorio.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}