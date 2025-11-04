<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/User.php';

$userModel = new User($pdo);

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';

$channelOptions = [
    'Call',
    'LinkedIn',
    'Instagram',
    'Whatsapp',
    'Indicação Cliente',
    'Indicação Cartório',
    'Website',
    'Bitrix',
    'Evento',
    'Outro'
];

$defaultChannel = 'Outro';

$assignedOwnerId = $currentUserPerfil === 'vendedor' ? $currentUserId : null;
$vendors = $currentUserPerfil === 'vendedor' ? [] : $userModel->getActiveVendors();

$pageTitle = 'Importar Leads via CSV';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $pageTitle; ?></h1>
        <form action="<?php echo APP_URL; ?>/crm/clientes/importar_processar.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <div class="flex items-center justify-between">
                    <label for="csv_file" class="block text-sm font-medium text-gray-700">Arquivo CSV</label>
                    <a href="<?php echo APP_URL; ?>/crm/clientes/importar_modelo.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Baixar modelo</a>
                </div>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="mt-1 block w-full text-sm text-gray-700" />
                <p class="mt-2 text-sm text-gray-500">Estrutura esperada: Nome do Lead / Empresa, Nome do Lead Principal, E-mail, Telefone.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="default_channel" class="block text-sm font-medium text-gray-700">Canal de Origem padrão</label>
                    <select id="default_channel" name="default_channel" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <?php foreach ($channelOptions as $channel): ?>
                            <option value="<?php echo htmlspecialchars($channel); ?>" <?php echo $channel === $defaultChannel ? 'selected' : ''; ?>><?php echo htmlspecialchars($channel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="delimiter" class="block text-sm font-medium text-gray-700">Delimitador</label>
                    <select id="delimiter" name="delimiter" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <option value=";">Ponto e vírgula (;)</option>
                        <option value=",">Vírgula (,)</option>
                    </select>
                </div>

                <div class="flex items-center pt-6">
                    <input id="has_header" name="has_header" type="checkbox" value="1" checked class="h-4 w-4 text-blue-600 border-gray-300 rounded" />
                    <label for="has_header" class="ml-2 block text-sm text-gray-700">Primeira linha contém cabeçalho</label>
                </div>
            </div>

            <?php if ($currentUserPerfil === 'vendedor'): ?>
                <input type="hidden" name="assigned_owner" value="<?php echo (int) $assignedOwnerId; ?>" />
            <?php else: ?>
                <div>
                    <label for="assigned_owner" class="block text-sm font-medium text-gray-700">Responsável pelos leads</label>
                    <select id="assigned_owner" name="assigned_owner" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <option value="" disabled selected>Selecione um vendedor</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?php echo (int) $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Pré-visualização do cabeçalho esperado</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 border border-gray-200 text-left">Nome do Lead / Empresa</th>
                                <th class="px-4 py-2 border border-gray-200 text-left">Nome do Lead Principal</th>
                                <th class="px-4 py-2 border border-gray-200 text-left">E-mail</th>
                                <th class="px-4 py-2 border border-gray-200 text-left">Telefone</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Importar Leads</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../../app/views/layouts/footer.php';
?>
