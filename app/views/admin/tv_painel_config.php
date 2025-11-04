<div class="max-w-5xl mx-auto bg-white rounded-xl shadow-lg p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Configurações do Painel de TV</h1>
        <p class="text-gray-600">Personalize as cores, o intervalo de atualização e o formato do painel exibido nos monitores.</p>
    </header>

    <form action="<?php echo APP_URL; ?>/admin.php?action=save_tv_panel_config" method="POST" class="space-y-8" data-tv-config-form>
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Cores por prazo</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="overdue_color" class="block text-sm font-medium text-gray-700 mb-1">Processos atrasados</label>
                    <input type="text" id="overdue_color" name="overdue_color" value="<?php echo htmlspecialchars($settings['overdue_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ex.: bg-red-200 text-red-800" required>
                </div>
                <div>
                    <label for="due_today_color" class="block text-sm font-medium text-gray-700 mb-1">Vencimento hoje</label>
                    <input type="text" id="due_today_color" name="due_today_color" value="<?php echo htmlspecialchars($settings['due_today_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label for="due_soon_color" class="block text-sm font-medium text-gray-700 mb-1">Vence em até 3 dias</label>
                    <input type="text" id="due_soon_color" name="due_soon_color" value="<?php echo htmlspecialchars($settings['due_soon_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label for="on_track_color" class="block text-sm font-medium text-gray-700 mb-1">Prazos confortáveis</label>
                    <input type="text" id="on_track_color" name="on_track_color" value="<?php echo htmlspecialchars($settings['on_track_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Atualização automática</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div>
                    <label for="refresh_interval" class="block text-sm font-medium text-gray-700 mb-1">Intervalo padrão</label>
                    <select id="refresh_interval" name="refresh_interval" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php $options = [60 => '1 minuto', 180 => '3 minutos', 300 => '5 minutos']; ?>
                        <?php foreach ($options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ((int) $settings['refresh_interval'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="color_scheme" class="block text-sm font-medium text-gray-700 mb-1">Tema do painel</label>
                    <select id="color_scheme" name="color_scheme" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="dark" <?php echo ($settings['color_scheme'] === 'dark') ? 'selected' : ''; ?>>Preto (tema escuro)</option>
                        <option value="light" <?php echo ($settings['color_scheme'] === 'light') ? 'selected' : ''; ?>>Branco (tema claro)</option>
                    </select>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Recursos visuais</h2>
            <div class="space-y-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="enable_alert_pulse" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" <?php echo !empty($settings['enable_alert_pulse']) ? 'checked' : ''; ?>>
                    <span class="ml-3 text-sm text-gray-700">Ativar destaque animado para prazos críticos</span>
                </label>
            </div>
        </section>

        <?php
            $panelsData = $panels ?? [];
            $statusesData = $availableStatuses ?? [];
            $panelsJson = htmlspecialchars(json_encode($panelsData, JSON_UNESCAPED_UNICODE));
            $statusesJson = htmlspecialchars(json_encode($statusesData, JSON_UNESCAPED_UNICODE));
        ?>
        <section data-panel-builder data-statuses="<?php echo $statusesJson; ?>" data-panels="<?php echo $panelsJson; ?>" data-panel-url="<?php echo htmlspecialchars(APP_URL . '/admin.php?action=tv_panel&panel_id='); ?>">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-700">Quadros personalizados</h2>
                    <p class="text-sm text-gray-500">Organize diferentes visões simultâneas para acompanhar orçamentos e serviços.</p>
                </div>
                <button type="button" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" data-add-panel>Adicionar painel</button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Processos finalizados, cancelados ou recusados são ocultados automaticamente.</p>
            <div class="space-y-6" data-panel-list></div>
            <input type="hidden" name="panels" id="panels-config-input" value="<?php echo $panelsJson; ?>">
        </section>

        <div class="flex justify-end space-x-4">
            <button type="submit" class="px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Salvar configurações</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const builder = document.querySelector('[data-panel-builder]');
    if (!builder) {
        return;
    }

    const panelsInput = document.getElementById('panels-config-input');
    const list = builder.querySelector('[data-panel-list]');
    const addButton = builder.querySelector('[data-add-panel]');
    const form = document.querySelector('[data-tv-config-form]');
    const panelUrlBase = builder.dataset.panelUrl || '';

    const parseJson = (value, fallback) => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    };

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const basePanels = parseJson(builder.dataset.panels || '[]', []);
    const baseStatuses = parseJson(builder.dataset.statuses || '[]', []);
    const panels = basePanels.length > 0 ? basePanels : [{ id: '', title: '', statuses: [] }];

    const createPanelId = () => {
        return 'panel_' + Math.random().toString(36).slice(2, 10);
    };

    const buildStatusesOptions = (selectedStatuses) => {
        const options = Array.from(new Set([...baseStatuses, ...selectedStatuses]));
        return options.map((status) => {
            const checked = selectedStatuses.includes(status);
            return `
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" value="${escapeHtml(status)}" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" ${checked ? 'checked' : ''}>
                    <span>${escapeHtml(status)}</span>
                </label>
            `;
        }).join('');
    };

    const updateHiddenInput = () => {
        panelsInput.value = JSON.stringify(panels);
    };

    const renderPanels = () => {
        list.innerHTML = '';

        if (panels.length === 0) {
            panels.push({ id: '', title: '', statuses: [] });
        }

        panels.forEach((panel, index) => {
            if (!panel.id) {
                panel.id = createPanelId();
            }

            const panelElement = document.createElement('div');
            panelElement.className = 'border border-gray-200 rounded-lg bg-gray-50 p-6 space-y-4 shadow-sm';
            panelElement.dataset.panelIndex = String(index);

            panelElement.innerHTML = `
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-700" data-panel-heading>${escapeHtml(panel.title || `Painel ${index + 1}`)}</h3>
                    <div class="flex items-center gap-3">
                        <a href="#" target="_blank" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700" data-view-panel>Ver painel</a>
                        <button type="button" class="text-sm font-semibold text-red-600 hover:text-red-700 ${panels.length === 1 ? 'hidden' : ''}" data-remove-panel>Remover</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do painel</label>
                    <input type="text" value="${escapeHtml(panel.title)}" placeholder="Ex.: Orçamentos pendentes" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" data-panel-title>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-2">Filtrar por status</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" data-panel-statuses>
                        ${buildStatusesOptions(panel.statuses || [])}
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Sem seleção significa exibir todos os processos ativos.</p>
                </div>
            `;

            list.appendChild(panelElement);

            const heading = panelElement.querySelector('[data-panel-heading]');
            const titleInput = panelElement.querySelector('[data-panel-title]');
            const removeButton = panelElement.querySelector('[data-remove-panel]');
            const statusContainer = panelElement.querySelector('[data-panel-statuses]');
            const viewButton = panelElement.querySelector('[data-view-panel]');

            if (viewButton) {
                if (panel.id && panelUrlBase) {
                    viewButton.href = panelUrlBase + encodeURIComponent(panel.id);
                    viewButton.classList.remove('pointer-events-none', 'opacity-50');
                } else {
                    viewButton.href = '#';
                    viewButton.classList.add('pointer-events-none', 'opacity-50');
                }
            }

            titleInput.addEventListener('input', () => {
                panels[index].title = titleInput.value;
                heading.textContent = panels[index].title.trim() !== '' ? panels[index].title : `Painel ${index + 1}`;
                updateHiddenInput();
            });

            if (removeButton) {
                removeButton.addEventListener('click', () => {
                    panels.splice(index, 1);
                    updateHiddenInput();
                    renderPanels();
                });
            }

            statusContainer.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const statusValue = checkbox.value;
                    const current = Array.isArray(panels[index].statuses) ? panels[index].statuses.slice() : [];
                    const next = new Set(current);

                    if (checkbox.checked) {
                        next.add(statusValue);
                    } else {
                        next.delete(statusValue);
                    }

                    panels[index].statuses = Array.from(next);
                    updateHiddenInput();
                });
            });
        });

        updateHiddenInput();
    };

    if (addButton) {
        addButton.addEventListener('click', () => {
            panels.push({ id: createPanelId(), title: '', statuses: [] });
            updateHiddenInput();
            renderPanels();
        });
    }

    if (form) {
        form.addEventListener('submit', () => {
            updateHiddenInput();
        });
    }

    renderPanels();
});
</script>
