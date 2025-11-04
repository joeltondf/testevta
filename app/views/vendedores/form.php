<h1 class="text-3xl font-bold mb-4 pb-4 border-b border-gray-200">
    <?php echo isset($vendedor['id']) ? 'Editar Vendedor' : 'Cadastrar Novo Vendedor'; ?>
</h1>

<?php include __DIR__ . '/../partials/messages.php'; ?>

<?php 
    // CORREÇÃO: O formulário agora envia para vendedores.php com as ações corretas (update/store)
    $form_action = isset($vendedor['id']) 
        ? 'vendedores.php?action=update&id=' . $vendedor['id'] 
        : 'vendedores.php?action=store';
?>

<form action="<?php echo $form_action; ?>" method="POST" class="bg-white p-6 rounded-lg shadow-lg">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="md:col-span-2">
            <label for="nome_completo" class="block text-sm font-medium text-gray-700">Nome Completo *</label>
            <input type="text" name="nome_completo" id="nome_completo" value="<?php echo htmlspecialchars($vendedor['nome_completo'] ?? ''); ?>" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
        </div>
        
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">E-mail *</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($vendedor['email'] ?? ''); ?>" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
        </div>

        <div>
            <label for="senha" class="block text-sm font-medium text-gray-700">Senha <?php echo !isset($vendedor['id']) ? '*' : ''; ?></label>
            <input type="password" name="senha" id="senha" <?php echo !isset($vendedor['id']) ? 'required' : ''; ?> class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
            <?php if (isset($vendedor['id'])): ?>
                <small class="text-gray-500">Deixe em branco para não alterar.</small>
            <?php endif; ?>
        </div>

        <div>
            <label for="percentual_comissao" class="block text-sm font-medium text-gray-700">Percentual de Comissão (%)</label>
            <input type="number" step="0.01" name="percentual_comissao" id="percentual_comissao" value="<?php echo htmlspecialchars($vendedor['percentual_comissao'] ?? '0.00'); ?>" placeholder="Ex: 5.50" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
        </div>

        <div>
            <label for="data_contratacao" class="block text-sm font-medium text-gray-700">Data de Contratação</label>
            <input type="date" name="data_contratacao" id="data_contratacao" value="<?php echo htmlspecialchars($vendedor['data_contratacao'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
        </div>

         <div>
            <label for="ativo" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="ativo" id="ativo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                <option value="1" <?php echo (isset($vendedor['ativo']) && $vendedor['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                <option value="0" <?php echo (isset($vendedor['ativo']) && $vendedor['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </div>
    </div>

    <div class="flex justify-end mt-6 pt-5 border-t">
        <a href="vendedores.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md mr-3">Cancelar</a>
        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md">
            <?php echo isset($vendedor['id']) ? 'Atualizar Vendedor' : 'Salvar Vendedor'; ?>
        </button>
    </div>
</form>