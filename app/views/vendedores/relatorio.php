<?php?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Meu Relatório de Desempenho</h1>
        <p class="text-gray-600">Analise suas vendas e comissões.</p>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" action="relatorio_vendedor.php">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data Inicial:</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-medium text-gray-700">Data Final:</label>
                <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Filtrar</button>
        </div>
    </form>
</div>

<div class="bg-white p-5 rounded-lg shadow-xl border border-gray-200 mb-8">
    <h4 class="text-xl font-bold text-gray-800 mb-4">Vendas por Mês</h4>
    <div style="height: 350px;">
        <canvas id="vendasMensaisChart"></canvas>
    </div>
</div>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <h3 class="text-xl font-semibold p-4 border-b">Detalhes do Período</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 text-left">Processo</th>
                    <th class="px-5 py-3 border-b-2 text-left">Cliente</th>
                    <th class="px-5 py-3 border-b-2 text-left">Data Venda</th>
                    <th class="px-5 py-3 border-b-2 text-right">Valor Venda</th>
                    <th class="px-5 py-3 border-b-2 text-right">Comissão</th>
                    <th class="px-5 py-3 border-b-2 text-center">Status Comissão</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendasDoPeriodo)): ?>
                    <tr><td colspan="6" class="text-center p-5">Nenhuma venda encontrada para o período.</td></tr>
                <?php else: ?>
                    <?php foreach ($vendasDoPeriodo as $venda): ?>
                        <tr>
                            <td class="px-5 py-4 border-b"><a href="processos.php?action=view&id=<?php echo $venda['id']; ?>" class="text-blue-600 hover:underline">#<?php echo $venda['id']; ?> - <?php echo htmlspecialchars($venda['titulo']); ?></a></td>
                            <td class="px-5 py-4 border-b"><?php echo htmlspecialchars($venda['nome_cliente']); ?></td>
                            <td class="px-5 py-4 border-b"><?php echo date('d/m/Y', strtotime($venda['data_criacao'])); ?></td>
                            <td class="px-5 py-4 border-b text-right font-semibold">R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></td>
                            <td class="px-5 py-4 border-b text-right text-green-600 font-bold">R$ <?php echo number_format(($venda['valor_total'] * ($vendedor['percentual_comissao']/100)), 2, ',', '.'); ?></td>
                            <td class="px-5 py-4 border-b text-center"><span class="px-2 py-1 font-semibold leading-tight rounded-full bg-yellow-100 text-yellow-800">Pendente</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctxVendas = document.getElementById('vendasMensaisChart');
    if (ctxVendas) {
        new Chart(ctxVendas, {
            type: 'line',
            data: {
                labels: <?php echo $labels_vendas; ?>,
                datasets: [{
                    label: 'Valor Total Vendido',
                    data: <?php echo $valores_vendas; ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});
</script>