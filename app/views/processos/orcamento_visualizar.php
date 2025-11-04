<?php
/**
 * @file /app/views/processos/orcamento_visualizar.php
 * @description Template para visualização e impressão de orçamentos.
 *
 * @var array $process O processo (orçamento).
 * @var array $documents Os documentos/itens do orçamento.
 * @var array|null $client O cliente.
 * @var array|null $user O usuário responsável.
 * @var string|null $system_logo O caminho para o logo do sistema.
 * @var bool $fullPage Define se deve renderizar a página HTML completa.
 * @var bool $showPrintButton Define se o botão de impressão deve ser exibido.
 */

// Helpers para formatação
$parseMoney = function ($value): float {
    if (is_numeric($value)) {
        return (float)$value;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0.0;
        }

        if (strpos($trimmed, ',') !== false) {
            $normalized = str_replace(['.', ','], ['', '.'], $trimmed);
            return (float)$normalized;
        }

        return (float)$trimmed;
    }

    return 0.0;
};

$formatCurrency = fn($value) => 'R$ ' . number_format($parseMoney($value), 2, ',', '.');
$formatDate = fn($date) => $date ? date('d/m/Y', strtotime($date)) : 'N/A';

$budgetItems = [];
$documentsList = $documents ?? [];

if (!empty($documentsList)) {
    foreach ($documentsList as $document) {
        $quantity = (int)($document['quantidade'] ?? 1);
        $quantity = $quantity > 0 ? $quantity : 1;
        $unitValue = $parseMoney($document['valor_unitario'] ?? 0);
        $subtotal = $unitValue * $quantity;

        $categoryLabel = $document['categoria'] ?? null;
        $title = $document['tipo_documento'] ?? 'Item';
        if (!empty($categoryLabel) && stripos($title, (string)$categoryLabel) === false) {
            $title = $categoryLabel . ' — ' . $title;
        }

        $budgetItems[] = [
            'title' => $title,
            'description' => $document['nome_documento'] ?? null,
            'quantity' => $quantity,
            'unitValue' => $unitValue,
            'subtotal' => $subtotal,
        ];
    }
}

$apostilleQuantity = (int)($process['apostilamento_quantidade'] ?? 0);
$apostilleUnit = $parseMoney($process['apostilamento_valor_unitario'] ?? 0);
if ($apostilleQuantity > 0 && $apostilleUnit > 0) {
    $budgetItems[] = [
        'title' => 'Apostilamento',
        'description' => null,
        'quantity' => $apostilleQuantity,
        'unitValue' => $apostilleUnit,
        'subtotal' => $apostilleQuantity * $apostilleUnit,
    ];
}

$shippingQuantity = (int)($process['postagem_quantidade'] ?? 0);
$shippingUnit = $parseMoney($process['postagem_valor_unitario'] ?? 0);
if ($shippingQuantity > 0 && $shippingUnit > 0) {
    $budgetItems[] = [
        'title' => 'Envio / Postagem',
        'description' => null,
        'quantity' => $shippingQuantity,
        'unitValue' => $shippingUnit,
        'subtotal' => $shippingQuantity * $shippingUnit,
    ];
}

$total = array_reduce(
    $budgetItems,
    static fn($carry, $item) => $carry + ($item['subtotal'] ?? 0),
    0.0
);

if ($total <= 0) {
    $total = $parseMoney($process['valor_total'] ?? 0);
}

if (empty($budgetItems) && $total > 0) {
    $budgetItems[] = [
        'title' => $process['titulo'] ?? 'Serviço',
        'description' => null,
        'quantity' => 1,
        'unitValue' => $total,
        'subtotal' => $total,
    ];
}

$logo_url = null;
if (!empty($system_logo)) {
    // Garante que o caminho do logo seja uma URL absoluta para e-mails e visualização
    $logo_url = APP_URL . '/' . ltrim($system_logo, '/');
}

?>

<?php if ($fullPage): ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?php echo htmlspecialchars($process['orcamento_numero'] ?? ''); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: #fff;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .print-p-0 {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 print-p-0">
<?php endif; ?>

<div class="max-w-4xl mx-auto my-8 p-8 bg-white rounded-lg shadow-lg border border-gray-200 print-container">

    <!-- Cabeçalho -->
    <header class="flex justify-between items-center pb-6 border-b-2 border-gray-200">
        <div>
            <?php if ($logo_url): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo da Empresa" class="h-16 w-auto">
            <?php else: ?>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-bold text-gray-700">ORÇAMENTO</h2>
            <p class="text-gray-500">#<?php echo htmlspecialchars($process['orcamento_numero'] ?? ''); ?></p>
            <p class="text-gray-500">Data: <?php echo $formatDate($process['data_criacao'] ?? date('Y-m-d')); ?></p>
        </div>
    </header>

    <!-- Informações do Cliente e Empresa -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-8 my-6">
        <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">DE</h3>
            <p class="font-bold text-gray-800"><?php echo htmlspecialchars(APP_NAME); ?></p>
            <?php if ($user && !empty($user['email'])): ?>
                <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
            <?php endif; ?>
        </div>
        <div class="md:text-right">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">PARA</h3>
            <?php if ($client): ?>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars(mb_strtoupper($client['nome_cliente'] ?? 'N/A')); ?></p>
                <p class="text-gray-600"><?php echo htmlspecialchars($client['email'] ?? 'E-mail não informado'); ?></p>
                <p class="text-gray-600"><?php echo htmlspecialchars($client['telefone'] ?? 'Telefone não informado'); ?></p>
            <?php else: ?>
                <p class="font-bold text-gray-800">Cliente não informado</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Tabela de Itens -->
    <section class="mb-8">
        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Detalhes do Serviço</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-sm font-semibold text-gray-600 uppercase">Serviço / Documento</th>
                        <th class="px-4 py-2 text-center text-sm font-semibold text-gray-600 uppercase">Qtd.</th>
                        <th class="px-4 py-2 text-right text-sm font-semibold text-gray-600 uppercase">Valor Unit.</th>
                        <th class="px-4 py-2 text-right text-sm font-semibold text-gray-600 uppercase">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($budgetItems)): ?>
                        <?php foreach ($budgetItems as $item): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($item['title']); ?></p>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-4 py-3 text-right text-gray-600"><?php echo $formatCurrency($item['unitValue']); ?></td>
                                <td class="px-4 py-3 text-right text-gray-800 font-medium"><?php echo $formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="px-4 py-3 text-center text-gray-500" colspan="4">Nenhum item de orçamento informado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Total e Pagamento -->
    <section class="flex justify-end mb-8">
        <div class="w-full md:w-1/2 lg:w-1/3">
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-600">Subtotal</span>
                <span class="text-gray-800"><?php echo $formatCurrency($total); ?></span>
            </div>
            <div class="flex justify-between py-2 bg-gray-100 px-4 rounded-md mt-2">
                <span class="font-bold text-lg text-gray-900">TOTAL</span>
                <span class="font-bold text-lg text-gray-900"><?php echo $formatCurrency($total); ?></span>
            </div>
        </div>
    </section>

    <!-- Observações e Rodapé -->
    <footer class="pt-6 border-t-2 border-gray-200 text-sm">
        <?php if (!empty($process['observacoes'])): ?>
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-2 text-base">Observações:</h4>
                <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($process['observacoes']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Termos e Condições -->
        <div class="my-8">
            <h4 class="font-bold text-gray-800 mb-3 text-base">Termos e Condições</h4>
            <div class="space-y-4 text-gray-700">
                <div>
                    <h5 class="font-semibold">1. Formas de Pagamento:</h5>
                    <ul class="list-disc list-inside pl-4 mt-1 space-y-1">
                        <li>50% de entrada para iniciar o serviço e 50% na entrega final da Tradução Juramentada.</li>
                        <li>Aceitamos pagamentos via PIX, transferência bancária ou outras opções.</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold">2. Observações Importantes:</h5>
                    <ul class="list-disc list-inside pl-4 mt-1 space-y-1">
                        <li>Para a precificação de documentos técnicos como procurações, processos de divórcio e histórico escolar, é essencial que os documentos sejam enviados de forma digitalizada para uma avaliação precisa.</li>
                        <li>O prazo de entrega de 10 a 12 dias úteis se inicia a partir da confirmação do pagamento da entrada.</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold">3. Condições de Aceite:</h5>
                    <ul class="list-disc list-inside pl-4 mt-1 space-y-1">
                        <li>Este orçamento é válido por 30 dias a partir da data de emissão. Para aprovar e iniciar o trabalho, por favor, assine este documento e envie-o de volta para nós.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="text-gray-700">
            <h4 class="font-bold text-gray-800 mb-2 text-base">Próximos Passos e Garantia de Qualidade</h4>
            <p>Na Vetta Documental, nossa prioridade é a sua tranquilidade. Este orçamento representa nosso compromisso com a excelência, garantindo uma tradução juramentada precisa e ágil para seus documentos mais importantes.</p>
            <p class="mt-2">Estamos prontos para iniciar o seu projeto. Se tiver qualquer dúvida, por favor, não hesite em nos contatar.</p>
        </div>

        <div class="text-center text-xs text-gray-500 border-t pt-4 mt-8">
            <p class="font-semibold"><?php echo htmlspecialchars(APP_NAME); ?></p>
            <p>SRTVS Qd 701, Conj L, BLOCO 1, n° 38, salas 731/733 - Asa Sul - Brasília - DF</p>
            <p>CEP: 70.340-000 | CNPJ: 62.334.673/0001-</p>
        </div>
    </footer>

    <?php if ($showPrintButton): ?>
    <div class="text-center mt-8 no-print">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-transform transform hover:scale-105">
            <i class="fas fa-print mr-2"></i>
            Imprimir Orçamento
        </button>
    </div>
    <?php endif; ?>

</div>

<?php if ($fullPage): ?>
</body>
</html>
<?php endif; ?>