<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="text-sm text-gray-500">Gerencie os registros sincronizados da Omie.</p>
        </div>
        <div class="space-x-2">
            <a href="admin.php?action=omie_settings" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100">
                &larr; Voltar
            </a>
            <form action="admin.php?action=sync_omie_support" method="POST" class="inline" onsubmit="return confirm('Deseja sincronizar agora com a Omie?');">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($supportType); ?>">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">
                    <i class="fas fa-sync-alt mr-2"></i> Sincronizar Omie
                </button>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/messages.php'; ?>

    <?php if (empty($records)): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 text-center text-gray-600">
            Nenhum registro encontrado.
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <?php foreach ($definition['columns'] as $column): ?>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?php echo htmlspecialchars($column['label']); ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Ações</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($records as $record): ?>
                    <tr class="hover:bg-gray-50">
                        <?php foreach ($definition['columns'] as $column): ?>
                            <?php
                            $value = $record[$column['key']] ?? '';
                            if ($column['key'] === 'ativo') {
                                $isActive = (int)$value === 1;
                                $value = $isActive ? 'Ativo' : 'Inativo';
                                $badgeClass = $isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                echo '<td class="px-4 py-3"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badgeClass . '">' . htmlspecialchars($value) . '</span></td>';
                                continue;
                            }
                            if ($column['key'] === 'valor_unitario' && $value !== null && $value !== '') {
                                $value = number_format((float)$value, 2, ',', '.');
                            }
                            echo '<td class="px-4 py-3 text-sm text-gray-700">' . htmlspecialchars((string)$value) . '</td>';
                            ?>
                        <?php endforeach; ?>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="admin.php?action=omie_support_edit&amp;type=<?php echo urlencode($supportType); ?>&amp;id=<?php echo (int)$record['id']; ?>"
                               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                <i class="fas fa-edit mr-2"></i> Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
