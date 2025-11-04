<?php
// Lógica para obter o ID do vendedor correspondente ao usuário logado
$loggedInVendedorId = null;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'vendedor' && isset($_SESSION['user_id'])) {
    // Busca o ID do vendedor na tabela 'vendedores' usando o 'user_id' da sessão
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendedor_logado = $stmt->fetch();
    if ($vendedor_logado) {
        $loggedInVendedorId = $vendedor_logado['id'];
    }
}

// Verifica as condições da página (se veio de prospecção, se é vendedor, etc.)
$isVendedor = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'vendedor');
$fromProspeccao = isset($_GET['prospeccao_id']);
$cliente_id_prospeccao = $_GET['cliente_id'] ?? null;

// Define qual vendedor deve ser selecionado:
// 1. O vendedor da prospecção (se existir)
// 2. O vendedor logado (se não vier da prospecção)
$vendedor_id_selecionado = $_GET['vendedor_id'] ?? $loggedInVendedorId;

// Desabilita os campos de cliente e vendedor se for um vendedor convertendo uma prospecção
$disableFields = $isVendedor && $fromProspeccao;
?>

<h1 class="text-3xl font-bold mb-4 pb-4 border-b border-gray-200">Cadastrar Nova Venda</h1>

<form action="vendas.php?action=cadastrar" method="POST" class="bg-white p-6 rounded-lg shadow-lg border border-gray-200">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div>
            <label for="vendedor_id" class="block text-sm font-semibold text-gray-700">Vendedor *</label>
            <select name="vendedor_id" id="vendedor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required <?php if ($disableFields) echo 'disabled'; ?>>
                <option value="">Selecione um vendedor</option>
                <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?php echo $vendedor['id']; ?>" <?php if ($vendedor_id_selecionado == $vendedor['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($vendedor['nome_vendedor']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="cliente_id" class="block text-sm font-semibold text-gray-700">Cliente *</label>
            <select name="cliente_id" id="cliente_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required <?php if ($disableFields) echo 'disabled'; ?>>
                <option value="">Selecione um cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['id']; ?>" <?php if ($cliente_id_prospeccao == $cliente['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="valor_total" class="block text-sm font-semibold text-gray-700">Valor Total (R$) *</label>
            <input type="text" name="valor_total" id="valor_total" data-currency-input class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" placeholder="R$ 0,00" required>
        </div>

        <div>
            <label for="data_venda" class="block text-sm font-semibold text-gray-700">Data da Venda *</label>
            <input type="datetime-local" name="data_venda" id="data_venda" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
        </div>

        <div class="md:col-span-2">
            <label for="descricao" class="block text-sm font-semibold text-gray-700">Descrição</label>
            <textarea name="descricao" id="descricao" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" placeholder="Detalhes do produto ou serviço vendido..."></textarea>
        </div>

        <div>
            <label for="status_venda" class="block text-sm font-semibold text-gray-700">Status da Venda</label>
            <select name="status_venda" id="status_venda" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                <option value="Pendente" selected>Pendente</option>
                <option value="Concluída">Concluída</option>
                <option value="Cancelada">Cancelada</option>
            </select>
        </div>
    </div>

    <?php if ($disableFields): ?>
        <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($cliente_id_prospeccao); ?>">
        <input type="hidden" name="vendedor_id" value="<?php echo htmlspecialchars($vendedor_id_selecionado); ?>">
    <?php endif; ?>

    <div class="flex items-center justify-end mt-6 pt-5 border-t border-gray-200">
        <a href="vendas.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md mr-3">Cancelar</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Salvar Venda</button>
    </div>
</form>