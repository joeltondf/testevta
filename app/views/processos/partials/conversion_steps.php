<?php
$profile = $_SESSION['user_perfil'] ?? '';
if ($profile !== 'vendedor') {
    return;
}

$stepItems = isset($conversionSteps) && is_array($conversionSteps) ? $conversionSteps : [];
if ($stepItems === []) {
    return;
}

$currentStepKey = isset($currentStep) ? (string)$currentStep : '';
$completedStepKeys = isset($completedSteps) && is_array($completedSteps) ? $completedSteps : [];
$stepCount = count($stepItems);
?>
<div class="mb-8">
    <ol class="flex flex-col space-y-4 md:flex-row md:items-center md:space-y-0 md:space-x-4">
        <?php foreach ($stepItems as $index => $stepData):
            $stepKey = (string)($stepData['key'] ?? $index);
            $label = (string)($stepData['label'] ?? ('Etapa ' . ($index + 1)));
            $description = isset($stepData['description']) ? (string)$stepData['description'] : '';

            $status = 'pending';
            if (in_array($stepKey, $completedStepKeys, true)) {
                $status = 'done';
            } elseif ($stepKey === $currentStepKey) {
                $status = 'current';
            }

            $indicatorClass = [
                'done' => 'bg-green-500 text-white',
                'current' => 'bg-orange-500 text-white',
                'pending' => 'bg-gray-200 text-gray-600',
            ][$status];

            $labelClass = [
                'done' => 'text-gray-800',
                'current' => 'text-gray-800',
                'pending' => 'text-gray-500',
            ][$status];

            $connectorClass = [
                'done' => 'bg-green-500',
                'current' => 'bg-orange-500',
                'pending' => 'bg-gray-200',
            ][$status];
        ?>
        <li class="flex items-center md:flex-1">
            <div class="flex items-center">
                <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold <?php echo $indicatorClass; ?>">
                    <?php echo $status === 'done' ? '✓' : ($index + 1); ?>
                </span>
                <div class="ml-3">
                    <p class="text-sm font-semibold <?php echo $labelClass; ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php if ($description !== ''): ?>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($index < $stepCount - 1): ?>
                <div class="hidden flex-1 items-center px-4 md:flex">
                    <span class="h-0.5 w-full rounded <?php echo $connectorClass; ?>"></span>
                </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php
unset($conversionSteps, $currentStep, $completedSteps);
?>
