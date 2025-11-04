<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';
require_once __DIR__ . '/../../app/services/KanbanConfigService.php';
require_once __DIR__ . '/../../app/views/layouts/header.php';

$kanbanConfigService = new KanbanConfigService($pdo);
$prospectionModel = new Prospeccao($pdo);

$userId = $_SESSION['user_id'] ?? null;
$userProfile = $_SESSION['user_perfil'] ?? '';
$isVendor = ($userProfile === 'vendedor');

$selectedOwnerParam = $_GET['responsavel_id'] ?? null;
$filterOnlyUnassigned = false;
$selectedOwnerId = null;

$paymentProfileLabels = [
    'mensalista' => 'Possível mensalista',
    'avista' => 'Possível à vista',
];

$rawPaymentProfile = $_GET['perfil_pagamento'] ?? '';
$normalizedPaymentProfile = is_string($rawPaymentProfile)
    ? mb_strtolower(trim($rawPaymentProfile), 'UTF-8')
    : '';

if (!array_key_exists($normalizedPaymentProfile, $paymentProfileLabels)) {
    $normalizedPaymentProfile = '';
}

if ($isVendor && $userId !== null) {
    $selectedOwnerId = (int)$userId;
} else {
    if ($selectedOwnerParam === 'none') {
        $filterOnlyUnassigned = true;
    } else {
        $selectedOwnerId = filter_input(INPUT_GET, 'responsavel_id', FILTER_VALIDATE_INT);
        if ($selectedOwnerId === false) {
            $selectedOwnerId = null;
        }
    }
}

$errorMessage = null;

try {
    $kanbanColumns = $kanbanConfigService->getColumns();
    $kanbanOwners = $prospectionModel->getKanbanOwners($kanbanColumns);
    if (!$isVendor) {
        $activeSellers = $prospectionModel->getActiveSellers();
        if (!empty($activeSellers)) {
            $ownersMap = [];

            foreach ($activeSellers as $seller) {
                $ownersMap[(int)$seller['id']] = $seller['nome_completo'];
            }

            foreach ($kanbanOwners as $owner) {
                $ownersMap[(int)$owner['id']] = $owner['nome_completo'];
            }

            $kanbanOwners = [];
            foreach ($ownersMap as $id => $name) {
                $kanbanOwners[] = [
                    'id' => $id,
                    'nome_completo' => $name
                ];
            }
        }
    }
    $hasUnassignedLeads = !$isVendor && $prospectionModel->hasUnassignedKanbanLeads($kanbanColumns, $normalizedPaymentProfile ?: null);

    $kanbanLeads = $prospectionModel->getKanbanLeads(
        $kanbanColumns,
        $selectedOwnerId,
        $filterOnlyUnassigned,
        'responsavel_id',
        $normalizedPaymentProfile ?: null
    );
    $assignableLeads = $prospectionModel->getLeadsOutsideKanban(
        $kanbanColumns,
        $selectedOwnerId,
        $filterOnlyUnassigned,
        'responsavel_id',
        $normalizedPaymentProfile ?: null
    );
} catch (Throwable $exception) {
    $errorMessage = 'Não foi possível carregar o Kanban. Tente novamente mais tarde.';
    error_log('Kanban error: ' . $exception->getMessage());
    $kanbanColumns = $kanbanConfigService->getDefaultColumns();
    $kanbanOwners = [];
    $hasUnassignedLeads = false;
    $kanbanLeads = [];
    $assignableLeads = [];
}

$leadsByStatus = [];
foreach ($kanbanColumns as $column) {
    $leadsByStatus[$column] = [];
}

foreach ($kanbanLeads as $lead) {
    $status = $lead['status'] ?? '';
    if (!isset($leadsByStatus[$status])) {
        continue;
    }
    $leadsByStatus[$status][] = $lead;
}

$defaultFilterValue = 'all';
if ($filterOnlyUnassigned) {
    $defaultFilterValue = 'none';
} elseif ($selectedOwnerId !== null) {
    $defaultFilterValue = (string)$selectedOwnerId;
}

if ($isVendor) {
    $defaultFilterValue = (string)$selectedOwnerId;
    if (empty($kanbanOwners)) {
        $kanbanOwners[] = [
            'id' => $selectedOwnerId,
            'nome_completo' => $_SESSION['user_nome'] ?? 'Meus leads'
        ];
    }
}

$assignableLeadsCount = count($assignableLeads ?? []);
$defaultKanbanDestination = $kanbanColumns[0] ?? '';
$selectedPaymentProfile = $normalizedPaymentProfile;
?>

<style>
    .kanban-board {
        display: flex;
        overflow-x: auto;
        padding-bottom: 1rem;
        min-height: 80vh;
        cursor: grab;
        user-select: none;
    }

    .kanban-board:active {
        cursor: grabbing;
    }

    .kanban-column {
        flex: 0 0 320px;
        margin-right: 1rem;
        background-color: #f9fafb;
        border-radius: 0.75rem;
        display: flex;
        flex-direction: column;
        transition: background-color 0.2s;
    }

    .kanban-column:hover {
        background-color: #f1f5f9;
    }

    .kanban-cards-container {
        flex-grow: 1;
        min-height: 100px;
    }

    .kanban-card {
        cursor: pointer;
        background-color: #ffffff;
        padding: 1rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, background-color 0.2s;
    }

    .kanban-card:hover {
        background-color: #e5e7eb;
        transform: translateY(-5px);
    }

    .kanban-card:active {
        transform: translateY(2px);
    }

    .avatar {
        width: 32px;
        height: 32px;
        background-color: #3b82f6;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: bold;
    }

    .kanban-board::-webkit-scrollbar {
        height: 12px;
    }

    .kanban-board::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 10px;
    }

    .kanban-board::-webkit-scrollbar-thumb {
        background-color: #9ca3af;
        border-radius: 10px;
        border: 3px solid #e5e7eb;
    }

    .kanban-board::-webkit-scrollbar-thumb:hover {
        background-color: #6b7280;
    }

    .kanban-column h3 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #1d4ed8;
        color: white;
        padding: 0.75rem;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        font-size: 0.95rem;
    }

    .kanban-column h3 span {
        font-weight: 500;
    }

    .empty-column-message {
        font-size: 0.875rem;
        color: #64748b;
        padding: 1rem;
        text-align: center;
        background-color: #f1f5f9;
        border-radius: 0.5rem;
    }
</style>

<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b border-gray-200 pb-4 mb-4">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Funil de Vendas (Kanban)</h1>
        <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300">Ver em Lista</a>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <form method="get" class="flex flex-wrap items-center gap-3" id="kanbanFilterForm">
            <?php if ($isVendor): ?>
                <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                    <span class="font-semibold text-blue-700">Filtro ativo:</span>
                    <span><?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Seus leads'); ?></span>
                </div>
                <input type="hidden" name="responsavel_id" value="<?php echo htmlspecialchars($defaultFilterValue, ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
                <label for="sellerFilter" class="text-sm text-gray-700 font-medium">Vendedor</label>
                <select name="responsavel_id" id="sellerFilter" class="border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="" <?php echo $defaultFilterValue === 'all' ? 'selected' : ''; ?>>Todos os vendedores</option>
                    <?php foreach ($kanbanOwners as $owner): ?>
                        <option value="<?php echo (int)$owner['id']; ?>" <?php echo ((string)$owner['id'] === $defaultFilterValue) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($owner['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($hasUnassignedLeads): ?>
                        <option value="none" <?php echo $defaultFilterValue === 'none' ? 'selected' : ''; ?>>Sem responsável</option>
                    <?php endif; ?>
                </select>
            <?php endif; ?>

            <label for="paymentProfileFilter" class="text-sm text-gray-700 font-medium">Perfil de pagamento</label>
            <select name="perfil_pagamento" id="paymentProfileFilter" class="border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="" <?php echo $selectedPaymentProfile === '' ? 'selected' : ''; ?>>Todos os perfis</option>
                <?php foreach ($paymentProfileLabels as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPaymentProfile === $value ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <script>
            (function () {
                const filterForm = document.getElementById('kanbanFilterForm');
                const sellerField = document.getElementById('sellerFilter');
                const paymentField = document.getElementById('paymentProfileFilter');

                if (sellerField) {
                    sellerField.addEventListener('change', () => filterForm.submit());
                }

                if (paymentField) {
                    paymentField.addEventListener('change', () => filterForm.submit());
                }
            })();
        </script>
        <button id="openAddLeadsModal" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $assignableLeadsCount === 0 ? 'disabled' : ''; ?>>Adicionar leads ao Kanban<?php echo $assignableLeadsCount > 0 ? ' (' . $assignableLeadsCount . ')' : ''; ?></button>
        <?php if ($assignableLeadsCount === 0): ?>
            <span class="text-xs text-gray-500">Nenhum lead disponível fora do Kanban para este filtro.</span>
        <?php endif; ?>
        <button id="toggleSelectionBtn" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Selecionar múltiplos</button>
        <div id="bulkActions" class="hidden items-center gap-2">
            <span class="text-sm text-gray-600">Selecionados: <span id="selectedCount">0</span></span>
            <select id="bulkStatusSelect" class="border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php foreach ($kanbanColumns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            <button id="bulkMoveBtn" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>Mover selecionados</button>
        </div>
    </div>
</div>

<?php if ($errorMessage): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 text-sm">
        <?php echo htmlspecialchars($errorMessage); ?>
    </div>
<?php endif; ?>

<div class="kanban-board" id="kanbanBoard">
    <?php foreach ($kanbanColumns as $column): ?>
        <div class="kanban-column">
            <h3>
                <span><?php echo htmlspecialchars($column); ?></span>
                <span class="text-xs font-semibold">(<?php echo count($leadsByStatus[$column] ?? []); ?>)</span>
            </h3>
            <div class="p-3 kanban-cards-container space-y-3" data-status="<?php echo htmlspecialchars($column); ?>">
                <?php if (empty($leadsByStatus[$column])): ?>
                    <p class="empty-column-message">Nenhum lead neste estágio.</p>
                <?php else: ?>
                    <?php foreach ($leadsByStatus[$column] as $lead): ?>
                        <div class="bg-white p-3 rounded-lg shadow-sm kanban-card" data-id="<?php echo (int)$lead['id']; ?>" data-owner-id="<?php echo (int)($lead['responsavel_id'] ?? 0); ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($lead['nome_prospecto']); ?></p>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($lead['nome_cliente'] ?? 'Lead não associado'); ?></p>
                                    <?php if (!empty($lead['responsavel_nome'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">Vendedor: <?php echo htmlspecialchars($lead['responsavel_nome']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($lead['perfil_pagamento'])): ?>
                                        <span class="mt-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600">
                                            <?php echo htmlspecialchars($paymentProfileLabels[$lead['perfil_pagamento']] ?? ucfirst($lead['perfil_pagamento']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <input type="checkbox" class="card-checkbox hidden mt-1 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" aria-label="Selecionar lead">
                            </div>
                            <div class="flex justify-between items-center mt-3">
                                <p class="text-xs text-blue-600 font-bold">R$ <?php echo number_format((float)($lead['valor_proposto'] ?? 0), 2, ',', '.'); ?></p>
                                <?php if (!empty($lead['responsavel_nome'])): ?>
                                    <div class="avatar" title="<?php echo htmlspecialchars($lead['responsavel_nome']); ?>">
                                        <?php echo strtoupper(substr($lead['responsavel_nome'], 0, 2)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-gray-400" title="Sem responsável">--</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="addLeadsModal" class="hidden fixed inset-0 z-40">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="relative bg-white rounded-lg shadow-xl m-4 max-w-3xl w-full z-10 p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Adicionar leads ao Kanban</h2>
                    <p class="text-sm text-gray-500">Selecione os leads fora do Kanban e escolha a coluna de destino.</p>
                </div>
                <button type="button" id="closeAddLeadsModal" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            <div class="border rounded-lg max-h-72 overflow-y-auto mb-4 bg-gray-50">
                <?php if (empty($assignableLeads)): ?>
                    <p class="text-sm text-gray-500 p-4" id="addLeadsEmptyState">Nenhum lead disponível para adicionar.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200" id="addLeadsList">
                        <?php foreach ($assignableLeads as $lead): ?>
                            <li class="flex items-start gap-3 p-3">
                                <input type="checkbox" class="mt-1 add-lead-checkbox h-4 w-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="<?php echo (int)$lead['id']; ?>">
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($lead['nome_prospecto']); ?></p>
                                    <p class="text-xs text-gray-500">Status atual: <?php echo htmlspecialchars($lead['status'] ?? 'Sem status'); ?></p>
                                    <?php if (!empty($lead['responsavel_nome'])): ?>
                                        <p class="text-xs text-gray-500">Vendedor: <?php echo htmlspecialchars($lead['responsavel_nome']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($lead['perfil_pagamento'])): ?>
                                        <span class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600">
                                            <?php echo htmlspecialchars($paymentProfileLabels[$lead['perfil_pagamento']] ?? ucfirst($lead['perfil_pagamento']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <label for="addLeadTargetColumn" class="text-sm text-gray-700 font-medium">Enviar para</label>
                    <select id="addLeadTargetColumn" class="border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($kanbanColumns as $column): ?>
                            <option value="<?php echo htmlspecialchars($column); ?>" <?php echo $column === $defaultKanbanDestination ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($column); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" id="cancelAddLeadsBtn" class="border border-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-100">Cancelar</button>
                    <button type="button" id="confirmAddLeadsBtn" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed" <?php echo empty($assignableLeads) ? 'disabled' : ''; ?>>Adicionar selecionados</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="cardModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div id="modalContent" class="bg-white rounded-lg shadow-xl m-4 max-w-2xl w-full z-10 p-6 space-y-4">
            <p class="text-center">Carregando...</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_BASE_URL = '<?php echo APP_URL; ?>/crm/prospeccoes';

        const containers = document.querySelectorAll('.kanban-cards-container');
        const sortableInstances = [];

        containers.forEach(container => {
            const sortable = new Sortable(container, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: handleSortEnd
            });
            sortableInstances.push(sortable);
        });

        const toggleSelectionBtn = document.getElementById('toggleSelectionBtn');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCountEl = document.getElementById('selectedCount');
        const bulkStatusSelect = document.getElementById('bulkStatusSelect');
        const bulkMoveBtn = document.getElementById('bulkMoveBtn');
        const addLeadsModal = document.getElementById('addLeadsModal');
        const openAddLeadsBtn = document.getElementById('openAddLeadsModal');
        const closeAddLeadsBtn = document.getElementById('closeAddLeadsModal');
        const cancelAddLeadsBtn = document.getElementById('cancelAddLeadsBtn');
        const confirmAddLeadsBtn = document.getElementById('confirmAddLeadsBtn');
        const addLeadTargetSelect = document.getElementById('addLeadTargetColumn');

        const selectionElementsExist = toggleSelectionBtn && bulkActions && selectedCountEl && bulkStatusSelect && bulkMoveBtn;

        let selectionMode = false;
        const selectedCards = new Set();

        if (openAddLeadsBtn && addLeadsModal) {
            const closeAddLeadsModal = () => addLeadsModal.classList.add('hidden');

            openAddLeadsBtn.addEventListener('click', () => {
                if (openAddLeadsBtn.hasAttribute('disabled')) {
                    return;
                }
                addLeadsModal.classList.remove('hidden');
            });

            [closeAddLeadsBtn, cancelAddLeadsBtn].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', closeAddLeadsModal);
                }
            });

            addLeadsModal.addEventListener('click', event => {
                if (event.target === addLeadsModal) {
                    closeAddLeadsModal();
                }
            });

            if (confirmAddLeadsBtn) {
                confirmAddLeadsBtn.addEventListener('click', () => {
                    const checkboxes = Array.from(document.querySelectorAll('.add-lead-checkbox'));
                    const selectedIds = checkboxes
                        .filter(checkbox => checkbox.checked)
                        .map(checkbox => checkbox.value);

                    if (selectedIds.length === 0) {
                        alert('Selecione ao menos um lead para adicionar.');
                        return;
                    }

                    const targetStatus = addLeadTargetSelect ? addLeadTargetSelect.value : '';
                    if (!targetStatus) {
                        alert('Escolha a coluna de destino.');
                        return;
                    }

                    confirmAddLeadsBtn.disabled = true;
                    const originalText = confirmAddLeadsBtn.textContent;
                    confirmAddLeadsBtn.textContent = 'Adicionando...';

                    const updatePromises = selectedIds.map(id => postStatusChange(id, targetStatus));

                    Promise.allSettled(updatePromises).then(results => {
                        const hasError = results.some(result => result.status === 'rejected');
                        if (hasError) {
                            alert('Alguns leads não foram adicionados corretamente. A página será recarregada.');
                        }
                        window.location.reload();
                    }).catch(() => {
                        alert('Erro ao adicionar leads ao Kanban.');
                        confirmAddLeadsBtn.disabled = false;
                        confirmAddLeadsBtn.textContent = originalText;
                    });
                });
            }
        }

        if (selectionElementsExist) {
            toggleSelectionBtn.addEventListener('click', () => {
                selectionMode = !selectionMode;
                toggleSelectionBtn.textContent = selectionMode ? 'Cancelar seleção' : 'Selecionar múltiplos';
                bulkActions.classList.toggle('hidden', !selectionMode);
                bulkActions.classList.toggle('flex', selectionMode);

                if (!selectionMode) {
                    selectedCards.clear();
                }

                refreshSelectionState();
            });

            bulkMoveBtn.addEventListener('click', () => {
                if (selectedCards.size === 0) {
                    return;
                }

                const targetStatus = bulkStatusSelect.value;

                if (!targetStatus) {
                    alert('Selecione uma coluna de destino.');
                    return;
                }

                bulkMoveBtn.disabled = true;

                const ids = Array.from(selectedCards);
                const updatePromises = ids.map(id => postStatusChange(id, targetStatus));

                Promise.allSettled(updatePromises).then(results => {
                    const hasError = results.some(result => result.status === 'rejected');
                    if (hasError) {
                        alert('Alguns leads não foram atualizados corretamente. A página será recarregada.');
                    }
                    window.location.reload();
                });
            });
        }

        function postStatusChange(prospeccaoId, novoStatus) {
            return fetch(`${API_BASE_URL}/atualizar_status_kanban.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prospeccao_id: prospeccaoId, novo_status: novoStatus })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao atualizar o status.');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao atualizar o status.');
                }
                return data;
            });
        }

        function handleSortEnd(evt) {
            const prospeccaoId = evt.item.dataset.id;
            const novoStatus = evt.to.dataset.status;

            postStatusChange(prospeccaoId, novoStatus).catch(() => {
                alert('Erro ao atualizar.');
                location.reload();
            });
        }

        function toggleCardSelection(card, shouldSelect) {
            if (!selectionElementsExist) {
                return;
            }

            const cardId = card.dataset.id;
            if (!cardId) {
                return;
            }

            if (shouldSelect) {
                selectedCards.add(cardId);
            } else {
                selectedCards.delete(cardId);
            }

            refreshSelectionState();
        }

        function refreshSelectionState() {
            if (!selectionElementsExist) {
                return;
            }

            sortableInstances.forEach(instance => instance.option('disabled', selectionMode));

            document.querySelectorAll('.kanban-card').forEach(card => {
                const checkbox = card.querySelector('.card-checkbox');
                if (!checkbox) {
                    return;
                }

                if (selectionMode) {
                    checkbox.classList.remove('hidden');
                    const isSelected = selectedCards.has(card.dataset.id);
                    checkbox.checked = isSelected;
                    card.classList.toggle('ring-2', isSelected);
                    card.classList.toggle('ring-blue-500', isSelected);
                    card.classList.toggle('bg-blue-50', isSelected);
                } else {
                    checkbox.classList.add('hidden');
                    checkbox.checked = false;
                    card.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
                }
            });

            selectedCountEl.textContent = selectedCards.size;
            bulkMoveBtn.disabled = selectedCards.size === 0;
        }

        const modal = document.getElementById('cardModal');
        const modalContent = document.getElementById('modalContent');

        document.querySelectorAll('.kanban-card').forEach(card => {
            const checkbox = card.querySelector('.card-checkbox');

            if (selectionElementsExist && checkbox) {
                checkbox.addEventListener('click', (event) => event.stopPropagation());
                checkbox.addEventListener('change', (event) => {
                    toggleCardSelection(card, event.target.checked);
                });
            }

            card.addEventListener('click', function(event) {
                if (selectionElementsExist && selectionMode) {
                    const isSelected = selectedCards.has(card.dataset.id);
                    toggleCardSelection(card, !isSelected);
                    event.preventDefault();
                    return;
                }

                const prospeccaoId = this.dataset.id;
                openCardModal(prospeccaoId);
            });
        });

        if (selectionElementsExist) {
            refreshSelectionState();
        }

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });

        function openCardModal(id) {
            modal.classList.remove('hidden');
            modalContent.innerHTML = '<p class="text-center">Carregando detalhes...</p>';

            fetch(`${API_BASE_URL}/api_get_prospeccao_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.prospect;
                        let interacoesHtml = '';
                        data.interacoes.forEach(i => {
                            const dataFormatada = new Date(i.data_interacao).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            interacoesHtml += `<div class="text-xs border-t pt-2 mt-2"><p class="text-gray-500">${i.usuario_nome || 'Sistema'} - ${dataFormatada}</p><p class="text-gray-800">${i.observacao}</p></div>`;
                        });

                        modalContent.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">${p.nome_prospecto}</h3>
                                    <p class="text-sm text-gray-600">${p.cliente_nome}</p>
                                </div>
                                <button onclick="document.getElementById('cardModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm">Últimas Interações</h4>
                                <div class="mt-2 space-y-2 max-h-40 overflow-y-auto p-2 bg-gray-50 rounded">${interacoesHtml || '<p class="text-xs text-gray-500">Nenhuma interação recente.</p>'}</div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm">Adicionar Nota Rápida</h4>
                                <form onsubmit="addQuickNote(event, ${p.id})">
                                    <textarea name="observacao" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" rows="2" required></textarea>
                                    <div class="text-right mt-2">
                                        <button type="submit" class="bg-blue-600 text-white font-bold py-1 px-3 rounded-lg text-sm">Adicionar</button>
                                    </div>
                                </form>
                            </div>
                            <div class="text-center border-t pt-3">
                                <a href="detalhes.php?id=${p.id}" class="text-sm text-indigo-600 hover:underline">Ver todos os detalhes &rarr;</a>
                            </div>
                        `;
                    } else {
                        modalContent.innerHTML = `<p class="text-red-500">Erro: ${data.message}</p>`;
                    }
                });
        }

        window.addQuickNote = function(event, prospeccaoId) {
            event.preventDefault();
            const form = event.target;
            const textarea = form.querySelector('textarea');

            const formData = new FormData();
            formData.append('action', 'add_interaction');
            formData.append('prospeccao_id', prospeccaoId);
            formData.append('observacao', textarea.value);

            fetch(`${API_BASE_URL}/atualizar.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    openCardModal(prospeccaoId);
                } else {
                    alert('Erro ao adicionar a nota.');
                }
            });
        }

        const board = document.getElementById('kanbanBoard');
        let isPanning = false;
        let startX;
        let scrollLeft;

        board.addEventListener('mousedown', (event) => {
            if (event.target.closest('.kanban-card')) {
                return;
            }
            isPanning = true;
            board.classList.add('active');
            startX = event.pageX - board.offsetLeft;
            scrollLeft = board.scrollLeft;
        });

        ['mouseleave', 'mouseup'].forEach(eventName => {
            board.addEventListener(eventName, () => {
                isPanning = false;
                board.classList.remove('active');
            });
        });

        board.addEventListener('mousemove', (event) => {
            if (!isPanning) {
                return;
            }
            event.preventDefault();
            const x = event.pageX - board.offsetLeft;
            const walk = (x - startX) * 2;
            board.scrollLeft = scrollLeft - walk;
        });
    });
</script>

<?php
require_once __DIR__ . '/../../app/views/layouts/footer.php';
?>
