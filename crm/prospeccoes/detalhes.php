<?php
// Arquivo: crm/prospeccoes/detalhes.php (VERSÃO CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Configuracao.php';

$user_perfil = $_SESSION['user_perfil'];
$prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
    exit;
}

function formatSaoPauloDate(?string $dateTime, string $format = 'd/m/Y H:i', string $sourceTimezone = 'UTC'): string
{
    if ($dateTime === null || trim($dateTime) === '') {
        return '';
    }

    try {
        $date = new \DateTime($dateTime, new \DateTimeZone($sourceTimezone));
        $date->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

        return $date->format($format);
    } catch (\Exception $exception) {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            return $dateTime;
        }

        return date($format, $timestamp);
    }
}

function formatLeadInternationalPhone(array $data): string
{
    $rawPhone = $data['cliente_telefone'] ?? '';
    $ddiValue = $data['cliente_telefone_ddi'] ?? '';
    $dddValue = $data['cliente_telefone_ddd'] ?? '';
    $numberValue = $data['cliente_telefone_numero'] ?? '';

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
}

try {
    // A consulta já está correta
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome_completo AS responsavel_nome, c.nome_cliente, c.nome_responsavel AS lead_responsavel_nome,
               c.telefone AS cliente_telefone, c.telefone_ddi AS cliente_telefone_ddi,
               c.telefone_ddd AS cliente_telefone_ddd, c.telefone_numero AS cliente_telefone_numero,
               c.canal_origem AS cliente_canal_origem
        FROM prospeccoes p
        LEFT JOIN users u ON p.responsavel_id = u.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $prospeccao_id]);
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prospect) {
        header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
        exit;
    }

    // A busca de interações também está correta
    $stmt_interacoes = $pdo->prepare("
        SELECT i.observacao, i.data_interacao, i.tipo, u.nome_completo AS usuario_nome 
        FROM interacoes i 
        LEFT JOIN users u ON i.usuario_id = u.id 
        WHERE i.prospeccao_id = :prospeccao_id 
        ORDER BY i.data_interacao DESC
    ");
    $stmt_interacoes->execute(['prospeccao_id' => $prospeccao_id]);
    $interacoes = $stmt_interacoes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados da prospecção: " . $e->getMessage());
}

$loggedUserId = (int) ($_SESSION['user_id'] ?? 0);
$managementProfiles = ['admin', 'gerencia', 'supervisor'];
$availableVendors = [];
if (in_array($user_perfil, $managementProfiles, true)) {
    $userModel = new User($pdo);
    $availableVendors = $userModel->getActiveVendors();
}

$configModel = new Configuracao($pdo);
$hasManagementPassword = trim((string) ($configModel->get('prospection_management_password_hash') ?? '')) !== '';

$leadCategories = ['Entrada', 'Qualificado', 'Com Orçamento', 'Em Negociação', 'Cliente Ativo', 'Sem Interesse'];
$currentLeadCategory = $prospect['leadCategory'] ?? 'Entrada';
if (!in_array($currentLeadCategory, $leadCategories, true)) {
    $currentLeadCategory = 'Entrada';
}
$paymentProfileOptions = [
    'mensalista' => 'Possível mensalista',
    'avista' => 'Possível à vista',
];
$currentPaymentProfile = $prospect['perfil_pagamento'] ?? '';
if (!in_array($currentPaymentProfile, array_keys($paymentProfileOptions), true)) {
    $currentPaymentProfile = '';
}
$redirectUrl = APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospect['id'];

$canManageVendor = in_array($user_perfil, $managementProfiles, true);
$canScheduleInternal = in_array($user_perfil, ['sdr', 'admin', 'gerencia', 'supervisor'], true);
$canConvertDirectly = $canManageVendor;
$canRequestConversion = ($user_perfil === 'sdr');
$isResponsibleVendor = ($user_perfil === 'vendedor' && (int) ($prospect['responsavel_id'] ?? 0) === $loggedUserId);
$hasAssignedVendor = (int) ($prospect['responsavel_id'] ?? 0) > 0;

$canScheduleMeeting = $hasAssignedVendor && (
    $isResponsibleVendor ||
    in_array($user_perfil, ['sdr', 'admin', 'gerencia', 'supervisor'], true)
);
$meetingResponsibleName = trim((string) ($prospect['responsavel_nome'] ?? ''));
if ($meetingResponsibleName === '') {
    $meetingResponsibleName = 'Vendedor não definido';
}

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php if (isset($_GET['request_sent'])): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
  <strong class="font-bold">Sucesso!</strong>
  <span class="block sm:inline">Sua solicitação de exclusão foi enviada para a gerência/supervisão.</span>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <?php
                    $leadResponsavelNome = $prospect['lead_responsavel_nome'] ?? $prospect['nome_prospecto'] ?? '';
                    $leadNome = $prospect['nome_cliente'] ?? 'Lead não vinculado';
                    $clienteTelefone = formatLeadInternationalPhone($prospect);
                    $canalOrigem = $prospect['cliente_canal_origem'] ?? '';
                    $statusAtual = $prospect['status'] ?? '';
                ?>
                <div class="flex justify-between items-start mb-6">
                    <?php
                        $leadResponsavelNome = $prospect['lead_responsavel_nome'] ?? $prospect['nome_prospecto'] ?? '';
                        $budgetParams = [
                            'prospeccao_id' => $prospect['id'],
                            'return_to' => 'crm/prospeccoes/detalhes.php?id=' . $prospect['id'],
                        ];

                        $prospectClientId = $prospect['cliente_id'] ?? null;
                        if (!empty($prospectClientId)) {
                            $budgetParams['cliente_id'] = (int) $prospectClientId;
                        }

                        $prospectTitle = trim((string) ($prospect['nome_prospecto'] ?? ''));
                        if ($prospectTitle !== '') {
                            $budgetParams['titulo'] = $prospectTitle;
                        }

                        $budgetUrl = APP_URL . '/processos.php?action=create&' . http_build_query($budgetParams);

                    ?>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($leadNome); ?></h2>
                        <p class="text-sm text-gray-500">Responsável: <span class="font-medium text-indigo-600"><?php echo htmlspecialchars($leadResponsavelNome); ?></span></p>
                        <?php if (!empty($statusAtual) || !empty($currentLeadCategory)): ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php if (!empty($statusAtual)): ?>
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 uppercase tracking-wide"><?php echo htmlspecialchars($statusAtual); ?></span>
                                <?php endif; ?>
                                <span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 uppercase tracking-wide">Categoria: <?php echo htmlspecialchars($currentLeadCategory); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($user_perfil !== 'sdr'): ?>
                            <a href="<?php echo htmlspecialchars($budgetUrl); ?>" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 shadow-sm">
                                Orçamento
                            </a>
                        <?php endif; ?>

                        <?php if ($canScheduleInternal): ?>
                            <button type="button" data-modal-target="internalScheduleModal" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 shadow-sm">
                                Agendar
                            </button>
                        <?php endif; ?>

                        <?php if ($canManageVendor): ?>
                            <button type="button" data-modal-target="delegateVendorModal" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 shadow-sm">
                                Delegar para vendedor
                            </button>
                        <?php endif; ?>

                        <?php if ($canConvertDirectly): ?>
                            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/converter.php" method="POST" class="inline">
                                <input type="hidden" name="prospeccao_id" value="<?php echo (int) $prospect['id']; ?>">
                                <button type="submit" class="bg-teal-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-teal-700 shadow-sm">
                                    Converter
                                </button>
                            </form>
                        <?php elseif ($canRequestConversion): ?>
                            <div class="flex flex-col">
                                <form id="prospectionConversionForm" action="<?php echo APP_URL; ?>/crm/prospeccoes/converter.php" method="POST" class="inline-flex">
                                    <input type="hidden" name="prospeccao_id" value="<?php echo (int) $prospect['id']; ?>">
                                    <input type="hidden" name="authorization_token" id="conversion_authorization_token" value="">
                                    <button type="submit" id="convertProspectionButton" class="bg-teal-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-teal-700 shadow-sm">
                                        Converter
                                    </button>
                                </form>
                                <span id="conversionAuthorizationInfo" class="text-xs text-emerald-600 hidden mt-1"></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($canManageVendor): ?>
                            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/excluir_prospeccao.php" method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $prospect['id']; ?>">
                                <button type="submit" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 shadow-sm"
                                        onclick="return confirm('ATENÇÃO:\n\nTem certeza que deseja excluir esta prospecção?\n\nEsta ação é irreversível e apagará também todo o histórico de interações.');">
                                    Excluir
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" onclick="openModal()" class="bg-red-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-600 shadow-sm">
                                Solicitar Exclusão
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-4 mb-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Nome</label>
                            <input type="text" value="<?php echo htmlspecialchars($leadNome); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Nome do Responsável (Lead)</label>
                            <input type="text" value="<?php echo htmlspecialchars($leadResponsavelNome); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Telefone do Cliente</label>
                            <input type="text" value="<?php echo htmlspecialchars($clienteTelefone); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Origem da Chamada</label>
                            <input type="text" value="<?php echo htmlspecialchars($canalOrigem); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                    </div>
                </div>

                <form action="<?php echo APP_URL; ?>/crm/prospeccoes/atualizar.php" method="POST" class="space-y-6">
                    <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                    <input type="hidden" name="action" value="update_prospect">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="lead_category" class="block text-sm font-medium text-gray-700">Categoria do Lead</label>
                            <select name="lead_category" id="lead_category" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                                <?php foreach ($leadCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($currentLeadCategory === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="perfil_pagamento" class="block text-sm font-medium text-gray-700">Perfil de pagamento</label>
                            <select name="perfil_pagamento" id="perfil_pagamento" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                                <option value="" <?php echo $currentPaymentProfile === '' ? 'selected' : ''; ?>>Não informado</option>
                                <?php foreach ($paymentProfileOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentPaymentProfile === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center pt-6">
                            <input type="checkbox" name="reuniao_compareceu" id="reuniao_compareceu" value="1" <?php echo (!empty($prospect['reuniao_compareceu'])) ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="reuniao_compareceu" class="ml-2 block text-sm font-medium text-gray-700">Compareceu?</label>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t mt-6">
                        <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3">Voltar</a>
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Coluna da Direita: Card de Atividades e Histórico -->
    <div class="space-y-6">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Registrar Atividade</h3>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button onclick="openActivityModal('nota')" class="w-full bg-gray-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-gray-700 flex items-center justify-center">
                            <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span>Nota</span>
                        </button>
                        <button onclick="openActivityModal('chamada')" class="w-full bg-blue-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-blue-700 flex items-center justify-center">
                            <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span>Chamada</span>
                        </button>
                        <?php if ($canScheduleMeeting): ?>
                            <button type="button" data-modal-target="meetingScheduleModal" class="w-full bg-green-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-green-700 flex items-center justify-center">
                                <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>Reunião de venda</span>
                            </button>
                        <?php endif; ?>
                    </div>
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900">Histórico</h3>
                <div class="mt-4 flow-root">
                    <ul role="list" class="-mb-8">
                        <?php if (empty($interacoes)): ?>
                            <li><p class="text-gray-500 text-sm">Nenhuma interação registrada.</p></li>
                        <?php else: ?>
                            <?php foreach ($interacoes as $interacao): ?>
                            <?php
                                $tipo = $interacao['tipo'];
                                $bg_color = 'bg-gray-400'; $icon_svg = '';
                                if ($tipo == 'nota') { $bg_color = 'bg-gray-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>'; } 
                                elseif ($tipo == 'chamada') { $bg_color = 'bg-blue-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>'; } 
                                elseif ($tipo == 'reuniao') { $bg_color = 'bg-green-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'; } 
                                elseif ($tipo == 'log_sistema') { $bg_color = 'bg-yellow-400'; $icon_svg = '<svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" /><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" /></svg>'; }
                            ?>
                            <li>
                                <div class="relative pb-8">
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                                    <div class="relative flex space-x-3">
                                        <div><span class="h-8 w-8 rounded-full <?php echo $bg_color; ?> flex items-center justify-center ring-8 ring-white"><?php echo $icon_svg; ?></span></div>
                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                            <div>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($interacao['usuario_nome'] ?? 'Sistema'); ?></p>
                                                <p class="mt-1 text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($interacao['observacao'])); ?></p>
                                            </div>
                                            <div class="text-right text-sm whitespace-nowrap text-gray-500"><time><?php echo htmlspecialchars(formatSaoPauloDate($interacao['data_interacao'])); ?></time></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManageVendor): ?>
<div id="delegateVendorModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-60 px-4">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Delegar para vendedor</h2>
            <button type="button" data-modal-close="delegateVendorModal" class="text-gray-500 hover:text-gray-700">
                <span class="sr-only">Fechar</span>
                &times;
            </button>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($availableVendors)): ?>
                <p class="text-sm text-gray-600">Nenhum vendedor ativo disponível para distribuição.</p>
            <?php else: ?>
                <form action="<?php echo APP_URL; ?>/crm/prospeccoes/atualizar.php" method="POST" class="space-y-4" id="delegateVendorForm">
                    <input type="hidden" name="prospeccao_id" value="<?php echo (int) $prospect['id']; ?>">
                    <input type="hidden" name="action" value="assign_vendor">
                    <input type="hidden" name="delegation_token" id="delegation_token" value="">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="delegate_vendor_id" class="block text-sm font-medium text-gray-700">Selecione o vendedor</label>
                            <button type="button" data-open-manager-validation="vendor_delegation" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Validar gestor</button>
                        </div>
                        <p id="delegateLockNotice" class="text-xs text-red-600">Valide as credenciais de um gestor para desbloquear este campo.</p>
                        <p id="validatedManagerInfo" class="text-xs text-emerald-600 hidden"></p>
                        <select name="vendor_id" id="delegate_vendor_id" required disabled class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-gray-100 cursor-not-allowed">
                            <option value="">Escolha um vendedor</option>
                            <?php foreach ($availableVendors as $vendor): ?>
                                <option value="<?php echo (int) $vendor['id']; ?>" <?php echo ((int) ($prospect['responsavel_id'] ?? 0) === (int) $vendor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendor['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-modal-close="delegateVendorModal" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" id="delegateSubmitButton" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 opacity-50 cursor-not-allowed" disabled>Delegar</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="managerCredentialModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-60 px-4">
    <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Validar credenciais do gestor</h2>
            <button type="button" data-modal-close="managerCredentialModal" class="text-gray-500 hover:text-gray-700">
                <span class="sr-only">Fechar</span>
                &times;
            </button>
        </div>
        <form id="managerCredentialForm" class="px-6 py-4 space-y-4">
            <input type="hidden" name="authorization_context" id="manager_authorization_context" value="vendor_delegation">
            <div class="space-y-2">
                <span class="block text-sm font-medium text-gray-700">Como deseja validar?</span>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-6">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="radio" name="validation_mode" value="manager_login" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" checked>
                        <span class="ml-2">Login de gestor</span>
                    </label>
                    <?php if ($hasManagementPassword): ?>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="validation_mode" value="management_password" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                            <span class="ml-2">Senha da gerência</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <div data-validation-section="manager_login" class="space-y-4">
                <div>
                    <label for="manager_email" class="block text-sm font-medium text-gray-700">E-mail corporativo</label>
                    <input type="email" id="manager_email" name="email" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="gestor@empresa.com">
                </div>
                <div>
                    <label for="manager_password" class="block text-sm font-medium text-gray-700">Senha</label>
                    <input type="password" id="manager_password" name="password" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>
            <?php if ($hasManagementPassword): ?>
                <div data-validation-section="management_password" class="space-y-4 hidden">
                    <div>
                        <label for="management_password" class="block text-sm font-medium text-gray-700">Senha da gerência</label>
                        <input type="password" id="management_password" name="management_password" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Digite a senha configurada no admin">
                        <p class="mt-1 text-xs text-gray-500">Definida no painel administrativo. Apenas gestores ativos podem utilizá-la.</p>
                    </div>
                </div>
            <?php endif; ?>
            <p id="managerCredentialError" class="text-sm text-red-600 hidden"></p>
            <div class="flex justify-end gap-3">
                <button type="button" data-modal-close="managerCredentialModal" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Validar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($canScheduleInternal): ?>
<div id="internalScheduleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-60 px-4">
    <div class="w-full max-w-xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Agendar atividade interna</h2>
            <button type="button" data-modal-close="internalScheduleModal" class="text-gray-500 hover:text-gray-700">
                <span class="sr-only">Fechar</span>
                &times;
            </button>
        </div>
        <form action="<?php echo APP_URL; ?>/crm/agendamentos/salvar_agendamento.php" method="POST" class="px-6 py-4 space-y-4">
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectUrl); ?>">
            <input type="hidden" name="prospeccao_id" value="<?php echo (int) $prospect['id']; ?>">
            <?php if (!empty($prospect['cliente_id'])): ?>
                <input type="hidden" name="cliente_id" value="<?php echo (int) $prospect['cliente_id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="usuario_id" value="<?php echo $loggedUserId; ?>">
            <input type="hidden" name="agendamento_context" value="internal_followup">
            <input type="hidden" name="status" value="Pendente">
            <div>
                <label for="internal_title" class="block text-sm font-medium text-gray-700">Título</label>
                <input type="text" id="internal_title" name="titulo" value="Atividade interna" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="internalStart" class="block text-sm font-medium text-gray-700">Início</label>
                    <input type="datetime-local" id="internalStart" name="data_inicio" required step="1800" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                </div>
                <div>
                    <label for="internalEnd" class="block text-sm font-medium text-gray-700">Fim</label>
                    <input type="datetime-local" id="internalEnd" name="data_fim" required step="1800" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                </div>
            </div>
            <div>
                <label for="internal_location" class="block text-sm font-medium text-gray-700">Local ou link</label>
                <input type="text" id="internal_location" name="local_link" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" placeholder="Insira um link ou local">
            </div>
            <div>
                <label for="internal_notes" class="block text-sm font-medium text-gray-700">Observações</label>
                <textarea id="internal_notes" name="observacoes" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" data-modal-close="internalScheduleModal" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700">Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canScheduleMeeting): ?>
<div id="meetingScheduleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-60 px-4">
    <div class="w-full max-w-xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Agendar reunião com o lead</h2>
                <p class="mt-1 text-xs text-gray-500">Horários disponíveis entre 09:00 e 17:00, em intervalos de 30 minutos.</p>
            </div>
            <button type="button" data-modal-close="meetingScheduleModal" class="text-gray-500 hover:text-gray-700">
                <span class="sr-only">Fechar</span>
                &times;
            </button>
        </div>
        <form action="<?php echo APP_URL; ?>/crm/agendamentos/salvar_agendamento.php" method="POST" class="px-6 py-4 space-y-4">
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectUrl); ?>">
            <input type="hidden" name="prospeccao_id" value="<?php echo (int) $prospect['id']; ?>">
            <?php if (!empty($prospect['cliente_id'])): ?>
                <input type="hidden" name="cliente_id" value="<?php echo (int) $prospect['cliente_id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="usuario_id" value="<?php echo (int) ($prospect['responsavel_id'] ?? 0); ?>">
            <input type="hidden" name="agendamento_context" value="meeting">
            <input type="hidden" name="status" value="Confirmado">
            <div>
                <label class="block text-sm font-medium text-gray-700">Responsável</label>
                <input type="text" value="<?php echo htmlspecialchars($meetingResponsibleName); ?>" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 bg-gray-100 text-gray-700" disabled>
            </div>
            <div>
                <label for="meeting_title" class="block text-sm font-medium text-gray-700">Título</label>
                <input type="text" id="meeting_title" name="titulo" value="Reunião com lead" required class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="meetingStart" class="block text-sm font-medium text-gray-700">Início</label>
                    <input type="datetime-local" id="meetingStart" name="data_inicio" required step="1800" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="meetingEnd" class="block text-sm font-medium text-gray-700">Fim</label>
                    <input type="datetime-local" id="meetingEnd" name="data_fim" required step="1800" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label for="meeting_location" class="block text-sm font-medium text-gray-700">Local ou link</label>
                <input type="text" id="meeting_location" name="local_link" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" placeholder="Informe o local ou link da reunião">
            </div>
            <div>
                <label for="meeting_notes" class="block text-sm font-medium text-gray-700">Observações</label>
                <textarea id="meeting_notes" name="observacoes" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" data-modal-close="meetingScheduleModal" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Agendar reunião</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Dinâmico para Registrar Atividades -->
<div id="activityModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" onclick="closeActivityModal()"></div>
        <div class="bg-white rounded-lg shadow-xl m-4 max-w-lg w-full z-10">
            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/atualizar.php" method="POST">
                <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                <input type="hidden" name="action" value="add_interaction">
                <input type="hidden" name="tipo_interacao" id="tipo_interacao_hidden" value="">
                <div class="p-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Registrar Atividade</h3>
                    <div class="mt-4 space-y-4">
                        <div id="resultadoChamada" class="hidden">
                            <label for="resultado" class="block text-sm font-medium text-gray-700">Resultado da Chamada</label>
                            <select name="resultado" id="resultado" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option>Atendeu</option><option>Não atendeu</option><option>Caixa Postal</option><option>Número errado</option>
                            </select>
                        </div>
                        <div>
                            <label for="observacao" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="observacao" id="observacao" rows="4" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeActivityModal()" class="bg-white border border-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Solicitação de Exclusão -->
<div id="deleteRequestModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/solicitar_exclusao.php" method="POST">
                <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Solicitar Exclusão da Prospecção</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Informe o motivo. Sua solicitação será enviada por e-mail para a gerência/supervisão.</p>
                                <textarea name="motivo" rows="4" required class="mt-2 block w-full border border-gray-300 rounded-md shadow-sm" placeholder="Digite o motivo da exclusão..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Enviar Solicitação</button>
                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const activityModal = document.getElementById('activityModal');
    const modalTitle = document.getElementById('modalTitle');
    const tipoInteracaoHidden = document.getElementById('tipo_interacao_hidden');
    const resultadoChamadaDiv = document.getElementById('resultadoChamada');

    function openActivityModal(tipo) {
        tipoInteracaoHidden.value = tipo;
        if (tipo === 'nota') { modalTitle.innerText = 'Adicionar Nova Nota'; resultadoChamadaDiv.classList.add('hidden'); }
        else if (tipo === 'chamada') { modalTitle.innerText = 'Registrar Chamada Telefônica'; resultadoChamadaDiv.classList.remove('hidden'); }
        else if (tipo === 'reuniao') { modalTitle.innerText = 'Registrar Reunião'; resultadoChamadaDiv.classList.add('hidden'); }
        activityModal.classList.remove('hidden');
    }

    function closeActivityModal() { activityModal.classList.add('hidden'); }

    const toggleModalVisibility = (modalId, show) => {
        if (!modalId) {
            return;
        }
        const targetModal = document.getElementById(modalId);
        if (!targetModal) {
            return;
        }
        if (show) {
            targetModal.classList.remove('hidden');
        } else {
            targetModal.classList.add('hidden');
        }
    };

    document.querySelectorAll('[data-modal-target]').forEach((button) => {
        button.addEventListener('click', () => {
            toggleModalVisibility(button.getAttribute('data-modal-target'), true);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            toggleModalVisibility(button.getAttribute('data-modal-close'), false);
        });
    });

    const delegateVendorSelect = document.getElementById('delegate_vendor_id');
    const delegateSubmitButton = document.getElementById('delegateSubmitButton');
    const delegationTokenInput = document.getElementById('delegation_token');
    const delegateLockNotice = document.getElementById('delegateLockNotice');
    const validatedManagerInfo = document.getElementById('validatedManagerInfo');
    const managerCredentialForm = document.getElementById('managerCredentialForm');
    const managerCredentialError = document.getElementById('managerCredentialError');
    const managerAuthorizationContextInput = document.getElementById('manager_authorization_context');
    const managerValidationTriggers = document.querySelectorAll('[data-open-manager-validation]');
    let managerValidationCallback = null;
    let delegationUnlockTimer = null;
    const DELEGATION_UNLOCK_DURATION = 5 * 60 * 1000;

    const lockVendorField = () => {
        if (!delegateVendorSelect || !delegateSubmitButton || !delegationTokenInput) {
            return;
        }

        delegationTokenInput.value = '';
        delegateVendorSelect.disabled = true;
        delegateVendorSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
        delegateVendorSelect.classList.remove('bg-white');
        delegateSubmitButton.disabled = true;
        delegateSubmitButton.classList.add('opacity-50', 'cursor-not-allowed');
        if (delegateLockNotice) {
            delegateLockNotice.classList.remove('hidden');
        }
        if (validatedManagerInfo) {
            validatedManagerInfo.textContent = '';
            validatedManagerInfo.classList.add('hidden');
        }
        if (delegationUnlockTimer) {
            clearTimeout(delegationUnlockTimer);
            delegationUnlockTimer = null;
        }
    };

    const unlockVendorField = (token, managerName) => {
        if (!delegateVendorSelect || !delegateSubmitButton || !delegationTokenInput) {
            return;
        }

        delegationTokenInput.value = token;
        delegateVendorSelect.disabled = false;
        delegateVendorSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
        delegateVendorSelect.classList.add('bg-white');
        delegateSubmitButton.disabled = false;
        delegateSubmitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        if (delegateLockNotice) {
            delegateLockNotice.classList.add('hidden');
        }
        if (validatedManagerInfo) {
            const nameToDisplay = managerName && managerName.trim() !== '' ? managerName : 'Gestor autorizado';
            validatedManagerInfo.textContent = `Desbloqueado por ${nameToDisplay}.`;
            validatedManagerInfo.classList.remove('hidden');
        }
        if (delegationUnlockTimer) {
            clearTimeout(delegationUnlockTimer);
        }
        delegationUnlockTimer = window.setTimeout(() => {
            lockVendorField();
        }, DELEGATION_UNLOCK_DURATION);
    };

    if (delegateVendorSelect && delegateSubmitButton) {
        lockVendorField();
    }

    const validationModeInputs = document.querySelectorAll('input[name="validation_mode"]');
    const managerLoginSection = document.querySelector('[data-validation-section="manager_login"]');
    const managementPasswordSection = document.querySelector('[data-validation-section="management_password"]');
    const managerEmailInput = document.getElementById('manager_email');
    const managerPasswordInput = document.getElementById('manager_password');
    const managementPasswordInput = document.getElementById('management_password');

    const applyValidationMode = () => {
        const selectedModeInput = document.querySelector('input[name="validation_mode"]:checked');
        const mode = selectedModeInput ? selectedModeInput.value : 'manager_login';

        if (managerLoginSection) {
            if (mode === 'manager_login') {
                managerLoginSection.classList.remove('hidden');
            } else {
                managerLoginSection.classList.add('hidden');
            }
        }

        if (managementPasswordSection) {
            if (mode === 'management_password') {
                managementPasswordSection.classList.remove('hidden');
            } else {
                managementPasswordSection.classList.add('hidden');
            }
        }

        if (managerEmailInput) {
            managerEmailInput.disabled = mode !== 'manager_login';
            managerEmailInput.required = mode === 'manager_login';
            if (mode !== 'manager_login') {
                managerEmailInput.value = '';
            }
        }

        if (managerPasswordInput) {
            managerPasswordInput.disabled = mode !== 'manager_login';
            managerPasswordInput.required = mode === 'manager_login';
            if (mode !== 'manager_login') {
                managerPasswordInput.value = '';
            }
        }

        if (managementPasswordInput) {
            managementPasswordInput.disabled = mode !== 'management_password';
            managementPasswordInput.required = mode === 'management_password';
            if (mode !== 'management_password') {
                managementPasswordInput.value = '';
            }
        }
    };

    validationModeInputs.forEach((input) => {
        input.addEventListener('change', applyValidationMode);
    });

    const requestManagerValidation = (context, onSuccess) => {
        if (!managerCredentialForm) {
            return;
        }

        managerValidationCallback = typeof onSuccess === 'function' ? onSuccess : null;

        if (managerAuthorizationContextInput) {
            managerAuthorizationContextInput.value = context;
        }

        managerCredentialForm.reset();
        if (managerCredentialError) {
            managerCredentialError.textContent = '';
            managerCredentialError.classList.add('hidden');
        }

        const defaultMode = document.querySelector('input[name="validation_mode"][value="manager_login"]');
        if (defaultMode) {
            defaultMode.checked = true;
        }

        applyValidationMode();
        toggleModalVisibility('managerCredentialModal', true);
    };

    managerValidationTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const context = trigger.getAttribute('data-open-manager-validation') || 'vendor_delegation';
            requestManagerValidation(context, (result) => {
                if (context === 'vendor_delegation') {
                    unlockVendorField(result.token, result.managerName ?? '');
                }
            });
        });
    });

    applyValidationMode();

    if (managerCredentialForm) {
        managerCredentialForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (managerCredentialError) {
                managerCredentialError.textContent = '';
                managerCredentialError.classList.add('hidden');
            }

            try {
                const formData = new FormData(managerCredentialForm);
                const response = await fetch('<?php echo APP_URL; ?>/crm/prospeccoes/validar_credenciais.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    const message = result && result.message ? result.message : 'Não foi possível validar as credenciais.';
                    throw new Error(message);
                }

                toggleModalVisibility('managerCredentialModal', false);
                const context = result.context || (managerAuthorizationContextInput ? managerAuthorizationContextInput.value : 'vendor_delegation');
                if (managerValidationCallback) {
                    managerValidationCallback(result, context);
                    managerValidationCallback = null;
                } else if (context === 'vendor_delegation') {
                    unlockVendorField(result.token, result.managerName ?? '');
                }
                managerCredentialForm.reset();
                applyValidationMode();
            } catch (error) {
                if (managerCredentialError) {
                    managerCredentialError.textContent = error.message || 'Não foi possível validar as credenciais informadas.';
                    managerCredentialError.classList.remove('hidden');
                }
            }
        });
    }

    const conversionForm = document.getElementById('prospectionConversionForm');
    const conversionTokenInput = document.getElementById('conversion_authorization_token');
    const conversionAuthorizationInfo = document.getElementById('conversionAuthorizationInfo');
    let conversionAuthorizationTimer = null;

    const clearConversionAuthorization = () => {
        if (conversionTokenInput) {
            conversionTokenInput.value = '';
        }
        if (conversionAuthorizationInfo) {
            conversionAuthorizationInfo.textContent = '';
            conversionAuthorizationInfo.classList.add('hidden');
        }
        if (conversionAuthorizationTimer) {
            clearTimeout(conversionAuthorizationTimer);
            conversionAuthorizationTimer = null;
        }
    };

    const applyConversionAuthorization = (token, managerName) => {
        if (!conversionTokenInput) {
            return;
        }

        conversionTokenInput.value = token;
        if (conversionAuthorizationInfo) {
            const displayName = managerName && managerName.trim() !== '' ? managerName : 'gestor autorizado';
            conversionAuthorizationInfo.textContent = `Conversão liberada por ${displayName}.`;
            conversionAuthorizationInfo.classList.remove('hidden');
        }

        if (conversionAuthorizationTimer) {
            clearTimeout(conversionAuthorizationTimer);
        }

        conversionAuthorizationTimer = window.setTimeout(() => {
            clearConversionAuthorization();
        }, DELEGATION_UNLOCK_DURATION);
    };

    if (conversionForm && conversionTokenInput) {
        conversionForm.addEventListener('submit', (event) => {
            if (conversionTokenInput.value) {
                return;
            }

            event.preventDefault();
            requestManagerValidation('prospection_conversion', (result) => {
                applyConversionAuthorization(result.token, result.managerName ?? '');
                conversionForm.submit();
            });
        });
    }

    const formatDateTimeLocal = (date) => {
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const roundToNextHalfHour = (date) => {
        const rounded = new Date(date.getTime());
        rounded.setSeconds(0, 0);
        const minutes = rounded.getMinutes();
        const remainder = minutes % 30;
        if (remainder !== 0) {
            rounded.setMinutes(minutes + (30 - remainder));
        }
        if (rounded <= date) {
            rounded.setMinutes(rounded.getMinutes() + 30);
        }
        return rounded;
    };

    const MEETING_WINDOW_START_MINUTES = 9 * 60;
    const MEETING_WINDOW_END_MINUTES = 17 * 60;

    const keepWithinMeetingWindow = (candidate, durationMinutes) => {
        const adjusted = new Date(candidate.getTime());
        const startMinutes = adjusted.getHours() * 60 + adjusted.getMinutes();
        const latestStart = MEETING_WINDOW_END_MINUTES - durationMinutes;

        if (startMinutes < MEETING_WINDOW_START_MINUTES) {
            adjusted.setHours(9, 0, 0, 0);
            return adjusted;
        }

        if (startMinutes > latestStart) {
            adjusted.setDate(adjusted.getDate() + 1);
            adjusted.setHours(9, 0, 0, 0);
            return adjusted;
        }

        return adjusted;
    };

    const enforceThirtyMinuteStep = (inputElement) => {
        if (!inputElement.value) {
            return null;
        }

        const parsedDate = new Date(inputElement.value);
        const normalized = roundToNextHalfHour(parsedDate);
        inputElement.value = formatDateTimeLocal(normalized);
        return normalized;
    };

    const scheduleConfigs = [
        { startId: 'internalStart', endId: 'internalEnd', duration: 30, restrictToMeetingWindow: false },
        { startId: 'meetingStart', endId: 'meetingEnd', duration: 30, restrictToMeetingWindow: true }
    ];

    scheduleConfigs.forEach(({ startId, endId, duration, restrictToMeetingWindow }) => {
        const startInput = document.getElementById(startId);
        const endInput = document.getElementById(endId);

        if (!startInput || !endInput) {
            return;
        }

        const applyDefaults = () => {
            let base = roundToNextHalfHour(new Date());
            if (restrictToMeetingWindow) {
                base = keepWithinMeetingWindow(base, duration);
            }
            startInput.value = formatDateTimeLocal(base);
            const endDate = new Date(base.getTime() + duration * 60000);
            endInput.value = formatDateTimeLocal(endDate);
        };

        if (!startInput.value) {
            applyDefaults();
        }

        startInput.addEventListener('change', () => {
            if (!startInput.value) {
                return;
            }

            let normalizedStart = enforceThirtyMinuteStep(startInput);
            if (!normalizedStart) {
                return;
            }

            if (restrictToMeetingWindow) {
                normalizedStart = keepWithinMeetingWindow(normalizedStart, duration);
                startInput.value = formatDateTimeLocal(normalizedStart);
            }

            const normalizedEnd = new Date(normalizedStart.getTime() + duration * 60000);
            if (restrictToMeetingWindow && (normalizedEnd.getHours() * 60 + normalizedEnd.getMinutes()) > MEETING_WINDOW_END_MINUTES) {
                normalizedStart = new Date(normalizedStart.getTime() - duration * 60000);
                normalizedStart = keepWithinMeetingWindow(normalizedStart, duration);
                startInput.value = formatDateTimeLocal(normalizedStart);
            }

            const finalEnd = new Date(normalizedStart.getTime() + duration * 60000);
            endInput.value = formatDateTimeLocal(finalEnd);
        });

        endInput.addEventListener('change', () => {
            if (!endInput.value) {
                return;
            }

            let normalizedEnd = enforceThirtyMinuteStep(endInput);
            if (!normalizedEnd) {
                return;
            }

            const normalizedStart = new Date(normalizedEnd.getTime() - duration * 60000);
            if (restrictToMeetingWindow) {
                const startMinutes = normalizedStart.getHours() * 60 + normalizedStart.getMinutes();
                if (startMinutes < MEETING_WINDOW_START_MINUTES) {
                    const adjustedStart = new Date(normalizedEnd.getTime());
                    adjustedStart.setHours(9, 0, 0, 0);
                    startInput.value = formatDateTimeLocal(adjustedStart);
                    endInput.value = formatDateTimeLocal(new Date(adjustedStart.getTime() + duration * 60000));
                    return;
                }

                if ((normalizedEnd.getHours() * 60 + normalizedEnd.getMinutes()) > MEETING_WINDOW_END_MINUTES) {
                    const adjustedStart = new Date(normalizedEnd.getTime());
                    adjustedStart.setDate(adjustedStart.getDate() + 1);
                    adjustedStart.setHours(9, 0, 0, 0);
                    startInput.value = formatDateTimeLocal(adjustedStart);
                    endInput.value = formatDateTimeLocal(new Date(adjustedStart.getTime() + duration * 60000));
                    return;
                }
            }

            startInput.value = formatDateTimeLocal(normalizedStart);
        });
    });

    const deleteModal = document.getElementById('deleteRequestModal');
    function openModal() { deleteModal.classList.remove('hidden'); }
    function closeModal() { deleteModal.classList.add('hidden'); }
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>