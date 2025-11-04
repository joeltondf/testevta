<?php 
// /app/views/clientes/form.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$cliente = $cliente ?? [];
$formData = isset($formData) && is_array($formData) ? $formData : [];
$isEdit = isset($cliente['id']);

// A variável $return_url agora é definida corretamente pelo ClientesController.php

// Define um valor padrão para $return_url para evitar erros caso não seja definida pelo controller.
$return_url = $return_url ?? 'clientes.php';

$formValues = array_merge($cliente, $formData);

// Preenche as variáveis para os campos do formulário
$nome_cliente = $formValues['nome_cliente'] ?? '';
$prazo_acordado_dias = $formValues['prazo_acordado_dias'] ?? '';
$nome_responsavel = $formValues['nome_responsavel'] ?? '';
$cpf_cnpj = $formValues['cpf_cnpj'] ?? '';
$email = $formValues['email'] ?? '';
$telefone = $formValues['telefone'] ?? '';
$telefone_ddi = $formValues['telefone_ddi'] ?? '55';

$formatPhoneInputValue = static function (string $ddd, string $phone): string {
    $dddDigits = stripNonDigits($ddd);
    $phoneDigits = stripNonDigits($phone);

    if ($dddDigits === '' && $phoneDigits === '') {
        return '';
    }

    if ($phoneDigits === '') {
        return $dddDigits;
    }

    if ($dddDigits === '') {
        return $phoneDigits;
    }

    $length = strlen($phoneDigits);
    if ($length <= 4) {
        return sprintf('(%s) %s', $dddDigits, $phoneDigits);
    }

    $firstPart = substr($phoneDigits, 0, $length - 4);
    $lastPart = substr($phoneDigits, -4);

    return sprintf('(%s) %s-%s', $dddDigits, $firstPart, $lastPart);
};

$rawPhoneDigits = stripNonDigits((string) $telefone);
$rawDdiDigits = stripNonDigits((string) $telefone_ddi);

if ($rawPhoneDigits !== '') {
    if ($rawDdiDigits !== '' && strpos($rawPhoneDigits, $rawDdiDigits) === 0 && strlen($rawPhoneDigits) > strlen($rawDdiDigits)) {
        $rawPhoneDigits = substr($rawPhoneDigits, strlen($rawDdiDigits));
    } elseif ($rawDdiDigits === '' && strlen($rawPhoneDigits) > 11) {
        $rawDdiDigits = substr($rawPhoneDigits, 0, strlen($rawPhoneDigits) - 11);
        $rawPhoneDigits = substr($rawPhoneDigits, -11);
    }

    if (strlen($rawPhoneDigits) > 2) {
        $dddDigits = substr($rawPhoneDigits, 0, 2);
        $phoneDigits = substr($rawPhoneDigits, 2);
        $telefone = $formatPhoneInputValue($dddDigits, $phoneDigits);
    } else {
        $telefone = $rawPhoneDigits;
    }
}

if ($rawDdiDigits !== '') {
    $telefone_ddi = $rawDdiDigits;
}
$numero = $formValues['numero'] ?? '';
$bairro = $formValues['bairro'] ?? '';
$cidade = $formValues['cidade'] ?? '';
$estado = $formValues['estado'] ?? '';
$endereco = $formValues['endereco'] ?? '';
$cep = $formValues['cep'] ?? '';
$tipo_assessoria = $formValues['tipo_assessoria'] ?? '';
$tipo_pessoa = $formValues['tipo_pessoa'] ?? 'Jurídica';
$criar_login = !empty($formValues['criar_login']);
$login_email = $formValues['login_email'] ?? '';
$login_senha = $formValues['login_senha'] ?? '';
$sincronizar_omie_checked = !isset($formData['sincronizar_omie']) || (bool)$formData['sincronizar_omie'];
$city_validation_source = ($cidade !== '' && $estado !== '') ? 'database' : 'manual';

$servicos_mensalista = isset($servicos_mensalista) && is_array($servicos_mensalista)
    ? $servicos_mensalista
    : [];

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    .cliente-form-wrapper {
        width: min(100%, 80vw);
        margin-left: auto;
        margin-right: auto;
    }

    .cliente-form-row {
        display: grid;
        gap: 1rem;
    }

    .cliente-grid-1 {
        grid-template-columns: 1fr;
    }

    .cliente-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cliente-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .cliente-grid-60-30-10 {
        grid-template-columns: 6fr 3fr 1fr;
    }

    .cliente-grid-45-45-10 {
        grid-template-columns: 9fr 9fr 2fr;
    }

    .cliente-form-card {
        background-color: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -4px rgba(15, 23, 42, 0.1);
    }

    .cliente-form-col {
        width: 100%;
    }

    .cliente-col-full {
        grid-column: 1 / -1;
    }

    @media (max-width: 768px) {
        .cliente-form-wrapper {
            width: 100%;
        }

        .cliente-form-row {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<div class="cliente-form-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEdit ? 'Editar Cliente' : 'Cadastrar Novo Cliente'; ?></h1>
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
            &larr; Voltar
        </a>
    </div>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Erro!</strong>
        <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<form action="clientes.php" method="POST" class="cliente-form-card" autocomplete="off">
    <!-- Campo invisível para enganar o autocomplete -->
    <input type="text" name="fake_username" style="position: absolute; top: -5000px;" tabindex="-1" autocomplete="username">
    <input type="password" name="fake_password" style="position: absolute; top: -5000px;" tabindex="-1" autocomplete="new-password">
    
    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_url); ?>">
    <?php if (isset($_GET['prospeccao_id'])): ?>
        <input type="hidden" name="continue_prospeccao_id" value="<?php echo htmlspecialchars($_GET['prospeccao_id']); ?>">
        <input type="hidden" name="continue_nome_servico" value="<?php echo htmlspecialchars($_GET['nome_servico']); ?>">
        <input type="hidden" name="continue_valor_inicial" value="<?php echo htmlspecialchars($_GET['valor_inicial']); ?>">
    <?php endif; ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'store'; ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
    <?php endif; ?>

    <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Cliente</label>
        <div class="flex items-center space-x-6">
            <label class="flex items-center cursor-pointer">
                <input type="radio" name="tipo_pessoa" value="Jurídica" class="h-4 w-4 text-blue-600 border-gray-300"  <?php echo ($tipo_pessoa === 'Jurídica') ? 'checked' : ''; ?>>
                <span class="ml-2 text-sm text-gray-700">Pessoa Jurídica</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="radio" name="tipo_pessoa" value="Física" class="h-4 w-4 text-blue-600 border-gray-300" <?php echo ($tipo_pessoa === 'Física') ? 'checked' : ''; ?>>
                <span class="ml-2 text-sm text-gray-700">Pessoa Física</span>
            </label>
        </div>
    </div>
    <div class="space-y-6">
        <div class="cliente-form-row cliente-grid-2">
            <div id="container_nome_cliente" class="cliente-form-col cliente-col-50">
                <label for="nome_cliente" id="label_nome_cliente" class="block text-sm font-semibold text-gray-700">Nome da Empresa *</label>
                <input type="text" id="nome_cliente" name="nome_cliente" autocomplete="nope" value="<?php echo htmlspecialchars($nome_cliente); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="60" required>
            </div>

            <div id="container_nome_responsavel" class="cliente-form-col cliente-col-50">
                <label for="nome_responsavel" class="block text-sm font-semibold text-gray-700">Nome do Responsável</label>
                <input type="text" id="nome_responsavel" name="nome_responsavel" autocomplete="nope" value="<?php echo htmlspecialchars($nome_responsavel); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="60">
            </div>
        </div>

        <div class="cliente-form-row cliente-grid-3">
            <div class="cliente-form-col cliente-col-33">
                <label for="cpf_cnpj" id="label_cpf_cnpj" class="block text-sm font-semibold text-gray-700">CPF/CNPJ *</label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj" autocomplete="nope" value="<?php echo htmlspecialchars($cpf_cnpj); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div class="cliente-form-col cliente-col-33">
                <label for="email" class="block text-sm font-semibold text-gray-700">E-mail *</label>
                <input type="email" id="email" name="email" autocomplete="nope" value="<?php echo htmlspecialchars($email); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div class="cliente-form-col cliente-col-33">
                <label for="telefone" class="block text-sm font-semibold text-gray-700">Telefone<?php echo $isEdit ? '' : ' *'; ?></label>
                <div class="mt-1 flex items-stretch gap-2">
                    <div class="w-24">
                        <input
                            type="text"
                            id="telefone_ddi"
                            name="telefone_ddi"
                            inputmode="numeric"
                            pattern="\d{1,4}"
                            maxlength="4"
                            value="<?php echo htmlspecialchars($telefone_ddi); ?>"
                            class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                    <div class="flex-1">
                        <input
                            type="text"
                            id="telefone"
                            name="telefone"
                            autocomplete="nope"
                            value="<?php echo htmlspecialchars($telefone); ?>"
                            class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            maxlength="20"<?php echo $isEdit ? '' : ' required'; ?>
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="cliente-form-row cliente-grid-60-30-10">
            <div class="cliente-form-col cliente-col-60">
                <label for="endereco" class="block text-sm font-semibold text-gray-700">Endereço *</label>
                <input type="text" id="endereco" name="endereco" autocomplete="nope" value="<?php echo htmlspecialchars($endereco); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="60" required>
            </div>

            <div class="cliente-form-col cliente-col-30">
                <label for="bairro" class="block text-sm font-semibold text-gray-700">Bairro *</label>
                <input type="text" id="bairro" name="bairro" autocomplete="nope" value="<?php echo htmlspecialchars($bairro); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div class="cliente-form-col cliente-col-10">
                <label for="numero" class="block text-sm font-semibold text-gray-700">Número *</label>
                <input type="text" id="numero" name="numero" autocomplete="nope" value="<?php echo htmlspecialchars($numero); ?>" placeholder="N/A" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            </div>
        </div>

        <div class="cliente-form-row cliente-grid-45-45-10">
            <div class="cliente-form-col cliente-col-45">
                <label for="cep" class="block text-sm font-semibold text-gray-700">CEP *</label>
                <input type="text" id="cep" name="cep" autocomplete="nope" value="<?php echo htmlspecialchars($cep); ?>" readonly onfocus="this.removeAttribute('readonly');" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="9" required>
            </div>

            <div class="cliente-form-col cliente-col-45">
                <label for="cidade" class="block text-sm font-semibold text-gray-700">Cidade *</label>
                <div id="cidade-combobox" data-city-autocomplete class="relative" role="combobox" aria-haspopup="listbox" aria-expanded="false">
                    <input type="hidden" name="city_validation_source" id="city_validation_source" value="<?php echo htmlspecialchars($city_validation_source); ?>">
                    <input
                        type="text"
                        id="cidade"
                        name="cidade"
                        autocomplete="off"
                        aria-controls="cidade-options"
                        aria-autocomplete="list"
                        aria-haspopup="listbox"
                        value="<?php echo htmlspecialchars($cidade); ?>"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        data-initial-city-selected="<?php echo $cidade !== '' ? '1' : '0'; ?>"
                        required
                    >
                    <div id="cidade-options" role="listbox" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-60 overflow-auto"></div>
                </div>
                <p id="cidade-status" class="mt-1 text-sm text-gray-500 hidden" role="status" aria-live="polite"></p>
            </div>

            <div class="cliente-form-col cliente-col-10">
                <label for="estado" class="block text-sm font-semibold text-gray-700">Estado (UF) *</label>
                <input type="text" id="estado" name="estado" autocomplete="off" value="<?php echo htmlspecialchars($estado); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-50 uppercase" maxlength="2" required readonly>
            </div>
        </div>

        <?php
            $userPerfil = $_SESSION['user_perfil'] ?? '';
            $perfisPrazoVisivel = ['admin', 'gerencia', 'supervisor', 'colaborador'];
            $mostrarPrazo = in_array($userPerfil, $perfisPrazoVisivel, true);
        ?>

        <div class="cliente-form-row <?php echo $mostrarPrazo ? 'cliente-grid-2' : 'cliente-grid-1'; ?>">
            <div class="cliente-form-col <?php echo $mostrarPrazo ? 'cliente-col-50' : 'cliente-col-full'; ?>">
                <label for="tipo_assessoria" class="block text-sm font-semibold text-gray-700">Tipo de Assessoria</label>
                <select id="tipo_assessoria" name="tipo_assessoria" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="" <?php echo ($tipo_assessoria == '') ? 'selected' : ''; ?>>Não definido</option>
                    <option value="À vista" <?php echo ($tipo_assessoria == 'À vista') ? 'selected' : ''; ?>>À vista</option>
                    <option value="Mensalista" <?php echo ($tipo_assessoria == 'Mensalista') ? 'selected' : ''; ?>>Mensalista</option>
                </select>
            </div>

            <?php if ($mostrarPrazo): ?>
                <div class="cliente-form-col cliente-col-50">
                    <label for="prazo_acordado_dias" class="block text-sm font-semibold text-gray-700">Prazo Acordado (dias)</label>
                    <input
                        type="number"
                        id="prazo_acordado_dias"
                        name="prazo_acordado_dias"
                        min="1"
                        value="<?php echo htmlspecialchars($prazo_acordado_dias); ?>"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="mensalista-servicos-container" class="mt-6 pt-6 border-t col-span-1 md:col-span-2 <?= ($tipo_assessoria === 'Mensalista') ? '' : 'hidden' ?>">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Serviços Contratados (Mensalista)</h3>
        <div id="servicos-lista" class="space-y-4">

            <?php if (!empty($servicos_mensalista)): ?>
                <?php foreach ($servicos_mensalista as $index => $servico): ?>
                    <div class="grid grid-cols-12 gap-4 items-start servico-item">
                        <div class="col-span-12 md:col-span-6 lg:col-span-5">
                            <select name="servicos_mensalistas[<?= $index ?>][produto_orcamento_id]" class="mensalista-produto-select block w-full rounded-md border-gray-300 shadow-sm text-sm py-2">
                                <option value="">Selecione um Produto/Serviço</option>
                                <?php foreach ($produtos_orcamento as $produto): ?>
                                    <?php
                                        $valorPadraoProduto = isset($produto['valor_padrao']) ? number_format((float)$produto['valor_padrao'], 2, '.', '') : '';
                                        $bloquearMinimo = !empty($produto['bloquear_valor_minimo']);
                                        $servicoTipoProduto = $produto['servico_tipo'] ?? 'Nenhum';
                                    ?>
                                    <option
                                        value="<?= $produto['id'] ?>"
                                        data-servico-tipo="<?= htmlspecialchars($servicoTipoProduto) ?>"
                                        data-valor-padrao="<?= htmlspecialchars($valorPadraoProduto) ?>"
                                        data-bloquear-minimo="<?= $bloquearMinimo ? '1' : '0' ?>"
                                        <?= ($produto['id'] == $servico['produto_orcamento_id']) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($produto['nome_categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-span-6 md:col-span-3 lg:col-span-3">
                            <input type="text" name="servicos_mensalistas[<?= $index ?>][valor_padrao]" data-currency-input class="servico-valor-input block w-full rounded-md border-gray-300 shadow-sm text-sm py-2" placeholder="R$ 0,00" value="<?= number_format((float)($servico['valor_padrao'] ?? 0), 2, '.', '') ?>">
                            <p class="valor-min-info text-xs text-gray-500 mt-1 hidden">Valor mínimo: <span class="servico-min-text font-semibold"></span></p>
                        </div>
                        <div class="col-span-6 md:col-span-2 lg:col-span-3 flex items-center md:justify-center">
                            <span class="servico-tipo-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">—</span>
                        </div>
                        <div class="col-span-12 md:col-span-1 flex justify-end md:items-center">
                            <button type="button" class="text-red-500 hover:text-red-700" onclick="removerServico(this)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <button type="button" id="adicionar-servico-btn" class="mt-4 bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300 text-sm">
            <i class="fas fa-plus mr-2"></i>Adicionar Serviço
        </button>
    </div>
    
    <?php if (!$isEdit || ($isEdit && empty($cliente['user_id']))): ?>
        <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="criar_login_checkbox" name="criar_login" value="1" class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?= $criar_login ? 'checked' : '' ?>>
                <span class="ml-3 text-sm font-semibold text-gray-700">Criar acesso de login para este cliente?</span>
            </label>
        </div>
    <?php endif; ?>

    <div id="login-fields-container" class="<?= $criar_login ? '' : 'hidden' ?> md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
        <div>
            <label for="login_email" class="block text-sm font-medium text-gray-700">Email de Acesso *</label>
            <input type="email" id="login_email" name="login_email" autocomplete="new-email" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Email que será usado para o login" value="<?= htmlspecialchars($login_email); ?>">
        </div>
        <div>
            <label for="login_senha" class="block text-sm font-medium text-gray-700">Senha de Acesso *</label>
            <input type="password" id="login_senha" name="login_senha" autocomplete="new-password" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Defina uma senha segura" value="<?= htmlspecialchars($login_senha); ?>">
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="md:col-span-2 mt-6 pt-6 border-t border-gray-200">
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" name="sincronizar_omie" value="1" class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?= $sincronizar_omie_checked ? 'checked' : '' ?>>
                <span class="ml-3 text-sm font-semibold text-gray-700">Atualizar cadastro na Omie após salvar</span>
            </label>
            <?php if (!empty($cliente['omie_id'])): ?>
                <p class="text-xs text-gray-500 mt-2">Código Omie atual: <?php echo htmlspecialchars($cliente['omie_id']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-end mt-6 pt-5 border-t border-gray-200">
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">Cancelar</a>
        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <?php echo $isEdit ? 'Atualizar Cliente' : 'Salvar Cliente'; ?>
        </button>
    </div>
</form>
</div>

<script src="assets/js/city-autocomplete.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cidadeInput = document.getElementById('cidade');
    const estadoInput = document.getElementById('estado');
    const cidadeList = document.getElementById('cidade-options');
    const cidadeStatus = document.getElementById('cidade-status');
    const cidadeWrapper = document.getElementById('cidade-combobox');
    const cityValidationSourceInput = document.getElementById('city_validation_source');
    const initialCitySelection = cidadeInput ? (cidadeInput.dataset.initialCitySelected || '0') : '0';

    if (cidadeInput) {
        cidadeInput.dataset.citySelected = initialCitySelection;
    }

    const markCitySelectionAsApi = () => {
        if (cidadeInput) {
            cidadeInput.dataset.citySelected = '1';
        }
        if (cityValidationSourceInput) {
            cityValidationSourceInput.value = 'api';
        }
    };

    const markCitySelectionAsManual = () => {
        if (cidadeInput) {
            cidadeInput.dataset.citySelected = '0';
        }
        if (cityValidationSourceInput && cityValidationSourceInput.value !== 'manual') {
            cityValidationSourceInput.value = 'manual';
        }
    };

    if (window.CityAutocomplete && cidadeInput && estadoInput && cidadeList && cidadeStatus) {
        window.CityAutocomplete.init({
            input: cidadeInput,
            ufInput: estadoInput,
            list: cidadeList,
            statusElement: cidadeStatus,
            wrapper: cidadeWrapper,
            onSelect: () => {
                markCitySelectionAsApi();
            },
            onClear: () => {
                markCitySelectionAsManual();
                if (estadoInput) {
                    estadoInput.value = '';
                }
            }
        });
    }

    const sanitizeUfInput = () => {
        if (!estadoInput) {
            return;
        }

        const sanitized = (estadoInput.value || '')
            .replace(/[^a-zA-Z]/g, '')
            .toUpperCase()
            .slice(0, 2);

        estadoInput.value = sanitized;
    };

    if (estadoInput) {
        sanitizeUfInput();
        estadoInput.addEventListener('input', () => {
            sanitizeUfInput();
            markCitySelectionAsManual();
        });
        estadoInput.addEventListener('blur', sanitizeUfInput);
    }

    if (cidadeInput) {
        cidadeInput.addEventListener('input', () => {
            markCitySelectionAsManual();
        });
    }

    function setCurrencyValue(input, value) {
        if (!input) {
            return;
        }

        if (window.CurrencyMask && typeof window.CurrencyMask.setValue === 'function') {
            window.CurrencyMask.setValue(input, value ?? '');
            return;
        }

        input.value = value ?? '';
    }

    const radiosTipoPessoa = document.querySelectorAll('input[name="tipo_pessoa"]');
    const labelNomeCliente = document.getElementById('label_nome_cliente');
    const containerNomeCliente = document.getElementById('container_nome_cliente');
    const labelCpfCnpj = document.getElementById('label_cpf_cnpj');
    const containerNomeResponsavel = document.getElementById('container_nome_responsavel');
    const inputNomeResponsavel = document.getElementById('nome_responsavel');

    const tipoAssessoriaSelect = document.getElementById('tipo_assessoria');
    const servicosContainer = document.getElementById('mensalista-servicos-container');
    const adicionarServicoBtn = document.getElementById('adicionar-servico-btn');
    const servicosLista = document.getElementById('servicos-lista');
    let servicoIndex = <?= !empty($servicos_mensalista) ? count($servicos_mensalista) : 0 ?>;


        function toggleServicosSection() {
            if (tipoAssessoriaSelect.value === 'Mensalista') {
                servicosContainer.classList.remove('hidden');
            } else {
                servicosContainer.classList.add('hidden');
            }
        }

        // Evento para quando o tipo de assessoria muda
        tipoAssessoriaSelect.addEventListener('change', toggleServicosSection);

        // Evento para adicionar uma nova linha de serviço
        adicionarServicoBtn.addEventListener('click', function() {
            if (!servicosLista) {
                return;
            }
            const itemHtml = `
                <div class="grid grid-cols-12 gap-4 items-start servico-item">
                    <div class="col-span-12 md:col-span-6 lg:col-span-5">
                        <select name="servicos_mensalistas[${servicoIndex}][produto_orcamento_id]" class="mensalista-produto-select block w-full rounded-md border-gray-300 shadow-sm text-sm py-2">
                            <option value="">Selecione um Produto/Serviço</option>
                            <?php foreach ($produtos_orcamento as $produto): ?>
                                <option value="<?= $produto['id'] ?>"
                                    data-servico-tipo="<?= htmlspecialchars($produto['servico_tipo'] ?? 'Nenhum') ?>"
                                    data-valor-padrao="<?= htmlspecialchars(isset($produto['valor_padrao']) ? number_format((float)$produto['valor_padrao'], 2, '.', '') : '') ?>"
                                    data-bloquear-minimo="<?= !empty($produto['bloquear_valor_minimo']) ? '1' : '0' ?>">
                                    <?= htmlspecialchars($produto['nome_categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-3 lg:col-span-3">
                        <input type="text" name="servicos_mensalistas[${servicoIndex}][valor_padrao]" data-currency-input class="servico-valor-input block w-full rounded-md border-gray-300 shadow-sm text-sm py-2" placeholder="R$ 0,00">
                        <p class="valor-min-info text-xs text-gray-500 mt-1 hidden">Valor mínimo: <span class="servico-min-text font-semibold"></span></p>
                    </div>
                    <div class="col-span-6 md:col-span-2 lg:col-span-3 flex items-center md:justify-center">
                        <span class="servico-tipo-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">Sem vínculo</span>
                    </div>
                    <div class="col-span-12 md:col-span-1 flex justify-end md:items-center">
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removerServico(this)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>`;
            servicosLista.insertAdjacentHTML('beforeend', itemHtml);
            const novaLinha = servicosLista.lastElementChild;
            if (novaLinha) {
                updateServicoMensalistaRow(novaLinha);
            }
            servicoIndex++;
        });

        // Função global para remover uma linha
        window.removerServico = function(button) {
            button.closest('.servico-item').remove();
        }

        const servicoTipoStyleMap = {
            'Tradução': 'bg-blue-100 text-blue-700',
            'CRC': 'bg-green-100 text-green-700',
            'Apostilamento': 'bg-yellow-100 text-yellow-700',
            'Postagem': 'bg-purple-100 text-purple-700',
            'Outros': 'bg-gray-100 text-gray-700',
            'Nenhum': 'bg-gray-200 text-gray-700'
        };

        function formatCurrencyBRL(value) {
            if (value === null || value === undefined || value === '') {
                return 'R$ 0,00';
            }
            const numeric = Number.parseFloat(value);
            if (Number.isNaN(numeric)) {
                return 'R$ 0,00';
            }
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numeric);
        }

        function updateServicoMensalistaRow(row, options = {}) {
            if (!row) {
                return;
            }

            const select = row.querySelector('.mensalista-produto-select');
            const valorInput = row.querySelector('.servico-valor-input');
            const badge = row.querySelector('.servico-tipo-badge');
            const minInfo = row.querySelector('.valor-min-info');
            const minText = row.querySelector('.servico-min-text');

            const selectedOption = select ? select.options[select.selectedIndex] : null;
            const tipoServico = selectedOption ? (selectedOption.dataset.servicoTipo || 'Nenhum') : 'Nenhum';
            const valorPadrao = selectedOption && selectedOption.dataset.valorPadrao !== undefined
                ? Number.parseFloat(selectedOption.dataset.valorPadrao)
                : null;
            const bloquearMinimo = selectedOption ? selectedOption.dataset.bloquearMinimo === '1' : false;

            if (valorInput) {
                const devePreservar = options.preserveValor && options.valorAtual !== undefined;
                let valorParaAplicar = valorInput.value;

                if (devePreservar) {
                    valorParaAplicar = options.valorAtual ?? '';
                } else if (valorPadrao !== null && !Number.isNaN(valorPadrao)) {
                    valorParaAplicar = valorPadrao;
                }

                setCurrencyValue(valorInput, valorParaAplicar);
            }

            if (badge) {
                const badgeBase = 'servico-tipo-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ';
                const classeCor = servicoTipoStyleMap[tipoServico] || servicoTipoStyleMap.Nenhum;
                badge.className = badgeBase + classeCor;
                badge.textContent = (tipoServico && tipoServico !== 'Nenhum') ? tipoServico : 'Sem vínculo';
            }

            if (minInfo && minText) {
                if (bloquearMinimo && valorPadrao !== null && !Number.isNaN(valorPadrao)) {
                    minText.textContent = formatCurrencyBRL(valorPadrao);
                    minInfo.classList.remove('hidden');
                } else {
                    minInfo.classList.add('hidden');
                }
            }
        }

        function initializeServicosMensalistaRows() {
            servicosLista.querySelectorAll('.servico-item').forEach(row => {
                const valorAtual = row.querySelector('.servico-valor-input')?.value;
                updateServicoMensalistaRow(row, { preserveValor: true, valorAtual });
            });
        }

        if (servicosLista) {
            servicosLista.addEventListener('change', event => {
                if (event.target && event.target.classList.contains('mensalista-produto-select')) {
                    updateServicoMensalistaRow(event.target.closest('.servico-item'));
                }
            });

            initializeServicosMensalistaRows();
        }
    


    // Função para validar CPF
    function validarCpf(cpf) {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11) return false;
        
        // Verifica se todos os números são iguais
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        // Validação do primeiro dígito
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = 11 - (soma % 11);
        let digito1 = resto === 10 || resto === 11 ? 0 : resto;
        if (digito1 !== parseInt(cpf.charAt(9))) return false;
        
        // Validação do segundo dígito
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = 11 - (soma % 11);
        let digito2 = resto === 10 || resto === 11 ? 0 : resto;
        if (digito2 !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    }

    // Função para validar CNPJ
    function validarCnpj(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        if (cnpj.length !== 14) return false;
        
        // Verifica se todos os números são iguais
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        
        // Validação do primeiro dígito
        let tamanho = cnpj.length - 2;
        let numeros = cnpj.substring(0, tamanho);
        let digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(0))) return false;
        
        // Validação do segundo dígito
        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(1))) return false;
        
        return true;
    }

    // Adiciona validação no envio do formulário
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked')?.value || 'Jurídica';
            const valorCpfCnpj = cpfCnpjInput ? cpfCnpjInput.value.trim() : '';

            if (!valorCpfCnpj) {
                e.preventDefault();
                alert('Informe o CPF ou CNPJ.');
                cpfCnpjInput?.focus();
                return;
            }

            if (tipoSelecionado === 'Física') {
                if (!validarCpf(valorCpfCnpj)) {
                    e.preventDefault();
                    alert('CPF inválido! Por favor, verifique o número digitado.');
                    cpfCnpjInput.focus();
                    return;
                }
            } else {
                if (!validarCnpj(valorCpfCnpj)) {
                    e.preventDefault();
                    alert('CNPJ inválido! Por favor, verifique o número digitado.');
                    cpfCnpjInput.focus();
                    return;
                }
            }

            const emailInput = document.getElementById('email');
            const emailValor = emailInput ? emailInput.value.trim() : '';
            if (!emailValor) {
                e.preventDefault();
                alert('Informe o e-mail.');
                emailInput?.focus();
                return;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailValor)) {
                e.preventDefault();
                alert('Informe um e-mail válido.');
                emailInput.focus();
                return;
            }

            const cepInput = document.getElementById('cep');
            const cepValor = cepInput ? cepInput.value.trim() : '';
            const cepDigitos = cepValor.replace(/\D/g, '');
            if (!cepDigitos) {
                e.preventDefault();
                alert('Informe o CEP.');
                cepInput?.focus();
                return;
            }
            if (cepDigitos.length !== 8) {
                e.preventDefault();
                alert('Informe um CEP válido.');
                cepInput.focus();
                return;
            }

            if (!cidadeInput || !cidadeInput.value.trim()) {
                e.preventDefault();
                alert('Selecione uma cidade.');
                cidadeInput?.focus();
                return;
            }

            sanitizeUfInput();
            const ufValor = estadoInput ? estadoInput.value.trim() : '';
            const cityValidationSource = cityValidationSourceInput ? cityValidationSourceInput.value : 'api';
            const shouldEnforceAutocomplete = cityValidationSource === 'api';

            if (shouldEnforceAutocomplete && cidadeInput.dataset.citySelected !== '1') {
                e.preventDefault();
                alert('Selecione uma cidade da lista disponibilizada.');
                cidadeInput.focus();
                return;
            }

            if (!estadoInput || !estadoInput.value.trim()) {
                e.preventDefault();
                alert('O estado (UF) é obrigatório.');
                estadoInput?.focus();
                return;
            }

            if (ufValor.length !== 2) {
                e.preventDefault();
                alert('Informe uma UF válida.');
                estadoInput.focus();
                return;
            }
        });
    }

    // Função específica para máscara de CPF
    function aplicarMascaraCpf(value) {
        // Remove tudo que não for número
        value = value.replace(/\D/g, '');
        
        // Limita a 11 dígitos
        value = value.substring(0, 11);
        
        // Aplica a máscara de CPF: 000.000.000-00
        if (value.length > 0) {
            value = value.replace(/^(\d{1,3})/, '$1');
            if (value.length > 3) {
                value = value.replace(/^(\d{3})(\d{1,3})/, '$1.$2');
            }
            if (value.length > 6) {
                value = value.replace(/^(\d{3})\.(\d{3})(\d{1,3})/, '$1.$2.$3');
            }
            if (value.length > 9) {
                value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            }
        }
        
        return value;
    }

    // Função específica para máscara de CNPJ
    function aplicarMascaraCnpj(value) {
        // Remove tudo que não for número
        value = value.replace(/\D/g, '');
        
        // Limita a 14 dígitos
        value = value.substring(0, 14);
        
        // Aplica a máscara de CNPJ: 00.000.000/0000-00
        if (value.length > 0) {
            value = value.replace(/^(\d{1,2})/, '$1');
            if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{1,3})/, '$1.$2');
            }
            if (value.length > 5) {
                value = value.replace(/^(\d{2})\.(\d{3})(\d{1,3})/, '$1.$2.$3');
            }
            if (value.length > 8) {
                value = value.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
            }
            if (value.length > 12) {
                value = value.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
            }
        }
        
        return value;
    }

    // Função para máscara de telefone
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

    // Função para máscara de CEP
    function aplicarMascaraCep(value) {
        value = value.replace(/\D/g, '');
        value = value.substring(0, 8);
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        return value;
    }

    // Adiciona evento de input para CPF/CNPJ
    const cpfCnpjInput = document.getElementById('cpf_cnpj');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            
            if (tipoSelecionado === 'Física') {
                e.target.value = aplicarMascaraCpf(e.target.value);
            } else {
                e.target.value = aplicarMascaraCnpj(e.target.value);
            }
        });

        // Adiciona evento de paste para CPF/CNPJ
        cpfCnpjInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            
            if (tipoSelecionado === 'Física') {
                e.target.value = aplicarMascaraCpf(pastedText);
            } else {
                e.target.value = aplicarMascaraCnpj(pastedText);
            }
        });

        // Adiciona evento de keypress para bloquear caracteres não numéricos
        cpfCnpjInput.addEventListener('keypress', function(e) {
            // Permite backspace, delete, tab, escape, enter
            const specialKeys = [8, 9, 27, 13];
            if (specialKeys.indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Garante que é um número
            const char = String.fromCharCode(e.which);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
                return;
            }
            
            // Verifica o limite de caracteres baseado no tipo
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            const valorAtual = e.target.value.replace(/\D/g, '');
            
            if (tipoSelecionado === 'Física' && valorAtual.length >= 11) {
                e.preventDefault();
            } else if (tipoSelecionado === 'Jurídica' && valorAtual.length >= 14) {
                e.preventDefault();
            }
        });

        // Adiciona evento keydown para permitir teclas de navegação
        cpfCnpjInput.addEventListener('keydown', function(e) {
            // Permite: backspace, delete, tab, escape, enter, end, home, setas
            const allowedKeys = [8, 9, 27, 13, 35, 36, 37, 38, 39, 40, 46];
            if (allowedKeys.indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Permite Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            if ((e.ctrlKey || e.metaKey) && [65, 67, 86, 88].indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Bloqueia se não for número
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }

    // Adiciona evento de input para Telefone
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        if (telefoneInput.value) {
            telefoneInput.value = aplicarMascaraTelefone(telefoneInput.value);
        }
        telefoneInput.addEventListener('input', function(e) {
            e.target.value = aplicarMascaraTelefone(e.target.value);
        });

        telefoneInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraTelefone(e.target.value);
            }, 100);
        });
    }

    // Adiciona evento de input para CEP
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            e.target.value = aplicarMascaraCep(e.target.value);
        });

        cepInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraCep(e.target.value);
            }, 100);
        });
    }

    // Função para ajustar formulário baseado no tipo de pessoa
    function ajustarFormulario() {
        const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
        const valorAtual = cpfCnpjInput.value.replace(/\D/g, '');

        if (tipoSelecionado === 'Física') {
            labelNomeCliente.textContent = 'Nome da Empresa *';
            labelCpfCnpj.textContent = 'CPF *';
            if (containerNomeCliente) {
                containerNomeCliente.classList.remove('cliente-col-50');
                containerNomeCliente.classList.add('cliente-col-full');
            }
            if (containerNomeResponsavel) {
                containerNomeResponsavel.classList.add('hidden');
            }
            if (inputNomeResponsavel) {
                inputNomeResponsavel.removeAttribute('required');
            }
            cpfCnpjInput.setAttribute('placeholder', '000.000.000-00');

            // Se o valor atual é maior que 11 dígitos (era um CNPJ), limpa o campo
            if (valorAtual.length > 11) {
                cpfCnpjInput.value = '';
            } else if (valorAtual.length > 0) {
                // Reaplica a máscara de CPF
                cpfCnpjInput.value = aplicarMascaraCpf(valorAtual);
            }
        } else { // Jurídica
            labelNomeCliente.textContent = 'Nome da Empresa *';
            labelCpfCnpj.textContent = 'CNPJ *';
            if (containerNomeCliente) {
                containerNomeCliente.classList.remove('cliente-col-full');
                containerNomeCliente.classList.add('cliente-col-50');
            }
            if (containerNomeResponsavel) {
                containerNomeResponsavel.classList.remove('hidden');
            }
            if (inputNomeResponsavel) {
                inputNomeResponsavel.setAttribute('required', 'required');
            }
            cpfCnpjInput.setAttribute('placeholder', '00.000.000/0000-00');

            // Se tinha um CPF válido (11 dígitos), limpa o campo
            if (valorAtual.length > 0 && valorAtual.length <= 11) {
                cpfCnpjInput.value = '';
            } else if (valorAtual.length > 11) {
                // Reaplica a máscara de CNPJ
                cpfCnpjInput.value = aplicarMascaraCnpj(valorAtual);
            }
        }
    }
    
    radiosTipoPessoa.forEach(radio => {
        radio.addEventListener('change', ajustarFormulario);
    });
    
    // Executa a função na carga da página para ajustar o estado inicial
    ajustarFormulario();
    
    // Aplica máscara inicial se houver valor no campo (caso de edição)
    if (cpfCnpjInput && cpfCnpjInput.value) {
        const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
        if (tipoSelecionado === 'Física') {
            cpfCnpjInput.value = aplicarMascaraCpf(cpfCnpjInput.value);
        } else {
            cpfCnpjInput.value = aplicarMascaraCnpj(cpfCnpjInput.value);
        }
    }

    // Lógica do checkbox de login
    const checkbox = document.getElementById('criar_login_checkbox');
    const container = document.getElementById('login-fields-container');
    if (checkbox) {
        const emailInput = document.getElementById('login_email');
        const senhaInput = document.getElementById('login_senha');
        const loginInitiallyChecked = <?= $criar_login ? 'true' : 'false' ?>;

        if (loginInitiallyChecked) {
            container.classList.remove('hidden');
            if (emailInput && senhaInput) {
                emailInput.required = true;
                senhaInput.required = true;
            }
        }

        checkbox.addEventListener('change', function() {
            if (!emailInput || !senhaInput) {
                return;
            }

            if (this.checked) {
                container.classList.remove('hidden');
                emailInput.required = true;
                senhaInput.required = true;
            } else {
                container.classList.add('hidden');
                emailInput.required = false;
                senhaInput.required = false;
            }
        });
    }

    // Prevenir autocomplete de forma mais agressiva
    const allInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
    allInputs.forEach(input => {
        // Define atributos adicionais para prevenir autocomplete
        input.setAttribute('autocomplete', 'nope');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('spellcheck', 'false');
        
        // Adiciona um pequeno delay no focus para evitar preenchimento automático
        input.addEventListener('focus', function() {
            this.setAttribute('autocomplete', 'nope');
        });
    });

    // Limpa os campos no carregamento (útil para prevenir autocomplete do navegador)
    setTimeout(function() {
        if (!<?php echo $isEdit ? 'true' : 'false'; ?>) {
            // Só limpa se for um novo cadastro
            const form = document.querySelector('form');
            if (form && !form.dataset.loaded) {
                form.dataset.loaded = 'true';
            }
        }
    }, 100);
});
</script>