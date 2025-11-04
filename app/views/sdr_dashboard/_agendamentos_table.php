<h2 class="text-xl font-semibold text-gray-800 mb-4">Próximos agendamentos</h2>
<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" id="schedule-search" placeholder="Buscar por lead, responsável ou status"
                   class="w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <a href="<?php echo $baseAppUrl; ?>/crm/agendamentos/novo_agendamento.php"
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors text-sm">
            <i class="fas fa-plus mr-2"></i> Novo agendamento
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="schedule-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Responsável</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Início</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" data-schedule-body>
            <?php if (empty($upcomingMeetings)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 text-sm">Nenhum agendamento futuro encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($upcomingMeetings as $meeting): ?>
                    <?php
                        $meetingId = (int) ($meeting['id'] ?? 0);
                        $title = $meeting['titulo'] ?? 'Agendamento';
                        $client = $meeting['nome_cliente'] ?? 'Lead não definido';
                        $owner = $meeting['responsavel_nome'] ?? 'Sem responsável';
                        $status = $meeting['status'] ?? 'Confirmado';
                        $startAt = isset($meeting['data_inicio']) ? date('d/m/Y H:i', strtotime($meeting['data_inicio'])) : '--';
                        $startIso = isset($meeting['data_inicio']) ? date('Y-m-d\TH:i', strtotime($meeting['data_inicio'])) : '';
                        $endIso = isset($meeting['data_fim']) ? date('Y-m-d\TH:i', strtotime($meeting['data_fim'])) : '';
                    ?>
                    <tr data-meeting-row
                        data-meeting-id="<?php echo $meetingId; ?>"
                        data-meeting-title="<?php echo htmlspecialchars($title); ?>"
                        data-meeting-status="<?php echo htmlspecialchars($status); ?>"
                        data-meeting-start="<?php echo htmlspecialchars($startIso); ?>"
                        data-meeting-end="<?php echo htmlspecialchars($endIso); ?>"
                        data-meeting-prospect="<?php echo (int) ($meeting['prospeccao_id'] ?? 0); ?>"
                        data-meeting-client="<?php echo htmlspecialchars($client); ?>">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                            <div><?php echo htmlspecialchars($title); ?></div>
                            <p class="text-xs text-gray-500">Lead: <?php echo htmlspecialchars($client); ?></p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($owner); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo $startAt; ?></td>
                        <td class="px-4 py-3 text-xs font-semibold">
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-50 text-blue-600" data-schedule-status><?php echo htmlspecialchars($status); ?></span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" class="px-2 py-1 text-blue-600 hover:text-blue-800" data-edit-meeting>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="px-2 py-1 text-red-600 hover:text-red-800" data-delete-meeting>
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php if (!empty($meeting['prospeccao_id'])): ?>
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) $meeting['prospeccao_id']; ?>"
                                       class="px-2 py-1 text-gray-500 hover:text-gray-700" title="Abrir prospecção">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="schedule-modal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Editar agendamento</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <form id="schedule-form" class="space-y-4">
            <input type="hidden" name="agendamento_id" id="schedule-id" />
            <div>
                <label for="schedule-title" class="block text-sm font-medium text-gray-700">Título</label>
                <input type="text" id="schedule-title" name="titulo" required
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="schedule-start" class="block text-sm font-medium text-gray-700">Início</label>
                    <input type="datetime-local" id="schedule-start" name="data_inicio" required
                           class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label for="schedule-end" class="block text-sm font-medium text-gray-700">Fim</label>
                    <input type="datetime-local" id="schedule-end" name="data_fim" required
                           class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
            </div>
            <div>
                <label for="schedule-status" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="schedule-status" name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php $statusOptions = ['Confirmado', 'Pendente', 'Realizado', 'Cancelado'];
                    foreach ($statusOptions as $option): ?>
                        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center justify-end gap-2 pt-3">
                <button type="button" class="px-4 py-2 text-sm text-gray-600 rounded-lg border border-gray-300" data-close-modal>Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('schedule-search');
        const tableBody = document.querySelector('[data-schedule-body]');
        const modal = document.getElementById('schedule-modal');
        const form = document.getElementById('schedule-form');
        const rows = [];
        const statusLabels = {
            Confirmado: 'bg-blue-50 text-blue-600',
            Pendente: 'bg-yellow-50 text-yellow-600',
            Realizado: 'bg-green-50 text-green-600',
            Cancelado: 'bg-red-50 text-red-600'
        };
        const apiEndpoint = <?php echo json_encode($baseAppUrl . '/crm/agendamentos/api_eventos.php?context=sdr-table'); ?>;

        const formatDateTime = (value) => {
            if (!value) {
                return '--';
            }
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '--';
            }
            return new Intl.DateTimeFormat('pt-BR', {
                dateStyle: 'short',
                timeStyle: 'short'
            }).format(date);
        };

        const applyStatusStyle = (row) => {
            const badge = row.querySelector('[data-schedule-status]');
            if (!badge) {
                return;
            }
            const status = badge.textContent.trim();
            const classes = statusLabels[status] || 'bg-gray-100 text-gray-600';
            badge.className = `inline-flex items-center px-2 py-1 rounded-full ${classes}`;
        };

        const openModal = (row) => {
            if (!modal || !form) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            form.reset();
            document.getElementById('schedule-id').value = row.dataset.meetingId;
            document.getElementById('schedule-title').value = row.dataset.meetingTitle;
            document.getElementById('schedule-start').value = row.dataset.meetingStart;
            document.getElementById('schedule-end').value = row.dataset.meetingEnd;
            document.getElementById('schedule-status').value = row.dataset.meetingStatus;
        };

        const closeModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.querySelectorAll('[data-close-modal]').forEach(button => {
            button.addEventListener('click', closeModal);
        });

        const bindRowActions = (row) => {
            const editButton = row.querySelector('[data-edit-meeting]');
            const deleteButton = row.querySelector('[data-delete-meeting]');

            editButton?.addEventListener('click', () => openModal(row));

            deleteButton?.addEventListener('click', async () => {
                if (!confirm('Deseja remover este agendamento?')) {
                    return;
                }

                try {
                    const response = await fetch('<?php echo $baseAppUrl; ?>/crm/agendamentos/excluir_agendamento.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({agendamento_id: row.dataset.meetingId})
                    });

                    const data = await response.json();
                    if (!data.success) {
                        alert(data.message || 'Não foi possível remover o agendamento.');
                        return;
                    }

                    const index = rows.indexOf(row);
                    if (index >= 0) {
                        rows.splice(index, 1);
                    }
                    row.remove();
                } catch (error) {
                    alert('Erro ao remover o agendamento.');
                }
            });
        };

        const rebuildRows = (data) => {
            if (!tableBody) {
                return;
            }

            tableBody.innerHTML = '';
            rows.length = 0;

            if (!Array.isArray(data) || data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 text-sm">Nenhum agendamento futuro encontrado.</td></tr>';
                return;
            }

            data.forEach(item => {
                const row = document.createElement('tr');
                const meetingId = parseInt(item.id ?? 0, 10) || 0;
                const startIso = item.data_inicio ? item.data_inicio.replace(' ', 'T') : '';
                const endIso = item.data_fim ? item.data_fim.replace(' ', 'T') : '';
                const status = item.status || 'Confirmado';
                const client = item.nome_cliente || 'Lead não definido';
                const owner = item.responsavel_nome || 'Sem responsável';

                row.setAttribute('data-meeting-row', '');
                row.dataset.meetingId = meetingId;
                row.dataset.meetingTitle = item.titulo || 'Agendamento';
                row.dataset.meetingStatus = status;
                row.dataset.meetingStart = startIso;
                row.dataset.meetingEnd = endIso;
                row.dataset.meetingProspect = item.prospeccao_id ?? 0;
                row.dataset.meetingClient = client;

                row.innerHTML = `
                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                        <div>${row.dataset.meetingTitle}</div>
                        <p class="text-xs text-gray-500">Lead: ${client}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${owner}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${formatDateTime(startIso)}</td>
                    <td class="px-4 py-3 text-xs font-semibold"><span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-50 text-blue-600" data-schedule-status>${status}</span></td>
                    <td class="px-4 py-3 text-center text-sm">
                        <div class="flex items-center justify-center gap-2">
                            <button type="button" class="px-2 py-1 text-blue-600 hover:text-blue-800" data-edit-meeting><i class="fas fa-edit"></i></button>
                            <button type="button" class="px-2 py-1 text-red-600 hover:text-red-800" data-delete-meeting><i class="fas fa-trash"></i></button>
                            ${item.prospeccao_id ? `<a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=${item.prospeccao_id}" class="px-2 py-1 text-gray-500 hover:text-gray-700" title="Abrir prospecção"><i class="fas fa-external-link-alt"></i></a>` : '<span class="text-xs text-gray-300">--</span>'}
                        </div>
                    </td>
                `;

                tableBody.appendChild(row);
                rows.push(row);
                applyStatusStyle(row);
                bindRowActions(row);
            });
        };

        const refreshFromApi = async (query = '') => {
            try {
                const url = query ? `${apiEndpoint}&q=${encodeURIComponent(query)}` : apiEndpoint;
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error('Erro ao buscar agendamentos.');
                }
                const data = await response.json();
                rebuildRows(data);
            } catch (error) {
                console.warn(error.message);
            }
        };

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);

            try {
                const response = await fetch('<?php echo $baseAppUrl; ?>/crm/agendamentos/editar_agendamento.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Não foi possível atualizar o agendamento.');
                    return;
                }

                await refreshFromApi(searchInput?.value.trim() ?? '');
                closeModal();
            } catch (error) {
                alert('Erro ao atualizar o agendamento.');
            }
        });

        refreshFromApi();

        searchInput?.addEventListener('input', () => {
            const term = searchInput.value.trim();
            if (term.length >= 3 || term.length === 0) {
                refreshFromApi(term);
            }
        });
    });
</script>
