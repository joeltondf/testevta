<?php
// app/views/admin/automacao_settings.php
// VERSÃO ATUALIZADA

// Carrega o header do layout
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col">
        <div class="w-full">
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="mb-4">
                    <h4 class="text-xl font-semibold">Configurações de Automação</h4>
                </div>
                <div class="mb-4">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success bg-green-100 text-green-800 p-4 rounded-md mb-4">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="admin.php?action=save_automacao_settings" method="POST">
                        
                        <div class="alert bg-blue-100 text-blue-800 p-4 rounded-md mb-4">
                            <h5 class="font-semibold">Configuração de E-mail Centralizada</h5>
                            <p>As configurações de envio de e-mail (SMTP) agora são gerenciadas em um local único para todo o sistema.</p>
                            <hr class="my-2">
                            <p>
                                Para ajustar as credenciais de envio, por favor, acesse a página de 
                                <a href="admin.php?action=smtp_settings" class="text-blue-600 underline">Configurações de Notificações e Alertas</a>.
                            </p>
                        </div>

                        <h5 class="mt-6 text-lg font-medium">API Digisac</h5>
                        <div class="mb-4">
                            <label for="digisac_api_url" class="block text-sm font-medium text-gray-700">URL da API Digisac</label>
                            <input type="text" class="form-control mt-2 block w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="digisac_api_url" name="digisac_api_url" value="<?php echo htmlspecialchars($settings['digisac_api_url'] ?? ''); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="digisac_api_token" class="block text-sm font-medium text-gray-700">Token da API Digisac</label>
                            <input type="password" class="form-control mt-2 block w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="digisac_api_token" name="digisac_api_token" placeholder="Deixe em branco para não alterar">
                            <small class="text-sm text-gray-500">Apenas um novo token será salvo.</small>
                        </div>
                        
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 mt-4">
                            Salvar Configurações da API
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Carrega o footer do layout
require_once __DIR__ . '/../layouts/footer.php';
?>
