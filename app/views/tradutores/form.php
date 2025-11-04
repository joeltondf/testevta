<?php 
// /app/views/tradutores/form.php 
$isEdit = isset($tradutor) && $tradutor;
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEdit ? 'Editar Tradutor' : 'Cadastrar Novo Tradutor'; ?></h1>
    <a href="tradutores.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
        Voltar à Lista
    </a>
</div>

<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl mx-auto">
    <form action="tradutores.php?action=<?php echo $isEdit ? 'update&id=' . $tradutor['id'] : 'store'; ?>" method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="md:col-span-2">
                <label for="nome_tradutor" class="block text-sm font-semibold text-gray-700">Nome do Tradutor *</label>
                <input type="text" id="nome_tradutor" name="nome_tradutor" value="<?php echo htmlspecialchars($tradutor['nome_tradutor'] ?? ''); ?>" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($tradutor['email'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="telefone" class="block text-sm font-semibold text-gray-700">Telefone</label>
                <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($tradutor['telefone'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="md:col-span-2">
                <label for="especialidade_idioma" class="block text-sm font-semibold text-gray-700">Especialidade / Idioma</label>
                <input type="text" id="especialidade_idioma" name="especialidade_idioma" value="<?php echo htmlspecialchars($tradutor['especialidade_idioma'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="md:col-span-2">
                 <label class="inline-flex items-center">
                    <input type="checkbox" name="ativo" value="1" class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" <?php echo (!isset($tradutor) || $tradutor['ativo']) ? 'checked' : ''; ?>>
                    <span class="ml-3 text-sm font-semibold text-gray-700">Tradutor Ativo</span>
                </label>
            </div>
            
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                <?php echo $isEdit ? 'Atualizar Tradutor' : 'Salvar Tradutor'; ?>
            </button>
        </div>
    </form>
</div>
<script>
// Função de máscara de telefone compatível com fixo e celular
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

// Aplica máscara ao campo de telefone durante a digitação e colagem
document.addEventListener('DOMContentLoaded', function () {
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        if (telefoneInput.value) {
            telefoneInput.value = aplicarMascaraTelefone(telefoneInput.value);
        }
        telefoneInput.addEventListener('input', function (e) {
            e.target.value = aplicarMascaraTelefone(e.target.value);
        });
        telefoneInput.addEventListener('paste', function (e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraTelefone(e.target.value);
            }, 100);
        });
    }
});
</script>
