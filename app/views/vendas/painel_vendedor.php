<?php // Incluir layout padrão (header, etc) ?>
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Meu Painel de Vendedor</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-blue-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-blue-800">Total Vendido</h4>
            <p class="text-3xl font-bold text-blue-900 mt-2">R$ <?php echo number_format($totalVendido, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-yellow-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-yellow-800">Comissões Pendentes</h4>
            <p class="text-3xl font-bold text-yellow-900 mt-2">R$ <?php echo number_format($totalComissaoPendente, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-green-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-green-800">Comissões Pagas</h4>
            <p class="text-3xl font-bold text-green-900 mt-2">R$ <?php echo number_format($totalComissaoPaga, 2, ',', '.'); ?></p>
        </div>
    </div>
    
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
         <h3 class="text-xl font-bold text-gray-800 p-4 border-b">Minhas Vendas Recentes</h3>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left">Data</th>
                    <th class="px-6 py-3 text-left">Descrição</th>
                    <th class="px-6 py-3 text-right">Valor Total</th>
                    <th class="px-6 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendas as $venda): ?>
                <tr>
                    <td class="px-6 py-4"><?php echo date('d/m/Y', strtotime($venda['data_venda'])); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($venda['descricao']); ?></td>
                    <td class="px-6 py-4 text-right">R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></td>
                    <td class="px-6 py-4 text-center"><?php echo htmlspecialchars($venda['status_venda']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php // Incluir layout padrão (footer, etc) ?>