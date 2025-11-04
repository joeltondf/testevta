    <h1 class="text-3xl font-bold mb-4">Relatório dos Vendedores</h1>

    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form action="vendas.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="vendedor_id" class="block text-sm font-medium text-gray-700">Vendedor</label>
                    <select name="vendedor_id" id="vendedor_id" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?php echo $vendedor['id']; ?>" <?php echo (isset($filtros['vendedor_id']) && $filtros['vendedor_id'] == $vendedor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vendedor['nome_vendedor']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data Início</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($filtros['data_inicio'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-medium text-gray-700">Data Fim</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($filtros['data_fim'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Filtrar</button>
                    <a href="vendas.php" class="w-full text-center bg-gray-300 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-400">Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-green-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-green-800">Valor Total Vendido</h4>
            <p class="text-3xl font-bold text-green-900 mt-2">R$ <?php echo number_format($stats['valor_total_vendido'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-indigo-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-indigo-800">Processos Contabilizados</h4>
            <p class="text-3xl font-bold text-indigo-900 mt-2"><?php echo count($processosFiltrados); ?></p>
        </div>
        <div class="bg-yellow-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-yellow-800">Total de Documentos</h4>
            <p class="text-3xl font-bold text-yellow-900 mt-2"><?php echo $stats['total_documentos']; ?></p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white shadow-md rounded-lg overflow-hidden">
            <h3 class="text-xl font-semibold p-4 border-b">Detalhes dos Processos</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 text-left">Processo</th>
                            <th class="px-5 py-3 border-b-2 text-left">Vendedor</th>
                            <th class="px-5 py-3 border-b-2 text-left">Data</th>
                            <th class="px-5 py-3 border-b-2 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($processosFiltrados)): ?>
                            <tr><td colspan="4" class="text-center p-5">Nenhum processo encontrado para os filtros selecionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($processosFiltrados as $proc): ?>
                                <tr>
                                    <td class="px-5 py-4 border-b"><a href="processos.php?action=view&id=<?php echo $proc['id']; ?>" class="text-blue-600 hover:underline">#<?php echo $proc['id']; ?> - <?php echo htmlspecialchars($proc['titulo']); ?></a></td>
                                    <td class="px-5 py-4 border-b"><?php echo htmlspecialchars($proc['nome_vendedor']); ?></td>
                                    <td class="px-5 py-4 border-b"><?php echo date('d/m/Y', strtotime($proc['data_criacao'])); ?></td>
                                    <td class="px-5 py-4 border-b text-right">R$ <?php echo number_format($proc['valor_total'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-xl font-semibold border-b pb-3 mb-3">Ranking de Vendedores</h3>
            <ol class="list-decimal list-inside space-y-2">
                <?php if (empty($stats['ranking_vendedores'])): ?>
                    <li class="text-gray-500">Nenhum dado para exibir.</li>
                <?php else: ?>
                    <?php foreach ($stats['ranking_vendedores'] as $nome => $valor): ?>
                        <li class="p-2 rounded-md hover:bg-gray-100">
                            <span class="font-semibold"><?php echo htmlspecialchars($nome); ?></span>
                            <span class="float-right text-green-700 font-bold">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        </div>
    </div>
