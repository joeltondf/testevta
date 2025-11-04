<?php
//  crm/clientes/lista.php 

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Cliente.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';

$clienteModel = new Cliente($pdo);
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$prospectionFilter = $_GET['prospection'] ?? 'all';
$ownerFilter = null;

if (!in_array($prospectionFilter, ['all', 'prospected', 'unprospected'], true)) {
    $prospectionFilter = 'all';
}

if ($currentUserPerfil !== 'vendedor') {
    $ownerFilter = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int) $_GET['owner_id'] : null;
}

$filters = [
    'search' => $searchQuery,
    'prospection' => $prospectionFilter,
    'ownerId' => $ownerFilter,
];

$clientes = $clienteModel->getCrmProspects($currentUserId, $currentUserPerfil, $filters);
$statsFilters = ['search' => $searchQuery];
if ($ownerFilter) {
    $statsFilters['ownerId'] = $ownerFilter;
}
$stats = $clienteModel->getProspectStats($currentUserId, $currentUserPerfil, $statsFilters);

$escapeHtml = static function ($value): string {
    if ($value === null) {
        return '';
    }

    if ($value instanceof Stringable) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    if (is_scalar($value)) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    return '';
};

$showVendorLinkColumn = in_array($currentUserPerfil, ['admin', 'gerencia', 'supervisor', 'sdr'], true);
$vendorsForFilter = [];
if ($currentUserPerfil !== 'vendedor') {
    $userModel = new User($pdo);
    $vendorsForFilter = $userModel->getActiveVendors();
}

$formatLeadPhone = static function (array $cliente): string {
    $rawPhone = $cliente['telefone'] ?? '';
    $ddiValue = $cliente['telefone_ddi'] ?? '';
    $dddValue = $cliente['telefone_ddd'] ?? '';
    $numberValue = $cliente['telefone_numero'] ?? '';

    $digits = stripNonDigits((string) $rawPhone);
    $ddiDigits = stripNonDigits((string) $ddiValue);

    if ($digits === '' && ($dddValue !== '' || $numberValue !== '')) {
        $digits = stripNonDigits((string) $dddValue . $numberValue);
    }

    if ($digits === '') {
        return '';
    }

    if ($ddiDigits !== '' && strpos($digits, $ddiDigits) === 0 && strlen($digits) > strlen($ddiDigits)) {
        $digits = substr($digits, strlen($ddiDigits));
    }

    try {
        $parts = extractPhoneParts($digits);
        $ddiToUse = $ddiDigits !== '' ? $ddiDigits : '55';

        return formatInternationalPhone($ddiToUse, $parts['ddd'] ?? '', $parts['phone'] ?? '');
    } catch (Throwable $exception) {
        return (string) $rawPhone;
    }
};

$pageTitle = "CRM - Lista de Leads";
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="w-full max-w-9/10 px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="<?php echo APP_URL; ?>/crm/clientes/importar.php" class="bg-green-600 text-white py-2 px-4 rounded-md shadow-md hover:bg-green-700 transition duration-300 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Importar Leads
            </a>
            <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="bg-blue-600 text-white py-2 px-4 rounded-md shadow-md hover:bg-blue-700 transition duration-300 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Novo Lead
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['import_summary'])): ?>
        <?php $summary = $_SESSION['import_summary']; unset($_SESSION['import_summary']); ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
            <p class="font-semibold">Resumo da importação</p>
            <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                <li>Leads criados: <?php echo (int)($summary['created'] ?? 0); ?></li>
                <li>Linhas ignoradas: <?php echo (int)($summary['skipped'] ?? 0); ?></li>
                <li>Registros duplicados: <?php echo (int)($summary['duplicates'] ?? 0); ?></li>
                <?php if (isset($summary['discarded'])): ?>
                    <li>Registros descartados: <?php echo (int)($summary['discarded'] ?? 0); ?></li>
                <?php endif; ?>
            </ul>
            <?php if (!empty($summary['errors'])): ?>
                <div class="mt-3 text-sm">
                    <p class="font-semibold">Ocorrências:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($summary['errors'] as $errorMessage): ?>
                            <li><?php echo $escapeHtml($errorMessage); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white shadow rounded-lg p-4 border border-gray-200">
            <p class="text-sm text-gray-500">Leads cadastrados</p>
            <p class="text-2xl font-semibold text-gray-800"><?php echo (int) ($stats['total'] ?? 0); ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-4 border border-gray-200">
            <p class="text-sm text-gray-500">Leads prospectados</p>
            <p class="text-2xl font-semibold text-emerald-600"><?php echo (int) ($stats['prospected'] ?? 0); ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-4 border border-gray-200">
            <p class="text-sm text-gray-500">Leads não prospectados</p>
            <p class="text-2xl font-semibold text-amber-600"><?php echo (int) ($stats['unprospected'] ?? 0); ?></p>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
        <form method="GET" class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="search" name="search" value="<?php echo $escapeHtml($searchQuery); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Nome, e-mail ou telefone">
                </div>
                <div>
                    <label for="prospection" class="block text-sm font-medium text-gray-700">Status de prospecção</label>
                    <select id="prospection" name="prospection" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all" <?php echo $prospectionFilter === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="prospected" <?php echo $prospectionFilter === 'prospected' ? 'selected' : ''; ?>>Prospectados</option>
                        <option value="unprospected" <?php echo $prospectionFilter === 'unprospected' ? 'selected' : ''; ?>>Não prospectados</option>
                    </select>
                </div>
                <?php if ($currentUserPerfil !== 'vendedor'): ?>
                    <div>
                        <label for="owner_id" class="block text-sm font-medium text-gray-700">Responsável</label>
                        <select id="owner_id" name="owner_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <?php foreach ($vendorsForFilter as $vendor): ?>
                                <option value="<?php echo (int) $vendor['id']; ?>" <?php echo ($ownerFilter === (int) $vendor['id']) ? 'selected' : ''; ?>>
                                    <?php echo $escapeHtml($vendor['nome_completo'] ?? null); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex justify-end gap-3">
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Limpar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Filtrar</button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nome / Empresa</th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Lead</th>
                        <?php if ($showVendorLinkColumn): ?>
                            <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Vínculo com Vendedor</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="<?php echo $showVendorLinkColumn ? '5' : '4'; ?>" class="px-6 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">Nenhuma prospecção encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                                $totalProspeccoes = (int) ($cliente['totalProspeccoes'] ?? 0);
                                $hasProspection = $totalProspeccoes > 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-medium"><?php echo $escapeHtml($cliente['nome_cliente'] ?? null); ?></p>
                                </td>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo $escapeHtml($cliente['email'] ?? null); ?></p>
                                    <p class="text-gray-600 whitespace-no-wrap mt-1"><?php echo $escapeHtml($formatLeadPhone($cliente)); ?></p>
                                </td>
                                <?php if ($showVendorLinkColumn): ?>
                                    <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm">
                                        <?php
                                            $ownerName = trim((string) ($cliente['ownerName'] ?? ''));
                                            $ownerLabel = $ownerName !== '' ? $ownerName : 'Sem vínculo';
                                        ?>
                                        <p class="text-gray-900 whitespace-no-wrap"><?php echo $escapeHtml($ownerLabel); ?></p>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <?php
                                        $statusClass = $hasProspection
                                            ? 'inline-block px-3 py-1 text-sm font-semibold text-green-800 bg-green-200 rounded-full'
                                            : 'inline-block px-3 py-1 text-sm font-semibold text-red-800 bg-red-200 rounded-full';
                                        $statusLabel = $hasProspection ? 'Prospecção' : 'Sem prospecção';
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                </td>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <div class="flex justify-center items-center space-x-3">
                                        <?php if (!$hasProspection): ?>
                                            <a href="<?php echo APP_URL; ?>/crm/prospeccoes/nova.php?cliente_id=<?php echo $cliente['id']; ?>" class="text-green-600 hover:text-green-800 font-semibold">Criar prospecção</a>
                                        <?php endif; ?>
                                        <a href="<?php echo APP_URL; ?>/crm/clientes/editar_cliente.php?id=<?php echo $cliente['id']; ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Editar</a>

                                        <form action="<?php echo APP_URL; ?>/crm/clientes/excluir_cliente.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este lead?');">
                                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../app/views/layouts/footer.php';
?>