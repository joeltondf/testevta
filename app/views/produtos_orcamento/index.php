<?php
// app/views/produtos_orcamento/index.php
$pageTitle = 'Produtos de Orçamento';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Produtos/Documentos</h1>
        <a href="fluxo_caixa.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Voltar ao Fluxo de Caixa
        </a>
    </div>

    <?php include_once __DIR__ . '/../partials/messages.php'; ?>

    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 mb-6">
        <h3 id="form-title" class="text-xl font-bold text-gray-800 mb-5 border-b-2 border-gray-100 pb-4">Adicionar / Editar Produto</h3>
        <form id="produto-form" action="produtos_orcamento.php?action=store" method="POST">
            <input type="hidden" name="id" id="produto_id">
            <input type="hidden" name="codigo_integracao" id="codigo_integracao" value="">
            <input type="hidden" name="codigo" id="codigo" value="">
            <input type="hidden" name="unidade" id="unidade" value="UN">
            <input type="hidden" name="ncm" id="ncm" value="0000.00.00">
            <input type="hidden" name="cfop" id="cfop" value="">
            <input type="hidden" name="codigo_servico_municipal" id="codigo_servico_municipal" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div class="md:col-span-2">
                    <label for="nome_categoria" class="block text-sm font-medium text-gray-700 mb-1">Nome do Produto</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-pencil-alt text-gray-400"></i>
                        </div>
                        <input type="text" name="nome_categoria" id="nome_categoria" required class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 py-2.5" placeholder="Ex: Tradução Juramentada de Passaporte">
                    </div>
                </div>

                <div>
                    <label for="valor_padrao" class="block text-sm font-medium text-gray-700 mb-1">Valor do Produto (R$)</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-dollar-sign text-gray-400"></i>
                        </div>
                        <input type="text" name="valor_padrao" id="valor_padrao" data-currency-input required class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 py-2.5" placeholder="Ex: R$ 150,00">
                    </div>
                </div>

                <div>
                    <label for="servico_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Serviço Vinculado</label>
                    <select name="servico_tipo" id="servico_tipo" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Nenhum">Não vincular a um serviço específico</option>
                        <option value="Tradução">Tradução</option>
                        <option value="CRC">CRC</option>
                        <option value="Apostilamento">Apostilamento</option>
                        <option value="Postagem">Postagem</option>
                        <option value="Outros">Outros</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">A seleção determina em quais formulários o produto estará disponível.</p>
                </div>

                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-700 mb-2">Status do Produto</span>
                    <div class="flex items-center space-x-6">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="ativo" value="1" id="status-ativo" class="h-4 w-4 text-blue-600 border-gray-300" checked>
                            <span class="ml-2 text-sm text-gray-700">Ativo</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="ativo" value="0" id="status-inativo" class="h-4 w-4 text-blue-600 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Inativo</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Os códigos de integração, unidade (<strong>UN</strong>) e NCM (<strong>0000.00.00</strong>) são definidos automaticamente e enviados para a Omie junto com o cadastro.
                </p>
            </div>

            <div class="flex items-center mt-4">
                <label class="flex items-center">
                    <input type="checkbox" name="bloquear_valor_minimo" value="1" id="bloquear_valor_minimo" class="h-4 w-4 text-indigo-600 ring-gray-400 rounded">
                    <span class="ml-2 text-sm text-gray-700">Exigir este valor como mínimo nos formulários</span>
                </label>
            </div>

            <div class="flex items-center mt-4">
                <label class="flex items-center">
                    <input type="checkbox" name="sincronizar_omie" value="1" id="sincronizar_omie" class="h-4 w-4 text-indigo-600 ring-gray-400 rounded">
                    <span class="ml-2 text-sm text-gray-700">Sincronizar com a Omie após salvar</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-6 border-t pt-5 space-x-3">
                <button type="button" onclick="resetForm()" class="text-sm font-medium text-gray-600 px-5 py-2.5 rounded-lg border ring-gray-400 hover:bg-gray-100">
                    Cancelar
                </button>
                <button type="submit" id="submit-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition-transform transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>
                    Salvar Produto
                </button>
            </div>
        </form>
    </div>
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo de Serviço</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Valor Padrão</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Valor Mínimo?</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Código Omie</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Código Integração</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($produto['nome_categoria']) ?></td>
                    <?php $tipoServico = $produto['servico_tipo'] ?? 'Nenhum'; ?>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                        <?= $tipoServico === 'Nenhum' ? '—' : htmlspecialchars($tipoServico) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">R$ <?= number_format($produto['valor_padrao'] ?? 0, 2, ',', '.') ?></td>
                    <?php $exigeMinimo = !empty($produto['bloquear_valor_minimo']); ?>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $exigeMinimo ? 'Sim' : 'Não' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= htmlspecialchars($produto['omie_codigo_produto'] ?? '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= htmlspecialchars($produto['omie_codigo_integracao'] ?? '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $produto['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                        <button onclick='editProduto(<?= json_encode($produto, JSON_HEX_APOS) ?>)' class="text-indigo-600 hover:text-indigo-900">Editar</button>
                        <a href="produtos_orcamento.php?action=delete&id=<?= $produto['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Tem certeza que deseja excluir este produto?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function resolveProdutoId(produto) {
    if (!produto || typeof produto !== 'object') {
        return null;
    }

    const possibleId = produto.id ?? produto.ID ?? produto.local_produto_id ?? produto.cf_id ?? produto['cf.id'] ?? null;
    if (possibleId === null || possibleId === undefined) {
        return null;
    }

    const parsed = parseInt(possibleId, 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function setProdutoStatus(value) {
    const ativoInput = document.getElementById('status-ativo');
    const inativoInput = document.getElementById('status-inativo');

    if (!ativoInput || !inativoInput) {
        return;
    }

    const normalizedValue = Number(value) === 1 ? 1 : 0;
    ativoInput.checked = normalizedValue === 1;
    inativoInput.checked = normalizedValue === 0;
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

function editProduto(produto) {
    const form = document.getElementById('produto-form');
    const title = document.getElementById('form-title');
    const submitButton = document.getElementById('submit-button');

    const produtoId = resolveProdutoId(produto);
    if (!produtoId) {
        alert('Não foi possível identificar o produto selecionado para edição.');
        return;
    }

    if (title) title.innerText = 'Editar Produto';
    form.action = `produtos_orcamento.php?action=update&id=${produtoId}`;
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Atualizar Produto';

    document.getElementById('produto_id').value = produtoId;
    document.getElementById('nome_categoria').value = produto.nome_categoria || '';
    const valorPadraoInput = document.getElementById('valor_padrao');
    const valorPadrao = produto.valor_padrao ?? produto.omie_valor_unitario ?? '';
    setCurrencyValue(valorPadraoInput, valorPadrao);
    document.getElementById('servico_tipo').value = produto.servico_tipo || 'Nenhum';
    document.getElementById('bloquear_valor_minimo').checked = Number(produto.bloquear_valor_minimo || 0) === 1;
    setProdutoStatus(produto.ativo ?? 1);
    document.getElementById('codigo_integracao').value = produto.omie_codigo_integracao || '';
    document.getElementById('codigo').value = produto.omie_codigo || '';
    document.getElementById('ncm').value = produto.omie_ncm || '0000.00.00';
    document.getElementById('unidade').value = produto.omie_unidade || 'UN';
    document.getElementById('cfop').value = produto.omie_cfop || '';
    document.getElementById('codigo_servico_municipal').value = produto.omie_codigo_servico_municipal || '';
    document.getElementById('sincronizar_omie').checked = false;

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    const form = document.getElementById('produto-form');
    const title = document.getElementById('form-title');
    const submitButton = document.getElementById('submit-button');

    if (title) title.innerText = 'Adicionar / Editar Produto';
    form.action = 'produtos_orcamento.php?action=store';
    form.reset();
    document.getElementById('produto_id').value = '';
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Salvar Produto';
    document.getElementById('servico_tipo').value = 'Nenhum';
    document.getElementById('bloquear_valor_minimo').checked = false;
    setProdutoStatus(1);
    document.getElementById('codigo_integracao').value = '';
    document.getElementById('codigo').value = '';
    document.getElementById('ncm').value = '0000.00.00';
    document.getElementById('unidade').value = 'UN';
    document.getElementById('cfop').value = '';
    document.getElementById('codigo_servico_municipal').value = '';
    document.getElementById('sincronizar_omie').checked = false;
    const valorPadraoInput = document.getElementById('valor_padrao');
    setCurrencyValue(valorPadraoInput, '');
}
</script>
