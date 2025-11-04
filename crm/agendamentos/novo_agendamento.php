<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$prospectionModel = new Prospeccao($pdo);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userProfile = $_SESSION['user_perfil'] ?? '';

$prospeccaoId = filter_input(INPUT_GET, 'prospeccao_id', FILTER_VALIDATE_INT);
$selectedLead = null;
$leadOptions = [];
$errorMessage = null;

if ($prospeccaoId) {
    try {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.nome_prospecto, p.cliente_id, p.responsavel_id, c.nome_cliente
             FROM prospeccoes p
             LEFT JOIN clientes c ON p.cliente_id = c.id
             WHERE p.id = :id'
        );
        $stmt->bindValue(':id', $prospeccaoId, PDO::PARAM_INT);
        $stmt->execute();

        $selectedLead = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($selectedLead === null) {
            $errorMessage = 'Prospecção não encontrada.';
        }
    } catch (PDOException $exception) {
        $errorMessage = 'Erro ao carregar a prospecção selecionada.';
        error_log('Erro ao buscar prospecção: ' . $exception->getMessage());
    }
}

if ($prospeccaoId === null && $userId > 0) {
    if ($userProfile === 'sdr') {
        $leadOptions = $prospectionModel->getSdrLeads($userId);
    } elseif ($userProfile === 'vendedor') {
        $leadOptions = $prospectionModel->getVendorLeads($userId);
    }
}

$clienteId = (int) ($selectedLead['cliente_id'] ?? 0);
$usuarioId = (int) ($selectedLead['responsavel_id'] ?? ($userProfile === 'vendedor' ? $userId : 0));

$defaultRedirect = APP_URL . ($userProfile === 'vendedor' ? '/dashboard_vendedor.php' : '/sdr_dashboard.php');
$redirectTo = $selectedLead ? APP_URL . '/crm/prospeccoes/detalhes.php?id=' . (int) $selectedLead['id'] : $defaultRedirect;

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow sm:rounded-lg p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Novo agendamento</h1>
    <?php if ($errorMessage): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo APP_URL; ?>/crm/agendamentos/salvar_agendamento.php" method="POST" class="space-y-6">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo); ?>">
        <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo $clienteId > 0 ? $clienteId : ''; ?>">
        <input type="hidden" name="usuario_id" id="usuario_id" value="<?php echo $usuarioId > 0 ? $usuarioId : ($userId > 0 ? $userId : ''); ?>">

        <?php if ($selectedLead): ?>
            <input type="hidden" name="prospeccao_id" value="<?php echo (int) $selectedLead['id']; ?>">
            <p class="text-sm text-gray-600">
                Para a oportunidade <span class="font-semibold"><?php echo htmlspecialchars($selectedLead['nome_prospecto']); ?></span>
                com o lead <span class="font-semibold"><?php echo htmlspecialchars($selectedLead['nome_cliente'] ?? 'Não informado'); ?></span>.
            </p>
        <?php else: ?>
            <div class="space-y-2">
                <label for="leadSelect" class="block text-sm font-medium text-gray-700">Selecione a prospecção</label>
                <?php if (!empty($leadOptions)): ?>
                    <select id="leadSelect" name="prospeccao_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-default-user-id="<?php echo $userId > 0 ? $userId : ''; ?>">
                        <option value="">Escolha um lead</option>
                        <?php foreach ($leadOptions as $leadOption): ?>
                            <option value="<?php echo (int) $leadOption['id']; ?>"
                                    data-cliente-id="<?php echo (int) ($leadOption['cliente_id'] ?? 0); ?>"
                                    data-vendor-id="<?php echo (int) ($leadOption['responsavel_id'] ?? 0); ?>"
                                    data-lead-nome="<?php echo htmlspecialchars($leadOption['nome_prospecto'] ?? 'Lead', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cliente-nome="<?php echo htmlspecialchars($leadOption['nome_cliente'] ?? 'Cliente', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(($leadOption['nome_prospecto'] ?? 'Lead') . ' • ' . ($leadOption['nome_cliente'] ?? 'Cliente'), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500" id="lead-info">Selecione um lead para vincular o agendamento.</p>
                <?php else: ?>
                    <p class="rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-700">
                        Nenhum lead disponível para agendamento no momento.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label for="titulo" class="block text-sm font-medium text-gray-700">Título do evento</label>
                <input type="text" name="titulo" id="titulo" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="data_inicio" class="block text-sm font-medium text-gray-700">Início</label>
                <input type="datetime-local" name="data_inicio" id="data_inicio" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-medium text-gray-700">Fim</label>
                <input type="datetime-local" name="data_fim" id="data_fim" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label for="local_link" class="block text-sm font-medium text-gray-700">Canal / Link da reunião</label>
                <input type="text" name="local_link" id="local_link" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="Google Meet, Teams, endereço físico, etc.">
            </div>
        </div>

        <div>
            <label for="observacoes" class="block text-sm font-medium text-gray-700">Observações</label>
            <textarea name="observacoes" id="observacoes" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        <div class="flex justify-end gap-3 border-t pt-4">
            <a href="<?php echo htmlspecialchars($redirectTo); ?>" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">Salvar agendamento</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const leadSelect = document.getElementById('leadSelect');
        const clienteInput = document.getElementById('cliente_id');
        const usuarioInput = document.getElementById('usuario_id');
        const leadInfo = document.getElementById('lead-info');
        const defaultUserId = usuarioInput ? usuarioInput.value : '';

        if (!leadSelect) {
            return;
        }

        leadSelect.addEventListener('change', () => {
            const option = leadSelect.selectedOptions[0];
            if (!option || option.value === '') {
                if (clienteInput) {
                    clienteInput.value = '';
                }
                if (usuarioInput) {
                    usuarioInput.value = defaultUserId;
                }
                if (leadInfo) {
                    leadInfo.textContent = 'Selecione um lead para vincular o agendamento.';
                }
                return;
            }

            if (clienteInput) {
                clienteInput.value = option.dataset.clienteId && option.dataset.clienteId !== '0'
                    ? option.dataset.clienteId
                    : '';
            }

            if (usuarioInput) {
                usuarioInput.value = option.dataset.vendorId && option.dataset.vendorId !== '0'
                    ? option.dataset.vendorId
                    : (defaultUserId || '');
            }

            if (leadInfo) {
                const leadNome = option.dataset.leadNome || 'Lead';
                const clienteNome = option.dataset.clienteNome || 'Cliente';
                leadInfo.textContent = `Lead selecionado: ${leadNome} • Cliente: ${clienteNome}`;
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php';
?>
