<?php
$processos = $processos ?? [];
$overallSummary = $overallSummary ?? [
    'total_valor_total' => 0,
    'total_valor_entrada' => 0,
    'total_valor_restante' => 0,
    'media_valor_documento' => 0,
];
$vendedores = $vendedores ?? [];
$formas_pagamento = $formas_pagamento ?? [];
$clientes = $clientes ?? [];
$filters = $filters ?? [];
$aggregatedTotals = $aggregatedTotals ?? [];
$statusFinanceiroOptions = $statusFinanceiroOptions ?? [
    'pendente' => 'Pendente',
    'parcial' => 'Parcial',
    'pago' => 'Pago',
];
$csrfToken = $csrfToken ?? ($_SESSION['csrf_token'] ?? '');
$groupBy = $filters['group_by'] ?? 'month';

$formatCurrency = static function ($value): string {
    $numeric = is_numeric($value) ? (float) $value : 0.0;
    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '—';
    }

    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('d/m/Y');
    } catch (Throwable $exception) {
        return '—';
    }
};

$formatPeriod = static function (string $period, string $mode): string {
    if ($period === '') {
        return '—';
    }

    switch ($mode) {
        case 'day':
            try {
                return (new DateTime($period))->format('d/m/Y');
            } catch (Throwable $exception) {
                return htmlspecialchars($period, ENT_QUOTES, 'UTF-8');
            }
        case 'year':
            return htmlspecialchars($period, ENT_QUOTES, 'UTF-8');
        default:
            $parts = explode('-', $period);
            if (count($parts) === 2) {
                return sprintf('%02d/%s', (int) $parts[1], $parts[0]);
            }
            return htmlspecialchars($period, ENT_QUOTES, 'UTF-8');
    }
};
?>

<div id="financial-report" class="mx-auto w-full max-w-none px-4 py-8 space-y-6" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <h1 class="text-3xl font-bold text-gray-800">Relatório Financeiro</h1>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="export-csv-btn" aria-label="Exportar tabela para CSV"
                class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                <span class="fas fa-file-csv" aria-hidden="true"></span>
                Exportar CSV
            </button>
            <button type="button" id="print-btn" aria-label="Imprimir relatório"
                class="inline-flex items-center gap-2 rounded-md bg-gray-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                <span class="fas fa-print" aria-hidden="true"></span>
                Imprimir
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Valor total do período</h2>
            <p class="mt-2 text-2xl font-semibold text-blue-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['total_valor_total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Valor recebido</h2>
            <p class="mt-2 text-2xl font-semibold text-emerald-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['total_valor_entrada'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Valor restante</h2>
            <p class="mt-2 text-2xl font-semibold text-rose-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['total_valor_restante'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Média por documento</h2>
            <p class="mt-2 text-2xl font-semibold text-purple-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['media_valor_documento'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Comissão do vendedor</h2>
            <p class="mt-2 text-2xl font-semibold text-amber-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['total_comissao_vendedor'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-medium text-gray-500">Comissão do SDR</h2>
            <p class="mt-2 text-2xl font-semibold text-cyan-600">
                <?= htmlspecialchars($formatCurrency($overallSummary['total_comissao_sdr'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow print:hidden">
        <form method="get" action="/financeiro.php" class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Data inicial
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </label>

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Data final
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? date('Y-m-t'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </label>

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Agrupar por
                <select name="group_by" id="group_by"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <?php
                    $groupOptions = [
                        'day' => 'Dia',
                        'month' => 'Mês',
                        'year' => 'Ano',
                    ];
                    foreach ($groupOptions as $value => $label):
                        $selected = ($groupBy === $value) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $selected; ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Cliente
                <select name="cliente_id" id="cliente_id"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= htmlspecialchars((string) ($cliente['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ((string) ($filters['cliente_id'] ?? '') === (string) ($cliente['id'] ?? '')) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cliente['nome'] ?? $cliente['nome_cliente'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Forma de pagamento
                <select name="forma_pagamento_id" id="forma_pagamento_id"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="">Todas</option>
                    <?php foreach ($formas_pagamento as $forma): ?>
                        <option value="<?= htmlspecialchars((string) ($forma['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ((string) ($filters['forma_pagamento_id'] ?? '') === (string) ($forma['id'] ?? '')) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($forma['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm font-semibold text-gray-700">
                Vendedor
                <select name="vendedor_id" id="vendedor_id"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="">Todos</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                        <option value="<?= htmlspecialchars((string) ($vendedor['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ((string) ($filters['vendedor_id'] ?? '') === (string) ($vendedor['id'] ?? '')) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($vendedor['nome_vendedor'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="flex items-end">
                <button type="submit" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    Aplicar filtros
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($aggregatedTotals)): ?>
        <div class="space-y-3">
            <h2 class="text-lg font-semibold text-gray-700">Totais agregados por período</h2>
            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-[0.65rem] uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Período</th>
                            <th class="px-4 py-3 text-right">Processos</th>
                            <th class="px-4 py-3 text-right">Valor total</th>
                            <th class="px-4 py-3 text-right">Valor recebido</th>
                            <th class="px-4 py-3 text-right">Valor restante</th>
                            <th class="px-4 py-3 text-right">Comissão vendedor</th>
                            <th class="px-4 py-3 text-right">Comissão SDR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-gray-700">
                        <?php foreach ($aggregatedTotals as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium">
                                    <?= $formatPeriod((string) ($row['period'] ?? ''), $groupBy); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars((string) ($row['process_count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars($formatCurrency($row['total_valor_total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars($formatCurrency($row['total_valor_recebido'] ?? $row['total_valor_entrada'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars($formatCurrency($row['total_valor_restante'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars($formatCurrency($row['total_comissao_vendedor'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?= htmlspecialchars($formatCurrency($row['total_comissao_sdr'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table id="processos-table" class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50 text-[0.65rem] uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">OS Omie</th>
                        <th class="px-3 py-2 text-left min-w-[14rem]">Cliente</th>
                        <th class="px-3 py-2 text-left min-w-[16rem]">Serviço</th>
                        <th class="px-3 py-2 text-left">Vendedor</th>
                        <th class="px-3 py-2 text-left">Forma de pagamento</th>
                        <th class="px-3 py-2 text-right">Valor total</th>
                        <th class="px-3 py-2 text-right">Desconto</th>
                        <th class="px-3 py-2 text-right">Valor recebido</th>
                        <th class="px-3 py-2 text-right">Valor restante</th>
                        <th class="px-3 py-2 text-right">Comissão vendedor</th>
                        <th class="px-3 py-2 text-right">Comissão SDR</th>
                        <th class="px-3 py-2 text-left">Parcelas</th>
                        <th class="px-3 py-2 text-left">Status financeiro</th>
                        <th class="px-3 py-2 text-left">Data pagamento</th>
                        <th class="px-3 py-2 text-left">Data criação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-gray-700">
                    <?php if (empty($processos)): ?>
                        <tr>
                            <td colspan="15" class="px-4 py-6 text-center text-xs text-gray-500">Nenhum processo encontrado para os filtros selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($processos as $processo):
                            $processId = (int) ($processo['id'] ?? 0);
                            $omieNumberRaw = $processo['os_numero_omie'] ?? '';
                            $omieDigits = preg_replace('/\D/', '', (string) $omieNumberRaw);
                            $omieShort = $omieDigits !== '' ? substr($omieDigits, -5) : '—';
                            if ($omieShort === '—') {
                                $omieDisplay = '—';
                            } else {
                                $omieTrimmed = ltrim($omieShort, '0');
                                $omieDisplay = $omieTrimmed !== '' ? $omieTrimmed : '0';
                            }
                            $valorTotal = $formatCurrency($processo['valor_total'] ?? 0);
                            $desconto = $formatCurrency($processo['desconto'] ?? 0);
                            $valorRecebido = $formatCurrency($processo['valor_recebido'] ?? 0);
                            $valorRestante = $formatCurrency($processo['valor_restante'] ?? 0);
                            $comissaoVendedor = $formatCurrency($processo['total_comissao_vendedor'] ?? 0);
                            $comissaoSdr = $formatCurrency($processo['total_comissao_sdr'] ?? 0);
                            $rawValorRestanteNumeric = (float) ($processo['valor_restante'] ?? 0);
                            $rawPaymentDate = $processo['data_pagamento'] ?? null;
                            $statusFinanceiro = strtolower((string) ($processo['status_financeiro'] ?? 'pendente'));
                            $parcelCount = max(1, (int) ($processo['orcamento_parcelas'] ?? 1));
                            $hasSecondParcel = $parcelCount >= 2;
                            $secondPaymentRaw = $processo['data_pagamento_2'] ?? null;
                            $secondPaymentDate = $formatDate($secondPaymentRaw);
                            $needsSecondParcel = $hasSecondParcel && $rawValorRestanteNumeric > 0.01;
                            $parcelLabel = $parcelCount === 1 ? 'Pagamento único' : sprintf('%d parcelas', $parcelCount);
                            ?>
                            <tr data-process-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    <?php
                                    $omieTitle = ((string) $omieNumberRaw !== '') ? (string) $omieNumberRaw : 'Sem número Omie';
                                    ?>
                                    <span title="<?= htmlspecialchars($omieTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars((string) $omieDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <?= htmlspecialchars($processo['cliente_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?= htmlspecialchars($processo['titulo'] ?? $processo['categorias_servico'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?= htmlspecialchars($processo['nome_vendedor'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <select class="editable-select w-full rounded-md border border-gray-300 px-2 py-1 text-[0.75rem] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        data-field="forma_pagamento_id" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Selecionar forma de pagamento">
                                        <option value="">Não informado</option>
                                        <?php foreach ($formas_pagamento as $forma):
                                            $formaId = (string) ($forma['id'] ?? '');
                                            $selected = ((string) ($processo['forma_pagamento_id'] ?? '') === $formaId) ? 'selected' : '';
                                            ?>
                                            <option value="<?= htmlspecialchars($formaId, ENT_QUOTES, 'UTF-8'); ?>" <?= $selected; ?>>
                                                <?= htmlspecialchars($forma['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="editable-content inline-flex min-w-[5rem] justify-end rounded px-2 py-1" contenteditable="true"
                                        data-field="valor_total" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" data-value-type="currency"
                                        data-original-value="<?= htmlspecialchars($valorTotal, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($valorTotal, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="editable-content inline-flex min-w-[5rem] justify-end rounded px-2 py-1" contenteditable="true"
                                        data-field="desconto" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" data-value-type="currency"
                                        data-original-value="<?= htmlspecialchars($desconto, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($desconto, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="editable-content inline-flex min-w-[5rem] justify-end rounded px-2 py-1" contenteditable="true"
                                        data-field="valor_recebido" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" data-value-type="currency"
                                        data-original-value="<?= htmlspecialchars($valorRecebido, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($valorRecebido, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="editable-content inline-flex min-w-[5rem] justify-end rounded px-2 py-1" contenteditable="true"
                                        data-field="valor_restante" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" data-value-type="currency"
                                        data-original-value="<?= htmlspecialchars($valorRestante, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($valorRestante, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-700">
                                    <?= htmlspecialchars($comissaoVendedor, ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-700">
                                    <?= htmlspecialchars($comissaoSdr, ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-col gap-1 text-[0.7rem] leading-4 text-gray-700">
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($parcelLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($hasSecondParcel): ?>
                                            <span class="inline-flex w-fit items-center gap-1 rounded border px-2 py-0.5 text-[0.65rem] font-semibold <?= $needsSecondParcel ? 'border-amber-200 bg-amber-100 text-amber-800' : 'border-emerald-200 bg-emerald-100 text-emerald-700'; ?>">
                                                <?= $needsSecondParcel ? '2ª parcela pendente' : '2ª parcela quitada'; ?>
                                            </span>
                                            <?php if (!empty($secondPaymentRaw) && $secondPaymentDate !== '—'): ?>
                                                <span class="text-[0.65rem] text-gray-500">Prevista: <?= htmlspecialchars($secondPaymentDate, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($needsSecondParcel): ?>
                                                <span class="text-[0.65rem] font-semibold text-rose-600">Cobrar a 2ª parcela.</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <select class="editable-select w-full rounded-md border border-gray-300 px-2 py-1 text-[0.75rem] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        data-field="status_financeiro" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Selecionar status financeiro">
                                        <?php foreach ($statusFinanceiroOptions as $value => $label):
                                            $isSelected = ($statusFinanceiro === strtolower((string) $value));
                                            ?>
                                            <option value="<?= htmlspecialchars(strtolower((string) $value), ENT_QUOTES, 'UTF-8'); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <?php $displayDate = $formatDate($rawPaymentDate); ?>
                                    <span class="editable-content inline-flex min-w-[6rem] rounded px-2 py-1" contenteditable="true"
                                        data-field="data_pagamento" data-id="<?= htmlspecialchars((string) $processId, ENT_QUOTES, 'UTF-8'); ?>" data-value-type="date"
                                        data-original-value="<?= htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-raw-value="<?= htmlspecialchars((string) $rawPaymentDate, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <?= htmlspecialchars($formatDate($processo['data_criacao'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="finance-feedback" class="print:hidden" aria-live="polite"></div>
</div>

<style>
    #financial-report .editable-content:focus {
        outline: 2px solid rgba(59, 130, 246, 0.6);
        outline-offset: 2px;
    }
    #financial-report .editable-content[contenteditable="true"] {
        cursor: text;
        min-height: 1.75rem;
    }
    #financial-report table {
        font-size: 0.75rem;
    }
    #financial-report thead th {
        font-size: 0.65rem;
    }
</style>

<script>
(function () {
    const container = document.getElementById('financial-report');
    if (!container) {
        return;
    }

    const csrfToken = container.dataset.csrfToken || '';
    const feedback = document.getElementById('finance-feedback');

    const currencyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
    });

    function showFeedback(message, type = 'success') {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.className = type === 'success'
            ? 'mt-2 rounded-md bg-emerald-100 px-3 py-2 text-sm font-medium text-emerald-800'
            : 'mt-2 rounded-md bg-rose-100 px-3 py-2 text-sm font-medium text-rose-800';

        setTimeout(() => {
            feedback.textContent = '';
            feedback.className = '';
        }, 4000);
    }

    function parseValue(type, value, element) {
        const trimmed = value.trim();

        if (trimmed === '') {
            return null;
        }

        if (type === 'currency') {
            const normalized = trimmed
                .replace(/[^0-9,.-]/g, '')
                .replace(/\.(?=\d{3})/g, '')
                .replace(',', '.');
            const numericValue = Number.parseFloat(normalized);
            if (Number.isNaN(numericValue)) {
                throw new Error('Valor monetário inválido.');
            }
            return numericValue.toFixed(2);
        }

        if (type === 'date') {
            const isoPattern = /^\d{4}-\d{2}-\d{2}$/;
            const brPattern = /^\d{2}\/\d{2}\/\d{4}$/;

            if (isoPattern.test(trimmed)) {
                return trimmed;
            }

            if (brPattern.test(trimmed)) {
                const [day, month, year] = trimmed.split('/');
                return `${year}-${month}-${day}`;
            }

            throw new Error('Data em formato inválido.');
        }

        return trimmed;
    }

    function formatValueForDisplay(type, value) {
        if (value === null || value === '') {
            return '—';
        }

        if (type === 'currency') {
            return currencyFormatter.format(Number.parseFloat(value));
        }

        if (type === 'date') {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '—';
            }
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        return value;
    }

    function handleEditableBlur(event) {
        const target = event.currentTarget;
        const original = target.dataset.originalValue ?? '';
        const valueType = target.dataset.valueType || 'text';
        const processId = target.dataset.id;
        const field = target.dataset.field;

        if (!processId || !field) {
            return;
        }

        const currentValue = target.textContent.trim();
        if (currentValue === original.trim()) {
            target.textContent = original;
            return;
        }

        let payloadValue;
        try {
            payloadValue = parseValue(valueType, target.textContent, target);
        } catch (error) {
            showFeedback(error.message, 'error');
            target.textContent = original;
            return;
        }

        submitUpdate(processId, field, payloadValue, target, valueType);
    }

    function handleEditableKeyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            event.currentTarget.blur();
        }
    }

    function submitUpdate(id, field, value, element, valueType) {
        element.classList.add('bg-blue-50');

        const formData = new URLSearchParams();
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value === null ? '' : value);
        formData.append('csrf_token', csrfToken);

        fetch('/financeiro.php?action=update_field', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString(),
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({ status: 'error', message: 'Erro desconhecido.' }));
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Falha ao atualizar.');
                }

                if (value === null || value === '') {
                    element.textContent = '—';
                    element.dataset.originalValue = '—';
                    if (valueType === 'date') {
                        element.dataset.rawValue = '';
                    }
                } else {
                    const displayValue = formatValueForDisplay(valueType, value);
                    element.textContent = displayValue;
                    element.dataset.originalValue = displayValue;
                    if (valueType === 'date') {
                        element.dataset.rawValue = value;
                    }
                }

                element.classList.remove('bg-blue-50');
                element.classList.add('bg-emerald-50');
                setTimeout(() => element.classList.remove('bg-emerald-50'), 1200);
                if (element.tagName === 'SELECT') {
                    element.dataset.originalValue = element.value;
                }
                showFeedback('Campo atualizado com sucesso.');
            })
            .catch((error) => {
                element.classList.remove('bg-blue-50');
                element.classList.add('bg-rose-50');
                setTimeout(() => element.classList.remove('bg-rose-50'), 1200);
                showFeedback(error.message || 'Erro ao atualizar campo.', 'error');
                element.textContent = element.dataset.originalValue ?? '—';
            });
    }

    function wireEditableElements() {
        const editableElements = container.querySelectorAll('.editable-content');
        editableElements.forEach((element) => {
            element.addEventListener('blur', handleEditableBlur);
            element.addEventListener('keydown', handleEditableKeyDown);
            element.addEventListener('focus', () => {
                element.dataset.originalValue = element.textContent.trim();
            });
        });

        const selectElements = container.querySelectorAll('.editable-select');
        selectElements.forEach((select) => {
            select.addEventListener('focus', () => {
                select.dataset.originalValue = select.value;
            });

            select.addEventListener('change', () => {
                const field = select.dataset.field;
                const id = select.dataset.id;
                const newValue = select.value;

                if (select.dataset.originalValue === newValue) {
                    return;
                }

                submitUpdate(id, field, newValue, select, 'text');
            });
        });
    }

    function exportTableToCSV(table, filename) {
        const rows = Array.from(table.querySelectorAll('tr'));
        const separator = ';';
        const csvRows = [];

        if (rows.length === 0) {
            return;
        }

        const headers = Array.from(rows[0].querySelectorAll('th')).map((header) => `"${header.innerText.trim()}"`);
        csvRows.push(headers.join(separator));

        rows.slice(1).forEach((row) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (!cells.length) {
                return;
            }

            const values = cells.map((cell) => {
                if (cell.querySelector('select')) {
                    const select = cell.querySelector('select');
                    const option = select.options[select.selectedIndex];
                    return `"${(option ? option.text : '').replace(/"/g, '""')}"`;
                }

                const editable = cell.querySelector('.editable-content');
                if (editable) {
                    const type = editable.dataset.valueType || 'text';
                    if (type === 'currency') {
                        try {
                            const numeric = parseValue(type, editable.textContent, editable);
                            return `"${numeric ?? ''}"`;
                        } catch (error) {
                            return '""';
                        }
                    }
                    if (type === 'date') {
                        const raw = editable.dataset.rawValue || '';
                        return `"${raw}"`;
                    }
                    return `"${editable.textContent.trim().replace(/"/g, '""')}"`;
                }

                return `"${cell.textContent.trim().replace(/"/g, '""')}"`;
            });

            csvRows.push(values.join(separator));
        });

        const blob = new Blob(["\uFEFF" + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    const table = document.getElementById('processos-table');
    const exportBtn = document.getElementById('export-csv-btn');
    const printBtn = document.getElementById('print-btn');

    if (exportBtn && table) {
        exportBtn.addEventListener('click', () => exportTableToCSV(table, 'relatorio-financeiro.csv'));
    }

    if (printBtn) {
        printBtn.addEventListener('click', () => window.print());
    }

    wireEditableElements();
})();
</script>
