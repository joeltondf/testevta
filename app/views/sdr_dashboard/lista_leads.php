<?php
$baseAppUrl = rtrim(APP_URL, '/');
$leads = $leads ?? [];
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leads do SDR</h1>
        <p class="mt-1 text-gray-600">Visualize todos os leads sob sua responsabilidade e acompanhe o status atual.</p>
    </div>
    <a href="<?php echo $baseAppUrl; ?>/sdr_dashboard.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
    </a>
</div>

<div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Meus leads</h2>
        <div class="relative w-full md:w-80">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" id="lead-search" placeholder="Filtrar por nome, cliente ou status"
                   class="w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="lead-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vendedor</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Atualização</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" data-lead-body>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500 text-sm">Nenhum lead atribuído.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $leadId = (int) ($lead['id'] ?? 0);
                            $leadName = $lead['nome_prospecto'] ?? 'Lead';
                            $clientName = $lead['nome_cliente'] ?? 'Cliente não informado';
                            $status = $lead['status'] ?? 'Sem status';
                            $vendor = $lead['vendor_name'] ?? 'Aguardando vendedor';
                            $updatedAt = isset($lead['data_ultima_atualizacao']) ? date('d/m/Y H:i', strtotime($lead['data_ultima_atualizacao'])) : '--';
                            $value = isset($lead['valor_proposto']) ? number_format((float) $lead['valor_proposto'], 2, ',', '.') : null;
                        ?>
                        <tr data-lead-row>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                                <div><?php echo htmlspecialchars($leadName); ?></div>
                                <?php if ($value !== null): ?>
                                    <p class="text-xs text-gray-500">Valor proposto: R$ <?php echo $value; ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($clientName); ?></td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-50 text-blue-600" data-lead-status><?php echo htmlspecialchars($status); ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($vendor); ?></td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?php echo $updatedAt; ?></td>
                            <td class="px-4 py-3 text-center text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo $leadId; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-xs">
                                        Detalhes
                                    </a>
                                    <a href="<?php echo $baseAppUrl; ?>/qualificacao.php?action=create&amp;id=<?php echo $leadId; ?>" class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-xs">
                                        Qualificar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('lead-search');
        const rows = Array.from(document.querySelectorAll('#lead-table [data-lead-row]'));
        const statusClasses = {
            Qualificado: 'bg-green-50 text-green-600',
            'Primeiro Contato': 'bg-indigo-50 text-indigo-600',
            Agendamento: 'bg-orange-50 text-orange-600',
            Descoberta: 'bg-purple-50 text-purple-600',
            Negociação: 'bg-yellow-50 text-yellow-600'
        };

        rows.forEach(row => {
            const badge = row.querySelector('[data-lead-status]');
            if (!badge) {
                return;
            }
            const status = badge.textContent.trim();
            const classes = statusClasses[status] || 'bg-gray-100 text-gray-600';
            badge.className = `inline-flex items-center px-2 py-1 rounded-full ${classes}`;
        });

        searchInput?.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            rows.forEach(row => {
                const haystack = row.innerText.toLowerCase();
                row.classList.toggle('hidden', term !== '' && !haystack.includes(term));
            });
        });
    });
</script>
