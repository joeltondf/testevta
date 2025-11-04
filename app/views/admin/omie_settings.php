<?php
// /app/views/admin/omie_settings.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$serviceTaxationCode = $settings['omie_os_taxation_code'] ?? '01';
$serviceTaxationOptions = [
    '01' => '01 - Tributação no município',
    '02' => '02 - Tributação fora do município',
    '03' => '03 - Isenção',
    '04' => '04 - Imune',
    '05' => '05 - Exigibilidade suspensa por decisão judicial',
    '06' => '06 - Exigibilidade suspensa por procedimento administrativo',
];
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Configurações da Integração Omie</h1>

    <?php include_once __DIR__ . '/../partials/messages.php'; ?>

    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <form action="admin.php?action=save_settings" method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Credenciais da API -->
                <div class="md:col-span-2">
                    <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Credenciais da API</h2>
                </div>
                <div>
                    <label for="omie_app_key" class="block text-sm font-medium text-gray-700">Omie App Key</label>
                    <input type="text" name="omie_app_key" id="omie_app_key" value="<?php echo htmlspecialchars($settings['omie_app_key'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="omie_app_secret" class="block text-sm font-medium text-gray-700">Omie App Secret</label>
                    <input type="password" name="omie_app_secret" id="omie_app_secret" value="<?php echo htmlspecialchars($settings['omie_app_secret'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>

                <!-- Configurações Padrão para Ordem de Serviço -->
                <div class="md:col-span-2 mt-4">
                    <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Padrões para Ordem de Serviço (OS)</h2>
                </div>
                <div>
                    <label for="omie_os_service_code" class="block text-sm font-medium text-gray-700">Código do Serviço Padrão (LC 116)</label>
                    <input type="text" name="omie_os_service_code" id="omie_os_service_code" value="<?php echo htmlspecialchars($settings['omie_os_service_code'] ?? '1.07'); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Ex: 1.07 para Suporte Técnico. Consulte a documentação da Omie.</p>
                </div>
                <div>
                    <label for="omie_os_taxation_code" class="block text-sm font-medium text-gray-700">Tipo de Tributação do Serviço (cTribServ)</label>
                    <select name="omie_os_taxation_code" id="omie_os_taxation_code" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm bg-white">
                        <?php foreach ($serviceTaxationOptions as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $serviceTaxationCode === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Utilize um dos códigos oficiais da NFS-e (01 a 06). Ajuste a retenção de impostos no cadastro do serviço, se necessário.</p>
                </div>
                <div>
                    <label for="omie_os_category_code" class="block text-sm font-medium text-gray-700">Código da Categoria Padrão da OS</label>
                    <input type="text" name="omie_os_category_code" id="omie_os_category_code" value="<?php echo htmlspecialchars($settings['omie_os_category_code'] ?? '1.01.02'); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Ex: 1.01.02 para Serviços. Consulte a documentação da Omie.</p>
                </div>
                <div>
                    <label for="omie_os_bank_account_code" class="block text-sm font-medium text-gray-700">Código da Conta Corrente (nCodCC)</label>
                    <input type="text" name="omie_os_bank_account_code" id="omie_os_bank_account_code" value="<?php echo htmlspecialchars($settings['omie_os_bank_account_code'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Informe o código da conta corrente cadastrada na Omie responsável pela OS.</p>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 mt-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Sincronização de Produtos</h2>
        <p class="text-sm text-gray-600 mb-4">
            Utilize a sincronização abaixo para atualizar os produtos e serviços cadastrados na Omie. Certifique-se de que as
            credenciais estejam atualizadas antes de executar.
        </p>
        <form action="admin.php?action=sync_omie_support" method="POST" onsubmit="return confirm('Deseja sincronizar os produtos com a Omie agora?');">
            <button type="submit" class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">
                Sincronizar Produtos com a Omie
            </button>
        </form>
    </div>
</div>