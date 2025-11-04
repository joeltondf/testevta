    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gerenciar Grupos Financeiros</h1>

        <a href="fluxo_caixa.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Voltar ao Fluxo de Caixa
        </a>
    </div>

    

        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 mb-6">
        <h3 class="text-xl font-bold text-gray-800 mb-5 border-b-2 border-gray-100 pb-4">Adicionar / Editar Grupo</h3>
        <form action="categorias.php?action=save" method="POST" id="category-form">
            <input type="hidden" name="id" id="cat-id">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-5">
    
                <div class="lg:col-span-2">
                    <label for="cat-grupo" class="block text-sm font-medium text-gray-700 mb-1">Grupo Principal</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-layer-group text-gray-400"></i>
                        </div>
                        <input type="text" name="grupo_principal" id="cat-grupo" list="grupos-list" required autocomplete="off" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5" placeholder="Digite ou selecione um grupo">
                        <datalist id="grupos-list">
                            <?php foreach ($grupos_principais as $grupo): ?>
                                <option value="<?php echo htmlspecialchars($grupo); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label for="cat-nome" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-pencil-alt text-gray-400"></i>
                        </div>
                        <input type="text" name="nome_categoria" id="cat-nome" required autocomplete="off" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5" placeholder="Ex: Aluguel da Sala">
                    </div>
                </div>
    
                <div class="">
                    <label for="cat-tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-exchange-alt text-gray-400"></i>
                        </div>
                        <select name="tipo_lancamento" id="cat-tipo" required onchange="updateColor(this)" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5 font-semibold">
                            <option value="DESPESA" data-color="red">Despesa (Subtrai)</option>
                            <option value="RECEITA" data-color="green">Receita (Agrega)</option>
                        </select>
                    </div>
                </div>
    
                <div class="">
                    <label for="cat-ativo" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                     <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-power-off text-gray-400"></i>
                        </div>
                        <select name="ativo" id="cat-ativo" required onchange="updateColor(this)" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5 font-semibold">
                            <option value="1" data-color="green">Ativo</option>
                            <option value="0" data-color="red">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
                <div id="receita-fields" class="hidden md:col-span-4 lg:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-5 mt-4 pt-4 border-t">
                    <div>
                        <label for="cat-valor" class="block text-sm font-medium text-gray-700 mb-1">Valor Padrão (R$)</label>
                        <input type="text" name="valor_padrao" id="cat-valor" data-currency-input class="block w-full rounded-lg border-gray-300 shadow-sm text-sm py-2.5" placeholder="R$ 0,00">
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="bloquear_valor_minimo" value="1" id="cat-bloquear" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Usar como valor mínimo</span>
                        </label>
                    </div>
                </div>    
            <div class="flex items-center justify-end mt-6 border-t pt-5 space-x-3">
                 <button type="button" onclick="resetForm()" class="text-sm font-medium text-gray-600 px-5 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100">
                    Cancelar
                 </button>
                 <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition-transform transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>
                    Salvar Grupo
                </button>
            </div>
        </form>
    </div>


<div class="bg-white shadow-md rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        </table>
</div>

    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form action="categorias.php" method="GET" class="flex items-center">
            <input type="checkbox" id="show_inactive" name="show_inactive" value="1" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" onchange="this.form.submit()" <?php if ($show_inactive) echo 'checked'; ?>>
            <label for="show_inactive" class="ml-2 block text-sm font-medium text-gray-700">
                Mostrar categorias inativas
            </label>
        </form>
    </div>

<div class="bg-white shadow-md rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-3 text-left">Descrição</th>
                <th class="px-6 py-3 text-left">Tipo</th>
                <th class="px-6 py-3 text-left">Status</th>
                <th class="px-6 py-3 text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Agrupa as categorias pelo grupo principal
            $grouped_categorias = [];
            foreach($categorias as $cat) {
                $grouped_categorias[$cat['grupo_principal']][] = $cat;
            }
            ksort($grouped_categorias); // Ordena os grupos em ordem alfabética
            ?>

            <?php foreach($grouped_categorias as $grupo => $items): ?>
                <tr class="bg-gray-200 border-b-2 border-gray-300">
                    <td colspan="4" class="px-4 py-2 text-sm font-bold text-gray-800">
                        <div class="flex justify-between items-center">
                            <span><?php echo htmlspecialchars($grupo); ?></span>
                            <div>
                                <button type="button" onclick="renameGroup('<?php echo htmlspecialchars($grupo, ENT_QUOTES); ?>')" class="text-xs text-blue-600 hover:text-blue-900 font-normal">Renomear Grupo</button>
                                <span class="mx-1 text-gray-400">|</span>
                                <button type="button" onclick="deleteGroup('<?php echo htmlspecialchars($grupo, ENT_QUOTES); ?>')" class="text-xs text-red-600 hover:text-red-900 font-normal">Excluir Grupo</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php foreach($items as $cat): ?>
                    <tr class="<?php echo $cat['ativo'] ? '' : 'bg-gray-100 text-gray-500'; ?>">
                        <td class="px-6 py-3 pl-8"><?php echo htmlspecialchars($cat['nome_categoria']); ?></td>
                        <td class="px-6 py-3 font-semibold <?php echo $cat['tipo_lancamento'] == 'RECEITA' ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $cat['tipo_lancamento']; ?></td>
                        <td class="px-6 py-3"><?php echo $cat['ativo'] ? '<span class="px-2 py-1 font-semibold leading-tight text-green-700 bg-green-100 rounded-full">Ativo</span>' : '<span class="px-2 py-1 font-semibold leading-tight text-red-700 bg-red-100 rounded-full">Inativo</span>'; ?></td>
                        <td class="px-6 py-4 text-center text-sm space-x-2">
                            <button type="button" onclick='editCategory(<?php echo json_encode($cat, JSON_HEX_APOS); ?>)' class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</button>

                            <?php if ($cat['ativo']): ?>
                                <a href="categorias.php?action=deactivate&id=<?php echo $cat['id']; ?>" onclick="return confirm('Tem certeza que deseja desativar esta categoria?')" class="text-yellow-600 hover:text-yellow-900 font-medium">Desativar</a>
                            <?php else: ?>
                                <a href="categorias.php?action=reactivate&id=<?php echo $cat['id']; ?>" class="text-green-600 hover:text-green-900 font-medium">Ativar</a>
                            <?php endif; ?>

                            <a href="categorias.php?action=delete_permanente&id=<?php echo $cat['id']; ?>" 
                            onclick="return confirm('ATENÇÃO! Esta ação é IRREVERSÍVEL e apagará a categoria permanentemente. Deseja continuar?')" 
                            class="text-red-600 hover:text-red-900 font-medium">
                            Excluir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<form id="group-action-form" method="POST" class="hidden" aria-hidden="true"></form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoLancamentoSelect = document.getElementById('cat-tipo');
    const groupActionForm = document.getElementById('group-action-form');

    // --- FUNÇÕES PRINCIPAIS ---

    window.editCategory = function(category) {
        const form = document.getElementById('category-form');
        const title = document.getElementById('form-title'); // Este ID não existe no seu HTML, vamos usar outro
        const h3Title = form.querySelector('h3'); // Usando a tag h3 como alvo
        const submitButton = form.querySelector('button[type="submit"]');

        if (h3Title) h3Title.textContent = 'Editar Grupo/Categoria';
        form.action = 'categorias.php?action=save';

        document.getElementById('cat-id').value = category.id;
        document.getElementById('cat-nome').value = category.nome_categoria;
        document.getElementById('cat-grupo').value = category.grupo_principal;
        document.getElementById('cat-tipo').value = category.tipo_lancamento;
        document.getElementById('cat-ativo').value = category.ativo;

        toggleReceitaFields();

        const valorPadraoInput = document.getElementById('cat-valor');
        if (category.tipo_lancamento === 'RECEITA') {
            if (valorPadraoInput) {
                valorPadraoInput.value = category.valor_padrao ?? '';
                valorPadraoInput.dispatchEvent(new Event('currency:refresh'));
            }
            document.getElementById('cat-bloquear').checked = category.bloquear_valor_minimo == 1;
        } else if (valorPadraoInput) {
            valorPadraoInput.value = '';
            valorPadraoInput.dispatchEvent(new Event('currency:refresh'));
        }

        submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Atualizar Grupo';
        submitButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        submitButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');

        setInitialColors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    window.resetForm = function() {
        const form = document.getElementById('category-form');
        const h3Title = form.querySelector('h3');
        const submitButton = form.querySelector('button[type="submit"]');

        if (h3Title) h3Title.textContent = 'Adicionar / Editar Grupo';
        form.action = 'categorias.php?action=save';

        form.reset();
        document.getElementById('cat-id').value = '';

        submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Salvar Grupo';
        submitButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700');

        toggleReceitaFields();
        setInitialColors();
        const valorPadraoInput = document.getElementById('cat-valor');
        if (valorPadraoInput) {
            valorPadraoInput.value = '';
            valorPadraoInput.dispatchEvent(new Event('currency:refresh'));
        }
    }

    // --- FUNÇÕES AUXILIARES ---

    window.renameGroup = function(groupName) {
        const newName = prompt('Digite o novo nome para o grupo:', groupName);
        if (newName === null) {
            return;
        }

        const trimmedName = newName.trim();
        if (trimmedName === '' || trimmedName === groupName) {
            alert('Informe um nome diferente para renomear o grupo.');
            return;
        }

        submitGroupAction('rename_group', {
            old_name: groupName,
            new_name: trimmedName
        });
    };

    window.deleteGroup = function(groupName) {
        const confirmed = confirm(`Tem certeza que deseja excluir o grupo "${groupName}" e todas as categorias associadas?`);
        if (!confirmed) {
            return;
        }

        submitGroupAction('delete_group', {
            group_name: groupName
        });
    };

    function submitGroupAction(action, fields) {
        if (!groupActionForm) {
            return;
        }

        groupActionForm.innerHTML = '';

        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            groupActionForm.appendChild(input);
        });

        groupActionForm.action = `categorias.php?action=${action}`;
        groupActionForm.submit();
    }

    function updateColor(selectElement) {
        if (!selectElement) return;
        selectElement.classList.remove('text-green-700', 'bg-green-50', 'text-red-700', 'bg-red-50');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (selectedOption) {
            const color = selectedOption.getAttribute('data-color');
            if (color === 'green') {
                selectElement.classList.add('text-green-700', 'bg-green-50');
            } else if (color === 'red') {
                selectElement.classList.add('text-red-700', 'bg-red-50');
            }
        }
    }

    function setInitialColors() {
        updateColor(document.getElementById('cat-tipo'));
        updateColor(document.getElementById('cat-ativo'));
    }

    function toggleReceitaFields() {
        const receitaFieldsContainer = document.getElementById('receita-fields');
        if (tipoLancamentoSelect && receitaFieldsContainer) {
            receitaFieldsContainer.classList.toggle('hidden', tipoLancamentoSelect.value !== 'RECEITA');
        }
    }

    // --- CÓDIGO INICIAL ---
    setInitialColors();
    toggleReceitaFields(); // Garante o estado inicial correto
    if (tipoLancamentoSelect) {
        tipoLancamentoSelect.addEventListener('change', () => {
            toggleReceitaFields();
            updateColor(tipoLancamentoSelect);
        });
    }
});
</script>