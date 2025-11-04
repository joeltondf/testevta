<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="text-sm text-gray-500">Atualize as informações necessárias e salve para manter a consistência com a Omie.</p>
        </div>
        <a href="admin.php?action=omie_support&amp;type=<?php echo urlencode($supportType); ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100">
            &larr; Voltar
        </a>
    </div>

    <?php include __DIR__ . '/../partials/messages.php'; ?>

    <form action="admin.php?action=omie_support_update" method="POST" class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 space-y-4">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($supportType); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$record['id']; ?>">

        <?php foreach ($definition['fields'] as $field): ?>
            <?php
            $name = $field['name'];
            $label = $field['label'] ?? ucfirst($name);
            $type = $field['type'] ?? 'text';
            $value = $record[$name] ?? '';
            $isReadonly = !empty($field['readonly']);
            $isRequired = !empty($field['required']);
            ?>
            <div>
                <label for="<?php echo htmlspecialchars($name); ?>" class="block text-sm font-semibold text-gray-700 mb-1"><?php echo htmlspecialchars($label); ?><?php echo $isRequired ? ' *' : ''; ?></label>
                <?php if ($type === 'checkbox'): ?>
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="<?php echo htmlspecialchars($name); ?>" name="<?php echo htmlspecialchars($name); ?>" value="1" class="h-4 w-4 text-indigo-600 border-gray-300 rounded"
                            <?php echo ((int)$value === 1) ? 'checked' : ''; ?> <?php echo $isReadonly ? 'disabled' : ''; ?>>
                        <span class="ml-2 text-sm text-gray-700">Marcar como ativo</span>
                    </label>
                <?php else: ?>
                    <input
                        type="<?php echo htmlspecialchars($type); ?>"
                        id="<?php echo htmlspecialchars($name); ?>"
                        name="<?php echo htmlspecialchars($name); ?>"
                        value="<?php echo htmlspecialchars((string)$value); ?>"
                        <?php echo $isReadonly ? 'readonly' : ''; ?>
                        <?php echo $isRequired ? 'required' : ''; ?>
                        <?php echo isset($field['step']) ? 'step="' . htmlspecialchars($field['step']) . '"' : ''; ?>
                        class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 <?php echo $isReadonly ? 'bg-gray-100' : ''; ?>"
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
            <a href="admin.php?action=omie_support&amp;type=<?php echo urlencode($supportType); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100">
                Cancelar
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                <i class="fas fa-save mr-2"></i> Salvar Alterações
            </button>
        </div>
    </form>
</div>
