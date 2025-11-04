<?php
// Arquivo: crm/clientes/editar_cliente.php (VERSÃO FINAL COM BOTÃO DE EXCLUIR)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';

function escape($value): string
{
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
}

// Valida o ID do cliente na URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit;
}

try {
    // Busca os dados do cliente
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $_SESSION['error_message'] = "Lead não encontrado.";
        header('Location: ' . APP_URL . '/crm/clientes/lista.php');
        exit;
    }

    $currentUserPerfil = $_SESSION['user_perfil'] ?? '';
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($currentUserPerfil === 'vendedor' && (int)($cliente['crmOwnerId'] ?? 0) !== $currentUserId) {
        $_SESSION['error_message'] = "Você não tem permissão para acessar este lead.";
        header('Location: ' . APP_URL . '/crm/clientes/lista.php');
        exit;
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados do lead: " . $e->getMessage());
}

$telefoneDdi = stripNonDigits((string)($cliente['telefone_ddi'] ?? ''));
if ($telefoneDdi === '') {
    $telefoneDdi = '55';
}

$telefoneInputValue = '';
$telefoneDigits = stripNonDigits((string)($cliente['telefone'] ?? ''));
$telefoneDdd = stripNonDigits((string)($cliente['telefone_ddd'] ?? ''));
$telefoneNumero = stripNonDigits((string)($cliente['telefone_numero'] ?? ''));

if ($telefoneDigits !== '') {
    if ($telefoneDdd === '' || $telefoneNumero === '') {
        if ($telefoneDdi !== ''
            && strpos($telefoneDigits, $telefoneDdi) === 0
            && strlen($telefoneDigits) > strlen($telefoneDdi)
        ) {
            $telefoneDigits = substr($telefoneDigits, strlen($telefoneDdi));
        }

        try {
            $parts = extractPhoneParts($telefoneDigits);
            $telefoneDdd = $parts['ddd'] ?? '';
            $telefoneNumero = $parts['phone'] ?? '';
        } catch (Throwable $exception) {
            $telefoneInputValue = (string)($cliente['telefone'] ?? '');
        }
    }

    if ($telefoneInputValue === '' && $telefoneDdd !== '' && $telefoneNumero !== '') {
        $localNumberLength = strlen($telefoneNumero);
        if ($localNumberLength > 4) {
            $localNumber = substr($telefoneNumero, 0, $localNumberLength - 4) . '-' . substr($telefoneNumero, -4);
        } else {
            $localNumber = $telefoneNumero;
        }

        $telefoneInputValue = sprintf('(%s) %s', $telefoneDdd, $localNumber);
    }
}

if ($telefoneInputValue === '' && !empty($cliente['telefone'])) {
    $telefoneInputValue = (string)$cliente['telefone'];
}

$pageTitle = "Editar Lead";
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-4">
        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
            Editar Lead: <span class="text-blue-600"><?php echo escape($cliente['nome_cliente'] ?? null); ?></span>
        </h1>
    </div>

    <form action="<?php echo APP_URL; ?>/crm/clientes/salvar.php" method="POST" class="space-y-6 mt-6">
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
        <?php if (isset($_GET['prospeccao_id'])): ?>
            <input type="hidden" name="prospeccao_id" value="<?php echo escape($_GET['prospeccao_id']); ?>">
        <?php endif; ?>

        <?php if (isset($_GET['vendedor_id'])): ?>
            <input type="hidden" name="vendedor_id" value="<?php echo escape($_GET['vendedor_id']); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nome_cliente" class="block text-sm font-medium text-gray-700">Nome do Lead / Empresa</label>
                <input type="text" name="nome_cliente" id="nome_cliente" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60" value="<?php echo escape($cliente['nome_cliente'] ?? null); ?>">
            </div>
            <div>
                <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">Nome do Lead Principal</label>
                <input type="text" name="nome_responsavel" id="nome_responsavel" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" maxlength="60" value="<?php echo escape($cliente['nome_responsavel'] ?? null); ?>">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo escape($cliente['email'] ?? null); ?>">
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
                            value="<?php echo escape($telefoneDdi); ?>"
                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                        >
                    </div>
                    <div class="flex-1">
                        <input
                            type="tel"
                            name="telefone"
                            id="telefone"
                            maxlength="20"
                            class="mt-0 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                            value="<?php echo escape($telefoneInputValue); ?>"
                        >
                    </div>
                </div>
            </div>
            <div>
                <label for="canal_origem" class="block text-sm font-medium text-gray-700">Canal de Origem</label>
                <select name="canal_origem" id="canal_origem" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="Call" <?php echo ($cliente['canal_origem'] == 'Call') ? 'selected' : ''; ?>>Call</option>
                    <option value="LinkedIn" <?php echo ($cliente['canal_origem'] == 'LinkedIn') ? 'selected' : ''; ?>>LinkedIn</option>
                    <option value="Instagram" <?php echo ($cliente['canal_origem'] == 'Instagram') ? 'selected' : ''; ?>>Instagram</option>
                    <option value="Whatsapp" <?php echo ($cliente['canal_origem'] == 'Whatsapp') ? 'selected' : ''; ?>>Whatsapp</option>
                    <option value="Indicação Cliente" <?php echo ($cliente['canal_origem'] == 'Indicação Cliente') ? 'selected' : ''; ?>>Indicação Cliente</option>
                    <option value="Indicação Cartório" <?php echo ($cliente['canal_origem'] == 'Indicação Cartório') ? 'selected' : ''; ?>>Indicação Cartório</option>
                    <option value="Website" <?php echo ($cliente['canal_origem'] == 'Website') ? 'selected' : ''; ?>>Website</option>
                    <option value="Bitrix" <?php echo ($cliente['canal_origem'] == 'Bitrix') ? 'selected' : ''; ?>>Bitrix</option>
                    <option value="Evento" <?php echo ($cliente['canal_origem'] == 'Evento') ? 'selected' : ''; ?>>Evento</option>
                    <option value="Outro" <?php echo ($cliente['canal_origem'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                </select>
            </div>
        </div>

        <div class="pt-5 flex justify-between items-center border-t border-gray-200 mt-6">
            <div>
                <button type="button" onclick="confirmarExclusao()" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 transition duration-300">
                    Excluir Lead
                </button>
            </div>

            <div>
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3 transition duration-300">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">Salvar Alterações</button>
            </div>
        </div>
    </form>
</div>

<form id="formExcluirCliente" action="<?php echo APP_URL; ?>/crm/clientes/excluir_cliente.php" method="POST" class="hidden">
    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
</form>

<script>
    function confirmarExclusao() {
        if (confirm('ATENÇÃO:\n\nTem certeza que deseja excluir este lead?\n\nTodas as prospecções ligadas a ele ficarão sem leads associados.')) {
            document.getElementById('formExcluirCliente').submit();
        }
    }

    function aplicarMascaraTelefone(value) {
        value = value.replace(/\D/g, '');
        if (value.length <= 10) {
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            value = value.substring(0, 11);
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
        }
        return value;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const telefoneInput = document.getElementById('telefone');
        const telefoneDdiInput = document.getElementById('telefone_ddi');

        if (telefoneInput) {
            telefoneInput.addEventListener('input', function (e) {
                e.target.value = aplicarMascaraTelefone(e.target.value);
            });
            telefoneInput.addEventListener('paste', function (e) {
                setTimeout(() => {
                    e.target.value = aplicarMascaraTelefone(e.target.value);
                }, 100);
            });
        }

        if (telefoneDdiInput) {
            telefoneDdiInput.addEventListener('input', function (e) {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            });
        }
    });
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>
