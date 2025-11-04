<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/views/layouts/header.php';

$token = $_GET['token'] ?? '';
$batch = $_SESSION['lead_import_batches'][$token] ?? null;

if (!$batch || ($batch['uploader_id'] ?? null) !== ($_SESSION['user_id'] ?? null)) {
    $_SESSION['error_message'] = 'Importação não encontrada ou expirada.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$rows = $batch['rows'] ?? [];
$duplicateRows = array_filter($rows, static fn(array $row) => $row['duplicate'] !== null);
$uniqueRows = array_filter($rows, static fn(array $row) => $row['duplicate'] === null);

$totalRows = count($rows);
$duplicateCount = count($duplicateRows);
$uniqueCount = count($uniqueRows);
$skippedCount = (int) ($batch['skipped'] ?? 0);
$errorRows = $batch['errors'] ?? [];

$pageTitle = 'Revisar duplicados da importação';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="text-sm text-gray-500 mt-1">Escolha como tratar os leads duplicados antes de concluir a importação.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-500">Registros analisados</p>
            <p class="text-2xl font-semibold text-gray-800"><?php echo $totalRows; ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-500">Prontos para importar</p>
            <p class="text-2xl font-semibold text-emerald-600"><?php echo $uniqueCount; ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-500">Duplicados detectados</p>
            <p class="text-2xl font-semibold text-amber-600"><?php echo $duplicateCount; ?></p>
        </div>
    </div>

    <?php if (!empty($errorRows)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6">
            <p class="font-semibold mb-2">Ocorrências durante a leitura</p>
            <ul class="list-disc list-inside space-y-1 text-sm">
                <?php foreach ($errorRows as $errorMessage): ?>
                    <li><?php echo htmlspecialchars($errorMessage); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Resumo</h2>
        <ul class="text-sm text-gray-600 space-y-2">
            <li><?php echo $uniqueCount; ?> lead(s) serão importados automaticamente.</li>
            <li><?php echo $duplicateCount; ?> lead(s) possuem dados semelhantes aos já cadastrados.</li>
            <li><?php echo $skippedCount; ?> linha(s) foram ignoradas durante a leitura.</li>
        </ul>
    </div>

    <form action="<?php echo APP_URL; ?>/crm/clientes/importar_confirmar.php" method="POST" class="space-y-6" id="duplicate-review-form">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <?php if ($duplicateCount > 0): ?>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Leads duplicados</h3>
                        <p class="text-sm text-gray-500">Defina o que deve acontecer com cada lead duplicado.</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <label for="bulk-action" class="text-gray-600">Aplicar a todos:</label>
                        <select id="bulk-action" class="border border-gray-300 rounded-md px-2 py-1">
                            <option value="keep">Manter existente</option>
                            <option value="import">Aceitar novo lead</option>
                            <option value="discard">Remover da importação</option>
                        </select>
                        <button type="button" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300" data-apply-bulk>Atribuir</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Lead do arquivo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Lead existente</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($duplicateRows as $rowKey => $duplicateRow): ?>
                                <?php $existing = $duplicateRow['duplicate'] ?? []; ?>
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($duplicateRow['company_name']); ?></p>
                                        <?php if ($duplicateRow['contact_name'] !== ''): ?>
                                            <p class="text-gray-500">Contato: <?php echo htmlspecialchars($duplicateRow['contact_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($duplicateRow['email'] !== ''): ?>
                                            <p class="text-gray-500">E-mail: <?php echo htmlspecialchars($duplicateRow['email']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($duplicateRow['phone'] !== ''): ?>
                                            <p class="text-gray-500">Telefone: <?php echo htmlspecialchars($duplicateRow['phone']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($existing['name'] ?? ''); ?></p>
                                        <?php if (!empty($existing['email'])): ?>
                                            <p class="text-gray-500">E-mail: <?php echo htmlspecialchars($existing['email']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($existing['phone'])): ?>
                                            <p class="text-gray-500">Telefone: <?php echo htmlspecialchars($existing['phone']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-2" data-action-group="<?php echo $rowKey; ?>">
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="radio" name="actions[<?php echo $rowKey; ?>]" value="keep" class="text-blue-600" checked>
                                                Manter existente (ignorar este lead)
                                            </label>
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="radio" name="actions[<?php echo $rowKey; ?>]" value="import" class="text-blue-600">
                                                Aceitar novo lead (duplicar)
                                            </label>
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="radio" name="actions[<?php echo $rowKey; ?>]" value="discard" class="text-blue-600">
                                                Remover da importação
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex justify-end gap-3">
            <a href="<?php echo APP_URL; ?>/crm/clientes/importar.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Concluir importação</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const bulkButton = document.querySelector('[data-apply-bulk]');
        const bulkSelect = document.getElementById('bulk-action');

        bulkButton?.addEventListener('click', () => {
            const value = bulkSelect?.value || 'keep';
            document.querySelectorAll('[data-action-group]').forEach(group => {
                const input = group.querySelector(`input[value="${value}"]`);
                if (input) {
                    input.checked = true;
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php';
