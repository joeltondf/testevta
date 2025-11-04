<?php
// Arquivo: crm/clientes/novo.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php'; // Garante que o usuário está logado
require_once __DIR__ . '/../../app/views/layouts/header.php';

$defaultRedirectUrl = APP_URL . '/crm/prospeccoes/nova.php';
$redirectUrl = filter_input(INPUT_GET, 'redirect_url', FILTER_SANITIZE_URL) ?: '';

if (strpos($redirectUrl, APP_URL) !== 0) {
    $redirectUrl = $defaultRedirectUrl;
}

$defaultPhoneDdi = '55';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-4">
        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
            Cadastrar Novo Lead
        </h1>
    </div>
    
    <div class="mt-6">
        <form action="<?php echo APP_URL; ?>/crm/clientes/salvar.php" method="POST" class="space-y-6">
            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirectUrl); ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="md:col-span-2">
                    <label for="nome_cliente" class="block text-sm font-medium text-gray-700">Nome do Lead / Empresa</label>
                    <input type="text" name="nome_cliente" id="nome_cliente" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60">
                </div>

                <div>
                    <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">Nome do Lead Principal <span class="text-gray-500 font-normal">(Opcional)</span></label>
                    <input type="text" name="nome_responsavel" id="nome_responsavel" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60">
                </div>
                
                <div>
                    <label for="canal_origem" class="block text-sm font-medium text-gray-700">Canal de Origem</label>
                    <select name="canal_origem" id="canal_origem" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <option value="">Selecione um canal</option>
                        <option value="Google (Anúncios/SEO)">Google (Anúncios/SEO)</option>
                        <option value="Website">Website</option>
                        <option value="Indicação Cliente">Indicação Cliente</option>
                        <option value="Instagram">Instagram</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Whatsapp">Whatsapp</option>
                        <option value="Bitrix">Bitrix</option>
                        <option value="Call">Call</option>
                        <option value="Indicação Cartório">Indicação Cartório</option>
                        <option value="Evento">Evento</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                </div>
                
                <div>
                    <label for="telefone" class="block text-sm font-medium text-gray-700">Telefone</label>
                    <div class="mt-1 flex items-stretch gap-2">
                        <div class="w-24">
                            <input
                                type="text"
                                id="telefone_ddi"
                                name="telefone_ddi"
                                inputmode="numeric"
                                pattern="\d{1,4}"
                                maxlength="4"
                                value="<?php echo htmlspecialchars($defaultPhoneDdi); ?>"
                                class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            >
                        </div>
                        <div class="flex-1">
                            <input
                                type="tel"
                                name="telefone"
                                id="telefone"
                                autocomplete="tel"
                                maxlength="20"
                                class="mt-0 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            >
                        </div>
                    </div>
                </div>

            </div>

            <div class="pt-5 flex justify-end border-t mt-6">
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar Lead</button>
            </div>
        </form>
    </div>
</div>
<script>
// Função para máscara de telefone – copia do cadastro padrão de clientes
function aplicarMascaraTelefone(value) {
    value = value.replace(/\D/g, '');
    if (value.length <= 10) {
        // Telefone fixo: (00) 0000-0000
        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    } else {
        // Celular: (00) 00000-0000
        value = value.substring(0, 11);
        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
    }
    return value;
}

document.addEventListener('DOMContentLoaded', function() {
    const telefoneInput = document.getElementById('telefone');
    const telefoneDdiInput = document.getElementById('telefone_ddi');

    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            e.target.value = aplicarMascaraTelefone(e.target.value);
        });
        telefoneInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraTelefone(e.target.value);
            }, 100);
        });
    }

    if (telefoneDdiInput) {
        telefoneDdiInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }
});

</script>


<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>