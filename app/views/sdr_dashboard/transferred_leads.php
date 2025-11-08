<?php
$pageTitle = $pageTitle ?? 'Leads transferidos';
$baseAppUrl = rtrim(APP_URL, '/');
$apiEndpoint = $baseAppUrl . '/api/handoffs/my-transfers';
$vendors = $vendors ?? [];
$defaultStatus = $defaultStatus ?? 'pending';
?>

<link rel="stylesheet" href="<?php echo $baseAppUrl; ?>/assets/css/components/sdr-dashboard.css">

<div class="max-w-7xl mx-auto px-4 lg:px-6 py-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Leads transferidos</h1>
            <p class="text-sm text-gray-600">Acompanhe os repasses realizados para a equipe de vendas e monitore a performance de aceite e conversão.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" data-action="export-csv" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 transition-colors">
                <i class="fas fa-file-download"></i>
                Exportar CSV
            </button>
        </div>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Total transferidos (mês)</p>
            <p id="total-transferred-month" class="sdr-summary-value">--</p>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Pendentes de aceitação</p>
            <p id="pending-acceptance" class="sdr-summary-value">--</p>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Taxa de conversão</p>
            <p id="conversion-rate" class="sdr-summary-value">--</p>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Tempo médio até 1º contato</p>
            <p id="avg-first-contact" class="sdr-summary-value">--</p>
        </article>
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
        <header class="px-6 py-5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Filtros</h2>
                <p class="text-sm text-gray-500">Refine os dados por vendedor, status e período.</p>
            </div>
            <div class="flex flex-wrap gap-3 items-center">
                <button type="button" data-action="reset-filters" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Limpar filtros
                </button>
                <button type="button" data-action="apply-filters" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow transition-colors">
                    Aplicar filtros
                </button>
            </div>
        </header>
        <form id="transfers-filters-form" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            <div class="flex flex-col gap-2">
                <label for="filter-vendor" class="text-sm font-medium text-gray-700">Vendedor</label>
                <select id="filter-vendor" name="vendor_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    <option value="">Todos</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <?php
                            $vendorId = (int)($vendor['id'] ?? 0);
                            $vendorName = $vendor['nome_completo'] ?? ($vendor['name'] ?? 'Vendedor');
                        ?>
                        <option value="<?php echo $vendorId; ?>"><?php echo htmlspecialchars($vendorName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <label for="filter-status" class="text-sm font-medium text-gray-700">Status</label>
                <select id="filter-status" name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    <option value="">Todos</option>
                    <option value="pending">Pendente</option>
                    <option value="accepted">Aceito</option>
                    <option value="converted">Convertido</option>
                    <option value="lost">Perdido</option>
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <label for="filter-date-start" class="text-sm font-medium text-gray-700">Data inicial</label>
                <input type="date" id="filter-date-start" name="start_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="filter-date-end" class="text-sm font-medium text-gray-700">Data final</label>
                <input type="date" id="filter-date-end" name="end_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
            </div>
        </form>
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <header class="px-6 py-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Leads transferidos</h2>
                <p class="text-sm text-gray-500">Visualize os repasses realizados com seus respectivos SLAs e status.</p>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span class="inline-flex items-center gap-1"><span class="sdr-status-badge sdr-status-badge--pending"></span>Pendente</span>
                <span class="inline-flex items-center gap-1"><span class="sdr-status-badge sdr-status-badge--accepted"></span>Aceito</span>
                <span class="inline-flex items-center gap-1"><span class="sdr-status-badge sdr-status-badge--converted"></span>Convertido</span>
                <span class="inline-flex items-center gap-1"><span class="sdr-status-badge sdr-status-badge--lost"></span>Perdido</span>
            </div>
        </header>

        <div class="relative">
            <div id="transfers-loading" class="sdr-dashboard-loading">
                <div class="loader"></div>
                <p class="text-sm text-gray-500">Carregando transferências...</p>
            </div>

            <div id="transfers-error" class="sdr-dashboard-error hidden"></div>

            <div class="overflow-x-auto">
                <table class="min-w-full sdr-dashboard-table" aria-live="polite">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead / Empresa</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendedor</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Data transferência</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">SLA</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="transfers-table-body" class="bg-white divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <div id="transfers-empty-state" class="hidden px-6 py-12 text-center text-gray-500 text-sm">
                Nenhuma transferência encontrada para os filtros selecionados.
            </div>
        </div>
    </section>
</div>

<script>
    window.SDR_DASHBOARD_CONFIG = {
        apiEndpoint: '<?php echo $apiEndpoint; ?>',
        defaultStatus: '<?php echo htmlspecialchars($defaultStatus, ENT_QUOTES, 'UTF-8'); ?>',
        baseAppUrl: '<?php echo $baseAppUrl; ?>'
    };
</script>
<script src="<?php echo $baseAppUrl; ?>/assets/js/sdr-dashboard.js"></script>
