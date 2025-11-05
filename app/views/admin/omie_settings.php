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
$omieSupportResources = [
    [
        'type' => 'etapas',
        'title' => 'Etapas de Faturamento',
        'description' => 'Etapas disponíveis na Omie para vincular pedidos de serviço.',
    ],
    [
        'type' => 'categorias',
        'title' => 'Categorias de Serviço',
        'description' => 'Categorias de faturamento utilizadas na geração das OS.',
    ],
    [
        'type' => 'contas',
        'title' => 'Contas Correntes',
        'description' => 'Contas bancárias cadastradas na Omie para recebimentos.',
    ],
    [
        'type' => 'cenarios',
        'title' => 'Cenários Fiscais',
        'description' => 'Cenários de impostos aplicados aos serviços emitidos.',
    ],
    [
        'type' => 'produtos',
        'title' => 'Produtos e Serviços',
        'description' => 'Catálogo de itens disponíveis para orçamentos e OS.',
    ],
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
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Sincronização de Dados da Omie</h2>
        <p class="text-sm text-gray-600 mb-6">
            Atualize e consulte as tabelas auxiliares importadas da Omie. Esses dados alimentam o cadastro de processos e a geração de Ordens de Serviço.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($omieSupportResources as $resource): ?>
                <div class="border border-gray-200 rounded-lg p-4 flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($resource['title']); ?></h3>
                        <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($resource['description']); ?></p>
                    </div>
                    <div class="flex items-center justify-between mt-auto space-x-2">
                        <a href="admin.php?action=omie_support&amp;type=<?php echo urlencode($resource['type']); ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100">
                            Ver registros
                        </a>
                        <form action="admin.php?action=sync_omie_support" method="POST" onsubmit="return confirm('Deseja sincronizar <?php echo addslashes($resource['title']); ?> com a Omie agora?');">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($resource['type']); ?>">
                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">
                                <i class="fas fa-sync-alt mr-2"></i> Sincronizar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>