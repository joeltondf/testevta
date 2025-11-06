<?php
$formData = $formData ?? [];
$processId = (int)($processo['id'] ?? 0);
$deadlineType = $formData['traducao_prazo_tipo'] ?? 'dias';

$conversionSteps = [
    [
        'key' => 'client',
        'label' => 'Cliente',
        'description' => 'Revise ou cadastre os dados do cliente.',
    ],
    [
        'key' => 'deadline',
        'label' => 'Prazo do serviço',
        'description' => 'Defina data de início e prazo de entrega.',
    ],
    [
        'key' => 'payment',
        'label' => 'Pagamento',
        'description' => 'Informe as condições financeiras.',
    ],
];
$currentStep = 'deadline';
$completedSteps = ['client'];
include __DIR__ . '/partials/conversion_steps.php';
?>
<div class="max-w-3xl mx-auto space-y-10">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Prazo do Serviço</h1>
            <p class="text-sm text-gray-600">Defina a data de início e o prazo acordado para a entrega.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_deadline&id=<?php echo $processId; ?>"
        method="POST"
        class="bg-white shadow rounded-lg p-8 space-y-8"
        data-conversion-step="deadline"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="data_inicio_traducao">Data de início</label>
                <input type="date" id="data_inicio_traducao" name="data_inicio_traducao" value="<?php echo htmlspecialchars($formData['data_inicio_traducao'] ?? date('Y-m-d')); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <span class="block text-sm font-semibold text-gray-700">Tipo de prazo</span>
                <div class="mt-3 flex space-x-6">
                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="traducao_prazo_tipo" value="dias" <?php echo $deadlineType === 'data' ? '' : 'checked'; ?> data-deadline-type>
                        <span class="text-sm text-gray-700">Dias</span>
                    </label>
                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="traducao_prazo_tipo" value="data" <?php echo $deadlineType === 'data' ? 'checked' : ''; ?> data-deadline-type>
                        <span class="text-sm text-gray-700">Data específica</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div id="deadline-days-wrapper" class="<?php echo $deadlineType === 'data' ? 'hidden' : ''; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="traducao_prazo_dias">Dias para entrega</label>
                <input type="number" min="1" id="traducao_prazo_dias" name="traducao_prazo_dias" value="<?php echo htmlspecialchars($formData['traducao_prazo_dias'] ?? ''); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-deadline-days>
            </div>
            <div id="deadline-date-wrapper" class="<?php echo $deadlineType === 'data' ? '' : 'hidden'; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="traducao_prazo_data">Data de entrega</label>
                <input type="date" id="traducao_prazo_data" name="traducao_prazo_data" value="<?php echo htmlspecialchars($formData['traducao_prazo_data'] ?? ''); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-deadline-date>
            </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <a href="processos.php?action=convert_to_service_client&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Voltar</a>
            <div class="flex items-center space-x-3">
                <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none">
                    Salvar e continuar
                </button>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/service-conversion.js"></script>
