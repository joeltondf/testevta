<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Campanhas de Automação</h1>
    <div class="flex space-x-4">
        <a href="admin.php?action=automacao_settings" class="text-sm text-gray-600 hover:text-blue-600 flex items-center">
            <i class="fas fa-cog mr-2"></i> Configurar API
        </a>
        <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            + Nova Campanha
        </button>
    </div>
</div>

<?php include __DIR__ . '/../partials/messages.php'; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-1">
        <div class="bg-white shadow-md rounded-lg p-5 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Colunas do Kanban</h2>
                    <p class="text-xs text-gray-500">A ordem define como os leads aparecem no funil e nos gatilhos.</p>
                </div>
                <button id="resetKanbanColumns" type="button" class="text-xs text-blue-600 hover:underline">Restaurar padrão</button>
            </div>

            <form action="admin.php?action=save_kanban_columns" method="post" id="kanbanColumnsForm" class="space-y-4">
                <div id="kanbanColumnsContainer" data-defaults='<?php echo json_encode($kanbanDefaultColumns, JSON_UNESCAPED_UNICODE); ?>'>
                    <?php foreach ($kanbanColumns as $index => $column): ?>
                        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mb-2 column-row">
                            <span class="text-sm text-gray-500 font-medium handle" title="Arraste ou use os botões para reordenar">#<?php echo $index + 1; ?></span>
                            <input type="text" name="kanban_columns[]" value="<?php echo htmlspecialchars($column); ?>" class="flex-1 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <div class="flex items-center gap-1">
                                <button type="button" class="move-btn text-gray-500 hover:text-blue-600" data-action="move-up" title="Subir">&#8593;</button>
                                <button type="button" class="move-btn text-gray-500 hover:text-blue-600" data-action="move-down" title="Descer">&#8595;</button>
                                <button type="button" class="remove-btn text-red-500 hover:text-red-600" data-action="remove" title="Remover">&times;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addKanbanColumn" class="w-full border border-dashed border-blue-400 text-blue-600 py-2 rounded-lg hover:bg-blue-50 text-sm font-semibold">Adicionar coluna</button>
                <p class="text-xs text-gray-500">As campanhas usam estes nomes para identificar os gatilhos. Evite duplicidades.</p>
                <div class="text-right">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Salvar colunas</button>
                </div>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Campanha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gatilhos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canais ativos</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($regras)): ?>
                        <tr>
                            <td colspan="5" class="text-center p-4 text-gray-500">Nenhuma campanha cadastrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regras as $regra): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($regra['nome_campanha']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php
                                        $gatilhos = json_decode($regra['crm_gatilhos'], true);
                                        if (is_array($gatilhos)) {
                                            foreach ($gatilhos as $gatilho) {
                                                echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-50 text-blue-700 mr-1">' . htmlspecialchars($gatilho) . '</span>';
                                            }
                                        }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php if (!empty($regra['digisac_template_id'])): ?>
                                        <span class="inline-flex items-center text-green-600 mr-3"><i class="fab fa-whatsapp mr-1"></i>WhatsApp</span>
                                    <?php endif; ?>
                                    <?php if (!empty($regra['email_assunto'])): ?>
                                        <span class="inline-flex items-center text-blue-600"><i class="fas fa-envelope mr-1"></i>E-mail</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <?php if ($regra['ativo']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                    <button onclick="openTestModal(<?php echo (int)$regra['id']; ?>)" class="text-green-600 hover:text-green-900">Testar</button>
                                    <button onclick="editModal(<?php echo (int)$regra['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <a href="admin.php?action=delete_automacao_campanha&id=<?php echo (int)$regra['id']; ?>" onclick="return confirm('Confirma a exclusão desta campanha?')" class="text-red-600 hover:text-red-900">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="campanhaModal" class="hidden fixed inset-0 z-50 bg-gray-700 bg-opacity-60 overflow-y-auto">
    <div class="relative mx-auto my-10 w-full max-w-4xl">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 id="campanhaModalTitle" class="text-lg font-semibold text-gray-800">Nova campanha de automação</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl" onclick="closeCampaignModal()">&times;</button>
            </div>
            <form id="campanhaForm" method="post" class="px-6 py-5 space-y-4" action="admin.php?action=store_automacao_campanha">
                <input type="hidden" name="id" id="campanhaId">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="campaignName">Nome da campanha</label>
                        <input type="text" name="nome_campanha" id="campaignName" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="campaignStages">Colunas que disparam a automação</label>
                        <select name="crm_gatilhos[]" id="campaignStages" multiple required size="<?php echo max(4, count($kanbanColumns)); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($kanbanColumns as $column): ?>
                                <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Selecione todas as colunas do Kanban que devem acionar esta campanha.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignConnection">Conexão Digisac (WhatsApp)</label>
                        <select name="digisac_conexao_id" id="campaignConnection" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Não enviar WhatsApp --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignTemplate">Template aprovado</label>
                        <select name="digisac_template_id" id="campaignTemplate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecionar template --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignUser">Usuário responsável (opcional)</label>
                        <select name="digisac_user_id" id="campaignUser" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecionar usuário --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignInterval">Intervalo mínimo entre envios (dias)</label>
                        <input type="number" min="0" name="intervalo_reenvio_dias" id="campaignInterval" value="0" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignEmailSubject">Assunto do e-mail</label>
                        <input type="text" name="email_assunto" id="campaignEmailSubject" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Deixe em branco para não enviar e-mail">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="campaignEmailHeader">Cabeçalho HTML</label>
                        <textarea name="email_cabecalho" id="campaignEmailHeader" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="HTML opcional que antecede o corpo"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="campaignEmailBody">Corpo do e-mail</label>
                        <textarea name="email_corpo" id="campaignEmailBody" rows="5" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Use os placeholders disponíveis (ex.: {{clientName}})"></textarea>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700" for="campaignParameters">Mapeamento de parâmetros (JSON)</label>
                    <textarea name="mapeamento_parametros" id="campaignParameters" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder='{"clientName":"{{clientName}}"}'></textarea>
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" name="ativo" id="campaignActive" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <span class="ml-2">Campanha ativa</span>
                    </label>
                    <div class="space-x-2">
                        <button type="button" class="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300" onclick="closeCampaignModal()">Cancelar</button>
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar campanha</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="testModal" class="hidden fixed inset-0 z-50 bg-gray-700 bg-opacity-60 overflow-y-auto">
    <div class="relative mx-auto my-20 w-full max-w-2xl">
        <div class="bg-white rounded-lg shadow-xl p-6">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Testar Campanha</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl" onclick="closeTestModal()">&times;</button>
            </div>
            <form id="testForm" class="space-y-4">
                <input type="hidden" name="campanha_id" id="testCampanhaId">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Canal de teste</label>
                    <div class="mt-2 flex space-x-4">
                        <label class="flex items-center text-sm text-gray-700"><input type="radio" name="test_type" value="whatsapp" checked class="h-4 w-4"><span class="ml-2">WhatsApp</span></label>
                        <label class="flex items-center text-sm text-gray-700"><input type="radio" name="test_type" value="email" class="h-4 w-4"><span class="ml-2">E-mail</span></label>
                    </div>
                </div>

                <div id="whatsapp-test-fields">
                    <label for="testClienteId" class="block text-sm font-medium text-gray-700">ID do cliente</label>
                    <input type="number" name="cliente_id" id="testClienteId" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ex: 123">
                    <p class="text-xs text-gray-500 mt-1">O cliente precisa ter telefone válido (55DDD9XXXXXXXX).</p>
                </div>

                <div id="email-test-fields" class="hidden">
                    <label for="testEmail" class="block text-sm font-medium text-gray-700">E-mail de teste (opcional)</label>
                    <input type="email" name="test_email" id="testEmail" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Informe para sobrescrever o e-mail do cliente">
                </div>

                <div class="flex justify-end space-x-2 border-t border-gray-200 pt-4">
                    <button type="button" onclick="closeTestModal()" class="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Enviar teste</button>
                </div>
            </form>
            <div id="testResult" class="hidden mt-4 p-4 bg-gray-900 text-green-200 font-mono text-sm rounded-md whitespace-pre-wrap max-h-60 overflow-y-auto"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const columnsContainer = document.getElementById('kanbanColumnsContainer');
        const addColumnBtn = document.getElementById('addKanbanColumn');
        const resetColumnsBtn = document.getElementById('resetKanbanColumns');

        function createColumnRow(value = '') {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mb-2 column-row';

            const badge = document.createElement('span');
            badge.className = 'text-sm text-gray-500 font-medium handle';
            badge.textContent = '#0';
            badge.title = 'Arraste ou use os botões para reordenar';
            wrapper.appendChild(badge);

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'kanban_columns[]';
            input.required = true;
            input.className = 'flex-1 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500';
            input.value = value;
            wrapper.appendChild(input);

            const actions = document.createElement('div');
            actions.className = 'flex items-center gap-1';

            const moveUpButton = document.createElement('button');
            moveUpButton.type = 'button';
            moveUpButton.className = 'move-btn text-gray-500 hover:text-blue-600';
            moveUpButton.dataset.action = 'move-up';
            moveUpButton.title = 'Subir';
            moveUpButton.innerHTML = '&#8593;';
            actions.appendChild(moveUpButton);

            const moveDownButton = document.createElement('button');
            moveDownButton.type = 'button';
            moveDownButton.className = 'move-btn text-gray-500 hover:text-blue-600';
            moveDownButton.dataset.action = 'move-down';
            moveDownButton.title = 'Descer';
            moveDownButton.innerHTML = '&#8595;';
            actions.appendChild(moveDownButton);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'remove-btn text-red-500 hover:text-red-600';
            removeButton.dataset.action = 'remove';
            removeButton.title = 'Remover';
            removeButton.innerHTML = '&times;';
            actions.appendChild(removeButton);

            wrapper.appendChild(actions);

            return wrapper;
        }

        function updateOrderIndicators() {
            const rows = columnsContainer.querySelectorAll('.column-row');
            rows.forEach((row, index) => {
                const badge = row.querySelector('.handle');
                if (badge) {
                    badge.textContent = `#${index + 1}`;
                }
            });
        }

        function rebuildColumns(values) {
            columnsContainer.innerHTML = '';
            values.forEach(value => {
                columnsContainer.appendChild(createColumnRow(value));
            });
            updateOrderIndicators();
        }

        addColumnBtn.addEventListener('click', () => {
            columnsContainer.appendChild(createColumnRow(''));
            updateOrderIndicators();
        });

        columnsContainer.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const action = target.dataset.action;
            if (!action) {
                return;
            }

            const row = target.closest('.column-row');
            if (!row) {
                return;
            }

            if (action === 'remove') {
                row.remove();
                updateOrderIndicators();
                return;
            }

            const sibling = action === 'move-up' ? row.previousElementSibling : row.nextElementSibling;
            if (sibling && sibling.classList.contains('column-row')) {
                if (action === 'move-up') {
                    columnsContainer.insertBefore(row, sibling);
                } else {
                    columnsContainer.insertBefore(sibling, row);
                }
                updateOrderIndicators();
            }
        });

        resetColumnsBtn.addEventListener('click', () => {
            const defaults = columnsContainer.dataset.defaults ? JSON.parse(columnsContainer.dataset.defaults) : [];
            rebuildColumns(defaults);
        });

        updateOrderIndicators();

        const campaignModal = document.getElementById('campanhaModal');
        const campaignForm = document.getElementById('campanhaForm');
        const campaignTitle = document.getElementById('campanhaModalTitle');
        const campaignIdInput = document.getElementById('campanhaId');
        const campaignStages = document.getElementById('campaignStages');
        const campaignConnection = document.getElementById('campaignConnection');
        const campaignTemplate = document.getElementById('campaignTemplate');
        const campaignUser = document.getElementById('campaignUser');
        const campaignParameters = document.getElementById('campaignParameters');
        const campaignActive = document.getElementById('campaignActive');

        const digisacCache = {
            connections: null,
            templates: null,
            users: null
        };

        function populateSelect(selectElement, data, placeholderText) {
            const currentValue = selectElement.value;
            selectElement.innerHTML = '';
            if (placeholderText !== null) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = placeholderText;
                selectElement.appendChild(option);
            }
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                selectElement.appendChild(option);
            });
            if (currentValue && selectElement.querySelector(`option[value="${CSS.escape(currentValue)}"]`)) {
                selectElement.value = currentValue;
            }
        }

        async function fetchDigisacData(endpoint) {
            const response = await fetch(endpoint);
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Erro ao carregar dados da Digisac');
            }
            return result.data || [];
        }

        async function ensureDigisacDataLoaded() {
            if (!digisacCache.connections) {
                digisacCache.connections = await fetchDigisacData('admin.php?action=get_digisac_conexoes');
            }
            if (!digisacCache.templates) {
                digisacCache.templates = await fetchDigisacData('admin.php?action=get_digisac_templates');
            }
            if (!digisacCache.users) {
                digisacCache.users = await fetchDigisacData('admin.php?action=get_digisac_users');
            }

            populateSelect(campaignConnection, digisacCache.connections, '-- Não enviar WhatsApp --');
            populateSelect(campaignTemplate, digisacCache.templates, '-- Selecionar template --');
            populateSelect(campaignUser, digisacCache.users, '-- Selecionar usuário --');
        }

        window.openModal = async function () {
            campaignForm.reset();
            campaignForm.action = 'admin.php?action=store_automacao_campanha';
            campaignTitle.textContent = 'Nova campanha de automação';
            campaignIdInput.value = '';
            campaignParameters.value = '';
            campaignActive.checked = true;
            Array.from(campaignStages.options).forEach(option => option.selected = false);

            try {
                await ensureDigisacDataLoaded();
            } catch (error) {
                alert(error.message);
            }

            campaignModal.classList.remove('hidden');
        };

        window.closeCampaignModal = function () {
            campaignModal.classList.add('hidden');
        };

        window.editModal = async function (campanhaId) {
            campaignForm.reset();
            campaignForm.action = 'admin.php?action=update_automacao_campanha';
            campaignTitle.textContent = 'Editar campanha de automação';

            try {
                await ensureDigisacDataLoaded();
            } catch (error) {
                alert(error.message);
            }

            const response = await fetch(`admin.php?action=get_automacao_campanha&id=${campanhaId}`);
            const result = await response.json();

            if (!result.success) {
                alert(result.message || 'Campanha não encontrada.');
                return;
            }

            const data = result.data;
            campaignIdInput.value = data.id;
            document.getElementById('campaignName').value = data.nome_campanha;
            campaignParameters.value = data.mapeamento_parametros ?? '';
            document.getElementById('campaignEmailSubject').value = data.email_assunto ?? '';
            document.getElementById('campaignEmailHeader').value = data.email_cabecalho ?? '';
            document.getElementById('campaignEmailBody').value = data.email_corpo ?? '';
            document.getElementById('campaignInterval').value = data.intervalo_reenvio_dias ?? 0;
            campaignActive.checked = Number(data.ativo) === 1;

            Array.from(campaignStages.options).forEach(option => {
                option.selected = Array.isArray(data.crm_gatilhos) && data.crm_gatilhos.includes(option.value);
            });

            if (data.digisac_conexao_id && campaignConnection.querySelector(`option[value="${CSS.escape(data.digisac_conexao_id)}"]`)) {
                campaignConnection.value = data.digisac_conexao_id;
            }
            if (data.digisac_template_id && campaignTemplate.querySelector(`option[value="${CSS.escape(data.digisac_template_id)}"]`)) {
                campaignTemplate.value = data.digisac_template_id;
            }
            if (data.digisac_user_id && campaignUser.querySelector(`option[value="${CSS.escape(data.digisac_user_id)}"]`)) {
                campaignUser.value = data.digisac_user_id;
            }

            campaignModal.classList.remove('hidden');
        };

        campaignModal.addEventListener('click', (event) => {
            if (event.target === campaignModal) {
                closeCampaignModal();
            }
        });

        const testModal = document.getElementById('testModal');
        const testForm = document.getElementById('testForm');
        const testResult = document.getElementById('testResult');
        const testCampanhaId = document.getElementById('testCampanhaId');
        const whatsappFields = document.getElementById('whatsapp-test-fields');
        const emailFields = document.getElementById('email-test-fields');
        const testClienteId = document.getElementById('testClienteId');
        const testEmail = document.getElementById('testEmail');

        window.openTestModal = function (campanhaId) {
            testForm.reset();
            testResult.classList.add('hidden');
            testResult.innerHTML = '';
            testCampanhaId.value = campanhaId;
            whatsappFields.classList.remove('hidden');
            emailFields.classList.add('hidden');
            testClienteId.required = true;
            testEmail.required = false;
            testModal.classList.remove('hidden');
        };

        window.closeTestModal = function () {
            testModal.classList.add('hidden');
        };

        testModal.addEventListener('click', (event) => {
            if (event.target === testModal) {
                closeTestModal();
            }
        });

        document.querySelectorAll('input[name="test_type"]').forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'email') {
                    emailFields.classList.remove('hidden');
                    testEmail.required = false;
                    testClienteId.required = false;
                } else {
                    emailFields.classList.add('hidden');
                    testClienteId.required = true;
                    testEmail.required = false;
                }
            });
        });

        if (testForm) {
            testForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                testResult.classList.remove('hidden');
                testResult.textContent = 'Enviando teste, aguarde...';

                const formData = new FormData(testForm);

                try {
                    const response = await fetch('admin.php?action=test_automacao_campanha', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    const logOutput = Array.isArray(result.log) ? result.log.join('\n') : '';

                    if (result.success) {
                        testResult.classList.remove('text-red-200');
                        testResult.classList.add('text-green-200');
                        testResult.textContent = 'SUCESSO!\n\n' + logOutput;
                    } else {
                        testResult.classList.remove('text-green-200');
                        testResult.classList.add('text-red-200');
                        testResult.textContent = 'FALHA NO ENVIO.\n\n' + logOutput + '\n\nMensagem: ' + (result.message || 'Erro desconhecido.');
                    }
                } catch (error) {
                    testResult.classList.remove('text-green-200');
                    testResult.classList.add('text-red-200');
                    testResult.textContent = 'Erro de comunicação: ' + error;
                }
            });
        }
    });
</script>
<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
