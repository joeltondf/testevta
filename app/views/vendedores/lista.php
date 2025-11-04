<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold">Gestão de Vendedores</h1>
    <a href="vendedores.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
        + Novo Vendedor
    </a>
</div>

<?php include __DIR__ . '/../partials/messages.php'; ?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <table class="min-w-full leading-normal">
        <thead>
            <tr>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase">E-mail</th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendedores)): ?>
                <tr><td colspan="4" class="text-center p-4">Nenhum vendedor cadastrado.</td></tr>
            <?php else: ?>
                <?php foreach ($vendedores as $vendedor): ?>
                <tr>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($vendedor['nome_vendedor']); ?></td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($vendedor['email']); ?></td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                         <span class="px-2 py-1 font-semibold leading-tight rounded-full <?php echo $vendedor['ativo'] ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100'; ?>">
                            <?php echo $vendedor['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center space-x-4">
                        <a href="vendedores.php?action=edit&id=<?php echo $vendedor['id']; ?>" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</a>
                        <a href="vendedores.php?action=delete&id=<?php echo $vendedor['id']; ?>" 
                        class="text-red-600 hover:text-red-900 font-medium" 
                        onclick="return confirm('ATENÇÃO!\n\nTem certeza que deseja excluir este vendedor?\nEsta ação também removerá o acesso de login do usuário associado e é irreversível.');">
                        Excluir
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>