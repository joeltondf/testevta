<?php
$leadsDistributed = (int) ($stats['leadsDistribuidos'] ?? 0);
$appointmentsCount = (int) ($stats['agendamentosRealizados'] ?? 0);
$appointmentRate = (float) ($stats['taxaAgendamento'] ?? 0);
$qualificationMetrics = $qualificationMetrics ?? ['qualified' => 0, 'discarded' => 0, 'converted' => 0];
$qualifiedCount = (int) ($qualificationMetrics['qualified'] ?? 0);
$convertedCount = (int) ($qualificationMetrics['converted'] ?? 0);
$discardedCount = (int) ($qualificationMetrics['discarded'] ?? 0);
$nextVendorPreview = $nextVendorPreview ?? null;
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Leads distribuídos</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $leadsDistributed; ?></p>
        </div>
        <span class="bg-indigo-100 text-indigo-600 p-3 rounded-full"><i class="fas fa-random"></i></span>
    </div>
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Agendamentos realizados</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $appointmentsCount; ?></p>
        </div>
        <span class="bg-green-100 text-green-600 p-3 rounded-full"><i class="fas fa-calendar-check"></i></span>
    </div>
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Taxa de agendamento</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($appointmentRate, 1, ',', '.'); ?>%</p>
        </div>
        <span class="bg-blue-100 text-blue-600 p-3 rounded-full"><i class="fas fa-percentage"></i></span>
    </div>
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Qualificados • Convertidos</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $qualifiedCount; ?> / <?php echo $convertedCount; ?></p>
            <p class="text-xs text-gray-400">Descartados: <?php echo $discardedCount; ?></p>
        </div>
        <span class="bg-yellow-100 text-yellow-600 p-3 rounded-full"><i class="fas fa-filter"></i></span>
    </div>
</div>

<?php if ($nextVendorPreview !== null): ?>
    <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200 mb-8 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Próximo vendedor na fila</p>
            <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($nextVendorPreview['vendorName'] ?? ''); ?></p>
            <?php if (!empty($nextVendorPreview['lastAssignedAt'])): ?>
                <p class="text-xs text-gray-400 mt-1">Último lead atribuído em <?php echo date('d/m/Y H:i', strtotime($nextVendorPreview['lastAssignedAt'])); ?></p>
            <?php endif; ?>
        </div>
        <span class="bg-indigo-100 text-indigo-600 p-3 rounded-full"><i class="fas fa-user-clock"></i></span>
    </div>
<?php endif; ?>
