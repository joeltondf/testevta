<?php
// /app/controllers/VendedorDashboardController.php

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Prospeccao.php'; 

class VendedorDashboardController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        // 1. Instancia os Models
        $processoModel = new Processo($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);
        $clienteModel = new Cliente($this->pdo);
        $prospeccaoModel = new Prospeccao($this->pdo);

        // 2. Pega os dados do vendedor logado
        $userPerfil = $_SESSION['user_perfil'] ?? '';
        if (in_array($userPerfil, ['admin', 'gerencia', 'supervisor']) && isset($_GET['vendedor_id'])) {
            // Perfis de gestão podem visualizar o painel de outro vendedor passando ?vendedor_id=ID
            $vendedorId = (int) $_GET['vendedor_id'];
            $vendedor   = $vendedorModel->getById($vendedorId);
            if (!$vendedor) { die("Erro: Vendedor não encontrado."); }
            $percentualComissao = $vendedor['percentual_comissao'];
            // Atualiza o userId para puxar estatísticas e agenda do CRM
            $userId = $vendedor['user_id'];
        } else {
            // Comportamento padrão: pega o vendedor logado
            $userId   = $_SESSION['user_id'];
            $vendedor = $vendedorModel->getByUserId($userId);
            if (!$vendedor) { die("Erro: Vendedor não encontrado."); }
            $vendedorId        = $vendedor['id'];
            $percentualComissao = $vendedor['percentual_comissao'];
        }


        // 3. Busca de dados de Processos (Vendas)
        $filters = $this->buildFiltersFromRequest($_GET ?? []);
        $filters['vendedor_id'] = $vendedorId;

        $todosOsProcessosDoVendedor = $processoModel->getFilteredProcesses($filters, 9999, 0);
        $totalVendasMes = $processoModel->getVendasTotalMesByVendedor($vendedorId);

        // 4. Calcula os Cards de Processos

        $stats = [
            'processos_ativos' => 0,
            'finalizados_mes' => 0,
            'processos_atrasados' => 0
        ];
        $valorTotalFinalizado = 0; 
        $mesCorrente = date('m');
        $anoCorrente = date('Y');

        foreach ($todosOsProcessosDoVendedor as $processo) {
            $statusInfo = $this->normalizeStatusData($processo['status_processo'] ?? '');
            $statusNormalized = $statusInfo['normalized'];
            if (in_array($statusNormalized, ['serviço pendente', 'serviço em andamento'], true)) {
                $stats['processos_ativos']++;
            }

            if ($statusNormalized === 'concluído') {
                $valorTotalFinalizado += (float)$processo['valor_total'];
                if (!empty($processo['data_finalizacao_real'])) {
                    $dataFinalizacao = new DateTime($processo['data_finalizacao_real']);
                    if ($dataFinalizacao->format('m') == $mesCorrente && $dataFinalizacao->format('Y') == $anoCorrente) {
                        $stats['finalizados_mes']++;
                    }
                }
            }
            
            $prazo = $processo['traducao_prazo_data'] ?? '';
            if (!empty($prazo) && strtotime($prazo) < time() && !in_array($statusNormalized, ['concluído', 'cancelado'], true)) {
                $stats['processos_atrasados']++;
            }
        }
        
        // 5. Calcula o valor da comissão
        $valorComissao = ($valorTotalFinalizado * $percentualComissao) / 100;

        // 6. Busca de dados e KPIs do CRM
        $crmStats = $prospeccaoModel->getStatsByResponsavel($userId);
        $prospeccoesPorStatus = $prospeccaoModel->getProspeccoesCountByStatus($userId);
        $proximosAgendamentos = $prospeccaoModel->getProximosAgendamentos($userId, 5);
        $nextLead = $prospeccaoModel->getNextLeadForVendor($userId);
        $labels_funil = json_encode(array_keys($prospeccoesPorStatus));
        $valores_funil = json_encode(array_values($prospeccoesPorStatus));
        
        // 7. Define a variável '$processos' para a tabela da view.
        $processos = $todosOsProcessosDoVendedor;

        // 8. Preparação para a View
        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Meu Painel';
        $filtrosAtuais = $this->cleanFilterValues($_GET ?? []);
        $hasFilters = !empty($filtrosAtuais);
        $totalProcessesCount = count($todosOsProcessosDoVendedor);

        $filters = $filtrosAtuais;
        $currentVendedorId = $vendedorId;

        // Carrega a view
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function buildFiltersFromRequest(array $request): array
    {
        $allowedKeys = ['titulo', 'cliente_id', 'os_numero', 'tipo_servico', 'status', 'data_inicio', 'data_fim', 'filtro_card'];
        $filters = [];

        foreach ($allowedKeys as $key) {
            if (!isset($request[$key])) {
                continue;
            }

            $value = is_string($request[$key]) ? trim($request[$key]) : $request[$key];
            if ($value === '' || $value === null) {
                continue;
            }

            if ($key === 'cliente_id') {
                $intValue = (int) $value;
                if ($intValue > 0) {
                    $filters[$key] = $intValue;
                }
                continue;
            }

            $filters[$key] = $value;
        }

        if (!empty($filters['status'])) {
            $statusInfo = $this->normalizeStatusData($filters['status']);
            $filters['status'] = $statusInfo['label'];
        }

        return $filters;
    }

    private function normalizeStatusData(?string $status): array
    {
        $normalized = mb_strtolower(trim((string)$status));

        if ($normalized === '') {
            return ['normalized' => '', 'label' => ''];
        }

        $aliases = [
            'orcamento' => 'orçamento',
            'orcamento pendente' => 'orçamento pendente',
            'serviço pendente' => 'serviço pendente',
            'servico pendente' => 'serviço pendente',
            'pendente' => 'serviço pendente',
            'aprovado' => 'serviço pendente',
            'serviço em andamento' => 'serviço em andamento',
            'servico em andamento' => 'serviço em andamento',
            'em andamento' => 'serviço em andamento',
            'aguardando pagamento' => 'aguardando pagamento',
            'finalizado' => 'concluído',
            'finalizada' => 'concluído',
            'concluido' => 'concluído',
            'concluida' => 'concluído',
            'arquivado' => 'cancelado',
            'arquivada' => 'cancelado',
            'recusado' => 'cancelado',
            'recusada' => 'cancelado',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        $labels = [
            'orçamento' => 'Orçamento',
            'orçamento pendente' => 'Orçamento Pendente',
            'serviço pendente' => 'Serviço Pendente',
            'serviço em andamento' => 'Serviço em Andamento',
            'aguardando pagamento' => 'Aguardando pagamento',
            'concluído' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];

        $label = $labels[$normalized] ?? ($status === '' ? 'N/A' : $status);

        return ['normalized' => $normalized, 'label' => $label];
    }

    private function cleanFilterValues(array $request): array
    {
        $allowedKeys = ['titulo', 'cliente_id', 'os_numero', 'tipo_servico', 'status', 'data_inicio', 'data_fim', 'filtro_card'];
        $result = [];

        foreach ($allowedKeys as $key) {
            if (!isset($request[$key]) || is_array($request[$key])) {
                continue;
            }

            $trimmed = trim((string) $request[$key]);
            if ($trimmed === '') {
                continue;
            }

            if ($key === 'status') {
                $statusInfo = $this->normalizeStatusData($trimmed);
                $result[$key] = $statusInfo['label'];
                continue;
            }

            $result[$key] = $trimmed;
        }

        return $result;
    }
}