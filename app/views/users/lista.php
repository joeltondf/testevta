<?php
/**
 * @file /app/views/users/lista.php
 * @description View responsável por exibir a lista de utilizadores (usuários) do sistema.
 */
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Gerenciar Usuários</h1>
    <a href="admin.php?action=create_user" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
        + Novo Usuário
    </a>
</div>

<?php
if (isset($_SESSION['success_message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="border-t border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Completo</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfil</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                // Verifica se a lista de utilizadores ($users) está vazia
                if (empty($users)) :
                ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum utilizador encontrado.</td>
                    </tr>
                <?php
                else :
                    // Itera sobre a lista de utilizadores e exibe cada um em uma linha
                    foreach ($users as $user) :
                ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['nome_completo']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ucfirst(htmlspecialchars($user['perfil'])); ?></td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                
                                <?php
                                // Lógica de segurança: O utilizador não pode apagar a si próprio.
                                // O botão 'Apagar' só é exibido se o ID do utilizador na linha for diferente do ID do utilizador logado na sessão.
                                if ($user['id'] != $_SESSION['user_id']) :
                                ?>
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Tem a certeza que deseja apagar este utilizador? Esta ação é irreversível.');">Apagar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php
                    endforeach; // Fim do loop de utilizadores
                endif; // Fim da verificação de lista vazia
                ?>
            </tbody>
        </table>
    </div>
</div>