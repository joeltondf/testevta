<?php
$baseAppUrl = rtrim(APP_URL, '/');
$updateStatusUrl = $baseAppUrl . '/sdr_dashboard.php?action=update_lead_status';
$assignOwnerUrl = $baseAppUrl . '/sdr_dashboard.php?action=assign_lead_owner';
$qualificationBaseUrl = $baseAppUrl . '/qualificacao.php';
$kanbanStatuses = $kanbanStatuses ?? ['Novo', 'Em Contato', 'Qualificado', 'Descartado', 'Convertido'];
$leadsByStatus = $leadsByStatus ?? [];
$stats = $stats ?? ['leadsDistribuidos' => 0, 'agendamentosRealizados' => 0, 'taxaAgendamento' => 0.0];
$statusCounts = $statusCounts ?? [];
$totalLeads = $totalLeads ?? 0;
$urgentLeads = $urgentLeads ?? [];
$upcomingMeetings = $upcomingMeetings ?? [];
$activeVendors = $activeVendors ?? [];
$vendorDistribution = $vendorDistribution ?? [];
$vendorConversionRates = $vendorConversionRates ?? [];
$assignedLeadCount = $assignedLeadCount ?? 0;
$unassignedLeadCount = $unassignedLeadCount ?? 0;
$kanbanEditUrl = $kanbanEditUrl ?? ($baseAppUrl . '/sdr_dashboard.php?action=update_columns');

$hasUnassigned = false;
foreach ($vendorDistribution as $distributionRow) {
    if ((int) ($distributionRow['vendorId'] ?? 0) === 0) {
        $hasUnassigned = true;
        break;
    }
}

if (!$hasUnassigned) {
    $vendorDistribution[] = [
        'vendorId' => 0,
        'vendorName' => 'Aguardando vendedor',
        'total' => 0
    ];
}
?>

<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Painel de Prospecção (SDR)</h1>
        <p class="mt-1 text-gray-600">Olá, <?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?>. Aqui está o panorama dos seus leads.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/nova.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-plus mr-2"></i> Nova Prospecção
        </a>
        <a href="<?php echo $baseAppUrl; ?>/sdr_dashboard.php?action=listar_leads" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-stream mr-2"></i> Meus Leads
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_stats_cards.php'; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
    <div class="xl:col-span-2 bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Leads que exigem atenção</h2>
            <span class="text-sm text-gray-500">Atualizados a cada 3 dias</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dias sem contato</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($urgentLeads)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-gray-500">Nenhum lead urgente no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($urgentLeads as $lead): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                                    <?php echo htmlspecialchars($lead['nome_prospecto'] ?? 'Lead'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php echo (int)($lead['dias_sem_contato'] ?? 0); ?> dia(s)
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int)($lead['id'] ?? 0); ?>" class="inline-flex items-center px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors mr-2">
                                        <i class="fas fa-eye mr-1"></i> Detalhes
                                    </a>
                                    <a href="<?php echo $qualificationBaseUrl; ?>?action=create&amp;id=<?php echo (int)($lead['id'] ?? 0); ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-check mr-1"></i> Qualificar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <?php require __DIR__ . '/_agendamentos_table.php'; ?>
    </div>
</div>

<div id="vendor-distribution-wrapper" class="bg-white p-6 rounded-lg shadow-md border border-gray-200 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Controle de distribuição</h2>
            <p class="text-sm text-gray-500">Acompanhe como os leads estão distribuídos entre os vendedores.</p>
        </div>
        <div class="text-sm text-gray-600">
            <span class="font-semibold text-indigo-600" id="assigned-leads-count" data-counter="assigned"><?php echo (int) $assignedLeadCount; ?></span>
            leads delegados •
            <span class="font-semibold text-red-500" id="unassigned-leads-count" data-counter="unassigned"><?php echo (int) $unassignedLeadCount; ?></span>
            aguardando vendedor
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="vendor-distribution-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendedor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Leads</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($vendorDistribution as $distributionRow): ?>
                    <?php
                        $vendorId = (int) ($distributionRow['vendorId'] ?? 0);
                        $vendorName = $distributionRow['vendorName'] ?? 'Aguardando vendedor';
                        $totalLeadsVendor = (int) ($distributionRow['total'] ?? 0);
                        $hasAction = $vendorId > 0 && $totalLeadsVendor > 0;
                        $filterUrl = $hasAction
                            ? $baseAppUrl . '/crm/prospeccoes/lista.php?responsavel_id=' . $vendorId
                            : '';
                    ?>
                    <tr data-vendor-row="<?php echo $vendorId; ?>">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-700" data-vendor-name="<?php echo $vendorId; ?>">
                            <?php echo htmlspecialchars($vendorName); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600" data-vendor-total="<?php echo $vendorId; ?>">
                            <?php echo $totalLeadsVendor; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-center">
                            <?php if ($hasAction): ?>
                                <a href="<?php echo htmlspecialchars($filterUrl); ?>" target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-xs"
                                   data-vendor-link="<?php echo $vendorId; ?>">
                                    Ver leads
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">--</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-6">
        <h3 class="text-lg font-semibold text-gray-800">Taxa de conversão por vendedor</h3>
        <?php if (empty($vendorConversionRates)): ?>
            <p class="text-sm text-gray-500 mt-2">Ainda não há dados suficientes para calcular a conversão dos vendedores.</p>
        <?php else: ?>
            <div class="overflow-x-auto mt-3">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendedor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Leads Delegados</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Convertidos</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Taxa de Conversão</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($vendorConversionRates as $conversionRow): ?>
                            <?php
                                $totalLeadsVendor = (int) ($conversionRow['totalLeads'] ?? 0);
                                $convertedLeads = (int) ($conversionRow['convertedLeads'] ?? 0);
                                $conversionRate = $totalLeadsVendor > 0 ? ($convertedLeads / $totalLeadsVendor) * 100 : 0.0;
                                $vendorName = $conversionRow['vendorName'] ?? 'Aguardando vendedor';
                            ?>
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($vendorName); ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?php echo $totalLeadsVendor; ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?php echo $convertedLeads; ?></td>
                                <td class="px-4 py-3 text-sm font-semibold <?php echo $conversionRate >= 50 ? 'text-green-600' : 'text-gray-700'; ?>">
                                    <?php echo number_format($conversionRate, 1, ',', '.'); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/kanban.php'; ?>
