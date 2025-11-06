<?php
// Adicione esta lógica no início do seu ficheiro servico-rapido.php
$isEditMode = false; // Defina como false, pois este é um formulário de criação
$return_url = $_SERVER['HTTP_REFERER'] ?? 'processos.php'; // Usa a página anterior ou um padrão seguro.
$formData = $formData ?? [];
$cliente_pre_selecionado_id = $_GET['cliente_id'] ?? ($formData['cliente_id'] ?? null);
$financeiroServicos = $financeiroServicos ?? [];
$financeiroServicos['Tradução'] = $financeiroServicos['Tradução'] ?? [];
$financeiroServicos['CRC'] = $financeiroServicos['CRC'] ?? [];
$financeiroServicos['Apostilamento'] = $financeiroServicos['Apostilamento'] ?? [];
$financeiroServicos['Postagem'] = $financeiroServicos['Postagem'] ?? [];
$financeiroServicos['Outros'] = $financeiroServicos['Outros'] ?? [];

$translationAttachments = isset($translationAttachments) && is_array($translationAttachments) ? $translationAttachments : [];
$crcAttachments = isset($crcAttachments) && is_array($crcAttachments) ? $crcAttachments : [];
$paymentProofAttachments = isset($paymentProofAttachments) && is_array($paymentProofAttachments) ? $paymentProofAttachments : [];
$reuseTranslationForCrc = !empty($formData['reuseTraducaoForCrc'] ?? null);

$legacyDocuments = $formData['documentos'] ?? [];
$parseLegacyCurrency = static function ($value) {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value);
    if ($normalized === '' || $normalized === null) {
        return null;
    }
    $normalized = str_replace('.', '', $normalized);
    $normalized = str_replace(',', '.', $normalized);
    return is_numeric($normalized) ? (float) $normalized : null;
};

$aggregateLegacySection = static function (array $docs) use ($parseLegacyCurrency) {
    $totalQuantity = 0;
    $totalValue = 0.0;

    foreach ($docs as $doc) {
        if (!is_array($doc)) {
            continue;
        }

        $quantity = isset($doc['quantidade']) ? (int) $doc['quantidade'] : 0;
        $quantity = $quantity > 0 ? $quantity : 1;

        $lineTotal = $parseLegacyCurrency($doc['valor_total'] ?? null);
        $unitValue = $parseLegacyCurrency($doc['valor_unitario'] ?? null);

        if ($lineTotal === null && $unitValue !== null) {
            $lineTotal = $unitValue * $quantity;
        }

        if ($unitValue === null && $lineTotal !== null && $quantity > 0) {
            $unitValue = $lineTotal / $quantity;
        }

        if ($quantity > 0) {
            $totalQuantity += $quantity;
        }

        if ($lineTotal !== null) {
            $totalValue += $lineTotal;
        }
    }

    if ($totalQuantity <= 0 || $totalValue <= 0) {
        return null;
    }

    $unit = $totalValue / $totalQuantity;

    return [
        'quantidade' => $totalQuantity,
        'valor_unitario' => $unit,
        'valor_total' => $totalValue,
    ];
};

if (!empty($legacyDocuments['apostilamento'])
    && empty($formData['apostilamento_quantidade'])
    && empty($formData['apostilamento_valor_unitario'])
) {
    $legacyApostilamento = $aggregateLegacySection($legacyDocuments['apostilamento']);
    if ($legacyApostilamento !== null) {
        $formData['apostilamento_quantidade'] = (string) $legacyApostilamento['quantidade'];
        $formData['apostilamento_valor_unitario'] = number_format($legacyApostilamento['valor_unitario'], 2, ',', '.');
        $formData['apostilamento_valor_total'] = number_format($legacyApostilamento['valor_total'], 2, ',', '.');
    }
}

if (!empty($legacyDocuments['postagem'])
    && empty($formData['postagem_quantidade'])
    && empty($formData['postagem_valor_unitario'])
) {
    $legacyPostagem = $aggregateLegacySection($legacyDocuments['postagem']);
    if ($legacyPostagem !== null) {
        $formData['postagem_quantidade'] = (string) $legacyPostagem['quantidade'];
        $formData['postagem_valor_unitario'] = number_format($legacyPostagem['valor_unitario'], 2, ',', '.');
        $formData['postagem_valor_total'] = number_format($legacyPostagem['valor_total'], 2, ',', '.');
    }
}

$categoriasSelecionadas = [];
if (!empty($formData['categorias_servico'])) {
    if (is_array($formData['categorias_servico'])) {
        $categoriasSelecionadas = $formData['categorias_servico'];
    } else {
        $categoriasSelecionadas = array_filter(array_map('trim', explode(',', (string) $formData['categorias_servico'])));
    }
}
$prazoTipoSelecionado = $formData['prazo_tipo'] ?? 'dias';


?>

    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Cadastrar Novo Serviço</h1>
             
            <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
                &larr; Voltar
            </a>
        </div>

<form action="servico-rapido.php?action=store" method="POST" enctype="multipart/form-data" class="bg-white shadow-lg rounded-lg p-8 space-y-6">
    
    <fieldset class="border border-gray-200 rounded-md p-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Detalhes do Serviço</legend>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700">Nome do Serviço / Família</label>
                <input type="text" name="titulo" id="titulo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($formData['titulo'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Número da OS Omni</label>
                <div class="mt-1 block w-full p-2 border border-dashed border-gray-300 rounded-md bg-gray-50 text-sm text-gray-600">
                    Será gerado automaticamente pela Omni após a criação do serviço.
                </div>
                <p class="text-xs text-gray-500 mt-1">O número será exibido no dashboard quando a Omni retornar a OS.</p>
            </div>
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente Associado</label>
                <div class="flex items-center space-x-2 mt-1">
                    <select name="cliente_id" id="cliente_id" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value=""></option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option
                                value="<?php echo $cliente['id']; ?>"
                                data-tipo-assessoria="<?php echo $cliente['tipo_assessoria']; ?>"
                                data-prazo-acordado="<?php echo htmlspecialchars($cliente['prazo_acordado_dias'] ?? ''); ?>"
                                <?php
                                    $isSelected = ($cliente_pre_selecionado_id == $cliente['id']);
                                    echo $isSelected ? 'selected' : '';
                                ?>>
                                <?php
                                    $displayName = $cliente['budgetDisplayName'] ?? ($cliente['nome_cliente'] ?? '');
                                    echo htmlspecialchars($displayName);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                                <input type="hidden" id="cliente_tipo_assessoria" name="cliente_tipo_assessoria" value="<?php echo htmlspecialchars($formData['cliente_tipo_assessoria'] ?? ''); ?>">

                    <a href="<?php echo APP_URL; ?>/clientes.php?action=create&return_to=<?php echo urlencode(APP_URL . $_SERVER['REQUEST_URI']); ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-md text-sm whitespace-nowrap" title="Adicionar Novo Cliente">
                        +
                    </a>
                </div>
                <p class="text-xs text-gray-500 mt-2">Prazo acordado de <span id="prazo-acordado-display" class="font-semibold text-gray-700">--</span> dia(s).</p>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-semibold text-gray-700">Serviços Contratados *</label>
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                    <?php
                    $servicos_lista = ['Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros'];
                    $slug_map = ['Tradução' => 'tradução', 'CRC' => 'crc', 'Apostilamento' => 'apostilamento', 'Postagem' => 'postagem', 'Outros' => 'outros'];
                    $labelColorMap = [
                        'Tradução' => 'border-blue-300 bg-blue-50 hover:bg-blue-100',
                        'CRC' => 'border-green-300 bg-green-50 hover:bg-green-100',
                        'Apostilamento' => 'border-yellow-300 bg-yellow-50 hover:bg-yellow-100',
                        'Postagem' => 'border-purple-300 bg-purple-50 hover:bg-purple-100',
                        'Outros' => 'border-gray-300 bg-gray-50 hover:bg-gray-100',
                    ];
                    $checkboxColorMap = [
                        'Tradução' => 'text-blue-600 focus:ring-blue-500',
                        'CRC' => 'text-green-600 focus:ring-green-500',
                        'Apostilamento' => 'text-yellow-600 focus:ring-yellow-500',
                        'Postagem' => 'text-purple-600 focus:ring-purple-500',
                        'Outros' => 'text-gray-600 focus:ring-gray-500',
                    ];
                    $textColorMap = [
                        'Tradução' => 'text-blue-800',
                        'CRC' => 'text-green-800',
                        'Apostilamento' => 'text-yellow-800',
                        'Postagem' => 'text-purple-800',
                        'Outros' => 'text-gray-800',
                    ];
                    foreach ($servicos_lista as $servico):
                        $slug = $slug_map[$servico];
                        $labelClasses = $labelColorMap[$servico];
                        $checkboxClasses = $checkboxColorMap[$servico];
                        $textClasses = $textColorMap[$servico];
                    ?>
                        <label for="cat_rapido_<?php echo $slug; ?>" class="flex items-center p-3 border rounded-lg cursor-pointer transition-all duration-200 <?php echo $labelClasses; ?>">
                            <input id="cat_rapido_<?php echo $slug; ?>" name="categorias_servico[]" type="checkbox" value="<?php echo $servico; ?>" class="h-5 w-5 border-gray-300 rounded service-checkbox <?php echo $checkboxClasses; ?>" <?php echo in_array($servico, $categoriasSelecionadas, true) ? 'checked' : ''; ?>>
                            <span class="ml-3 block text-sm font-semibold <?php echo $textClasses; ?>"><?php echo $servico; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-6 pt-6 border-t border-gray-200">
            <div class="md:col-span-1">
                <label for="data_inicio_traducao" class="block text-sm font-medium text-gray-700">Data de Envio para Tradutor</label>
                <input type="date" name="data_inicio_traducao" id="data_inicio_traducao" value="<?php echo htmlspecialchars($formData['data_inicio_traducao'] ?? date('Y-m-d')); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="md:col-span-1">
                <label for="prazo_tipo" class="block text-sm font-medium text-gray-700">Definir Prazo Para Serviço</label>
                <select name="prazo_tipo" id="prazo_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="dias" <?php echo ($prazoTipoSelecionado === 'dias') ? 'selected' : ''; ?>>Por Dias</option>
                    <option value="data" <?php echo ($prazoTipoSelecionado === 'data') ? 'selected' : ''; ?>>Por Data</option>
                </select>
            </div>
            <div class="md:col-span-1">
                <div id="prazo_dias_container" class="<?php echo ($prazoTipoSelecionado === 'data') ? 'hidden' : ''; ?>">
                    <label for="traducao_prazo_dias" class="block text-sm font-medium text-gray-700">Quantidade de Dias Corridos</label>
                    <input type="number" name="traducao_prazo_dias" id="traducao_prazo_dias" placeholder="Ex: 5" value="<?php echo htmlspecialchars($formData['traducao_prazo_dias'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" <?php echo ($prazoTipoSelecionado === 'data') ? 'disabled' : ''; ?>>
                </div>
                <div id="prazo_data_container" class="<?php echo ($prazoTipoSelecionado === 'data') ? '' : 'hidden'; ?>">
                    <label for="traducao_prazo_data" class="block text-sm font-medium text-gray-700">Data da Entrega</label>
                    <input type="date" name="traducao_prazo_data" id="traducao_prazo_data" value="<?php echo htmlspecialchars($formData['traducao_prazo_data'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" <?php echo ($prazoTipoSelecionado === 'data') ? '' : 'disabled'; ?>>
                </div>
            </div>
        </div>
    </fieldset>

        <div id="section-container-tradução" class="bg-blue-50 p-6 rounded-lg shadow-lg border border-blue-200" style="display: none;">
            <h2 class="text-xl font-semibold mb-4 border-b border-blue-200 pb-2 text-blue-800">Detalhes da Tradução</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label for="traducao_modalidade" class="block text-sm font-medium text-gray-700">Modalidade</label>
                    <select name="traducao_modalidade" id="traducao_modalidade" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Normal" <?php echo (($formData['traducao_modalidade'] ?? 'Normal') === 'Normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="Express" <?php echo (($formData['traducao_modalidade'] ?? '') === 'Express') ? 'selected' : ''; ?>>Express</option>
                    </select>
                </div>
                <div>
                    <label for="tradutor_id" class="block text-sm font-medium text-gray-700">Tradutor</label>
                    <select name="tradutor_id" id="tradutor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="">Selecione um tradutor</option>
                        <?php foreach ($tradutores as $tradutor): ?>
                            <option value="<?php echo $tradutor['id']; ?>" <?php echo (($formData['tradutor_id'] ?? '') == $tradutor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tradutor['nome_tradutor']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="assinatura_tipo" class="block text-sm font-medium text-gray-700">Assinatura</label>
                    <select name="assinatura_tipo" id="assinatura_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Digital" <?php echo (($formData['assinatura_tipo'] ?? 'Digital') === 'Digital') ? 'selected' : ''; ?>>Assinatura Digital</option>
                        <option value="Física" <?php echo (($formData['assinatura_tipo'] ?? '') === 'Física') ? 'selected' : ''; ?>>Assinatura Física</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-md font-semibold text-gray-800">Anexar documentos de tradução</h3>
                    <span class="text-xs text-blue-700 font-medium" data-upload-counter="translation">0 arquivos novos</span>
                </div>
                <div class="space-y-2">
                    <label for="fastTranslationFiles" class="sr-only">Escolher arquivos</label>
                    <input type="file" name="translationFiles[]" id="fastTranslationFiles" multiple data-preview-target="translation" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    <p class="text-xs text-gray-500">Inclua tradução, referências e outros documentos de apoio.</p>
                    <ul class="text-xs text-gray-700 bg-white border border-blue-100 rounded-md divide-y" data-upload-preview="translation" data-empty-message="Nenhum arquivo selecionado.">
                        <li class="py-2 px-3 text-gray-500" data-upload-placeholder="translation">Nenhum arquivo selecionado.</li>
                    </ul>
                </div>
                <?php if (!empty($translationAttachments)): ?>
                    <div class="rounded-md border border-blue-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-blue-700 mb-2">Arquivos já anexados</h4>
                        <ul class="space-y-2">
                            <?php foreach ($translationAttachments as $anexo): ?>
                                <li class="flex items-center justify-between text-sm text-gray-700">
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-6 pt-6 border-t border-blue-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-700">Documentos para Tradução</h3>
                    <button type="button" onclick="adicionarDocumento('tradução')" class="bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-600 text-sm">
                        <i class="fas fa-plus mr-2"></i>Adicionar Documento
                    </button>
                </div>
                <div id="documentos-container-tradução" class="space-y-4">
                    </div>
            </div>
        </div>
    <div id="section-container-crc" class="bg-green-50 p-6 rounded-lg shadow-lg border border-green-200" style="display: none;">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-green-200 pb-2">
                <h2 class="text-xl font-semibold text-green-800">Documentos CRC</h2>
                <button type="button" class="add-doc-row bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 transition duration-150 ease-in-out" data-section="crc">Adicionar</button>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-md font-semibold text-gray-800">Anexar documentos CRC</h3>
                    <span class="text-xs text-green-700 font-medium" data-upload-counter="crc">0 arquivos novos</span>
                </div>
                <input type="file" name="crcFiles[]" id="fastCrcFiles" multiple data-preview-target="crc" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200" <?php echo $reuseTranslationForCrc ? 'disabled' : ''; ?>>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="fastReuseTranslationForCrc" name="reuseTraducaoForCrc" value="1" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" <?php echo $reuseTranslationForCrc ? 'checked' : ''; ?>>
                    <label for="fastReuseTranslationForCrc" class="text-xs text-gray-700">Reutilizar automaticamente os arquivos enviados para Tradução</label>
                </div>
                <p class="text-xs text-gray-500">Inclua arquivos legais para a etapa do CRC, como por exemplo, certidões.</p>
                <ul class="text-xs text-gray-700 bg-white border border-green-100 rounded-md divide-y" data-upload-preview="crc" data-empty-message="Nenhum arquivo selecionado.">
                    <li class="py-2 px-3 text-gray-500" data-upload-placeholder="crc"><?php echo $reuseTranslationForCrc ? 'Os arquivos de tradução serão reutilizados.' : 'Nenhum arquivo selecionado.'; ?></li>
                </ul>
            </div>
            <div id="documentos-container-crc" class="space-y-4"></div>
            <?php if (!empty($crcAttachments)): ?>
                <div class="rounded-md border border-green-200 bg-white p-4">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Arquivos já anexados</h4>
                    <ul class="space-y-2">
                        <?php foreach ($crcAttachments as $anexo): ?>
                            <li class="flex items-center justify-between text-sm text-gray-700">
                                <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-green-600 hover:underline">
                                    <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="section-container-outros" class="bg-gray-50 p-6 rounded-lg shadow-lg border border-gray-200 mt-6" style="display: none;">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                <h2 class="text-xl font-semibold text-gray-800">Serviços Outros</h2>
                <button type="button" class="add-doc-row bg-gray-600 text-white px-4 py-2 rounded-md text-sm hover:bg-gray-700 transition duration-150 ease-in-out" data-section="outros">Adicionar</button>
            </div>
            <div id="documentos-container-outros" class="space-y-4"></div>
        </div>
    </div>

    <div id="section-container-apostilamento" class="bg-yellow-50 p-6 rounded-lg shadow-lg border border-yellow-200 mt-6" style="display: none;">
        <h2 class="text-xl font-semibold text-yellow-800 border-b border-yellow-200 pb-2">Etapa Apostilamento</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <label for="apostilamento_quantidade" class="block text-sm font-medium text-gray-700">Quantidade</label>
                <input type="number" name="apostilamento_quantidade" id="apostilamento_quantidade" value="<?php echo htmlspecialchars($formData['apostilamento_quantidade'] ?? '0'); ?>" class="mt-1 block w-full p-2 border border-yellow-300 rounded-md shadow-sm calculation-trigger">
            </div>
            <div>
                <label for="apostilamento_valor_unitario" class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
                <input type="text" name="apostilamento_valor_unitario" id="apostilamento_valor_unitario" value="<?php echo htmlspecialchars($formData['apostilamento_valor_unitario'] ?? '0,00'); ?>" class="mt-1 block w-full p-2 border border-yellow-300 rounded-md shadow-sm calculation-trigger">
            </div>
            <div>
                <label for="apostilamento_valor_total" class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
                <input type="text" name="apostilamento_valor_total" id="apostilamento_valor_total" value="<?php echo htmlspecialchars($formData['apostilamento_valor_total'] ?? '0,00'); ?>" class="mt-1 block w-full p-2 border border-yellow-300 rounded-md shadow-sm bg-gray-100" readonly>
            </div>
        </div>
    </div>

    <div id="section-container-postagem" class="bg-purple-50 p-6 rounded-lg shadow-lg border border-purple-200 mt-6" style="display: none;">
        <h2 class="text-xl font-semibold text-purple-800 border-b border-purple-200 pb-2">Etapa Postagem / Envio</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <label for="postagem_quantidade" class="block text-sm font-medium text-gray-700">Quantidade</label>
                <input type="number" name="postagem_quantidade" id="postagem_quantidade" value="<?php echo htmlspecialchars($formData['postagem_quantidade'] ?? '0'); ?>" class="mt-1 block w-full p-2 border border-purple-300 rounded-md shadow-sm calculation-trigger">
            </div>
            <div>
                <label for="postagem_valor_unitario" class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
                <input type="text" name="postagem_valor_unitario" id="postagem_valor_unitario" value="<?php echo htmlspecialchars($formData['postagem_valor_unitario'] ?? '0,00'); ?>" class="mt-1 block w-full p-2 border border-purple-300 rounded-md shadow-sm calculation-trigger">
            </div>
            <div>
                <label for="postagem_valor_total" class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
                <input type="text" name="postagem_valor_total" id="postagem_valor_total" value="<?php echo htmlspecialchars($formData['postagem_valor_total'] ?? '0,00'); ?>" class="mt-1 block w-full p-2 border border-purple-300 rounded-md shadow-sm bg-gray-100" readonly>
            </div>
        </div>
    </div>

    <div id="section-container-outros" class="bg-gray-50 p-6 rounded-lg shadow-lg border border-gray-200 mt-6" style="display: none;">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                <h2 class="text-xl font-semibold text-gray-800">Outros Serviços</h2>
                <button type="button" class="add-doc-row bg-gray-600 text-white px-4 py-2 rounded-md text-sm hover:bg-gray-700 transition duration-150 ease-in-out" data-section="outros">Adicionar</button>
            </div>
            <div id="documentos-container-outros" class="space-y-4"></div>
        </div>
    </div>

    <fieldset class="border border-gray-200 rounded-md p-6 mt-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Resumo do Serviço</legend>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-500">Total de Documentos</label>
                <p id="total-documentos-display" class="mt-1 text-xl font-bold text-gray-800">0</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Valor Total do Serviço</label>
                <p id="total-geral-display" class="mt-1 text-xl font-bold text-green-600">R$ 0,00</p>
                <input type="hidden" name="valor_total_hidden" id="valor_total_hidden" value="<?php echo htmlspecialchars($formData['valor_total_hidden'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Valor Total (cópia para pagamento)</label>
                <input type="text" id="valor_total_servico" class="mt-1 block w-full p-2 border border-green-200 rounded-md bg-green-50 text-green-700 font-semibold" value="R$ 0,00" readonly>
            </div>
        </div>
    </fieldset>

    <fieldset class="border border-gray-200 rounded-md p-6 mt-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Condições de Pagamento</legend>
        <div class="space-y-6 mt-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="billing_type" class="block text-sm font-medium text-gray-700">Forma de Cobrança</label>
                    <select name="orcamento_forma_pagamento" id="billing_type" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Pagamento único">Pagamento único</option>
                        <option value="Pagamento parcelado">Pagamento parcelado</option>
                        <option value="Pagamento mensal">Pagamento mensal</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700" for="billing_total_display">Valor Total do Serviço</label>
                    <input type="text" id="billing_total_display" class="mt-1 block w-full p-2 border border-blue-200 rounded-md bg-blue-50 text-blue-700 font-semibold" value="R$ 0,00" readonly>
                </div>
            </div>

            <div class="space-y-4" data-billing-section="Pagamento único">
                <h3 class="text-md font-semibold text-gray-800">Pagamento único</h3>
                <p class="text-sm text-gray-600">O valor recebido será igual ao total calculado do serviço.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_unique_date">Data do pagamento</label>
                        <input type="date" id="billing_unique_date" data-field-name="data_pagamento_1" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_unique_receipt">Comprovante do pagamento</label>
                        <label for="billing_unique_receipt" class="mt-2 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 px-4 py-5 text-center text-blue-600 transition hover:border-blue-400 hover:bg-blue-100 cursor-pointer" role="button">
                            <svg class="mb-2 h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m8 4a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h7.586a2 2 0 011.414.586l4.414 4.414A2 2 0 0120 9.414V19z" />
                            </svg>
                            <span class="text-sm font-semibold">Clique para selecionar ou arraste o arquivo</span>
                            <span class="mt-1 text-xs text-blue-500" data-upload-filename="billing_unique_receipt" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                        </label>
                        <input type="file" id="billing_unique_receipt" data-field-name="paymentProofFiles[]" data-upload-display="billing_unique_receipt" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.heic">
                    </div>
                </div>
            </div>

            <div class="space-y-4 hidden" data-billing-section="Pagamento parcelado">
                <h3 class="text-md font-semibold text-gray-800">Pagamento parcelado</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div id="entrada-wrapper">
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_entrada">Valor da 1ª parcela</label>
                        <input type="text" id="billing_parcelado_entrada" data-field-name="orcamento_valor_entrada" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm valor-servico" placeholder="R$ 0,00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_data1">Data da 1ª parcela</label>
                        <input type="date" id="billing_parcelado_data1" data-field-name="data_pagamento_1" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_receipt1">Comprovante da 1ª parcela</label>
                        <label for="billing_parcelado_receipt1" class="mt-2 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-green-300 bg-green-50 px-4 py-5 text-center text-green-600 transition hover:border-green-400 hover:bg-green-100 cursor-pointer" role="button">
                            <svg class="mb-2 h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm font-semibold">Envie o comprovante da primeira parcela</span>
                            <span class="mt-1 text-xs text-green-600" data-upload-filename="billing_parcelado_receipt1" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                        </label>
                        <input type="file" id="billing_parcelado_receipt1" data-field-name="paymentProofFiles[]" data-upload-display="billing_parcelado_receipt1" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.heic">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_restante">Valor Restante</label>
                        <input type="text" id="billing_parcelado_restante" data-field-name="orcamento_valor_restante" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm bg-gray-100" value="R$ 0,00" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_data2">Data da 2ª parcela</label>
                        <input type="date" id="billing_parcelado_data2" data-field-name="data_pagamento_2" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_parcelado_receipt2">Comprovante da 2ª parcela</label>
                        <label for="billing_parcelado_receipt2" class="mt-2 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-purple-300 bg-purple-50 px-4 py-5 text-center text-purple-600 transition hover:border-purple-400 hover:bg-purple-100 cursor-pointer" role="button">
                            <svg class="mb-2 h-6 w-6 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm font-semibold">Envie o comprovante da segunda parcela</span>
                            <span class="mt-1 text-xs text-purple-600" data-upload-filename="billing_parcelado_receipt2" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                        </label>
                        <input type="file" id="billing_parcelado_receipt2" data-field-name="paymentProofFiles[]" data-upload-display="billing_parcelado_receipt2" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.heic">
                    </div>
                </div>
            </div>

            <div class="space-y-4 hidden" data-billing-section="Pagamento mensal">
                <h3 class="text-md font-semibold text-gray-800">Pagamento mensal</h3>
                <p class="text-sm text-gray-600">A primeira cobrança será igual ao valor total calculado para o período.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_mensal_date">Data do pagamento</label>
                        <input type="date" id="billing_mensal_date" data-field-name="data_pagamento_1" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="billing_mensal_receipt">Comprovante do pagamento</label>
                        <label for="billing_mensal_receipt" class="mt-2 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-indigo-300 bg-indigo-50 px-4 py-5 text-center text-indigo-600 transition hover:border-indigo-400 hover:bg-indigo-100 cursor-pointer" role="button">
                            <svg class="mb-2 h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m8 4a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h7.586a2 2 0 011.414.586l4.414 4.414A2 2 0 0120 9.414V19z" />
                            </svg>
                            <span class="text-sm font-semibold">Envie o comprovante do pagamento mensal</span>
                            <span class="mt-1 text-xs text-indigo-600" data-upload-filename="billing_mensal_receipt" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                        </label>
                        <input type="file" id="billing_mensal_receipt" data-field-name="paymentProofFiles[]" data-upload-display="billing_mensal_receipt" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.heic">
                    </div>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
        <a href="dashboard.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out mr-4">Cancelar</a>
        <button type="submit" class="bg-green-600 text-white font-bold px-6 py-2 rounded-md hover:bg-green-700 transition duration-150 ease-in-out">Salvar Serviço</button>
    </div>
</form>

    </div>

<template id="template-crc">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-gray-50 relative items-end">
        <input type="hidden" name="documentos[crc][{index}][categoria]" value="CRC">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
            <select
                name="documentos[crc][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="CRC"
            >
                <option value="">Selecione o serviço...</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Nome do Titular</label>
            <input type="text" name="documentos[crc][{index}][nome_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Nome Completo">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Valor</label>
            <input type="text" name="documentos[crc][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>


<template id="template-outros">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-gray-50 relative items-end">
        <input type="hidden" name="documentos[outros][{index}][categoria]" value="Outros">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
            <select
                name="documentos[outros][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="Outros"
            >
                <option value="">Selecione o serviço...</option>
                <?php foreach ($financeiroServicos['Outros'] as $servicoOutros): ?>
                    <option value="<?php echo htmlspecialchars($servicoOutros['nome_categoria']); ?>"
                            data-valor-padrao="<?php echo $servicoOutros['valor_padrao']; ?>"
                            data-bloqueado="<?php echo $servicoOutros['bloquear_valor_minimo']; ?>">
                        <?php echo htmlspecialchars($servicoOutros['nome_categoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Descrição</label>
            <input type="text" name="documentos[outros][{index}][nome_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Descrição do serviço">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Valor</label>
            <input type="text" name="documentos[outros][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>



<template id="template-tradução-avista">
    <div class="doc-row grid grid-cols-12 gap-4 items-center">
        <input type="hidden" name="documentos[tradução][{index}][categoria]" value="Tradução">
        <div class="col-span-5">
            <label class="block text-xs font-medium text-gray-500 sr-only">Tipo de Documento *</label>
            <select
                name="documentos[tradução][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="Tradução"
            >
                <option value="">Selecione o serviço...</option>
            </select>
        </div>
        <div class="col-span-4">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input
                type="text"
                name="documentos[tradução][{index}][nome_documento]"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm"
                placeholder="Titular do documento"
            >
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="text" name="documentos[tradução][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="R$ 0,00">
        </div>
        <div class="col-span-1 text-right">
            <button type="button" class="remove-doc-row text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
        </div>
    </div>
</template>

<template id="template-outros">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-white relative items-end">
        <input type="hidden" name="documentos[outros][{index}][categoria]" value="Outros">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Serviço</label>
            <select
                name="documentos[outros][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="Outros"
            >
                <option value="">Selecione o serviço...</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Descrição</label>
            <input type="text" name="documentos[outros][{index}][nome_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Detalhes">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Valor</label>
            <input type="text" name="documentos[outros][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>

<template id="template-tradução-mensalista">
    <div class="doc-row grid grid-cols-12 gap-4 items-center">
        <input type="hidden" name="documentos[tradução][{index}][categoria]" value="Tradução">
        <div class="col-span-5">
            <label class="block text-xs font-medium text-gray-500 sr-only">Serviço Contratado *</label>
            <select name="documentos[tradução][{index}][tipo_documento]" class="servico-select mensalista-servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" data-servico-tipo="Tradução">
                <option value="">Selecione o serviço contratado...</option>
            </select>
        </div>
        <div class="col-span-4">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input
                type="text"
                name="documentos[tradução][{index}][nome_documento]"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm"
                placeholder="Titular do documento"
            >
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="text"
                name="documentos[tradução][{index}][valor_unitario]"
                class="doc-valor valor-servico mensalista-valor mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                placeholder="R$ 0,00"
            />
        </div>
        <div class="col-span-1 flex items-center justify-end">
            <button type="button" class="remove-doc-row text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
        </div>
    </div>
</template>



<script>
// Script responsável por lidar com interações do formulário de serviço rápido.
document.addEventListener('DOMContentLoaded', function () {
    // === ELEMENTOS DO FORMULÁRIO E VARIÁVEIS GLOBAIS ===
    const clienteSelect = document.getElementById('cliente_id');
    const tipoAssessoriaInput = document.getElementById('cliente_tipo_assessoria');
    const prazoAcordadoDisplay = document.getElementById('prazo-acordado-display');
    const servicosCheckboxes = document.querySelectorAll('.service-checkbox');
    const userPerfil = "<?php echo $_SESSION['user_perfil'] ?? 'colaborador'; ?>";
    const sectionCounters = { tradução: 0, crc: 0, outros: 0 };
    const financeServices = <?php echo json_encode($financeiroServicos, JSON_UNESCAPED_UNICODE); ?>;
    const isGestor = ['admin', 'gerencia', 'supervisor'].includes(userPerfil);
    const minValueAlertMessage = 'Atenção: O valor informado está abaixo do mínimo cadastrado. A supervisão irá validar e o serviço ficará pendente até a aprovação.';
    let clienteCache = { tipo: 'À vista', servicos: [] };
    const billingTypeSelect = document.getElementById('billing_type');
    const billingSections = document.querySelectorAll('[data-billing-section]');
    const prazoTipoSelect = document.getElementById('prazo_tipo');
    const prazoDiasInput = document.getElementById('traducao_prazo_dias');
    const prazoDataInput = document.getElementById('traducao_prazo_data');
    let currentDeadlineDays = (() => {
        if (!prazoDiasInput) {
            return null;
        }
        const initialValue = parseInt(prazoDiasInput.value, 10);
        return Number.isNaN(initialValue) ? null : initialValue;
    })();
    let agreedDeadlineFromClient = currentDeadlineDays;

    function updateFileTileDisplay(input) {
        if (!input) {
            return;
        }

        const displayId = input.dataset.uploadDisplay;
        if (!displayId) {
            return;
        }

        const displayElement = document.querySelector(`[data-upload-filename="${displayId}"]`);
        if (!displayElement) {
            return;
        }

        const placeholder = displayElement.dataset.placeholder || 'Nenhum arquivo selecionado';

        if (!input.files || input.files.length === 0) {
            displayElement.textContent = placeholder;
            return;
        }

        const names = Array.from(input.files).map(file => file.name).join(', ');
        displayElement.textContent = names;
    }

    function bindFileTileInputs() {
        document.querySelectorAll('input[type="file"][data-upload-display]').forEach(input => {
            input.addEventListener('change', () => updateFileTileDisplay(input));
            updateFileTileDisplay(input);
        });
    }

    function setPreviewMessage(target, message = null) {
        const preview = document.querySelector(`[data-upload-preview="${target}"]`);
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        const item = document.createElement('li');
        item.className = 'py-2 px-3 text-gray-500';
        item.dataset.uploadPlaceholder = target;
        item.textContent = message || preview.dataset.emptyMessage || 'Nenhum arquivo selecionado.';
        preview.appendChild(item);
    }

    function refreshUploadSummary(input) {
        if (!input) {
            return;
        }

        const target = input.dataset.previewTarget;
        if (!target) {
            return;
        }

        const preview = document.querySelector(`[data-upload-preview="${target}"]`);
        const counter = document.querySelector(`[data-upload-counter="${target}"]`);
        if (!preview) {
            return;
        }

        const files = Array.from(input.files || []);
        if (files.length === 0) {
            setPreviewMessage(target);
            if (counter) {
                counter.textContent = '0 arquivos novos';
            }
            return;
        }

        preview.innerHTML = '';
        files.forEach(file => {
            const item = document.createElement('li');
            item.className = 'py-2 px-3 flex items-center justify-between gap-3';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate font-medium text-gray-700';
            nameSpan.textContent = file.name;

            const sizeSpan = document.createElement('span');
            sizeSpan.className = 'text-gray-500 text-[11px] uppercase';
            sizeSpan.textContent = `${(file.size / 1024).toFixed(1)} KB`;

            item.appendChild(nameSpan);
            item.appendChild(sizeSpan);
            preview.appendChild(item);
        });

        if (counter) {
            counter.textContent = files.length === 1 ? '1 arquivo novo' : `${files.length} arquivos novos`;
        }
    }

    const uploadInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    uploadInputs.forEach(input => {
        input.addEventListener('change', () => refreshUploadSummary(input));
    });

    bindFileTileInputs();

    const fastReuseCheckbox = document.getElementById('fastReuseTranslationForCrc');
    const fastCrcInput = document.getElementById('fastCrcFiles');
    if (fastReuseCheckbox && fastCrcInput) {
        const applyReuseState = () => {
            if (fastReuseCheckbox.checked) {
                fastCrcInput.value = '';
                fastCrcInput.setAttribute('disabled', 'disabled');
                setPreviewMessage('crc', 'Os arquivos de tradução serão reutilizados.');
                const counter = document.querySelector('[data-upload-counter="crc"]');
                if (counter) {
                    counter.textContent = '0 arquivos novos';
                }
            } else {
                fastCrcInput.removeAttribute('disabled');
                setPreviewMessage('crc');
            }
        };

        fastReuseCheckbox.addEventListener('change', applyReuseState);
        applyReuseState();
    }

    // --- Campo oculto para controle de status proposto ---
    // Criado dinamicamente para que o backend saiba se o serviço deve iniciar 'Serviço Pendente' ou 'Serviço em Andamento'.
    const formElement = document.querySelector('form');
    if (formElement && !document.getElementById('status_proposto')) {
        const hiddenStatusInput = document.createElement('input');
        hiddenStatusInput.type = 'hidden';
        hiddenStatusInput.name = 'status_proposto';
        hiddenStatusInput.id = 'status_proposto';
        hiddenStatusInput.value = 'Serviço em Andamento'; // valor padrão
        formElement.appendChild(hiddenStatusInput);
    }

    // === INICIALIZAÇÃO DO SELECT2 ===
        $(clienteSelect).select2({
            placeholder: "Selecione um cliente...",
            allowClear: true
        });

    // Lógica para clientes mensalistas: carrega serviços via API.
    $(clienteSelect).on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const rawPrazoValue = selectedOption.data('prazo-acordado');
        const parsedPrazo = Number.parseInt(rawPrazoValue, 10);
        agreedDeadlineFromClient = Number.isNaN(parsedPrazo) ? null : parsedPrazo;
        currentDeadlineDays = agreedDeadlineFromClient;

        if (prazoAcordadoDisplay) {
            prazoAcordadoDisplay.textContent = agreedDeadlineFromClient !== null ? agreedDeadlineFromClient : '--';
        }

        if (prazoDiasInput) {
            prazoDiasInput.value = currentDeadlineDays !== null ? currentDeadlineDays : '';
        }
        const tipo = selectedOption.data('tipo-assessoria') || 'À vista';
        if (tipoAssessoriaInput) {
            tipoAssessoriaInput.value = tipo;
        }
        clienteCache.tipo = tipo;

        const clienteId = selectedOption.val();

        if (tipo === 'Mensalista' && clienteId) {
            clienteCache.servicos = [];
            refreshAllServiceSelects();
            updateAllCalculations();
            evaluateValorMinimo();

            fetch(`api_cliente.php?id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    clienteCache.servicos = (data.success && Array.isArray(data.servicos)) ? data.servicos : [];
                    refreshAllServiceSelects();
                    updateAllCalculations();
                    evaluateValorMinimo();
                })
                .catch(() => {
                    clienteCache.servicos = [];
                    refreshAllServiceSelects();
                    updateAllCalculations();
                    evaluateValorMinimo();
                });
            return;
        }

        clienteCache.servicos = [];
        refreshAllServiceSelects();
        updateAllCalculations();
        evaluateValorMinimo();
    });
    $(clienteSelect).trigger('change');

    function getAvailableServices(servicoTipo) {
        if (!servicoTipo) {
            return [];
        }

        if (clienteCache.tipo === 'Mensalista') {
            const personalizados = clienteCache.servicos
                .filter(servico => servico.servico_tipo === servicoTipo)
                .map(servico => ({
                    nome_categoria: servico.nome_categoria,
                    valor_padrao: servico.valor_padrao,
                    bloquear_valor_minimo: 1
                }));

            if (personalizados.length > 0) {
                return personalizados;
            }
        }

        return financeServices[servicoTipo] || [];
    }

    function populateServiceSelect(selectElement, preserveValue = false) {
        if (!selectElement) {
            return;
        }

        const servicoTipo = selectElement.dataset.servicoTipo || '';
        const currentValue = preserveValue ? selectElement.value : '';
        const availableServices = getAvailableServices(servicoTipo);

        selectElement.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Selecione o serviço...';
        selectElement.appendChild(placeholderOption);

        availableServices.forEach(item => {
            if (!item || !item.nome_categoria) {
                return;
            }

            const option = document.createElement('option');
            option.value = item.nome_categoria;
            option.textContent = item.nome_categoria;

            if (item.valor_padrao !== undefined && item.valor_padrao !== null && item.valor_padrao !== '') {
                option.dataset.valorPadrao = item.valor_padrao;
                option.dataset.valor = item.valor_padrao;
            }

            if (item.bloquear_valor_minimo !== undefined) {
                option.dataset.bloqueado = item.bloquear_valor_minimo ? '1' : '0';
            } else {
                option.dataset.bloqueado = clienteCache.tipo === 'Mensalista' ? '1' : '0';
            }

            selectElement.appendChild(option);
        });

        if (currentValue) {
            const match = Array.from(selectElement.options).find(opt => opt.value === currentValue);
            if (match) {
                match.selected = true;
            } else {
                const customOption = document.createElement('option');
                customOption.value = currentValue;
                customOption.textContent = currentValue;
                customOption.selected = true;
                customOption.dataset.custom = '1';
                selectElement.appendChild(customOption);
            }
        }
    }

    function applyServiceSelection(selectElement) {
        if (!selectElement) {
            return;
        }

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const valorInput = selectElement.closest('.doc-row')?.querySelector('.valor-servico');
        if (!valorInput) {
            return;
        }

        const rawValor = selectedOption?.dataset?.valorPadrao;
        const parsedValor = rawValor !== undefined ? parseFloat(rawValor) : null;

        if (parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.value = formatCurrency(Math.round(parsedValor * 100));
        }

        const bloqueado = selectedOption?.dataset?.bloqueado === '1';
        if (bloqueado && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else if (clienteCache.tipo === 'Mensalista' && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else {
            valorInput.dataset.minValor = '0';
        }
    }

    function refreshAllServiceSelects() {
        document.querySelectorAll('.servico-select').forEach(select => {
            populateServiceSelect(select, true);
            applyServiceSelection(select);
        });
    }

    // === ADICIONAR DOCUMENTOS ===
    window.adicionarDocumento = function(section, data = null, autoAdded = false) {
        const isMensalista = clienteCache.tipo === 'Mensalista' && section === 'tradução';
        let templateId;
        if (section === 'tradução') {
            templateId = isMensalista ? 'template-tradução-mensalista' : 'template-tradução-avista';
        } else {
            templateId = `template-${section}`;
        }
        const template = document.getElementById(templateId);
        if (!template) { console.error(`Template ${templateId} não encontrado!`); return; }
        const container = document.getElementById(`documentos-container-${section}`);
        const index = sectionCounters[section]++;
        container.insertAdjacentHTML('beforeend', template.innerHTML.replace(/\{index\}/g, index));
        const newRow = container.lastElementChild;
        const select = newRow.querySelector('.servico-select');
        const nomeInput = newRow.querySelector('input[name*="[nome_documento]"]');
        if (select) {
            populateServiceSelect(select);

            if (data && data.nome_categoria) {
                select.value = data.nome_categoria;
                if (!select.value) {
                    const customOption = new Option(data.nome_categoria, data.nome_categoria, true, true);
                    select.add(customOption);
                }
                const valorInput = newRow.querySelector('.valor-servico');
                if (valorInput && data.valor_padrao !== undefined) {
                    const valorPadrao = parseFloat(data.valor_padrao);
                    if (!Number.isNaN(valorPadrao)) {
                        valorInput.value = formatCurrency(Math.round(valorPadrao * 100));
                        valorInput.dataset.minValor = valorPadrao;
                    }
                }
                if (autoAdded) {
                    const removeBtn = newRow.querySelector('.remove-doc-row');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                }
            }

            applyServiceSelection(select);
        }

        if (nomeInput && data && data.nome_documento) {
            nomeInput.value = data.nome_documento;
        }
        updateAllCalculations();
        evaluateValorMinimo();
    };

    // === EVENTOS DINÂMICOS ===
    document.body.addEventListener('change', function(e) {
        if (e.target.matches('.servico-select')) {
            applyServiceSelection(e.target);
            updateAllCalculations();
            evaluateValorMinimo();
        }
    });

    document.body.addEventListener('click', e => {
        const addButton = e.target.closest('.add-doc-row');
        if (addButton) {
            e.preventDefault();
            const section = addButton.dataset.section;
            if (section) {
                adicionarDocumento(section);
            }
            return;
        }

        const target = e.target.closest('.remove-doc-row');
        if (target) {
            target.closest('.doc-row').remove();
            updateAllCalculations();
            evaluateValorMinimo();
        }
    });

    document.body.addEventListener('input', function(e) {
        const target = e.target;
        if (target.matches('.valor-servico')) {
            const raw = target.value.replace(/\D/g, '');
            target.value = (raw === '') ? '' : formatCurrency(raw);
            triggerMinValueAlert(target, minValueAlertMessage);
            updateParceladoRestante();
        } else if (target.matches('.doc-valor')) {
            const raw = target.value.replace(/\D/g, '');
            target.value = (raw === '') ? '' : formatCurrency(raw);
        } else if (target.matches('#apostilamento_valor_unitario, #postagem_valor_unitario')) {
            const raw = target.value.replace(/\D/g, '');
            target.value = (raw === '') ? '' : formatCurrency(raw);
        }
        if (target.matches('.doc-valor, .doc-qtd, .valor-servico, #apostilamento_quantidade, #postagem_quantidade, #apostilamento_valor_unitario, #postagem_valor_unitario')) {
            updateAllCalculations();
            evaluateValorMinimo();
            updateParceladoRestante();
        }
    });

    document.body.addEventListener('blur', function(e) {
        const target = e.target;
        if (target.matches('#apostilamento_valor_unitario, #postagem_valor_unitario, .valor-servico, .doc-valor')) {
            const parsed = parseCurrency(target.value);
            target.value = parsed > 0 ? formatCurrency(Math.round(parsed * 100)) : '';
        }
        if (target.matches('#apostilamento_quantidade, #postagem_quantidade')) {
            const digits = target.value.replace(/\D/g, '');
            target.value = digits;
        }
    }, true);

    // === FUNÇÕES AUXILIARES ===
    function triggerMinValueAlert(input, message) {
        if (!input) {
            return;
        }

        if (isGestor) {
            delete input.dataset.alertBelowMinShown;
            return;
        }

        const minValor = parseFloat(input.dataset.minValor || '0');
        const valorAtual = parseCurrency(input.value);

        if (Number.isNaN(minValor) || Number.isNaN(valorAtual)) {
            delete input.dataset.alertBelowMinShown;
            return;
        }

        if (minValor > 0 && valorAtual < minValor) {
            if (input.dataset.alertBelowMinShown !== 'true') {
                alert(message);
                input.dataset.alertBelowMinShown = 'true';
            }
        } else {
            delete input.dataset.alertBelowMinShown;
        }
    }

    function isSectionVisible(sectionId) {
        const element = document.getElementById(sectionId);
        if (!element) {
            return false;
        }
        return window.getComputedStyle(element).display !== 'none';
    }

    function updateAllCalculations() {
        let totalDocumentos = 0;
        let totalGeralCents = 0;

        const traducaoSectionVisible = isSectionVisible('section-container-tradução');
        const traducaoContainer = document.getElementById('documentos-container-tradução');
        if (traducaoSectionVisible && traducaoContainer) {
            traducaoContainer.querySelectorAll('.doc-row').forEach(row => {
                totalDocumentos += 1;
                const valorUnit = parseCurrency(row.querySelector('.doc-valor')?.value || '0');
                totalGeralCents += Math.round(valorUnit * 100);
            });
        }

        const crcSectionVisible = isSectionVisible('section-container-crc');
        const crcContainer = document.getElementById('documentos-container-crc');
        if (crcSectionVisible && crcContainer) {
            crcContainer.querySelectorAll('.doc-row').forEach(row => {
                totalDocumentos += 1;
                const valorUnit = parseCurrency(row.querySelector('.doc-valor')?.value || '0');
                totalGeralCents += Math.round(valorUnit * 100);
            });
        }

        const outrosSectionVisible = isSectionVisible('section-container-outros');
        const outrosContainer = document.getElementById('documentos-container-outros');
        if (outrosSectionVisible && outrosContainer) {
            outrosContainer.querySelectorAll('.doc-row').forEach(row => {
                totalDocumentos += 1;
                const valorUnit = parseCurrency(row.querySelector('.doc-valor')?.value || '0');
                totalGeralCents += Math.round(valorUnit * 100);
            });
        }

        const apostQtdInput = document.getElementById('apostilamento_quantidade');
        const apostUnitInput = document.getElementById('apostilamento_valor_unitario');
        const apostTotalInput = document.getElementById('apostilamento_valor_total');
        const apostVisible = isSectionVisible('section-container-apostilamento');
        if (apostQtdInput && apostUnitInput && apostTotalInput) {
            const qtd = parseInt(apostQtdInput.value, 10) || 0;
            const valorUnit = parseCurrency(apostUnitInput.value || '0');
            const linhaCents = Math.round(valorUnit * 100) * qtd;
            apostTotalInput.value = formatCurrency(linhaCents);
            if (apostVisible) {
                totalDocumentos += qtd;
                totalGeralCents += linhaCents;
            }
        }

        const postQtdInput = document.getElementById('postagem_quantidade');
        const postUnitInput = document.getElementById('postagem_valor_unitario');
        const postTotalInput = document.getElementById('postagem_valor_total');
        const postVisible = isSectionVisible('section-container-postagem');
        if (postQtdInput && postUnitInput && postTotalInput) {
            const qtd = parseInt(postQtdInput.value, 10) || 0;
            const valorUnit = parseCurrency(postUnitInput.value || '0');
            const linhaCents = Math.round(valorUnit * 100) * qtd;
            postTotalInput.value = formatCurrency(linhaCents);
            if (postVisible) {
                totalDocumentos += qtd;
                totalGeralCents += linhaCents;
            }
        }

        const totalDocsDisplay = document.getElementById('total-documentos-display');
        if (totalDocsDisplay) {
            totalDocsDisplay.textContent = totalDocumentos;
        }

        const formattedTotal = formatCurrency(totalGeralCents);
        const totalGeralDisplay = document.getElementById('total-geral-display');
        if (totalGeralDisplay) {
            totalGeralDisplay.textContent = formattedTotal;
        }
        const totalServicoInput = document.getElementById('valor_total_servico');
        if (totalServicoInput) {
            totalServicoInput.value = formattedTotal;
        }
        const billingTotalDisplay = document.getElementById('billing_total_display');
        if (billingTotalDisplay) {
            billingTotalDisplay.value = formattedTotal;
        }

        const hiddenTotal = document.getElementById('valor_total_hidden');
        if (hiddenTotal) {
            hiddenTotal.value = (totalGeralCents / 100).toFixed(2);
        }

        updateParceladoRestante();
    }

    function updateParceladoRestante() {
        const restanteInput = document.getElementById('billing_parcelado_restante');
        if (!restanteInput) {
            return;
        }
        const entradaInput = document.getElementById('billing_parcelado_entrada');
        const hiddenTotal = document.getElementById('valor_total_hidden');
        if (!entradaInput || !hiddenTotal) {
            restanteInput.value = 'R$ 0,00';
            return;
        }
        const total = parseFloat(hiddenTotal.value || '0');
        const entrada = parseCurrency(entradaInput.value || '0');
        const restante = Math.max((Number.isNaN(total) ? 0 : total) - entrada, 0);
        restanteInput.value = formatCurrency(Math.round(restante * 100));
    }

    function toggleBillingSections() {
        if (!billingSections || billingSections.length === 0) {
            return;
        }
        const selectedType = billingTypeSelect ? billingTypeSelect.value : '';
        billingSections.forEach(section => {
            const isActive = section.dataset.billingSection === selectedType;
            section.classList.toggle('hidden', !isActive);
            section.querySelectorAll('[data-field-name]').forEach(input => {
                if (isActive) {
                    input.name = input.dataset.fieldName;
                    input.disabled = false;
                } else {
                    input.removeAttribute('name');
                    input.disabled = true;
                    if (input.type === 'file') {
                        input.value = '';
                        updateFileTileDisplay(input);
                    }
                }
            });
        });
        updateParceladoRestante();
    }

    function toggleServiceSection(checkbox) {
        const sectionId = `section-container-${checkbox.value.toLowerCase().replace(/\s+/g, '')}`;
        const section = document.getElementById(sectionId);

        if (section) {
            section.style.display = checkbox.checked ? 'block' : 'none';
        }

        updateAllCalculations();
        evaluateValorMinimo();
    }

    function evaluateValorMinimo() {
        const statusInput = document.getElementById('status_proposto');
        if (!statusInput) {
            return;
        }

        if (isGestor) {
            statusInput.value = 'Serviço em Andamento';
            return;
        }

        let pendente = false;
        document.querySelectorAll('.valor-servico').forEach(input => {
            const min = parseFloat(input.dataset.minValor || '0');
            const atual = parseCurrency(input.value);
            if (min > 0 && atual < min) {
                pendente = true;
            }
        });

        statusInput.value = pendente ? 'Serviço Pendente' : 'Serviço em Andamento';
    }

    // Este bloco agora funcionará, pois 'servicosCheckboxes' foi definido no início
    servicosCheckboxes.forEach(cb => {
        toggleServiceSection(cb); // Verifica o estado inicial
        cb.addEventListener('change', () => toggleServiceSection(cb)); // Adiciona o evento de clique
    });

    if (billingTypeSelect) {
        toggleBillingSections();
        billingTypeSelect.addEventListener('change', () => toggleBillingSections());
    }

    function formatCurrency(valueInCents) {
        if (isNaN(valueInCents)) return '';
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valueInCents / 100);
    }
    function parseCurrency(formattedValue) {
        if (!formattedValue || typeof formattedValue !== 'string') return 0;
        return parseFloat(String(formattedValue).replace(/\D/g, '')) / 100 || 0;
    }

    // Gatilho inicial de cálculos e status
    updateAllCalculations();
    evaluateValorMinimo();

    const togglePrazoFields = () => {
        const diasContainer = document.getElementById('prazo_dias_container');
        const dataContainer = document.getElementById('prazo_data_container');

        if (!diasContainer || !dataContainer) {
            return;
        }

        if (prazoTipoSelect && prazoTipoSelect.value === 'data') {
            diasContainer.classList.add('hidden');
            dataContainer.classList.remove('hidden');
            if (prazoDiasInput) {
                prazoDiasInput.setAttribute('disabled', 'disabled');
            }
            if (prazoDataInput) {
                prazoDataInput.removeAttribute('disabled');
            }
            return;
        }

        dataContainer.classList.add('hidden');
        diasContainer.classList.remove('hidden');
        if (prazoDataInput) {
            prazoDataInput.setAttribute('disabled', 'disabled');
        }
        if (prazoDiasInput) {
            prazoDiasInput.removeAttribute('disabled');
            prazoDiasInput.value = currentDeadlineDays !== null ? currentDeadlineDays : '';
        }
    };

    if (prazoTipoSelect) {
        prazoTipoSelect.addEventListener('change', togglePrazoFields);
        togglePrazoFields();
    }

    if (prazoDiasInput) {
        prazoDiasInput.addEventListener('input', () => {
            const parsedValue = parseInt(prazoDiasInput.value, 10);
            currentDeadlineDays = Number.isNaN(parsedValue) ? null : parsedValue;
        });
    }
    
    updateAllCalculations(); // Execução inicial
});
</script>