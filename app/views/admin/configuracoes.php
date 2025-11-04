<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Configurações de Aparência</h1>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
        <p><?php echo $_SESSION['success_message']; ?></p>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?php echo $_SESSION['error_message']; ?></p>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>


<div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
    <form action="admin.php?action=save_config" method="POST" enctype="multipart/form-data">

        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-2">Cor Principal do Sistema</h3>
            <p class="text-gray-600 mb-4">Esta cor será aplicada a elementos chave da interface, como a barra superior.</p>
            <div class="relative">
                 <input type="color" name="theme_color" value="<?php echo htmlspecialchars($theme_color ?? '#4f46e5'); ?>" class="p-2 h-14 w-full block bg-white border border-gray-300 cursor-pointer rounded-lg disabled:opacity-50 disabled:pointer-events-none">
            </div>
        </div>

        <div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Logo do Sistema</h3>
            <p class="text-gray-600 mb-4">Envie uma imagem (PNG, JPG, SVG) para substituir o nome do sistema no topo da navegação.</p>

            <?php
            // --- INÍCIO DA CORREÇÃO ---
            // Constrói o caminho completo do ficheiro no servidor para verificação
            $logo_path_no_servidor = __DIR__ . '/../../../' . $system_logo;
            if (!empty($system_logo) && file_exists($logo_path_no_servidor)):
            // --- FIM DA CORREÇÃO ---
            ?>
                <div class="mb-4">
                    <p class="font-semibold text-gray-700">Logo Atual:</p>
                    <img src="<?php echo htmlspecialchars(APP_URL . '/' . $system_logo); ?>" alt="Logo Atual" class="mt-2 h-16 w-auto bg-gray-100 p-2 rounded-md">
                </div>
            <?php endif; ?>

            <label for="system_logo" class="block text-sm font-medium text-gray-700">Escolher nova logo:</label>
            <input type="file" name="system_logo" id="system_logo" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
        </div>

        <div class="mt-10 border-t pt-6">
            <h3 class="text-xl font-bold text-gray-800 mb-2">Senha da Gerência</h3>
            <p class="text-gray-600 mb-4">Defina uma senha compartilhada para autorizar ações sensíveis nas prospecções sem expor o login individual de um gestor.</p>
            <?php if (!empty($managementPasswordDefined)): ?>
                <p class="text-sm text-emerald-600 mb-4">Uma senha da gerência está configurada atualmente.</p>
            <?php else: ?>
                <p class="text-sm text-orange-600 mb-4">Nenhuma senha da gerência está configurada.</p>
            <?php endif; ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="management_password" class="block text-sm font-medium text-gray-700">Nova senha</label>
                    <input type="password" name="management_password" id="management_password" class="mt-1 block w-full rounded-lg border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Mínimo de 6 caracteres">
                </div>
                <div>
                    <label for="management_password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar senha</label>
                    <input type="password" name="management_password_confirmation" id="management_password_confirmation" class="mt-1 block w-full rounded-lg border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Repita a senha">
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Deixe ambos os campos em branco para manter a senha atual.</p>
        </div>

        <div class="text-right mt-8 border-t pt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-md">
                <i class="fas fa-save mr-2"></i>
                Salvar Alterações
            </button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>