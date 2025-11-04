<?php // /app/views/tradutores/lista.php ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gestão de Tradutores</h1>
    <a href="tradutores.php?action=create" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
        Novo Tradutor
    </a>
</div>

<?php
if (isset($_SESSION['success_message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="border-t border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome do Tradutor</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Idioma</th>
                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($tradutores)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum tradutor encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tradutores as $tradutor): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tradutor['nome_tradutor']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($tradutor['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($tradutor['telefone']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($tradutor['especialidade_idioma']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                <a href="tradutores.php?action=edit&id=<?php echo $tradutor['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                <a href="tradutores.php?action=delete&id=<?php echo $tradutor['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Tem a certeza que deseja desativar este tradutor?');">Desativar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
