<?php
// app/views/admin/smtp_settings.php
?>

<div class="bg-white shadow-md rounded-lg p-6">
    <div class="mb-4">
        <h4 class="text-xl font-semibold">Configurações de Notificações e Alertas</h4>
        <p class="text-sm text-gray-600">Gerencie os e-mails que receberão alertas e as configurações de envio.</p>
    </div>
    <div class="mb-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> p-3 mb-4 rounded-md">
                <?php echo $_SESSION['message']; ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <form action="admin.php?action=save_smtp_settings" method="POST">
            
            <h5 class="text-lg font-medium mt-4">1. Configurações de Alertas</h5>
            <p class="text-sm text-gray-600 mb-4">Defina aqui para quem o sistema deve enviar as notificações importantes.</p>
            
            <div class="mb-4">
                <label for="alert_emails" class="block text-sm font-medium text-gray-700">E-mails para receber Alertas</label>
                <textarea class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="alert_emails" name="alert_emails" rows="3" placeholder="Separe múltiplos e-mails por vírgula. ex: admin@site.com, gerente@site.com"><?php echo htmlspecialchars($alert_config['alert_emails'] ?? ''); ?></textarea>
                <small class="text-gray-500">Estes e-mails receberão notificações sobre novos orçamentos, serviços vencendo, etc.</small>
            </div>

            <div class="mb-4">
                <div class="flex items-center">
                    <input type="checkbox" class="h-4 w-4 text-blue-500 border-gray-300 rounded" id="alert_servico_vencido_enabled" name="alert_servico_vencido_enabled" value="1" <?php echo ($alert_config['alert_servico_vencido_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="ml-2 text-sm text-gray-700" for="alert_servico_vencido_enabled">Ativar alertas de serviços que passaram da validade</label>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="text-lg font-medium mt-4">2. Configurações de Conexão (SMTP)</h5>
            <p class="text-sm text-gray-600 mb-4">Dados técnicos para o envio de todos os e-mails do sistema.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="smtp_host" class="block text-sm font-medium text-gray-700">Servidor SMTP (Host)</label>
                    <input type="text" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_config['smtp_host'] ?? ''); ?>" placeholder="ex: smtp.gmail.com">
                </div>
                <div class="mb-4">
                    <label for="smtp_port" class="block text-sm font-medium text-gray-700">Porta</label>
                    <input type="number" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_config['smtp_port'] ?? ''); ?>" placeholder="ex: 587">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="smtp_user" class="block text-sm font-medium text-gray-700">Usuário (E-mail)</label>
                    <input type="email" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($smtp_config['smtp_user'] ?? ''); ?>" placeholder="ex: seu_email@provedor.com">
                </div>
                <div class="mb-4">
                    <label for="smtp_pass" class="block text-sm font-medium text-gray-700">Senha</label>
                    <input type="password" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_pass" name="smtp_pass" placeholder="Deixe em branco para não alterar">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="smtp_security" class="block text-sm font-medium text-gray-700">Segurança</label>
                <select class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_security" name="smtp_security">
                    <option value="tls" <?php echo ($smtp_config['smtp_security'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo ($smtp_config['smtp_security'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                </select>
            </div>

            <hr class="my-4">
            <h5 class="text-lg font-medium mt-4">Remetente Padrão</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="smtp_from_email" class="block text-sm font-medium text-gray-700">E-mail do Remetente</label>
                    <input type="email" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp_config['smtp_from_email'] ?? ''); ?>" placeholder="ex: nao-responda@suaempresa.com">
                </div>
                <div class="mb-4">
                    <label for="smtp_from_name" class="block text-sm font-medium text-gray-700">Nome do Remetente</label>
                    <input type="text" class="form-control mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp_config['smtp_from_name'] ?? ''); ?>" placeholder="ex: Nome da Sua Empresa">
                </div>
            </div>

            <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 mt-4"> 
                <i class="fas fa-save"></i> Salvar Todas as Configurações
            </button>
            
            <a href="admin.php?action=test_smtp" class="bg-teal-500 text-white px-6 py-2 rounded-md hover:bg-teal-600 mt-4 inline-block ml-3">
                <i class="fas fa-paper-plane"></i> Testar Conexão
            </a>        
        </form>
    </div>
</div>
