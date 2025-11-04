<?php
/**
 * @file /app/views/users/form.php
 * @description View com o formulário para cadastrar ou editar um utilizador (usuário).
 */
$isEdit = isset($user) && $user;
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEdit ? 'Editar Usuário' : 'Novo Usuário'; ?></h1>
    <a href="admin.php?action=users" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
        &larr; Voltar à Lista
    </a>
</div>

<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl mx-auto">
    <form action="admin.php?action=<?php echo $isEdit ? 'update_user' : 'store_user'; ?>" method="POST">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="md:col-span-2">
                <label for="nome_completo" class="block text-sm font-medium text-gray-700">Nome Completo *</label>
                <input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($user['nome_completo'] ?? ''); ?>" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="senha" class="block text-sm font-medium text-gray-700">Senha</label>
                <input type="password" id="senha" name="senha" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" <?php echo !$isEdit ? 'required' : ''; ?>>
                <?php if ($isEdit): ?>
                    <p class="text-xs text-gray-500 mt-1">Deixe em branco para não alterar a senha.</p>
                <?php endif; ?>
            </div>

            <div>
                <label for="perfil" class="block text-sm font-medium text-gray-700">Perfil *</label>
                    <select id="perfil" name="perfil" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php
                        // Linha modificada para remover os perfis de Vendedor e Cliente
                            $perfis = [
                                'master'      => 'Master',
                                'admin'       => 'Admin',
                                'gerencia'    => 'Gerência',
                                'supervisor'  => 'Supervisor',
                                'financeiro'  => 'Financeiro',
                                'sdr'         => 'SDR',
                                'vendedor'    => 'Vendedor',
                                'colaborador' => 'Colaborador',
                                'cliente'     => 'Cliente'
                            ];
                            $perfilAtual = $user['perfil'] ?? '';
                            foreach ($perfis as $valor => $texto) {
                                $selected = ($valor === $perfilAtual) ? 'selected' : '';
                                echo "<option value='{$valor}' {$selected}>{$texto}</option>";
                            }

                        ?>
                    </select>
            </div>

            <?php if ($isEdit): ?>
            <div>
                <label for="ativo" class="block text-sm font-medium text-gray-700">Status</label>
                <select name="ativo" id="ativo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="1" <?php echo ($user['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?php echo ($user['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                <?php echo $isEdit ? 'Atualizar Usuário' : 'Salvar Usuário'; ?>
            </button>
        </div>
    </form>
</div>
