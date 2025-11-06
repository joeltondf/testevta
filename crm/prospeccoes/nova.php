<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';

$prospectionModel = new Prospeccao($pdo);

$userPerfil = $_SESSION['user_perfil'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userNome = $_SESSION['user_nome'] ?? 'Usuário';

$selectedClientId = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);
$leadRecord = null;
$leadVendorId = null;
$leadVendorName = null;
$paymentProfileOptions = [
    '' => 'Selecione uma opção',
    'mensalista' => 'Possível mensalista',
    'avista' => 'Possível à vista',
];

try {
    $baseSelect = "SELECT c.id,
                           c.nome_cliente,
                           c.nome_responsavel,
                           c.crmOwnerId,
                           owner.nome_completo AS crm_owner_name
                    FROM clientes c
                    LEFT JOIN users owner ON owner.id = c.crmOwnerId
                    WHERE c.is_prospect = 1";

    $baseParams = [];
    if ($userPerfil === 'vendedor' && $userId > 0) {
        $baseSelect .= " AND c.crmOwnerId = :ownerId";
        $baseParams[':ownerId'] = $userId;
    }

    if ($selectedClientId) {
        $sqlSelected = $baseSelect . " AND c.id = :clientId";
        $stmtSelected = $pdo->prepare($sqlSelected);
        foreach ($baseParams as $key => $value) {
            $stmtSelected->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmtSelected->bindValue(':clientId', $selectedClientId, PDO::PARAM_INT);
        $stmtSelected->execute();
        $candidate = $stmtSelected->fetch(PDO::FETCH_ASSOC);

        if ($candidate && !$prospectionModel->hasActiveProspectionForClient((int) $candidate['id'])) {
            $leadRecord = $candidate;
        }
    }

    if ($leadRecord === null) {
        $sqlFirst = $baseSelect . "
                    AND NOT EXISTS (
                        SELECT 1
                        FROM prospeccoes p
                        WHERE p.cliente_id = c.id
                          AND p.status NOT IN ('Descartado', 'Convertido', 'Cliente Ativo', 'Inativo')
                    )
                    ORDER BY c.nome_cliente ASC
                    LIMIT 1";
        $stmtFirst = $pdo->prepare($sqlFirst);
        foreach ($baseParams as $key => $value) {
            $stmtFirst->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmtFirst->execute();
        $leadRecord = $stmtFirst->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($leadRecord) {
        $leadVendorId = (int) ($leadRecord['crmOwnerId'] ?? 0);
        if ($leadVendorId <= 0 || $leadVendorId === 17) {
            $leadVendorId = null;
        }

        if ($leadVendorId !== null) {
            $leadVendorName = $leadRecord['crm_owner_name'] ?? null;
            if ($leadVendorName === null) {
                $stmtVendor = $pdo->prepare('SELECT nome_completo FROM users WHERE id = :vendorId');
                $stmtVendor->bindValue(':vendorId', $leadVendorId, PDO::PARAM_INT);
                $stmtVendor->execute();
                $leadVendorName = $stmtVendor->fetchColumn() ?: null;
            }
        } else {
            $lastProspection = $prospectionModel->findLatestProspectionByClient((int) $leadRecord['id']);
            if ($lastProspection && !empty($lastProspection['responsavel_id'])) {
                $leadVendorId = (int) $lastProspection['responsavel_id'];
                $leadVendorName = $lastProspection['vendor_name'] ?? null;
            }
        }
    }
} catch (PDOException $exception) {
    die('Erro ao buscar leads disponíveis: ' . $exception->getMessage());
}

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Criar Nova Prospecção</h1>

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

    <?php if (!$leadRecord): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-bold">Nenhum lead disponível para prospecção.</p>
            <p>Cadastre um novo lead para continuar. <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="font-bold underline hover:text-yellow-800">Clique aqui para cadastrar.</a></p>
        </div>
    <?php else: ?>
        <form action="<?php echo APP_URL; ?>/crm/prospeccoes/salvar.php" method="POST" class="space-y-6" id="form-nova-prospeccao">
            <input type="hidden" name="cliente_id" value="<?php echo (int) $leadRecord['id']; ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700">Lead selecionado</label>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($leadRecord['nome_cliente']); ?></p>
                <?php if (!empty($leadRecord['nome_responsavel'])): ?>
                    <p class="text-sm text-gray-500">Contato: <?php echo htmlspecialchars($leadRecord['nome_responsavel']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Vínculo com vendedor</label>
                <?php if ($userPerfil === 'vendedor'): ?>
                    <input type="text" readonly value="<?php echo htmlspecialchars($userNome); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-100 text-gray-700">
                    <p class="text-xs text-gray-500 mt-1">Este lead será vinculado diretamente a você.</p>
                <?php elseif ($leadVendorId !== null && $leadVendorName !== null): ?>
                    <input type="text" readonly value="<?php echo htmlspecialchars($leadVendorName); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-100 text-gray-700">
                    <p class="text-xs text-gray-500 mt-1">O lead já possui um vendedor responsável cadastrado.</p>
                <?php else: ?>
                    <div class="mt-1 border border-dashed border-gray-300 rounded-md p-4 bg-gray-50 text-gray-600">
                        Este lead será distribuído automaticamente para o próximo vendedor disponível na fila de round-robin.
                    </div>
                <?php endif; ?>
            </div>

            <div class="pt-4">
                <label for="perfil_pagamento" class="block text-sm font-medium text-gray-700">Perfil de pagamento</label>
                <select id="perfil_pagamento" name="perfil_pagamento" class="mt-1 block w-full md:w-1/2 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <?php foreach ($paymentProfileOptions as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $value === '' ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end pt-4">
                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded hover:bg-gray-300 mr-3">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">Prospectar</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../app/views/layouts/footer.php';
