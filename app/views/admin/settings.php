<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Configurações</h1>
    <p class="text-gray-600">Ajuste as configurações globais do aplicativo e de integrações.</p>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg">
    <form action="admin.php?action=save_settings" method="POST">
        <p class="text-sm text-gray-500">Utilize esta página para ajustar parâmetros administrativos gerais do CRM.</p>
        <p class="text-sm text-gray-500 mt-2">As credenciais da integração Omie estão disponíveis em <a href="admin.php?action=omie_settings" class="text-indigo-600 hover:underline">Administração &gt; Integração Omie</a>.</p>

        <div class="mt-10">
            <h2 class="text-xl font-bold text-gray-800 mb-1">Comissões</h2>
            <p class="text-sm text-gray-500 mb-4">Defina o percentual da comissão do SDR.</p>

            <div class="space-y-4">
                <div>
                    <label for="percentual_sdr" class="block text-sm font-medium text-gray-700">
                        Percentual SDR (%)
                    </label>
                    <input
                        type="number"
                        name="percentual_sdr"
                        id="percentual_sdr"
                        step="0.01"
                        min="0"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        value="<?php echo htmlspecialchars(number_format((float) ($settings['percentual_sdr'] ?? 0.5), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="0.50"
                    >
                </div>
            </div>
        </div>

        <div class="mt-8 pt-5 border-t border-gray-200">
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Salvar Configurações
                </button>
            </div>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
