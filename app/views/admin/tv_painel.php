<?php
$refreshInterval = $settings['refresh_interval'] ?? 60;
$alertEnabled = !empty($settings['enable_alert_pulse']);
$tvEndpoint = APP_URL . '/admin.php?action=tv_panel_data';
$colorConfig = [
    'overdue' => $settings['overdue_color'] ?? 'bg-red-200 text-red-800',
    'due_today' => $settings['due_today_color'] ?? 'bg-red-200 text-red-800',
    'due_soon' => $settings['due_soon_color'] ?? 'bg-yellow-200 text-yellow-800',
    'on_track' => $settings['on_track_color'] ?? 'text-green-600',
];
$theme = $theme ?? ($settings['color_scheme'] ?? 'dark');
$themeClass = $theme === 'light' ? 'tv-panel-theme-light' : 'tv-panel-theme-dark';
$tableThemeClass = $theme === 'light' ? 'divide-y divide-gray-200 tv-panel-table' : 'divide-y divide-slate-800 tv-panel-table';
$theadThemeClass = $theme === 'light' ? 'bg-gray-50 text-gray-600' : 'tv-panel-thead-dark text-slate-200';
$tbodyThemeClass = $theme === 'light' ? 'bg-white divide-y divide-gray-200' : 'divide-y divide-slate-800 tv-panel-tbody-dark';
$showRowStatusColors = $theme === 'light';
$currentPanel = $panel ?? null;
if ($currentPanel === null && isset($processes) && is_array($processes)) {
    $currentPanel = [
        'id' => 'panel_processes',
        'title' => 'Processos Ativos',
        'statuses' => [],
        'processes' => $processes,
    ];
}

$panelId = $currentPanel['id'] ?? 'panel_processes';
$panelTitle = $currentPanel['title'] ?? 'Processos Ativos';
$panelStatuses = $currentPanel['statuses'] ?? [];
$panelProcesses = $currentPanel['processes'] ?? [];
$panelEndpoint = $tvEndpoint . '&panel_id=' . urlencode($panelId);
$tableBodyId = 'processes-table-body-' . $panelId;
?>
<div class="tv-panel-wrapper <?php echo htmlspecialchars($themeClass); ?>">
    <?php
        $processes = $panelProcesses;
        $showActions = false;
        $highlightAnimations = $alertEnabled;
        $deadlineColors = $colorConfig;
        $allowLinks = false;
    ?>
    <div class="tv-panel-container" data-tv-panel
         data-panel-id="<?php echo htmlspecialchars($panelId); ?>"
         data-endpoint="<?php echo htmlspecialchars($panelEndpoint); ?>"
         data-refresh-interval="<?php echo (int) $refreshInterval; ?>"
         data-alert-enabled="<?php echo $alertEnabled ? '1' : '0'; ?>">
        <section class="tv-panel-table-wrapper">
            <?php if (empty($panelProcesses)): ?>
                <div class="tv-panel-empty">Nenhum processo disponível no momento.</div>
            <?php else: ?>
                <?php
                    require __DIR__ . '/../dashboard/partials/process_table.php';
                ?>
            <?php endif; ?>
        </section>

        <footer class="tv-panel-footer">
            <div class="tv-panel-footer-info">
                <div>
                    <span class="font-semibold">Atualização automática:</span> a cada <span data-tv-interval><?php echo (int) ($refreshInterval / 60); ?></span> minuto(s)
                </div>
                <div>
                    <span class="font-semibold">Última atualização:</span> <span data-tv-last-update>Carregando...</span>
                </div>
                <div>
                    <span class="font-semibold">Total de processos:</span> <span data-tv-total><?php echo count($panelProcesses ?? []); ?></span>
                </div>
                <div class="tv-panel-footer-filters">
                    <span class="font-semibold">Filtro:</span>
                    <?php if (!empty($panelStatuses)): ?>
                        <span><?php echo htmlspecialchars(implode(', ', $panelStatuses)); ?></span>
                    <?php else: ?>
                        <span>Todos os processos ativos</span>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="font-semibold">Painel:</span> <span><?php echo htmlspecialchars($panelTitle); ?></span>
                </div>
            </div>
            <div class="tv-panel-meta-inline">
                <div class="tv-panel-clock" data-tv-clock></div>
                <a href="<?php echo APP_URL; ?>/admin.php?action=tv_panel_config" class="tv-panel-config-link">
                    <i class="fas fa-sliders-h mr-2"></i>Configurações
                </a>
            </div>
        </footer>
    </div>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/tv-panel.js"></script>
