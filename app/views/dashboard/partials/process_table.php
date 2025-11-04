<?php
$showActions = $showActions ?? true;
$highlightAnimations = $highlightAnimations ?? false;
$deadlineColors = $deadlineColors ?? [];
$tableThemeClass = $tableThemeClass ?? 'divide-y divide-gray-200';
$theadThemeClass = trim(($theadThemeClass ?? 'bg-gray-50') . ' sticky top-0 z-10');
$tbodyThemeClass = $tbodyThemeClass ?? 'bg-white divide-y divide-gray-200';
$tableClass = trim('min-w-full table-auto ' . $tableThemeClass);
$tableBodyId = $tableBodyId ?? 'processes-table-body';
?>
<table class="<?php echo htmlspecialchars($tableClass); ?>">
    <thead class="<?php echo htmlspecialchars($theadThemeClass); ?>">
        <tr>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Família</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessoria</th>
            <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Doc.</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OS Omie</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serviços</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrada</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Envio</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
            <?php if ($showActions): ?>
                <th scope="col" class="relative px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody class="<?php echo htmlspecialchars($tbodyThemeClass); ?>" id="<?php echo htmlspecialchars($tableBodyId); ?>" data-tv-table-body>
        <?php
            $processes = $processes ?? [];
            $renderOnlyBody = false;
            require __DIR__ . '/process_table_rows.php';
        ?>
    </tbody>
</table>
