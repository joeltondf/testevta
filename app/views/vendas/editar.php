    <h1 class="text-3xl font-bold mb-4 pb-4 border-b border-gray-200">Editar Venda #<?php echo htmlspecialchars($venda['id']); ?></h1>

    <form action="vendas.php?action=editar&id=<?php echo htmlspecialchars($venda['id']); ?>" method="POST" class="bg-white p-6 rounded-lg shadow-lg border border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
                <label for="vendedor_id" class="block text-sm font-semibold text-gray-700">Vendedor *</label>
                <select name="vendedor_id" id="vendedor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                    <?php foreach ($vendedores as $vendedor): ?>
                        <option value="<?php echo $vendedor['id']; ?>" <?php echo ($venda['vendedor_id'] == $vendedor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendedor['nome_vendedor']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="cliente_id" class="block text-sm font-semibold text-gray-700">Cliente *</label>
                <select name="cliente_id" id="cliente_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo $cliente['id']; ?>" <?php echo ($venda['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="valor_total" class="block text-sm font-semibold text-gray-700">Valor Total (R$) *</label>
                <input type="text" name="valor_total" id="valor_total" data-currency-input class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" value="<?php echo htmlspecialchars($venda['valor_total']); ?>" placeholder="R$ 0,00" required>
            </div>
            
            <div>
                <label for="data_venda" class="block text-sm font-semibold text-gray-700">Data da Venda *</label>
                <input type="datetime-local" name="data_venda" id="data_venda" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" value="<?php echo date('Y-m-d\TH:i', strtotime($venda['data_venda'])); ?>" required>
            </div>
            
            <div class="md:col-span-2">
                <label for="descricao" class="block text-sm font-semibold text-gray-700">Descrição</label>
                <textarea name="descricao" id="descricao" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm"><?php echo htmlspecialchars($venda['descricao']); ?></textarea>
            </div>

            <div>
                <label for="status_venda" class="block text-sm font-semibold text-gray-700">Status da Venda</label>
                <select name="status_venda" id="status_venda" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="Pendente" <?php echo ($venda['status_venda'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="Concluída" <?php echo ($venda['status_venda'] == 'Concluída') ? 'selected' : ''; ?>>Concluída</option>
                    <option value="Cancelada" <?php echo ($venda['status_venda'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end mt-6 pt-5 border-t border-gray-200">
            <a href="vendas.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md mr-3">Cancelar</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Atualizar Venda</button>
        </div>
    </form>
