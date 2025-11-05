<?php
$valoresLeads     = $valoresLeads ?? [];
$labelsLeads      = $labelsLeads ?? [];
$valoresPrevisto  = $valoresPrevisto ?? [];
$labelsPrevisto   = $labelsPrevisto ?? [];
$valoresAprovados = $valoresAprovados ?? [];
$labelsAprovados  = $labelsAprovados ?? [];
$valoresFinalizados = $valoresFinalizados ?? [];
$labelsFinalizados  = $labelsFinalizados ?? [];
$sdrSummary = $sdrSummary ?? [];
$sdrVendorSummary = $sdrVendorSummary ?? [];
$vendorCommissionReport = $vendorCommissionReport ?? [];
$budgetOverview = $budgetOverview ?? [
    'budgetCount' => 0,
    'budgetValue' => 0.0,
    'pipelineCount' => 0,
    'pipelineValue' => 0.0,
    'closedCount' => 0,
    'closedValue' => 0.0,
    'averageBudgetValue' => 0.0,
];
$managerKanbanStatuses = $managerKanbanStatuses ?? [];
$managerKanbanLeads = $managerKanbanLeads ?? [];
$selectedKanbanStatuses = $selectedKanbanStatuses ?? $managerKanbanStatuses;
$kanbanFilterOptions = $kanbanFilterOptions ?? [
    'status_options' => $managerKanbanStatuses,
    'vendor_options' => [],
    'sdr_options' => [],
    'payment_profiles' => [],
    'selected_vendor_id' => null,
    'selected_sdr_id' => null,
    'selected_payment_profile' => null,
    'only_unassigned' => false,
];
$leadQueuePreview = $leadQueuePreview ?? [];
$qualificationMetrics = $qualificationMetrics ?? ['qualified' => 0, 'discarded' => 0, 'converted' => 0];
$baseAppUrl = rtrim(APP_URL, '/');
$managerKanbanAction = $baseAppUrl . '/gerente_dashboard.php';
$statusOptions = $kanbanFilterOptions['status_options'] ?? $managerKanbanStatuses;
$vendorOptions = $kanbanFilterOptions['vendor_options'] ?? [];
$sdrOptions = $kanbanFilterOptions['sdr_options'] ?? [];
$paymentProfiles = $kanbanFilterOptions['payment_profiles'] ?? [];
$selectedVendorId = $kanbanFilterOptions['selected_vendor_id'] ?? null;
$selectedSdrId = $kanbanFilterOptions['selected_sdr_id'] ?? null;
$selectedPaymentProfile = $kanbanFilterOptions['selected_payment_profile'] ?? null;
$onlyUnassigned = !empty($kanbanFilterOptions['only_unassigned']);

$totalSdrLeads = array_sum(array_column($sdrSummary, 'totalLeads'));
$assignedSdrLeads = array_sum(array_column($sdrSummary, 'assignedLeads'));
$convertedSdrLeads = array_sum(array_column($sdrSummary, 'convertedLeads'));
$totalSdrAppointments = array_sum(array_column($sdrSummary, 'totalAppointments'));
$averageSdrConversion = $totalSdrLeads > 0 ? ($convertedSdrLeads / $totalSdrLeads) * 100 : 0;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h1 class="text-2xl font-bold text-gray-800 mb-5"><?= htmlspecialchars($pageTitle) ?></h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor em Orçamento (R$)</h3>
    <p class="text-xl font-bold text-green-600">
      R$ <?= number_format($budgetOverview['budgetValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['budgetCount'] ?> orçamentos</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor em Pipeline (R$)</h3>
    <p class="text-xl font-bold text-blue-600">
      R$ <?= number_format($budgetOverview['pipelineValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['pipelineCount'] ?> serviços</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor Fechado (R$)</h3>
    <p class="text-xl font-bold text-purple-600">
      R$ <?= number_format($budgetOverview['closedValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['closedCount'] ?> fechamentos</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Ticket Médio dos Orçamentos (R$)</h3>
    <p class="text-xl font-bold text-orange-600">
      R$ <?= number_format($budgetOverview['averageBudgetValue'], 2, ',', '.') ?>
    </p>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads SRD Gerados</h3>
    <p class="text-xl font-bold text-indigo-600">
      <?= $totalSdrLeads ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads Direcionados</h3>
    <p class="text-xl font-bold text-teal-600">
      <?= $assignedSdrLeads ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads Convertidos</h3>
    <p class="text-xl font-bold text-rose-600">
      <?= $convertedSdrLeads ?>
    </p>
    <p class="text-xs text-gray-500 mt-1">Taxa média <?= number_format($averageSdrConversion, 2, ',', '.') ?>%</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Agendamentos Marcados</h3>
    <p class="text-xl font-bold text-gray-700">
      <?= $totalSdrAppointments ?>
    </p>
  </div>
</div>

<?php if (!empty($managerKanbanStatuses)): ?>
  <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-semibold text-gray-800">Kanban consolidado de prospecção</h2>
        <p class="text-sm text-gray-500">Combine filtros de vendedor, SDR e perfil de pagamento para acompanhar gargalos.</p>
      </div>
      <div class="text-sm text-gray-600">
        Qualificados: <span class="font-semibold text-blue-600"><?php echo (int) ($qualificationMetrics['qualified'] ?? 0); ?></span>
        • Convertidos: <span class="font-semibold text-green-600"><?php echo (int) ($qualificationMetrics['converted'] ?? 0); ?></span>
        • Descartados: <span class="font-semibold text-gray-600"><?php echo (int) ($qualificationMetrics['discarded'] ?? 0); ?></span>
      </div>
    </div>
    <form method="get" action="<?php echo htmlspecialchars($managerKanbanAction); ?>" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
      <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Etapas acompanhadas</label>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-2">
          <?php foreach ($statusOptions as $statusOption): ?>
            <?php $checked = in_array($statusOption, $selectedKanbanStatuses, true); ?>
            <label class="flex items-center bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 cursor-pointer hover:border-blue-300">
              <input type="checkbox" name="kanban_status[]" value="<?php echo htmlspecialchars($statusOption); ?>" class="mr-2" <?php echo $checked ? 'checked' : ''; ?>>
              <?php echo htmlspecialchars($statusOption); ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <label for="kanban_vendor_id" class="block text-sm font-semibold text-gray-700 mb-2">Vendedor</label>
        <select id="kanban_vendor_id" name="kanban_vendor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todos</option>
          <?php foreach ($vendorOptions as $vendor): ?>
            <?php $value = (int) ($vendor['id'] ?? $vendor['vendorId'] ?? 0); ?>
            <?php $name = $vendor['nome_vendedor'] ?? $vendor['nome_completo'] ?? $vendor['vendorName'] ?? 'Vendedor'; ?>
            <option value="<?php echo $value; ?>" <?php echo ($selectedVendorId !== null && (int)$selectedVendorId === $value) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="kanban_sdr_id" class="block text-sm font-semibold text-gray-700 mb-2">SDR</label>
        <select id="kanban_sdr_id" name="kanban_sdr_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todos</option>
          <?php foreach ($sdrOptions as $sdr): ?>
            <?php $value = (int) ($sdr['id'] ?? 0); ?>
            <option value="<?php echo $value; ?>" <?php echo ($selectedSdrId !== null && (int)$selectedSdrId === $value) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($sdr['nome_completo'] ?? 'SDR'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="kanban_payment_profile" class="block text-sm font-semibold text-gray-700 mb-2">Perfil de pagamento</label>
        <select id="kanban_payment_profile" name="kanban_payment_profile" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todos</option>
          <?php foreach ($paymentProfiles as $profile): ?>
            <option value="<?php echo htmlspecialchars($profile); ?>" <?php echo ($selectedPaymentProfile && strcasecmp($selectedPaymentProfile, $profile) === 0) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($profile); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Escopo</label>
        <label class="flex items-center bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 cursor-pointer hover:border-blue-300">
          <input type="checkbox" name="kanban_scope" value="unassigned" class="mr-2" <?php echo $onlyUnassigned ? 'checked' : ''; ?>>
          Apenas leads sem vendedor
        </label>
      </div>
      <div class="md:col-span-5 flex items-center justify-end gap-3">
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors">
          <i class="fas fa-filter mr-2"></i> Aplicar
        </button>
        <a href="<?php echo htmlspecialchars($managerKanbanAction); ?>" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
          <i class="fas fa-undo mr-2"></i> Limpar
        </a>
      </div>
    </form>
    <div class="flex flex-col lg:flex-row gap-6">
      <div class="flex-1 overflow-x-auto">
        <div class="flex gap-4 min-w-full" style="overflow-x:auto; padding-bottom:1rem;">
          <?php foreach ($selectedKanbanStatuses as $status): ?>
            <?php $cards = $managerKanbanLeads[$status] ?? []; ?>
            <section class="flex-0 bg-gray-50 border border-gray-200 rounded-lg min-w-[260px]">
              <header class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-3 rounded-t-lg flex items-center justify-between">
                <span class="text-sm font-semibold"><?php echo htmlspecialchars($status); ?></span>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full"><?php echo count($cards); ?></span>
              </header>
              <div class="p-4 space-y-3">
                <?php if (empty($cards)): ?>
                  <p class="text-sm text-gray-400 text-center py-4">Sem leads nesta etapa.</p>
                <?php else: ?>
                  <?php foreach ($cards as $lead): ?>
                    <article class="bg-white border border-gray-200 rounded-lg shadow-sm p-3">
                      <h3 class="text-sm font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($lead['nome_prospecto'] ?? 'Lead'); ?></h3>
                      <p class="text-xs text-gray-500">Cliente: <?php echo htmlspecialchars($lead['nome_cliente'] ?? 'Não informado'); ?></p>
                      <p class="text-xs text-gray-500 mt-1">Vendedor: <?php echo htmlspecialchars($lead['responsavel_nome'] ?? 'A designar'); ?></p>
                      <?php if (!empty($lead['data_ultima_atualizacao'])): ?>
                        <p class="text-[11px] text-gray-400 mt-1">Atualizado em <?php echo date('d/m/Y H:i', strtotime($lead['data_ultima_atualizacao'])); ?></p>
                      <?php endif; ?>
                      <div class="mt-3 flex items-center gap-2">
                        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) ($lead['id'] ?? 0); ?>" class="px-3 py-1 text-xs bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Detalhes</a>
                        <a href="<?php echo $baseAppUrl; ?>/qualificacao.php?action=create&amp;id=<?php echo (int) ($lead['id'] ?? 0); ?>" class="px-3 py-1 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Acompanhar</a>
                      </div>
                    </article>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="w-full lg:w-72 bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Fila de distribuição</h3>
        <?php if (empty($leadQueuePreview)): ?>
          <p class="text-sm text-gray-500">Nenhum lead pendente na fila.</p>
        <?php else: ?>
          <ul class="space-y-3 text-sm text-gray-600">
            <?php foreach ($leadQueuePreview as $queueItem): ?>
              <li class="border-b pb-2">
                <div class="flex items-center justify-between">
                  <span>Lead #<?php echo (int) ($queueItem['prospeccao_id'] ?? 0); ?></span>
                  <span class="text-xs text-gray-400"><?php echo !empty($queueItem['atualizado_em']) ? date('d/m H:i', strtotime($queueItem['atualizado_em'])) : '--'; ?></span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Tentativas: <?php echo (int) ($queueItem['tentativas'] ?? 0); ?> | Status: <?php echo htmlspecialchars($queueItem['status'] ?? 'Pendente'); ?></p>
                <?php if (!empty($queueItem['mensagem_erro'])): ?>
                  <p class="text-xs text-red-500 mt-1"><?php echo htmlspecialchars($queueItem['mensagem_erro']); ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 ">
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Leads por Vendedor</h3>
    <canvas id="leadsChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Vendas Previstas por Vendedor (R$)</h3>
    <canvas id="previstoChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Serviços Pendentes por Vendedor</h3>
    <canvas id="aprovadosChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Vendas Concluídas por Vendedor</h3>
    <canvas id="finalizadosChart" class="w-full h-full"></canvas>
  </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Resumo do Trabalho do SRD</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">SDR</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Direcionados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Conversão (%)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Orçamento (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Convertido (R$)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($sdrSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['sdrName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['assignedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalBudget'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['convertedBudget'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sdrSummary)): ?>
          <tr>
            <td colspan="7" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Conversões do SRD por Vendedor</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads SRD</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Conversão (%)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Orçamento (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Convertido (R$)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($sdrVendorSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['vendorName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalBudget'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['convertedBudget'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sdrVendorSummary)): ?>
          <tr>
            <td colspan="6" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-cols-1 gap-6 mt-6">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Comissão por Vendedor</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Negócios</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Valor Vendido (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Ticket Médio (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Comissão (R$)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">% Base</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($vendorCommissionReport as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['vendorName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['dealsCount'] ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalSales'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['averageTicket'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalCommission'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['commissionPercent'], 2, ',', '.') ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($vendorCommissionReport)): ?>
          <tr>
            <td colspan="6" class="px-4 py-3 text-center text-gray-500">Nenhum dado de comissão encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<div class="grid grid-cols-1 gap-6 mt-10">
  <!-- Formulário de filtro -->
  <div class="bg-white p-4 rounded-lg shadow">
    <form method="GET" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Data Início:</label>
        <input type="date" name="data_inicio"
               value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Data Fim:</label>
        <input type="date" name="data_fim"
               value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
          Filtrar
        </button>
      </div>
    </form>
  </div>

  <!-- Tabela de performance -->
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Total de Vendas (R$)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Novos Leads (Mês)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa de Conversão (%)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($performance as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['nome']) ?></td>
            <td class="px-4 py-2 text-right"><?= number_format($item['total_vendas'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-center"><?= htmlspecialchars($item['novos_leads_mes']) ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['taxa_conversao'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
  // Leads
  new Chart(document.getElementById('leadsChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsLeads) ?>,
      datasets: [{
        label: 'Total de Leads',
        data: <?= json_encode($valoresLeads) ?>,
        backgroundColor: 'rgba(246, 173, 85, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Valor Previsto
  new Chart(document.getElementById('previstoChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsPrevisto) ?>,
      datasets: [{
        label: 'Valor Previsto (R$)',
        data: <?= json_encode($valoresPrevisto) ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Serviços Pendentes
  new Chart(document.getElementById('aprovadosChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsAprovados) ?>,
      datasets: [{
        label: 'Serviços Pendentes',
        data: <?= json_encode($valoresAprovados) ?>,
        backgroundColor: 'rgba(16, 185, 129, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Vendas Concluídas
  new Chart(document.getElementById('finalizadosChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsFinalizados) ?>,
      datasets: [{
        label: 'Vendas Concluídas',
        data: <?= json_encode($valoresFinalizados) ?>,
        backgroundColor: 'rgba(139, 92, 246, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });
});
</script>
