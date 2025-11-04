<?php
// Carrega os Models necessários para o relatório
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../core/access_control.php';

class VendasController {
    private $pdo;
    private $processoModel;
    private $vendedorModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($this->pdo);
        $this->vendedorModel = new Vendedor($this->pdo);
    }

    /**
     * Exibe o painel de relatório de vendas com filtros e estatísticas.
     */
    public function index() {
        require_permission(['admin', 'gerencia']);

        // Pega os filtros da URL (se houver)
        $filtros = [
            'vendedor_id' => $_GET['vendedor_id'] ?? null,
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null
        ];

        // Busca todos os processos que têm um vendedor e um valor (considerados 'vendas')
        // Passa os filtros para o model
        $processosFiltrados = $this->processoModel->getSalesByFilter($filtros);

        // --- CÁLCULO PARA OS CARDS ---
        $stats = [
            'valor_total_vendido' => 0,
            'total_documentos' => 0,
            'ranking_vendedores' => []
        ];

        $vendasPorVendedor = [];

        foreach ($processosFiltrados as $proc) {
            $stats['valor_total_vendido'] += $proc['valor_total'];
            $stats['total_documentos'] += $proc['total_documentos']; // Assumindo que o model retorna isso

            $vendedorNome = $proc['nome_vendedor'];
            if (!isset($vendasPorVendedor[$vendedorNome])) {
                $vendasPorVendedor[$vendedorNome] = 0;
            }
            $vendasPorVendedor[$vendedorNome] += $proc['valor_total'];
        }

        // Ordena o ranking de vendedores
        arsort($vendasPorVendedor);
        $stats['ranking_vendedores'] = $vendasPorVendedor;
        
        // Busca a lista de vendedores para popular o filtro
        $vendedores = $this->vendedorModel->getAll();

        // Carrega a view do relatório
        $pageTitle = "Relatório de Vendas";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendas/index.php'; // A view será atualizada no próximo passo
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    public function converter_para_servico() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $venda_id = $_POST['venda_id'];
        $this->vendaModel->atualizarStatus($venda_id, 'Serviço');

        $venda = $this->vendaModel->getVendaById($venda_id);
        $itens = $this->vendaModel->getItensVenda($venda_id);
        
        // Dados para o novo processo
        $dadosProcesso = [
            'cliente_id' => $venda['cliente_id'],
            'descricao' => 'Processo originado do orçamento #' . $venda_id,
            'status' => 'Não Iniciado',
            'valor_total' => $venda['valor_total'],
            'venda_origem_id' => $venda_id
        ];
        
        $processo_id = $this->processoModel->criar($dadosProcesso);

        // Lógica para Lançamentos Financeiros
        $lancamentoFinanceiroModel = new LancamentoFinanceiro($this->pdo);
        $categoriaModel = new CategoriaFinanceira($this->pdo);
        
        $produtosAgregados = ['Tradução' => 0, 'CRC' => 0, 'Outros' => 0];
        $idsProdutosAgregados = ['Tradução' => [], 'CRC' => [], 'Outros' => []];

        foreach ($itens as $item) {
            $categoria = $categoriaModel->getById($item['categoria_id']);
            
            // Verifica se é um 'Produto de Orçamento'
            if ($categoria && $categoria['eh_produto_orcamento'] == 1) {
                if ($categoria['servico_tipo'] === 'Tradução') {
                    $produtosAgregados['Tradução'] += $item['valor'];
                    $idsProdutosAgregados['Tradução'][] = $item['id'];
                } elseif ($categoria['servico_tipo'] === 'CRC') {
                    $produtosAgregados['CRC'] += $item['valor'];
                    $idsProdutosAgregados['CRC'][] = $item['id'];
                } elseif ($categoria['servico_tipo'] === 'Outros') {
                    $produtosAgregados['Outros'] += $item['valor'];
                    $idsProdutosAgregados['Outros'][] = $item['id'];
                }

                if (!array_key_exists($serviceType, $produtosAgregados)) {
                    $produtosAgregados[$serviceType] = 0.0;
                    $idsProdutosAgregados[$serviceType] = [];
                }

                $produtosAgregados[$serviceType] += (float)$item['valor'];
                $idsProdutosAgregados[$serviceType][] = $item['id'];
            } else {
                // Comportamento antigo: cria lançamento individual
                $dadosLancamento = [
                    'descricao' => $item['descricao'],
                    'valor' => $item['valor'],
                    'data_vencimento' => date('Y-m-d'), 
                    'tipo' => 'RECEITA',
                    'categoria_id' => $item['categoria_id'],
                    'cliente_id' => $venda['cliente_id'],
                    'processo_id' => $processo_id,
                    'status' => 'Pendente'
                ];
                $lancamentoFinanceiroModel->create($dadosLancamento);
            }
        }

        // Criar lançamentos agregados, se houver
        foreach ($produtosAgregados as $tipoServico => $valorTotal) {
            if ($valorTotal <= 0) {
                continue;
            }

            // Encontrar uma categoria correspondente para o lançamento agregado
            $categoriaAgregada = $categoriaModel->findByServiceType($tipoServico);

            $dadosLancamentoAgregado = [
                'descricao' => $tipoServico . ' — Orçamento #' . $venda_id,
                'valor' => number_format((float)$valorTotal, 2, '.', ''),
                'data_vencimento' => date('Y-m-d'),
                'tipo' => 'RECEITA',
                'categoria_id' => $categoriaAgregada ? $categoriaAgregada['id'] : null, // Usa a categoria encontrada
                'cliente_id' => $venda['cliente_id'],
                'processo_id' => $processo_id,
                'status' => 'Pendente',
                'eh_agregado' => 1, // Novo campo para identificar
                'itens_agregados_ids' => json_encode($idsProdutosAgregados[$tipoServico]) // Salva os IDs dos itens originais
            ];
            $lancamentoFinanceiroModel->create($dadosLancamentoAgregado);
        }

        $_SESSION['success_message'] = 'Orçamento convertido para serviço com sucesso!';
        header('Location: /processos/detalhe/' . $processo_id);
        exit();
    }
}
}