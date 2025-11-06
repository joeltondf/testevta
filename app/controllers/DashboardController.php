<?php
/**
 * @file /app/controllers/DashboardController.php
 * @description Controller responsável por montar e exibir a página principal do dashboard.
 */

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Tradutor.php';
require_once __DIR__ . '/../models/Vendedor.php';

class DashboardController
{
    private $pdo;
    private $processoModel;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($this->pdo);
    }

    public function index()
    {
        if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'sdr') {
            header('Location: ' . APP_URL . '/sdr_dashboard.php');
            exit();
        }

        // Instancia os models necessários
        $clienteModel = new Cliente($this->pdo);
        $tradutorModel = new Tradutor($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);

        $filters = $_GET ?? [];
        $sortOptions = ['titulo', 'cliente', 'omie', 'dataEntrada', 'dataEnvio'];
        $allowedDirections = ['ASC', 'DESC'];

        if (!empty($filters['sort']) && !in_array($filters['sort'], $sortOptions, true)) {
            unset($filters['sort']);
        }

        if (!empty($filters['direction'])) {
            $direction = strtoupper((string)$filters['direction']);
            if (!in_array($direction, $allowedDirections, true)) {
                unset($filters['direction']);
            } else {
                $filters['direction'] = $direction;
            }
        }

        if (empty($filters['sort']) && isset($filters['direction'])) {
            unset($filters['direction']);
        }

        if (!empty($filters['sort']) && empty($filters['direction'])) {
            $filters['direction'] = 'ASC';
        }
        $defaultStatusApplied = false;
        $defaultStatusLabel = 'Serviço em Andamento';

        $hasCardFilter = !empty($filters['filtro_card'] ?? '');
        $isAjaxRequest = isset($filters['ajax']) && $filters['ajax'] === '1';

        if (!$hasCardFilter && !$isAjaxRequest && !array_key_exists('status', $filters)) {
            $filters['status'] = $defaultStatusLabel;
            $defaultStatusApplied = true;
        }

        // LÓGICA CENTRAL DE FILTRO (APLICADA A TUDO)
        if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'vendedor') {
            $userId = $_SESSION['user_id'];
            $vendedor = $vendedorModel->getByUserId($userId);
            if ($vendedor) {
                $filters['vendedor_id'] = $vendedor['id'];
            } else {
                $filters['vendedor_id'] = 0;
            }
        }

        // BLOCO 1: TRATAMENTO DE REQUISIÇÃO AJAX (PARA O BOTÃO "VER MAIS" DE PROCESSOS)
        if (isset($filters['ajax']) && $filters['ajax'] === '1') {
            $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
            $limit = 50;
            $moreProcesses = $this->processoModel->getFilteredProcesses($filters, $limit, $offset);
            header('Content-Type: application/json');
            echo json_encode($moreProcesses);
            exit;
        }

        // =======================================================================
        // BLOCO 2: CARGA INICIAL DA PÁGINA (REQUISIÇÃO HTML)
        // =======================================================================

        // --- ALTERAÇÃO #1: LÓGICA DE BUSCA DE CLIENTES PARA O DASHBOARD ---
        $clienteSearchTerm = $filters['q_cliente'] ?? '';

        if (!empty($clienteSearchTerm)) {
            $clientes_para_dashboard = $clienteModel->searchAppClients($clienteSearchTerm);
        } else {
            $clientes_para_dashboard = $clienteModel->getAppClients();
        }
        // --- FIM DA ALTERAÇÃO #1 ---

        // 2.2. Busca de dados para os cards de resumo
        $dashboardStats = $this->processoModel->getDashboardStats($filters);

        // 2.3. Busca da lista inicial de processos
        $initialProcessLimit = 50;
        $offset = 0;
        $processos = $this->processoModel->getFilteredProcesses($filters, $initialProcessLimit, $offset);

        // 2.4. Busca do total de processos para a lógica do "Ver Mais"
        $totalProcessesCount = $this->processoModel->getTotalFilteredProcessesCount($filters);

        // 2.5. Busca de dados para seções secundárias
        $orcamentosRecentes = $this->processoModel->getRecentesOrcamentos(5);

        // --- ALTERAÇÃO #2: BUSCAR CLIENTES FINAIS PARA O DROPDOWN DE FILTRO ---
        $clientesParaFiltro = $clienteModel->getAppClients();
        // --- FIM DA ALTERAÇÃO #2 ---

        $tradutoresParaFiltro = $tradutorModel->getAll();

        // 2.7. Preparação de variáveis para a View
        $pageTitle = 'Dashboard';
        $activeFilters = array_filter(
            $filters,
            static function ($value, $key) use ($defaultStatusApplied) {
                if (in_array($key, ['ajax', 'offset', 'limit', 'sort', 'direction', 'filtro_card'], true)) {
                    return false;
                }

                if ($defaultStatusApplied && $key === 'status') {
                    return false;
                }

                return $value !== null && $value !== '';
            },
            ARRAY_FILTER_USE_BOTH
        );
        $hasFilters = !empty($activeFilters);
        $listTitle = 'Todos os Serviços';
        $cardFiltersLabels = [
            'ativos' => 'Serviços em Andamento',
            'pendentes' => 'Serviços Pendentes',
            'orcamentos' => 'Orçamentos Pendentes',
            'finalizados_mes' => 'Concluídos (Mês)',
            'atrasados' => 'Serviços Atrasados',
        ];

        if ($defaultStatusApplied) {
            $listTitle = 'Serviços em Andamento';
        } elseif (!empty($filters['filtro_card']) && isset($cardFiltersLabels[$filters['filtro_card']])) {
            $listTitle = $cardFiltersLabels[$filters['filtro_card']];
        } elseif ($hasFilters) {
            $listTitle = 'Resultados da Busca';
        }

        // 2.8. Renderização da página completa
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
