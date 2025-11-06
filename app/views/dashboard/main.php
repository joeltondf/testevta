<?php

// app/views/dashboard/main.php

// --- Configurações Iniciais ---
$hasFilters = $hasFilters ?? false;
$defaultStatusApplied = $defaultStatusApplied ?? false;
$listTitle = $listTitle ?? ($hasFilters ? 'Resultados da Busca' : 'Todos os Serviços');

// Definir o limite inicial de processos para exibir no dashboard
$initialProcessLimit = 50; // Altere este valor conforme necessário

if (!function_exists('dashboard_normalize_status_info')) {
    function dashboard_normalize_status_info(?string $status): array
    {
        $normalized = mb_strtolower(trim((string)$status));

        if ($normalized === '') {
            return ['normalized' => '', 'label' => 'N/A'];
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
}

$selectedStatusInfo = dashboard_normalize_status_info($filters['status'] ?? '');
$selectedStatusNormalized = $selectedStatusInfo['normalized'];

if (!function_exists('dashboard_get_aria_sort')) {
    function dashboard_get_aria_sort(string $sortKey, string $currentSort, string $currentDirection): string
    {
        if ($sortKey !== $currentSort) {
            return 'none';
        }

        return $currentDirection === 'DESC' ? 'descending' : 'ascending';
    }
}

if (!function_exists('dashboard_get_sort_indicator')) {
    function dashboard_get_sort_indicator(string $sortKey, string $currentSort, string $currentDirection): array
    {
        if ($sortKey !== $currentSort) {
            return ['symbol' => '&#8597;', 'class' => 'text-gray-300'];
        }

        return [
            'symbol' => $currentDirection === 'DESC' ? '&#8595;' : '&#8593;',
            'class' => 'text-blue-500'
        ];
    }
}

if (!function_exists('dashboard_build_sort_url')) {
    function dashboard_build_sort_url(string $sortKey, array $filters): string
    {
        $query = $filters;
        unset($query['ajax'], $query['offset'], $query['limit']);

        $currentSort = $filters['sort'] ?? '';
        $currentDirection = strtoupper($filters['direction'] ?? 'ASC');
        $currentDirection = $currentDirection === 'DESC' ? 'DESC' : 'ASC';
        $nextDirection = 'ASC';

        if ($currentSort === $sortKey) {
            $nextDirection = $currentDirection === 'ASC' ? 'DESC' : 'ASC';
        }

        $query['sort'] = $sortKey;
        $query['direction'] = $nextDirection;

        $filteredQuery = array_filter(
            $query,
            static function ($value) {
                return $value !== null && $value !== '';
            }
        );

        return 'dashboard.php?' . http_build_query($filteredQuery);
    }
}

if (!function_exists('dashboard_render_sortable_header')) {
    function dashboard_render_sortable_header(string $label, string $sortKey, array $filters, string $currentSort, string $currentDirection): string
    {
        $url = dashboard_build_sort_url($sortKey, $filters);
        $indicator = dashboard_get_sort_indicator($sortKey, $currentSort, $currentDirection);
        $isActive = $sortKey === $currentSort;
        $textClass = $isActive ? 'text-blue-600' : 'text-gray-500';
        $arrowClass = 'sort-indicator ' . ($indicator['class'] ?? 'text-gray-300');
        $baseClasses = 'flex items-center gap-1 group';

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="' . $baseClasses . '"><span class="' . $textClass . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><span class="' . $arrowClass . '" aria-hidden="true">' . $indicator['symbol'] . '</span></a>';
    }
}

if (!function_exists('dashboard_card_classes')) {
    function dashboard_card_classes(string $cardKey, string $currentCardFilter): string
    {
        $base = 'dashboard-card w-full bg-white px-4 py-3 rounded-lg shadow-md flex items-center gap-3 transition duration-200 text-left focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';

        if ($cardKey === $currentCardFilter) {
            return $base . ' ring-2 ring-blue-500 ring-offset-2 shadow-lg';
        }

        return $base . ' hover:shadow-lg';
    }
}

$currentSort = $filters['sort'] ?? '';
$currentDirection = strtoupper($filters['direction'] ?? 'ASC');
$currentDirection = in_array($currentDirection, ['ASC', 'DESC'], true) ? $currentDirection : 'ASC';
$cardFilterLabels = [
    'ativos' => 'Serviços em Andamento',
    'pendentes' => 'Serviços Pendentes',
    'orcamentos' => 'Orçamentos Pendentes',
    'finalizados_mes' => 'Concluídos (Mês)',
    'atrasados' => 'Serviços Atrasados',
];
$currentCardFilter = $filters['filtro_card'] ?? '';
if (!array_key_exists($currentCardFilter, $cardFilterLabels)) {
    $currentCardFilter = '';
}
$highlightedCardFilter = $currentCardFilter !== '' ? $currentCardFilter : ($defaultStatusApplied ? 'ativos' : '');

?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?>!</h1>
        <p class="mt-1 text-gray-600">Este é o seu painel de controle. Veja as últimas atualizações abaixo.</p>
    </div>
    <?php if ($_SESSION['user_perfil'] !== 'vendedor'): ?>
    <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
        <a href="servico-rapido.php?action=create" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-200">
            Novo Serviço
        </a>
        <a href="processos.php?action=create&return_to=dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-200">
            Novo Orçamento
        </a>
        <a href="clientes.php?action=create&return_to=dashboard.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-200">
            Novo Cliente
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="flex flex-col lg:flex-row gap-6 mb-8">

    <div class="w-full lg:w-[15%]">
        <div class="grid grid-cols-1 sm:grid-cols-1 gap-2">
            <?php $isActive = $highlightedCardFilter === 'ativos'; ?>
            <button type="button"
                    class="<?php echo dashboard_card_classes('ativos', $highlightedCardFilter); ?>"
                    data-card-filter="ativos"
                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                    title="Filtrar <?php echo htmlspecialchars($cardFilterLabels['ativos'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flex items-center gap-3 w-full">
                    <span class="bg-blue-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-gray-500 text-xs">Serviços em Andamento</span>
                        <span class="block text-xl font-bold text-gray-800"><?php echo $dashboardStats['processos_ativos'] ?? 0; ?></span>
                    </span>
                </span>
            </button>
            <?php $isActive = $highlightedCardFilter === 'pendentes'; ?>
            <button type="button"
                    class="<?php echo dashboard_card_classes('pendentes', $highlightedCardFilter); ?>"
                    data-card-filter="pendentes"
                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                    title="Filtrar <?php echo htmlspecialchars($cardFilterLabels['pendentes'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flex items-center gap-3 w-full">
                    <span class="bg-orange-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5 5a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-gray-500 text-xs">Serviços Pendentes</span>
                        <span class="block text-xl font-bold text-gray-800"><?php echo $dashboardStats['servicos_pendentes'] ?? 0; ?></span>
                    </span>
                </span>
            </button>
            <?php $isActive = $highlightedCardFilter === 'orcamentos'; ?>
            <button type="button"
                    class="<?php echo dashboard_card_classes('orcamentos', $highlightedCardFilter); ?>"
                    data-card-filter="orcamentos"
                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                    title="Filtrar <?php echo htmlspecialchars($cardFilterLabels['orcamentos'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flex items-center gap-3 w-full">
                    <span class="bg-yellow-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-gray-500 text-xs">Orçamentos Pendentes</span>
                        <span class="block text-xl font-bold text-gray-800"><?php echo $dashboardStats['orcamentos_pendentes'] ?? 0; ?></span>
                    </span>
                </span>
            </button>
            <?php $isActive = $highlightedCardFilter === 'finalizados_mes'; ?>
            <button type="button"
                    class="<?php echo dashboard_card_classes('finalizados_mes', $highlightedCardFilter); ?>"
                    data-card-filter="finalizados_mes"
                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                    title="Filtrar <?php echo htmlspecialchars($cardFilterLabels['finalizados_mes'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flex items-center gap-3 w-full">
                    <span class="bg-green-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-gray-500 text-xs">Concluídos (Mês)</span>
                        <span class="block text-xl font-bold text-gray-800"><?php echo $dashboardStats['finalizados_mes'] ?? 0; ?></span>
                    </span>
                </span>
            </button>
            <?php $isActive = $highlightedCardFilter === 'atrasados'; ?>
            <button type="button"
                    class="<?php echo dashboard_card_classes('atrasados', $highlightedCardFilter); ?>"
                    data-card-filter="atrasados"
                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                    title="Filtrar <?php echo htmlspecialchars($cardFilterLabels['atrasados'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flex items-center gap-3 w-full">
                    <span class="bg-red-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-gray-500 text-xs">Serviços Atrasados</span>
                        <span class="block text-xl font-bold text-gray-800"><?php echo $dashboardStats['processos_atrasados'] ?? 0; ?></span>
                    </span>
                </span>
            </button>
        </div>
    </div>

    <div class="w-full lg:w-[35%]">
        <div class="bg-white shadow-md rounded-lg h-full">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h4 class="text-xl font-bold text-gray-900">Últimos Orçamentos</h4>
            </div>
            <div class="px-4 py-3">
                <?php if(empty($orcamentosRecentes)): ?>
                    <p class="text-center text-gray-500 py-4">Nenhum orçamento recente.</p>
                <?php else: ?>
                    <div class="overflow-y-auto custom-scrollbar">
                        <ul class="divide-y divide-gray-200">
                        <?php foreach($orcamentosRecentes as $orc): ?>
                            <li class="py-1 flex justify-between items-center pr-2">
                                <div>
                                    <a href="processos.php?action=view&id=<?php echo $orc['id']; ?>" class="text-xs font-semibold text-blue-600 hover:underline"><?php echo htmlspecialchars($orc['orcamento_numero'] ?? ''); ?> - <?php echo htmlspecialchars(mb_strtoupper($orc['titulo'] ?? '')); ?></a>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars(mb_strtoupper($orc['nome_cliente'] ?? '')); ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo date('d/m/Y', strtotime($orc['data_criacao'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-[50%] bg-white px-5 py-4 rounded-lg shadow-xl border border-gray-200">
        <h4 class="text-xl font-bold text-gray-800 mb-5 border-b pb-2">Filtrar Serviços</h4>
        <form action="dashboard.php" method="GET" id="filter-form">
            <input type="hidden" name="filtro_card" value="<?php echo htmlspecialchars($filters['filtro_card'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($filters['sort'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="direction" value="<?php echo htmlspecialchars($filters['direction'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
                <div class="flex flex-col">
                    <label for="titulo" class="text-sm font-semibold text-gray-700 mb-1">Serviço</label>
                    <input type="text" id="titulo" name="titulo" placeholder="Digite o serviço" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200" value="<?php echo htmlspecialchars($filters['titulo'] ?? ''); ?>">
                </div>
                <div class="flex flex-col">
                    <label for="cliente_id" class="text-sm font-semibold text-gray-700 mb-1">Assessoria</label>
                    <select id="cliente_id" name="cliente_id" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white transition duration-200">
                        <option value="">Todas as Assessorias</option>
                        <?php foreach($clientesParaFiltro as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo (($filters['cliente_id'] ?? '') == $cliente['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cliente['nome_cliente'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label for="os_numero" class="text-sm font-semibold text-gray-700 mb-1">Chave OS Omie</label>
                    <input type="text" id="os_numero" name="os_numero" placeholder="Ex: 12345" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200" value="<?php echo htmlspecialchars($filters['os_numero'] ?? ''); ?>">
                </div>
                <div class="flex flex-col">
                    <label for="tipo_servico" class="text-sm font-semibold text-gray-700 mb-1">Tipo de Serviço</label>
                    <select id="tipo_servico" name="tipo_servico" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white transition duration-200">
                        <option value="">Todos os Tipos</option>
                        <?php $tipos = ['Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros']; foreach($tipos as $tipo): ?>
                            <option value="<?php echo $tipo; ?>" <?php echo (($filters['tipo_servico'] ?? '') == $tipo) ? 'selected' : ''; ?>><?php echo $tipo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($_SESSION['user_perfil'] !== 'vendedor'): ?>
                <div class="flex flex-col">
                    <label for="tradutor_id" class="text-sm font-semibold text-gray-700 mb-1">Tradutor</label>
                    <select id="tradutor_id" name="tradutor_id" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white transition duration-200">
                        <option value="">Todos os Tradutores</option>
                        <?php foreach($tradutoresParaFiltro as $tradutor): ?>
                            <option value="<?php echo $tradutor['id']; ?>" <?php echo (($filters['tradutor_id'] ?? '') == $tradutor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tradutor['nome_tradutor'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="flex flex-col">
                    <label for="status" class="text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white transition duration-200">
                        <option value="">Todos os Status</option>
                    <?php $statusOptions = ['Orçamento Pendente', 'Orçamento', 'Serviço Pendente', 'Serviço em Andamento', 'Aguardando pagamento', 'Concluído', 'Cancelado']; foreach ($statusOptions as $option): ?>
                            <?php $optionInfo = dashboard_normalize_status_info($option); ?>
                            <option value="<?php echo $optionInfo['label']; ?>" <?php echo ($selectedStatusNormalized === $optionInfo['normalized']) ? 'selected' : ''; ?>><?php echo $optionInfo['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label for="data_inicio" class="text-sm font-semibold text-gray-700 mb-1">Data de Inicial</label>
                    <input type="date" id="data_inicio" name="data_inicio" title="Data de Entrada (Início)" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200" value="<?php echo htmlspecialchars($filters['data_inicio'] ?? ''); ?>">
                </div>
                <div class="flex flex-col">
                    <label for="data_fim" class="text-sm font-semibold text-gray-700 mb-1">Data de Final</label>
                    <input type="date" id="data_fim" name="data_fim" title="Data de Entrada (Fim)" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200" value="<?php echo htmlspecialchars($filters['data_fim'] ?? ''); ?>">
                </div>
                <div class="flex flex-col">
                    <label for="tipo_prazo" class="text-sm font-semibold text-gray-700 mb-1">Prazo</label>
                    <select id="tipo_prazo" name="tipo_prazo" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white transition duration-200">
                        <option value="">Todos os Prazos</option>
                        <?php
                        $prazos = [
                            'falta_3' => 'Falta 3 dias', 'falta_2' => 'Falta 2 dias', 'falta_1' => 'Falta 1 dia',
                            'vence_hoje' => 'Vence hoje', 'venceu_1' => 'Venceu há 1 dia', 'venceu_2' => 'Venceu há 2 dias',
                            'venceu_3_mais' => 'Venceu há 3 ou mais dias'
                        ];
                        foreach($prazos as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo (($filters['tipo_prazo'] ?? '') == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-row sm:flex-row items-center justify-end mt-5 space-y-2 sm:space-y-0 sm:space-x-2">
                <a href="dashboard.php" class="w-full sm:w-auto text-center text-sm font-medium text-gray-600 hover:text-gray-900 px-5 py-2.5 rounded-lg border border-gray-300 hover:border-gray-400 transition duration-200">Limpar Filtros</a>
                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md hover:shadow-lg transition duration-200 ease-in-out">
                    <i class="fas fa-filter mr-2"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white shadow-md rounded-lg mb-6">
    <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-medium leading-6 text-gray-900"><?php echo htmlspecialchars($listTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
    </div>
    <?php if (empty($processos)): ?>
        <div class="text-center py-12"><p class="text-gray-500">Nenhum Serviço encontrado.</p></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-auto">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" aria-sort="<?php echo dashboard_get_aria_sort('titulo', $currentSort, $currentDirection); ?>">
                            <?php echo dashboard_render_sortable_header('Família', 'titulo', $filters, $currentSort, $currentDirection); ?>
                        </th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" aria-sort="<?php echo dashboard_get_aria_sort('cliente', $currentSort, $currentDirection); ?>">
                            <?php echo dashboard_render_sortable_header('Assessoria', 'cliente', $filters, $currentSort, $currentDirection); ?>
                        </th>
                        <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Doc.</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" aria-sort="<?php echo dashboard_get_aria_sort('omie', $currentSort, $currentDirection); ?>">
                            <?php echo dashboard_render_sortable_header('OS Omie', 'omie', $filters, $currentSort, $currentDirection); ?>
                        </th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serviços</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" aria-sort="<?php echo dashboard_get_aria_sort('dataEntrada', $currentSort, $currentDirection); ?>">
                            <?php echo dashboard_render_sortable_header('Entrada', 'dataEntrada', $filters, $currentSort, $currentDirection); ?>
                        </th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" aria-sort="<?php echo dashboard_get_aria_sort('dataEnvio', $currentSort, $currentDirection); ?>">
                            <?php echo dashboard_render_sortable_header('Envio', 'dataEnvio', $filters, $currentSort, $currentDirection); ?>
                        </th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
                        <th scope="col" class="relative px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="processes-table-body">
                    <?php foreach ($processos as $processo): ?>
                        <?php
                            $rowClass = 'hover:bg-gray-50';
                            $statusInfo = dashboard_normalize_status_info($processo['status_processo'] ?? '');
                            $statusNormalized = $statusInfo['normalized'];
                            switch ($statusNormalized) {
                                case 'orçamento':
                                case 'orçamento pendente':
                                    $rowClass = 'bg-blue-50 hover:bg-blue-100';
                                    break;
                                case 'serviço pendente':
                                    $rowClass = 'bg-orange-50 hover:bg-orange-100';
                                    break;
                                case 'serviço em andamento':
                                    $rowClass = 'bg-cyan-50 hover:bg-cyan-100';
                                    break;
                                case 'aguardando pagamento':
                                    $rowClass = 'bg-indigo-50 hover:bg-indigo-100';
                                    break;
                                case 'concluído':
                                    $rowClass = 'bg-purple-50 hover:bg-purple-100';
                                    break;
                                case 'cancelado':
                                    $rowClass = 'bg-red-50 hover:bg-red-100';
                                    break;
                            }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs font-medium">
                                <a href="processos.php?action=view&id=<?php echo $processo['id']; ?>" class="text-blue-600 hover:text-blue-800 hover:underline truncate" title="<?php echo htmlspecialchars(mb_strtoupper($processo['titulo'] ?? 'N/A')); ?>">
                                    <?php echo htmlspecialchars(mb_strtoupper(mb_strimwidth($processo['titulo'] ?? 'N/A', 0, 25, "..."))); ?>
                                </a>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars(mb_strtoupper($processo['nome_cliente'] ?? 'N/A')); ?>">
                                <?php echo htmlspecialchars(mb_strtoupper(mb_strimwidth($processo['nome_cliente'] ?? 'N/A', 0, 20, "..."))); ?>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500 text-center">
                                <?php echo $processo['total_documentos_soma']; ?>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500">
                                <?php 
                                    $osOmie = $processo['os_numero_omie'] ?? null;
                                    // Exibe apenas os últimos 5 dígitos para facilitar a leitura
                                    $osOmieFormatado = $osOmie ? substr((string)$osOmie, -5) : 'Aguardando Omie';
                                    echo htmlspecialchars($osOmieFormatado);
                                ?>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500">
                                <?php
                                $servicos = array_filter(array_map('trim', explode(',', $processo['categorias_servico'] ?? '')));
                                $serviceBadgeMap = [
                                    'Tradução' => ['label' => 'Trad.', 'class' => 'bg-blue-100 text-blue-800'],
                                    'CRC' => ['label' => 'CRC', 'class' => 'bg-green-100 text-green-800'],
                                    'Apostilamento' => ['label' => 'Apost.', 'class' => 'bg-yellow-100 text-yellow-800'],
                                    'Postagem' => ['label' => 'Post.', 'class' => 'bg-purple-100 text-purple-800'],
                                    'Outros' => ['label' => 'Out.', 'class' => 'bg-gray-100 text-gray-800'],
                                ];
                                foreach ($servicos as $servico) {
                                    if(isset($serviceBadgeMap[$servico])) {
                                        $badge = $serviceBadgeMap[$servico];
                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badge['class'] . ' mr-1">' . htmlspecialchars($badge['label']) . '</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500"><?php echo isset($processo['data_criacao']) ? date('d/m/Y', strtotime($processo['data_criacao'])) : 'N/A'; ?></td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs text-gray-500"><?php echo isset($processo['data_inicio_traducao']) ? date('d/m/Y', strtotime($processo['data_inicio_traducao'])) : 'N/A'; ?></td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-xs font-medium">
                                <?php
                                $texto_tempo = 'A definir'; 
                                $classe_tempo = 'text-gray-500';

                                if ($statusNormalized === 'concluído') {
                                    $texto_tempo = 'Concluído';
                                    $classe_tempo = 'bg-green-100 text-green-800';
                                } elseif (in_array($statusNormalized, ['cancelado', 'orçamento', 'aguardando pagamento'], true)) {
                                    $texto_tempo = 'N/A';
                                    $classe_tempo = 'text-gray-500';
                                } else {
                                    $data_previsao_final = null;
                                    if (!empty($processo['traducao_prazo_data'])) {
                                        $data_previsao_final = new DateTime($processo['traducao_prazo_data']);
                                    } elseif (!empty($processo['traducao_prazo_dias']) && !empty($processo['data_inicio_traducao'])) {
                                        $data_previsao_final = new DateTime($processo['data_inicio_traducao']);
                                        $data_previsao_final->modify('+' . $processo['traducao_prazo_dias'] . ' days');
                                    }

                                    if ($data_previsao_final) {
                                        $hoje = new DateTime('today');
                                        $diff = $hoje->diff($data_previsao_final);
                                        $dias_restantes = (int)$diff->format('%r%a');

                                        if ($dias_restantes < 0) {
                                            $texto_tempo = abs($dias_restantes) . ' dia(s) vencido(s)';
                                            $classe_tempo = 'bg-red-200 text-red-800';
                                        } elseif ($dias_restantes == 0) {
                                            $texto_tempo = 'Vence hoje';
                                            $classe_tempo = 'bg-red-200 text-red-800';
                                        } elseif ($dias_restantes <= 3) {
                                            $texto_tempo = $dias_restantes . ' dias';
                                            $classe_tempo = 'bg-yellow-200 text-yellow-800';
                                        } else {
                                            $texto_tempo = $dias_restantes . ' dias';
                                            $classe_tempo = 'text-green-600';
                                        }
                                    }
                                }
                                echo "<span class='px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full " . $classe_tempo . "'>" . $texto_tempo . "</span>";
                                ?>
                            </td>
                            <td class="px-3 py-0.5 whitespace-nowrap text-center text-xs font-medium">
                                <div class="relative inline-block p-1">
                                    <svg id="tooltip-trigger-<?php echo $processo['id']; ?>" class="w-6 h-6 text-gray-500 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         data-tooltip-trigger
                                         data-process-id="<?php echo $processo['id']; ?>"
                                         data-tooltip-content-json='<?php
                                            $status_assinatura_texto = 'Pendente';
                                            $status_assinatura_classe = 'bg-yellow-100 text-yellow-800';
                                            if (!empty($processo['data_devolucao_assinatura'])) {
                                                $status_assinatura_texto = 'Enviado';
                                                $status_assinatura_classe = 'bg-green-100 text-green-800';
                                            }
                                            $js_nome_tradutor = str_replace("'", "\'", htmlspecialchars($processo['nome_tradutor'] ?? 'Não definido'));
                                            $js_traducao_modalidade = str_replace("'", "\'", htmlspecialchars($processo['traducao_modalidade'] ?? 'N/A'));
                                            $js_envio_cartorio = isset($processo['data_envio_cartorio']) ? date('d/m/Y', strtotime($processo['data_envio_cartorio'])) : 'Pendente';
                                            $tooltip_html_content =
                                                '<div class="flex flex-col gap-2 text-left whitespace-normal leading-relaxed text-sm">' .
                                                '   <p class="font-semibold text-xs uppercase tracking-wide text-gray-200 border-b border-gray-600 pb-2">Detalhes Rápidos</p>' .
                                                '   <p><strong>Tradutor:</strong> ' . $js_nome_tradutor . '</p>' .
                                                '   <p><strong>Modalidade:</strong> ' . $js_traducao_modalidade . '</p>' .
                                                '   <p><strong>Status Ass.:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $status_assinatura_classe . ' mr-1">' . $status_assinatura_texto . '</span></p>' .
                                                '   <p><strong>Envio Cartório:</strong> ' . $js_envio_cartorio . '</p>' .
                                                '</div>' .
                                                '<div class="tooltip-arrow" data-tooltip-arrow></div>';
                                            echo json_encode($tooltip_html_content);
                                        ?>'>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalProcessesCount > $initialProcessLimit): ?>
            <div class="py-4 text-center flex justify-center">
                <button id="load-more-processes" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md hover:shadow-lg transition duration-200 ease-in-out">
                    Ver mais serviços (<?php echo $totalProcessesCount - $initialProcessLimit; ?> restantes)
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* Optional: Para uma aparência mais agradável da barra de rolagem */
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.dashboard-card {
    cursor: pointer;
}

.sort-indicator {
    font-size: 0.75rem;
    line-height: 1;
}

/* Aplicação de estilos Tailwind para inputs e selects (JIT/Custom) */
.form-input, .form-select {
    @apply w-full border-gray-300 rounded-md shadow-sm py-2.5 px-4 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500;
}

/* Adicionando margem aos rótulos para melhor espaçamento */
.flex.flex-col > .text-sm.font-semibold {
    margin-bottom: 0.5rem; /* Equivalente a mb-2 */
}

#dynamic-tooltip {
    color: #f9fafb;
}

#dynamic-tooltip p {
    margin: 0;
    color: #e5e7eb;
}

#dynamic-tooltip p + p {
    margin-top: 0.35rem;
}

#dynamic-tooltip strong {
    color: #f9fafb;
}

.tooltip-arrow {
    position: absolute;
    width: 12px;
    height: 12px;
    background-color: #111827;
    border: 1px solid rgba(55, 65, 81, 0.8);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    border-radius: 2px;
    transform: rotate(45deg);
}

@media (max-width: 768px) {
    #dynamic-tooltip {
        max-width: min(92vw, 22rem);
        width: min(92vw, 22rem);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let activeTooltip = null;
    let showDelayTimeout;
    let hideDelayTimeout;

    const filterForm = document.getElementById('filter-form');
    const cardFilterInput = filterForm ? filterForm.querySelector('input[name="filtro_card"]') : null;
    const sortInput = filterForm ? filterForm.querySelector('input[name="sort"]') : null;
    const directionInput = filterForm ? filterForm.querySelector('input[name="direction"]') : null;
    const statusSelect = document.getElementById('status');

    document.querySelectorAll('[data-card-filter]').forEach(cardButton => {
        cardButton.addEventListener('click', () => {
            if (!filterForm || !cardFilterInput) {
                return;
            }

            const selectedFilter = cardButton.dataset.cardFilter ?? '';
            const currentValue = cardFilterInput.value;
            const nextValue = currentValue === selectedFilter ? '' : selectedFilter;

            cardFilterInput.value = nextValue;

            if (statusSelect) {
                statusSelect.value = '';
            }

            if (sortInput && directionInput && !sortInput.value) {
                directionInput.value = '';
            }

            filterForm.submit();
        });
    });

    // Classe CSS para o tooltip dinâmico (Tailwind)
    const TOOLTIP_CLASSES = `
        absolute px-4 py-3 bg-gray-900 text-white text-sm leading-relaxed rounded-xl shadow-2xl z-50
        border border-gray-700 backdrop-blur-sm transition-opacity duration-200 ease-out
        max-w-xs sm:max-w-sm lg:max-w-md xl:max-w-lg
    `.trim().split(/\s+/);
    const TOOLTIP_SAFE_MARGIN = 12;
    const TOOLTIP_ARROW_OFFSET = 8;
    const MOBILE_BREAKPOINT = 768;

    function resolveTooltipPlacement(triggerRect, tooltipRect) {
        const viewportWidth = window.innerWidth;

        if (viewportWidth < MOBILE_BREAKPOINT) {
            return 'bottom';
        }

        const fitsOnRight = triggerRect.right + TOOLTIP_SAFE_MARGIN + tooltipRect.width <= viewportWidth - TOOLTIP_SAFE_MARGIN;
        if (fitsOnRight) {
            return 'right';
        }

        const fitsOnLeft = triggerRect.left - TOOLTIP_SAFE_MARGIN - tooltipRect.width >= TOOLTIP_SAFE_MARGIN;
        if (fitsOnLeft) {
            return 'left';
        }

        return 'bottom';
    }

    function updateArrowPosition(arrowElement, placement) {
        if (!arrowElement) return;

        arrowElement.style.left = '';
        arrowElement.style.right = '';
        arrowElement.style.top = '';
        arrowElement.style.bottom = '';
        arrowElement.style.transform = 'rotate(45deg)';

        if (placement === 'right') {
            arrowElement.style.left = `-${TOOLTIP_ARROW_OFFSET}px`;
            arrowElement.style.top = '50%';
            arrowElement.style.transform = 'translateY(-50%) rotate(45deg)';
        } else if (placement === 'left') {
            arrowElement.style.right = `-${TOOLTIP_ARROW_OFFSET}px`;
            arrowElement.style.top = '50%';
            arrowElement.style.transform = 'translateY(-50%) rotate(45deg)';
        } else {
            arrowElement.style.left = '50%';
            arrowElement.style.top = `-${TOOLTIP_ARROW_OFFSET}px`;
            arrowElement.style.transform = 'translate(-50%, -50%) rotate(45deg)';
        }
    }

    /**
     * Cria e anexa o elemento do tooltip ao DOM.
     * @param {string} htmlContent - O conteúdo HTML do tooltip.
     * @param {string} triggerId - O ID do elemento que disparou o tooltip.
     * @returns {HTMLElement} O elemento do tooltip criado.
     */
    function createTooltipElement(htmlContent, triggerId) {
        if (activeTooltip) {
            activeTooltip.remove();
            activeTooltip = null;
        }

        const tooltipDiv = document.createElement('div');
        tooltipDiv.innerHTML = htmlContent;
        tooltipDiv.classList.add(...TOOLTIP_CLASSES);
        tooltipDiv.id = 'dynamic-tooltip'; // ID para depuração
        tooltipDiv.dataset.originalTriggerId = triggerId; // Guarda o ID do gatilho original
        tooltipDiv.style.maxWidth = 'min(90vw, 24rem)';
        tooltipDiv.style.minWidth = 'min(16rem, 90vw)';
        tooltipDiv.style.width = 'max-content';
        tooltipDiv.style.boxSizing = 'border-box';
        tooltipDiv.style.wordBreak = 'break-word';
        tooltipDiv.style.lineHeight = '1.55';
        tooltipDiv.style.letterSpacing = '0.01em';

        // Estilos para medição: Fora da tela, invisível para o usuário, mas presente no layout
        Object.assign(tooltipDiv.style, {
            position: 'absolute',
            visibility: 'hidden', // Esconde da tela
            opacity: '0',       // Garante opacidade 0 para transição
            top: '-9999px',     // Fora da tela
            left: '-9999px',    // Fora da tela
            transform: 'none',  // Reseta transformações para medição
            pointerEvents: 'auto' // Permite interação do mouse no tooltip
        });

        document.body.appendChild(tooltipDiv);
        activeTooltip = tooltipDiv;
        return tooltipDiv;
    }

    /**
     * Posiciona o tooltip em relação ao elemento de destino.
     * @param {HTMLElement} tooltipElement - O elemento do tooltip.
     * @param {HTMLElement} targetElement - O elemento que disparou o tooltip.
     */
    function positionTooltip(tooltipElement, targetElement) {
        const triggerRect = targetElement.getBoundingClientRect();

        // Força o reflow para que as dimensões do tooltip sejam calculadas
        // AGORA que ele está no DOM e possui seus estilos base.
        // eslint-disable-next-line no-unused-vars
        const _ = tooltipElement.offsetHeight; // Força o reflow

        const tooltipRect = tooltipElement.getBoundingClientRect(); // Medição agora deve ser precisa

        const placement = resolveTooltipPlacement(triggerRect, tooltipRect);

        let top;
        let left;

        if (placement === 'right') {
            left = triggerRect.right + TOOLTIP_SAFE_MARGIN;
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
        } else if (placement === 'left') {
            left = triggerRect.left - tooltipRect.width - TOOLTIP_SAFE_MARGIN;
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
        } else {
            top = triggerRect.bottom + TOOLTIP_SAFE_MARGIN;
            left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2);
        }

        if (placement !== 'bottom') {
            if (top < TOOLTIP_SAFE_MARGIN) {
                top = TOOLTIP_SAFE_MARGIN;
            }
            if (top + tooltipRect.height > window.innerHeight - TOOLTIP_SAFE_MARGIN) {
                top = window.innerHeight - tooltipRect.height - TOOLTIP_SAFE_MARGIN;
            }
        } else {
            if (top + tooltipRect.height > window.innerHeight - TOOLTIP_SAFE_MARGIN) {
                top = Math.max(TOOLTIP_SAFE_MARGIN, window.innerHeight - tooltipRect.height - TOOLTIP_SAFE_MARGIN);
            }
        }

        if (left < TOOLTIP_SAFE_MARGIN) {
            left = TOOLTIP_SAFE_MARGIN;
        }
        if (left + tooltipRect.width > window.innerWidth - TOOLTIP_SAFE_MARGIN) {
            left = window.innerWidth - tooltipRect.width - TOOLTIP_SAFE_MARGIN;
        }

        const arrow = tooltipElement.querySelector('[data-tooltip-arrow]');
        updateArrowPosition(arrow, placement);
        tooltipElement.dataset.placement = placement;

        Object.assign(tooltipElement.style, {
            top: `${top + window.scrollY}px`,
            left: `${left + window.scrollX}px`,
            visibility: 'visible', // Torna o elemento visível no local correto
        });
    }

    /**
     * Exibe o tooltip com um pequeno atraso.
     * @param {HTMLElement} triggerElement - O elemento que disparou o tooltip.
     */
    function showTooltip(triggerElement) {
        clearTimeout(hideDelayTimeout); // Cancela o hide se o mouse entrar novamente
        showDelayTimeout = setTimeout(() => {
            const tooltipHtmlContent = triggerElement.dataset.tooltipContentJson;
            if (!tooltipHtmlContent) return;

            const tooltipElement = createTooltipElement(JSON.parse(tooltipHtmlContent), triggerElement.id);
            positionTooltip(tooltipElement, triggerElement); // Posiciona o tooltip

            // Inicia a transição de opacidade para mostrar o tooltip
            tooltipElement.style.opacity = '1';

            // Adicionar listeners para o próprio tooltip para que ele não suma ao passar o mouse sobre ele
            tooltipElement.addEventListener('mouseenter', () => clearTimeout(hideDelayTimeout));
            tooltipElement.addEventListener('mouseleave', hideTooltip);

        }, 100); // Pequeno atraso para aparecer
    }

    /**
     * Esconde o tooltip com um pequeno atraso.
     */
    function hideTooltip() {
        clearTimeout(showDelayTimeout); // Cancela o show se o mouse sair rapidamente
        hideDelayTimeout = setTimeout(() => {
            if (activeTooltip) {
                activeTooltip.style.opacity = '0'; // Inicia a transição de opacidade para esconder
                activeTooltip.addEventListener('transitionend', function handler() {
                    // Garante que é o tooltip certo e que a transição de opacidade 0 terminou
                    if (this === activeTooltip && this.style.opacity === '0') {
                        this.remove(); // Remove do DOM
                        activeTooltip = null;
                    }
                    this.removeEventListener('transitionend', handler); // Remove o listener
                }, { once: true }); // Usar { once: true } garante que o listener seja removido automaticamente
            }
        }, 200); // Pequeno atraso para desaparecer
    }

    /**
     * Inicializa os listeners do tooltip em um conjunto de elementos.
     * @param {NodeListOf<HTMLElement>} triggersToInitialize - Coleção de elementos para inicializar.
     */
    function initializeTooltips(triggersToInitialize) {
        triggersToInitialize.forEach(trigger => {
            if (trigger.dataset.tooltipInitialized) return; // Evita inicializar múltiplas vezes

            // Adiciona um ID único para o SVG se ele ainda não tiver um (para o resize/scroll)
            if (!trigger.id) {
                trigger.id = `tooltip-trigger-${Math.random().toString(36).substr(2, 9)}`;
            }

            trigger.addEventListener('mouseenter', () => showTooltip(trigger));
            trigger.addEventListener('mouseleave', hideTooltip);
            trigger.addEventListener('focus', () => showTooltip(trigger));
            trigger.addEventListener('blur', hideTooltip);

            trigger.dataset.tooltipInitialized = 'true'; // Marca como inicializado
        });
    }

    // --- Lógica para "Ver Mais" Processos ---
    const loadMoreButton = document.getElementById('load-more-processes');
    const processesTableBody = document.getElementById('processes-table-body');
    let currentLoadedProcesses = processesTableBody.children.length;
    const normalizeStatus = (status) => {
        const normalized = (status || '').toLowerCase();
        const aliases = {
            'orcamento': 'orçamento',
            'orcamento pendente': 'orçamento pendente',
            'serviço pendente': 'serviço pendente',
            'servico pendente': 'serviço pendente',
            'pendente': 'serviço pendente',
            'aprovado': 'serviço pendente',
            'serviço em andamento': 'serviço em andamento',
            'servico em andamento': 'serviço em andamento',
            'em andamento': 'serviço em andamento',
            'aguardando pagamento': 'aguardando pagamento',
            'finalizado': 'concluído',
            'finalizada': 'concluído',
            'concluido': 'concluído',
            'concluida': 'concluído',
            'arquivado': 'cancelado',
            'arquivada': 'cancelado',
            'recusado': 'cancelado',
            'recusada': 'cancelado'
        };
        return aliases[normalized] ?? normalized;
    };
    // O PHP já deve ter fornecido $totalProcessesCount para o JS
    const totalAvailableProcesses = <?php echo $totalProcessesCount; ?>;

    if (loadMoreButton) {
        // Atualiza o texto do botão ou o esconde se não houver mais processos
        if (totalAvailableProcesses > currentLoadedProcesses) {
            loadMoreButton.textContent = `Ver mais serviços (${totalAvailableProcesses - currentLoadedProcesses} restantes)`;
            loadMoreButton.style.display = 'block';
        } else {
            loadMoreButton.style.display = 'none';
        }

        loadMoreButton.addEventListener('click', async () => {
            loadMoreButton.disabled = true;
            loadMoreButton.textContent = 'Carregando...';
            loadMoreButton.classList.add('opacity-50', 'cursor-not-allowed');

            const formData = new FormData(document.getElementById('filter-form'));
            const params = new URLSearchParams();
            for (const pair of formData.entries()) {
                params.append(pair[0], pair[1]);
            }
            params.append('offset', currentLoadedProcesses);
            params.append('limit', 50); // Carrega mais 50 serviços
            params.append('ajax', '1'); // Indica que a requisição é via AJAX

            try {
                const response = await fetch(`dashboard.php?${params.toString()}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const newProcesses = await response.json();

                if (newProcesses && newProcesses.length > 0) {
                    let html = '';
                    newProcesses.forEach(processo => {
                        // Lógica para determinar o status da assinatura (replicada do PHP)
                        let status_assinatura_texto_js = 'Pendente';
                        let status_assinatura_classe_js = 'bg-yellow-100 text-yellow-800';
                        if (processo.data_devolucao_assinatura) {
                            status_assinatura_texto_js = 'Enviado';
                            status_assinatura_classe_js = 'bg-green-100 text-green-800';
                        }

                        // Lógica para os serviços (replicada do PHP)
                        let servicosHtml = '';
                        if (processo.categorias_servico) {
                            const mapServicos = {'Tradução': 'Trad.', 'CRC': 'CRC', 'Apostilamento': 'Apost.', 'Postagem': 'Post.', 'Outros': 'Out.'};
                            const serviceBadgeClassMap = {
                                'Tradução': 'bg-blue-100 text-blue-800',
                                'CRC': 'bg-green-100 text-green-800',
                                'Apostilamento': 'bg-yellow-100 text-yellow-800',
                                'Postagem': 'bg-purple-100 text-purple-800',
                                'Outros': 'bg-gray-100 text-gray-800'
                            };
                            const servicos = processo.categorias_servico.split(',').map(servico => servico.trim()).filter(Boolean);
                            servicos.forEach(servico => {
                                if (mapServicos[servico]) {
                                    const badgeClass = serviceBadgeClassMap[servico] || 'bg-gray-100 text-gray-800';
                                    servicosHtml += `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass} mr-1">${mapServicos[servico]}</span>`;
                                }
                            });
                        }

                        // Lógica para o prazo (replicada do PHP)
                        let texto_prazo_js = 'N/A';
                        let classe_prazo_js = 'text-gray-500';

                        const normalizedStatus = normalizeStatus(processo.status_processo);

                        if (normalizedStatus === 'concluído') {
                            texto_prazo_js = 'Concluído';
                            classe_prazo_js = 'bg-green-100 text-green-800';
                        } else if (['cancelado', 'orçamento', 'aguardando pagamento'].includes(normalizedStatus)) {
                            texto_prazo_js = 'N/A';
                            classe_prazo_js = 'text-gray-500';
                        } else {
                            if (processo.traducao_prazo_data) {
                                const previsao = new Date(processo.traducao_prazo_data);
                                const hoje = new Date();
                                hoje.setHours(0, 0, 0, 0); // Zera a hora para comparação de datas

                                // Ajuste para garantir que a data de previsão também não considere o fuso horário ao comparar
                                const previsaoUTC = new Date(previsao.getUTCFullYear(), previsao.getUTCMonth(), previsao.getUTCDate());
                                const diffTime = previsaoUTC.getTime() - hoje.getTime();
                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); // Diferença em dias

                                if (diffDays < 0) {
                                    texto_prazo_js = Math.abs(diffDays) + ' dia(s) vencido(s)';
                                    classe_prazo_js = 'bg-red-200 text-red-800';
                                } else if (diffDays === 0) {
                                    texto_prazo_js = 'Vence hoje';
                                    classe_prazo_js = 'bg-red-200 text-red-800';
                                } else if (diffDays <= 3) {
                                    texto_prazo_js = diffDays + ' dias';
                                    classe_prazo_js = 'bg-yellow-200 text-yellow-800';
                                } else {
                                    texto_prazo_js = diffDays + ' dias';
                                    classe_prazo_js = 'text-green-600';
                                }
                            } else {
                                texto_prazo_js = 'A definir';
                                classe_prazo_js = 'text-gray-500';
                            }
                        }

                        // Conteúdo HTML do tooltip para o data-attribute (JSON.stringify necessário)
                        const tooltip_html_content_for_js = `
                            <div class="flex flex-col gap-2 text-left whitespace-normal leading-relaxed text-sm">
                                <p class="font-semibold text-xs uppercase tracking-wide text-gray-200 border-b border-gray-600 pb-2">Detalhes Rápidos</p>
                                <p><strong>Tradutor:</strong> ${processo.nome_tradutor ?? 'Não definido'}</p>
                                <p><strong>Modalidade:</strong> ${processo.traducao_modalidade ?? 'N/A'}</p>
                                <p><strong>Status Ass.:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${status_assinatura_classe_js} mr-1">${status_assinatura_texto_js}</span></p>
                                <p><strong>Envio Cartório:</strong> ${processo.data_envio_cartorio ? new Date(processo.data_envio_cartorio).toLocaleDateString('pt-BR') : 'Pendente'}</p>
                            </div>
                            <div class="tooltip-arrow" data-tooltip-arrow></div>
                        `;

                        html += `
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-xs font-medium"><a href="processos.php?action=view&id=${processo.id}" class="text-blue-600 hover:text-blue-800 hover:underline truncate" title="${processo.titulo ?? 'N/A'}">${(processo.titulo || 'N/A').substring(0, 25)}${(processo.titulo && processo.titulo.length > 25) ? '...' : ''}</a></td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 truncate" title="${processo.nome_cliente ?? 'N/A'}">${(processo.nome_cliente || 'N/A').substring(0, 20)}${(processo.nome_cliente && processo.nome_cliente.length > 20) ? '...' : ''}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 text-center">${processo.total_documentos_soma}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">${processo.os_numero_omie || 'Aguardando Omie'}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">${servicosHtml}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">${processo.data_criacao ? new Date(processo.data_criacao).toLocaleDateString('pt-BR') : 'N/A'}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">${processo.data_inicio_traducao ? new Date(processo.data_inicio_traducao).toLocaleDateString('pt-BR') : 'N/A'}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs font-medium">
                                    <span class='px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${classe_prazo_js}'>${texto_prazo_js}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-xs font-medium">
                                    <div class="relative inline-block p-1">
                                        <svg id="tooltip-trigger-${processo.id}" class="w-6 h-6 text-gray-500 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            data-tooltip-trigger
                                            data-process-id="${processo.id}"
                                            data-tooltip-content-json='${JSON.stringify(tooltip_html_content_for_js)}'>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    processesTableBody.insertAdjacentHTML('beforeend', html);
                    currentLoadedProcesses += newProcesses.length;

                    // Re-inicializa os listeners do tooltip para os novos elementos adicionados
                    const newlyAddedTriggers = processesTableBody.querySelectorAll(`tr:nth-child(n+${currentLoadedProcesses - newProcesses.length + 1}) [data-tooltip-trigger]`);
                    initializeTooltips(newlyAddedTriggers);

                    const remainingProcesses = totalAvailableProcesses - currentLoadedProcesses;
                    if (remainingProcesses <= 0) {
                        loadMoreButton.style.display = 'none';
                    } else {
                        loadMoreButton.textContent = `Ver mais serviços (${remainingProcesses} restantes)`;
                        loadMoreButton.disabled = false;
                        loadMoreButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    }

                } else {
                    loadMoreButton.style.display = 'none'; // Não há mais processos para carregar
                }
            } catch (error) {
                console.error('Erro ao carregar mais serviços:', error);
                loadMoreButton.textContent = 'Erro ao carregar, tente novamente.';
                loadMoreButton.classList.add('bg-red-200', 'text-red-800');
                loadMoreButton.disabled = false;
                loadMoreButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    // Inicializa os tooltips para os elementos carregados inicialmente na página
    initializeTooltips(document.querySelectorAll('[data-tooltip-trigger]'));

    // Reposicionamento do Tooltip em Scroll/Resize
    window.addEventListener('resize', () => {
        if (activeTooltip) {
            const originalTriggerId = activeTooltip.dataset.originalTriggerId;
            if (originalTriggerId) {
                const originalTrigger = document.getElementById(originalTriggerId);
                if (originalTrigger) {
                    positionTooltip(activeTooltip, originalTrigger);
                } else {
                    hideTooltip(); // Trigger não existe mais
                }
            } else {
                hideTooltip(); // Não conseguimos referenciar o trigger, então esconde.
            }
        }
    });

    // É crucial reposicionar ou esconder no scroll para que o tooltip não fique "flutuando"
    window.addEventListener('scroll', () => {
        if (activeTooltip) {
            const originalTriggerId = activeTooltip.dataset.originalTriggerId;
            if (originalTriggerId) {
                const originalTrigger = document.getElementById(originalTriggerId);
                // Verifica se o trigger ainda está visível na tela
                if (originalTrigger) {
                    const rect = originalTrigger.getBoundingClientRect();
                    const isVisible = (
                        rect.top >= 0 &&
                        rect.left >= 0 &&
                        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                    );
                    if (isVisible) {
                        positionTooltip(activeTooltip, originalTrigger);
                    } else {
                        hideTooltip(); // Se o trigger saiu da tela, esconde o tooltip
                    }
                } else {
                    hideTooltip();
                }
            } else {
                hideTooltip();
            }
        }
    }, true); // Use 'true' para fase de captura para pegar scrolls de elementos internos
});
</script>