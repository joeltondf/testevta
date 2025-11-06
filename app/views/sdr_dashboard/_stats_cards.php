<?php
$leadsDistributed = (int) ($stats['leadsDistribuidos'] ?? 0);
$appointmentsCount = (int) ($stats['agendamentosRealizados'] ?? 0);
$appointmentRate = (float) ($stats['taxaAgendamento'] ?? 0);
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
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
</div>
