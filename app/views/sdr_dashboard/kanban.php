<?php
$vendorOptions = $activeVendors ?? [];
$kanbanColumns = $kanbanStatuses ?? [];
$kanbanConfigEndpoint = $kanbanEditUrl ?? ($baseAppUrl . '/sdr_dashboard.php?action=update_columns');
?>

<style>
    .kanban-wrapper {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    }

    .kanban-board {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding-bottom: 1rem;
    }

    .kanban-column {
        flex: 0 0 320px;
        background: #f8fafc;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        transition: border-color 0.2s ease-in-out, transform 0.2s;
    }

    .kanban-column.drag-over {
        border-color: #2563eb;
        transform: translateY(-4px);
    }

    .kanban-column-header {
        padding: 1rem;
        background: linear-gradient(135deg, #2563eb, #4338ca);
        color: #ffffff;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .kanban-cards {
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-height: 150px;
    }

    .kanban-card {
        background: #ffffff;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 1rem;
        cursor: grab;
        box-shadow: 0 10px 15px rgba(15, 23, 42, 0.07);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .kanban-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 22px rgba(15, 23, 42, 0.12);
    }

    .kanban-card.dragging {
        opacity: 0.6;
    }

    .kanban-card-actions a {
        font-size: 0.75rem;
    }

    .kanban-board::-webkit-scrollbar {
        height: 10px;
    }

    .kanban-board::-webkit-scrollbar-track {
        background: #e2e8f0;
        border-radius: 9999px;
    }

    .kanban-board::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 9999px;
    }
</style>

<div class="kanban-wrapper">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Kanban de Leads</h2>
            <p class="text-sm text-gray-500">Arraste os cartões para atualizar o status e organizar suas oportunidades.</p>
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <button type="button" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors" data-open-create-lead>
                <i class="fas fa-plus mr-2"></i> Adicionar lead
            </button>
            <button type="button" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors" data-open-column-editor>
                <i class="fas fa-columns mr-2"></i> Editar colunas
            </button>
            <span class="flex items-center">
                <i class="fas fa-arrows-alt mr-2 text-blue-500"></i>
                Atualização em tempo real
            </span>
        </div>
    </div>

    <div class="kanban-board" id="sdr-kanban-board"
         data-update-url="<?php echo htmlspecialchars($updateStatusUrl); ?>"
         data-assign-url="<?php echo htmlspecialchars($assignOwnerUrl ?? ($baseAppUrl . '/sdr_dashboard.php?action=assign_lead_owner')); ?>">
        <?php foreach ($kanbanColumns as $status): ?>
            <?php $cards = $leadsByStatus[$status] ?? []; ?>
            <section class="kanban-column" data-status="<?php echo htmlspecialchars($status); ?>">
                <header class="kanban-column-header">
                    <span class="font-semibold text-sm"><?php echo htmlspecialchars($status); ?></span>
                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full" data-column-counter="<?php echo htmlspecialchars($status); ?>"><?php echo count($cards); ?></span>
                </header>
                <div class="kanban-cards">
                    <?php if (empty($cards)): ?>
                        <p class="text-sm text-gray-400 text-center py-6">Sem leads aqui.</p>
                    <?php else: ?>
                        <?php foreach ($cards as $lead): ?>
                            <article class="kanban-card" draggable="true"
                                     data-lead-id="<?php echo (int) $lead['id']; ?>"
                                     data-current-status="<?php echo htmlspecialchars($lead['status'] ?? ''); ?>"
                                     data-current-vendor="<?php echo (int) ($lead['responsavel_id'] ?? 0); ?>">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($lead['nome_prospecto'] ?? 'Lead'); ?>
                                        </h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Cliente: <?php echo htmlspecialchars($lead['nome_cliente'] ?? 'Não informado'); ?>
                                        </p>
                                        <?php if (!empty($lead['data_ultima_atualizacao'])): ?>
                                            <p class="text-[11px] text-gray-400 mt-1">
                                                Atualizado em <?php echo date('d/m/Y H:i', strtotime($lead['data_ultima_atualizacao'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-full text-xs font-semibold"
                                          data-vendor-label="<?php echo (int) $lead['id']; ?>">
                                        <?php echo htmlspecialchars($lead['responsavel_nome'] ?? 'SDR'); ?>
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <label class="block text-[11px] uppercase tracking-wide text-gray-500 mb-1">Delegar para vendedor</label>
                                    <select class="assign-vendor-select w-full border border-gray-200 rounded-md text-sm px-2 py-1"
                                            data-lead-id="<?php echo (int) $lead['id']; ?>"
                                            data-current-vendor="<?php echo (int) ($lead['responsavel_id'] ?? 0); ?>">
                                        <option value="">Aguardando vendedor</option>
                                        <?php foreach ($vendorOptions as $vendor): ?>
                                            <option value="<?php echo (int) $vendor['id']; ?>" <?php echo ((int) ($lead['responsavel_id'] ?? 0) === (int) $vendor['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($vendor['nome_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="kanban-card-actions flex items-center gap-2 mt-4">
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) $lead['id']; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        Detalhes
                                    </a>
                                    <a href="<?php echo $qualificationBaseUrl; ?>?action=create&amp;id=<?php echo (int) $lead['id']; ?>" class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        Qualificar
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<div id="kanban-create-lead-modal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Adicionar lead ao Kanban</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-close-create-lead>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="kanban-create-lead-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="kanban_company_name" class="block text-sm font-medium text-gray-700">Nome do lead / empresa</label>
                    <input type="text" id="kanban_company_name" name="company_name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label for="kanban_contact_name" class="block text-sm font-medium text-gray-700">Contato principal</label>
                    <input type="text" id="kanban_contact_name" name="contact_name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="kanban_email" class="block text-sm font-medium text-gray-700">E-mail</label>
                    <input type="email" id="kanban_email" name="email" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="kanban_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                    <input type="text" id="kanban_phone" name="phone" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="kanban_channel" class="block text-sm font-medium text-gray-700">Canal</label>
                    <select id="kanban_channel" name="channel" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php
                        $channelOptions = ['Call', 'LinkedIn', 'Instagram', 'Whatsapp', 'Indicação Cliente', 'Indicação Cartório', 'Website', 'Bitrix', 'Evento', 'Outro'];
                        foreach ($channelOptions as $channelOption): ?>
                            <option value="<?php echo htmlspecialchars($channelOption); ?>" <?php echo $channelOption === 'Outro' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($channelOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="kanban_status" class="block text-sm font-medium text-gray-700">Coluna</label>
                    <select id="kanban_status" name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php foreach ($kanbanColumns as $columnName): ?>
                            <option value="<?php echo htmlspecialchars($columnName); ?>"><?php echo htmlspecialchars($columnName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="kanban_vendor" class="block text-sm font-medium text-gray-700">Delegar para vendedor</label>
                    <select id="kanban_vendor" name="vendor_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Aguardando vendedor</option>
                        <?php foreach ($vendorOptions as $vendor): ?>
                            <option value="<?php echo (int) ($vendor['id'] ?? 0); ?>"><?php echo htmlspecialchars($vendor['nome_completo'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg" data-close-create-lead>Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700" data-submit-create-lead>Adicionar lead</button>
            </div>
        </form>
    </div>
</div>

<div id="kanban-columns-modal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Configurar colunas</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-close-kanban-modal><i class="fas fa-times"></i></button>
        </div>
        <p class="text-sm text-gray-500 mb-4">As colunas "Primeiro Contato" e "Agendamento" são obrigatórias e sempre estarão presentes.</p>
        <form id="kanban-columns-form" class="space-y-4">
            <ul class="space-y-3" data-column-list>
                <?php foreach ($kanbanColumns as $column): ?>
                    <li class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2" data-column-item>
                        <span class="cursor-move text-gray-400"><i class="fas fa-grip-vertical"></i></span>
                        <input type="text" name="columns[]" value="<?php echo htmlspecialchars($column); ?>" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-gray-500 hover:text-gray-700" data-move-up title="Mover para cima"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="text-gray-500 hover:text-gray-700" data-move-down title="Mover para baixo"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" class="text-red-500 hover:text-red-600" data-remove-column title="Remover coluna"><i class="fas fa-times"></i></button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <button type="button" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg" data-add-column>
                    <i class="fas fa-plus mr-2"></i> Nova coluna
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg" data-close-kanban-modal>Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700">Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$vendorOptionsForJs = [];
foreach ($vendorOptions as $vendorOption) {
    $vendorOptionsForJs[] = [
        'id' => (int) ($vendorOption['id'] ?? 0),
        'name' => $vendorOption['nome_completo'] ?? '',
    ];
}
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const board = document.getElementById('sdr-kanban-board');
        if (!board) {
            return;
        }

        const updateUrl = board.dataset.updateUrl;
        const assignUrl = board.dataset.assignUrl;
        const createLeadEndpoint = <?php echo json_encode($baseAppUrl . '/sdr_dashboard.php?action=create_lead'); ?>;
        const baseListUrl = <?php echo json_encode($baseAppUrl . '/crm/prospeccoes/lista.php?responsavel_id='); ?>;
        const detailsBaseUrl = <?php echo json_encode($baseAppUrl . '/crm/prospeccoes/detalhes.php?id='); ?>;
        const qualificationUrlBase = <?php echo json_encode($qualificationBaseUrl); ?>;
        const vendorOptionsList = <?php echo json_encode($vendorOptionsForJs, JSON_UNESCAPED_UNICODE); ?>;
        const columnEditorButton = document.querySelector('[data-open-column-editor]');
        const modal = document.getElementById('kanban-columns-modal');
        const columnList = modal ? modal.querySelector('[data-column-list]') : null;
        const addColumnButton = modal ? modal.querySelector('[data-add-column]') : null;
        const columnForm = document.getElementById('kanban-columns-form');
        const columnEndpoint = <?php echo json_encode($kanbanConfigEndpoint); ?>;
        const createLeadModal = document.getElementById('kanban-create-lead-modal');
        const createLeadButton = document.querySelector('[data-open-create-lead]');
        const createLeadForm = document.getElementById('kanban-create-lead-form');
        const createLeadSubmitButton = createLeadForm ? createLeadForm.querySelector('[data-submit-create-lead]') : null;
        const closeCreateLeadButtons = createLeadModal ? createLeadModal.querySelectorAll('[data-close-create-lead]') : [];
        let draggedCard = null;

        const escapeSelector = value => {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return value.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
        };

        const escapeHtml = value => {
            const div = document.createElement('div');
            div.innerText = value ?? '';
            return div.innerHTML;
        };

        const formatDateTime = value => {
            if (!value) {
                return '';
            }
            const parsed = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }
            return parsed.toLocaleString('pt-BR');
        };

        const buildVendorOptions = selectedId => {
            const options = ['<option value="">Aguardando vendedor</option>'];
            const numericSelected = selectedId === null ? null : Number(selectedId);
            vendorOptionsList.forEach(vendor => {
                const label = escapeHtml(vendor.name || '');
                const selected = vendor.id === numericSelected ? 'selected' : '';
                options.push(`<option value="${vendor.id}" ${selected}>${label}</option>`);
            });
            return options.join('');
        };

        const updateCounters = () => {
            document.querySelectorAll('[data-column-counter]').forEach(counter => {
                const status = counter.getAttribute('data-column-counter');
                const selector = escapeSelector(status);
                const column = document.querySelector(`.kanban-column[data-status="${selector}"] .kanban-cards`);
                if (!column) {
                    return;
                }
                counter.textContent = column.querySelectorAll('.kanban-card').length;
            });
        };

        const updateAssignmentCounters = (assigned, unassigned) => {
            const assignedTargets = document.querySelectorAll('#assigned-leads-count, [data-counter="assigned"], #card-assigned-leads');
            assignedTargets.forEach(node => {
                node.textContent = typeof assigned === 'number' ? assigned : node.textContent;
            });

            const unassignedTargets = document.querySelectorAll('#unassigned-leads-count, [data-counter="unassigned"], #card-unassigned-leads');
            unassignedTargets.forEach(node => {
                node.textContent = typeof unassigned === 'number' ? unassigned : node.textContent;
            });
        };

        const ensureVendorRow = (tableBody, vendorId, vendorName) => {
            let row = tableBody.querySelector(`tr[data-vendor-row="${vendorId}"]`);
            if (!row) {
                row = document.createElement('tr');
                row.setAttribute('data-vendor-row', vendorId);
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm font-semibold text-gray-700" data-vendor-name="${vendorId}">${escapeHtml(vendorName)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600" data-vendor-total="${vendorId}">0</td>
                    <td class="px-4 py-3 text-sm text-center"><span class="text-xs text-gray-400">--</span></td>
                `;
                tableBody.appendChild(row);
            }
            return row;
        };

        const updateDistributionTable = distribution => {
            const tableBody = document.querySelector('#vendor-distribution-table tbody');
            if (!tableBody) {
                return;
            }

            const seen = new Set();

            distribution.forEach(item => {
                const vendorId = String(item.vendorId ?? 0);
                const vendorName = item.vendorName ?? 'Aguardando vendedor';
                const total = parseInt(item.total ?? 0, 10) || 0;
                const row = ensureVendorRow(tableBody, vendorId, vendorName);

                const nameCell = row.querySelector(`[data-vendor-name="${vendorId}"]`);
                const totalCell = row.querySelector(`[data-vendor-total="${vendorId}"]`);

                if (nameCell) {
                    nameCell.textContent = vendorName;
                }

                if (totalCell) {
                    totalCell.textContent = total;
                }

                const actionCell = row.querySelector('td:last-child');
                if (actionCell) {
                    if (parseInt(vendorId, 10) > 0 && total > 0) {
                        const link = document.createElement('a');
                        link.href = `${baseListUrl}${vendorId}`;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.className = 'inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-xs';
                        link.setAttribute('data-vendor-link', vendorId);
                        link.textContent = 'Ver leads';
                        actionCell.innerHTML = '';
                        actionCell.appendChild(link);
                    } else {
                        actionCell.innerHTML = '<span class="text-xs text-gray-400">--</span>';
                    }
                }

                seen.add(vendorId);
            });

            tableBody.querySelectorAll('tr[data-vendor-row]').forEach(row => {
                const vendorId = row.getAttribute('data-vendor-row');
                if (!seen.has(vendorId)) {
                    const totalCell = row.querySelector(`[data-vendor-total="${vendorId}"]`);
                    if (totalCell) {
                        totalCell.textContent = '0';
                    }
                    const actionCell = row.querySelector('td:last-child');
                    if (actionCell) {
                        actionCell.innerHTML = '<span class="text-xs text-gray-400">--</span>';
                    }
                }
            });
        };

        const createKanbanCardElement = lead => {
            const card = document.createElement('article');
            card.className = 'kanban-card';
            card.setAttribute('draggable', 'true');
            card.dataset.leadId = String(lead.id);
            card.dataset.currentStatus = lead.status;
            card.dataset.currentVendor = lead.vendorId ?? 0;

            const prospectName = escapeHtml(lead.prospectName ?? 'Lead');
            const clientName = escapeHtml(lead.clientName ?? 'Não informado');
            const vendorName = escapeHtml(lead.vendorName ?? 'Aguardando vendedor');
            const updatedLabel = formatDateTime(lead.updatedAt);
            const vendorDatasetValue = lead.vendorId ? String(lead.vendorId) : '';

            card.innerHTML = `
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">${prospectName}</h3>
                        <p class="text-xs text-gray-500 mt-1">Cliente: ${clientName}</p>
                        ${updatedLabel ? `<p class="text-[11px] text-gray-400 mt-1">Atualizado em ${updatedLabel}</p>` : ''}
                    </div>
                    <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-full text-xs font-semibold" data-vendor-label="${lead.id}">
                        ${vendorName}
                    </span>
                </div>
                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wide text-gray-500 mb-1">Delegar para vendedor</label>
                    <select class="assign-vendor-select w-full border border-gray-200 rounded-md text-sm px-2 py-1" data-lead-id="${lead.id}" data-current-vendor="${vendorDatasetValue}">
                        ${buildVendorOptions(lead.vendorId ?? null)}
                    </select>
                </div>
                <div class="kanban-card-actions flex items-center gap-2 mt-4">
                    <a href="${detailsBaseUrl}${lead.id}" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Detalhes
                    </a>
                    <a href="${qualificationUrlBase}?action=create&id=${lead.id}" class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Qualificar
                    </a>
                </div>
            `;

            return card;
        };

        const attachCardDragEvents = card => {
            card.addEventListener('dragstart', () => {
                draggedCard = card;
                card.classList.add('dragging');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                draggedCard = null;
            });
        };

        const attachVendorSelectEvents = select => {
            select.addEventListener('change', () => {
                if (!assignUrl) {
                    return;
                }

                const leadId = parseInt(select.dataset.leadId, 10);
                const newVendor = select.value === '' ? null : parseInt(select.value, 10);
                const previousVendor = select.dataset.currentVendor ? parseInt(select.dataset.currentVendor, 10) : null;
                if (!leadId || (newVendor === previousVendor)) {
                    return;
                }

                select.disabled = true;

                fetch(assignUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        leadId,
                        vendorId: newVendor ?? ''
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Não foi possível delegar o lead.');
                        }

                        select.dataset.currentVendor = newVendor ?? '';
                        const label = document.querySelector(`[data-vendor-label="${leadId}"]`);
                        if (label) {
                            label.textContent = data.vendorName || 'Aguardando vendedor';
                        }

                        const card = select.closest('.kanban-card');
                        if (card) {
                            card.dataset.currentVendor = newVendor ?? 0;
                        }

                        updateAssignmentCounters(data.assignedLeadCount ?? null, data.unassignedLeadCount ?? null);
                        if (Array.isArray(data.distribution)) {
                            updateDistributionTable(data.distribution);
                        }
                    })
                    .catch(error => {
                        alert(error.message || 'Não foi possível delegar o lead.');
                        select.value = previousVendor ? String(previousVendor) : '';
                    })
                    .finally(() => {
                        select.disabled = false;
                    });
            });
        };

        const lockMandatoryColumns = () => {
            if (!columnList) {
                return;
            }
            columnList.querySelectorAll('[data-column-item]').forEach(item => {
                const input = item.querySelector('input[name="columns[]"]');
                const name = input ? input.value.trim() : '';
                const isLocked = ['Primeiro Contato', 'Agendamento'].includes(name);
                item.querySelectorAll('[data-remove-column]').forEach(button => {
                    button.disabled = isLocked;
                    button.classList.toggle('opacity-50', isLocked);
                    button.classList.toggle('cursor-not-allowed', isLocked);
                });
            });
        };

        const createColumnItem = (value = '') => {
            if (!columnList) {
                return;
            }
            const li = document.createElement('li');
            li.className = 'flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2';
            li.setAttribute('data-column-item', '');
            li.innerHTML = `
                <span class="cursor-move text-gray-400"><i class="fas fa-grip-vertical"></i></span>
                <input type="text" name="columns[]" value="${escapeHtml(value)}"
                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <div class="flex items-center gap-2">
                    <button type="button" class="text-gray-500 hover:text-gray-700" data-move-up title="Mover para cima"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="text-gray-500 hover:text-gray-700" data-move-down title="Mover para baixo"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" class="text-red-500 hover:text-red-600" data-remove-column title="Remover coluna"><i class="fas fa-times"></i></button>
                </div>
            `;
            columnList.appendChild(li);
            lockMandatoryColumns();
        };

        const closeModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        const openModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        columnEditorButton?.addEventListener('click', openModal);
        modal?.addEventListener('click', event => {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.querySelectorAll('[data-close-kanban-modal]').forEach(button => {
            button.addEventListener('click', closeModal);
        });

        addColumnButton?.addEventListener('click', () => {
            createColumnItem('Nova coluna');
        });

        lockMandatoryColumns();

        columnList?.addEventListener('click', event => {
            const up = event.target.closest('[data-move-up]');
            const down = event.target.closest('[data-move-down]');
            const remove = event.target.closest('[data-remove-column]');

            if (up) {
                const item = up.closest('[data-column-item]');
                const previous = item?.previousElementSibling;
                if (item && previous) {
                    columnList.insertBefore(item, previous);
                    lockMandatoryColumns();
                }
                return;
            }

            if (down) {
                const item = down.closest('[data-column-item]');
                const next = item?.nextElementSibling;
                if (item && next) {
                    columnList.insertBefore(next, item);
                    lockMandatoryColumns();
                }
                return;
            }

            if (remove) {
                if (remove.disabled) {
                    return;
                }
                const item = remove.closest('[data-column-item]');
                item?.remove();
                lockMandatoryColumns();
            }
        });

        columnList?.addEventListener('input', event => {
            if (event.target.matches('input[name="columns[]"]')) {
                lockMandatoryColumns();
            }
        });

        columnForm?.addEventListener('submit', async event => {
            event.preventDefault();
            const formData = new FormData(columnForm);

            try {
                const response = await fetch(columnEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Não foi possível salvar as colunas.');
                    return;
                }
                window.location.reload();
            } catch (error) {
                alert('Erro ao salvar as colunas.');
            }
        });

        document.querySelectorAll('.kanban-card').forEach(card => {
            attachCardDragEvents(card);
        });

        document.querySelectorAll('.kanban-column').forEach(column => {
            const container = column.querySelector('.kanban-cards');

            container.addEventListener('dragover', event => {
                event.preventDefault();
                column.classList.add('drag-over');
            });

            container.addEventListener('dragleave', () => {
                column.classList.remove('drag-over');
            });

            container.addEventListener('drop', event => {
                event.preventDefault();
                column.classList.remove('drag-over');

                if (!draggedCard) {
                    return;
                }

                const leadId = parseInt(draggedCard.dataset.leadId, 10);
                const previousColumn = draggedCard.closest('.kanban-column');
                const previousStatus = draggedCard.dataset.currentStatus;
                const targetStatus = column.getAttribute('data-status');

                if (!leadId || !targetStatus || previousStatus === targetStatus) {
                    container.appendChild(draggedCard);
                    updateCounters();
                    return;
                }

                container.appendChild(draggedCard);
                updateCounters();

                fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        leadId,
                        newStatus: targetStatus
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Não foi possível atualizar o status.');
                        }
                        draggedCard.dataset.currentStatus = targetStatus;
                    })
                    .catch(error => {
                        alert(error.message || 'Não foi possível atualizar o status.');
                        if (previousColumn) {
                            const previousContainer = previousColumn.querySelector('.kanban-cards');
                            previousContainer.appendChild(draggedCard);
                        }
                        updateCounters();
                    });
            });
        });

        document.querySelectorAll('.assign-vendor-select').forEach(select => {
            attachVendorSelectEvents(select);
        });

        const appendLeadToColumn = lead => {
            const selector = escapeSelector(lead.status);
            const column = document.querySelector(`.kanban-column[data-status="${selector}"] .kanban-cards`);
            if (!column) {
                return;
            }
            const card = createKanbanCardElement(lead);
            column.prepend(card);
            attachCardDragEvents(card);
            const select = card.querySelector('.assign-vendor-select');
            if (select) {
                attachVendorSelectEvents(select);
            }
            updateCounters();
        };

        const resetCreateLeadForm = () => {
            createLeadForm?.reset();
        };

        const openCreateLeadModal = () => {
            if (!createLeadModal) {
                return;
            }
            resetCreateLeadForm();
            createLeadModal.classList.remove('hidden');
            createLeadModal.classList.add('flex');
        };

        const closeCreateLeadModal = () => {
            if (!createLeadModal) {
                return;
            }
            createLeadModal.classList.add('hidden');
            createLeadModal.classList.remove('flex');
        };

        createLeadButton?.addEventListener('click', openCreateLeadModal);
        closeCreateLeadButtons.forEach(button => {
            button.addEventListener('click', closeCreateLeadModal);
        });
        createLeadModal?.addEventListener('click', event => {
            if (event.target === createLeadModal) {
                closeCreateLeadModal();
            }
        });

        createLeadForm?.addEventListener('submit', async event => {
            event.preventDefault();
            if (!createLeadSubmitButton) {
                return;
            }

            const formData = new FormData(createLeadForm);
            const payload = {
                companyName: (formData.get('company_name') || '').toString().trim(),
                contactName: (formData.get('contact_name') || '').toString().trim(),
                email: (formData.get('email') || '').toString().trim(),
                phone: (formData.get('phone') || '').toString().trim(),
                channel: (formData.get('channel') || '').toString().trim(),
                status: (formData.get('status') || '').toString().trim(),
                vendorId: formData.get('vendor_id') ? Number(formData.get('vendor_id')) : null,
            };

            if (Number.isNaN(payload.vendorId)) {
                payload.vendorId = null;
            }

            createLeadSubmitButton.disabled = true;
            createLeadSubmitButton.classList.add('opacity-70', 'cursor-not-allowed');

            try {
                const response = await fetch(createLeadEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Não foi possível criar o lead.');
                }

                if (data.lead) {
                    appendLeadToColumn(data.lead);
                }

                updateAssignmentCounters(data.assignedLeadCount ?? null, data.unassignedLeadCount ?? null);
                if (Array.isArray(data.distribution)) {
                    updateDistributionTable(data.distribution);
                }

                closeCreateLeadModal();
            } catch (error) {
                alert(error.message || 'Não foi possível criar o lead.');
            } finally {
                createLeadSubmitButton.disabled = false;
                createLeadSubmitButton.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        });
    });
</script>
