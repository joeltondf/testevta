<?php
$formData = $formData ?? [];
$processId = (int)($processo['id'] ?? 0);
$mapLegacyPaymentMethod = static function (?string $method): string {
    $normalized = trim((string)$method);
    switch ($normalized) {
        case 'À vista':
        case 'Pagamento único':
            return 'Pagamento único';
        case 'Parcelado':
        case 'parcelado':
        case 'Pagamento parcelado':
            return 'Pagamento parcelado';
        case 'Mensal':
        case 'Pagamento mensal':
            return 'Pagamento mensal';
        default:
            return 'Pagamento único';
    }
};

$parseCurrencyValue = static function ($value): ?float {
    if ($value === null) {
        return null;
    }

    $normalized = trim((string)$value);
    if ($normalized === '') {
        return null;
    }

    $normalized = str_replace(['R$', ' '], '', $normalized);
    $normalized = preg_replace('/[^0-9,.-]/u', '', $normalized);

    $commaPos = strrpos($normalized, ',');
    $dotPos = strrpos($normalized, '.');
    $decimalSeparator = null;

    if ($commaPos !== false && $dotPos !== false) {
        $decimalSeparator = $commaPos > $dotPos ? ',' : '.';
    } elseif ($commaPos !== false) {
        $decimalSeparator = ',';
    } elseif ($dotPos !== false) {
        $decimalSeparator = '.';
    }

    if ($decimalSeparator !== null) {
        $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
        $normalized = str_replace($thousandSeparator, '', $normalized);
        $normalized = str_replace($decimalSeparator, '.', $normalized);
    } else {
        $normalized = str_replace([',', '.'], '', $normalized);
    }

    if (!is_numeric($normalized)) {
        return null;
    }

    return (float)$normalized;
};

$formatCurrencyDisplay = static function (?float $value): string {
    if ($value === null) {
        return 'R$ 0,00';
    }

    return 'R$ ' . number_format($value, 2, ',', '.');
};

$paymentMethod = $mapLegacyPaymentMethod($formData['forma_cobranca'] ?? null);
$totalValue = $formData['valor_total'] ?? ($processo['valor_total'] ?? '');
$totalNumeric = $parseCurrencyValue($totalValue);
$displayTotal = $totalNumeric !== null ? $formatCurrencyDisplay($totalNumeric) : 'Não informado';
$paymentDateOne = $formData['data_pagamento_1'] ?? '';
$paymentDateTwo = $formData['data_pagamento_2'] ?? '';
if ($paymentDateOne === '') {
    $paymentDateOne = date('Y-m-d');
}
$entryRaw = $formData['valor_entrada'] ?? '';
$entryNumeric = $parseCurrencyValue($entryRaw);
$entryDisplay = $entryNumeric !== null ? number_format($entryNumeric, 2, ',', '.') : (string)$entryRaw;
$balanceNumeric = ($totalNumeric !== null && $entryNumeric !== null)
    ? max($totalNumeric - $entryNumeric, 0.0)
    : null;
$balanceDisplay = $balanceNumeric !== null ? $formatCurrencyDisplay($balanceNumeric) : '-';
$parceladoRestDisplay = $balanceNumeric !== null ? $formatCurrencyDisplay($balanceNumeric) : 'R$ 0,00';
?>
<div class="max-w-3xl mx-auto space-y-10">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Pagamento</h1>
            <p class="text-sm text-gray-600">Informe os dados financeiros para finalizar a conversão do processo em serviço.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_payment&id=<?php echo $processId; ?>"
        method="POST"
        enctype="multipart/form-data"
        class="bg-white shadow rounded-lg p-8 space-y-8"
        data-conversion-step="payment"
    >
        <fieldset class="border border-gray-200 rounded-md p-8 space-y-8">
            <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Condições de Pagamento</legend>
            <div class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700" for="forma_cobranca">Forma de cobrança</label>
                        <select id="forma_cobranca" name="forma_cobranca" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-payment-method>
                            <option value="Pagamento único" <?php echo $paymentMethod === 'Pagamento único' ? 'selected' : ''; ?>>Pagamento único</option>
                            <option value="Pagamento parcelado" <?php echo $paymentMethod === 'Pagamento parcelado' ? 'selected' : ''; ?>>Pagamento parcelado</option>
                            <option value="Pagamento mensal" <?php echo $paymentMethod === 'Pagamento mensal' ? 'selected' : ''; ?>>Pagamento mensal</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700" for="valor_total">Valor total do serviço</label>
                        <input
                            type="text"
                            id="valor_total"
                            name="valor_total"
                            value="<?php echo htmlspecialchars((string)$totalValue); ?>"
                            class="mt-2 block w-full rounded-md border border-blue-200 bg-blue-50 text-blue-700 font-semibold shadow-sm focus:ring-orange-500 focus:border-orange-500"
                            data-total-value
                            readonly
                        >
                    </div>
                </div>

                <div class="space-y-6 <?php echo $paymentMethod === 'Pagamento único' ? '' : 'hidden'; ?>" data-payment-section="Pagamento único">
                    <h3 class="text-md font-semibold text-gray-800">Pagamento único</h3>
                    <p class="text-sm text-gray-600">O valor recebido será igual ao total calculado para o serviço.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="payment_unique_date">Data do pagamento</label>
                            <input
                                type="date"
                                id="payment_unique_date"
                                value="<?php echo htmlspecialchars($paymentDateOne); ?>"
                                class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                data-field-name="data_pagamento_1"
                                <?php echo $paymentMethod === 'Pagamento único' ? 'name="data_pagamento_1"' : 'disabled'; ?>
                            >
                        </div>
                        <div>
                            <label for="payment_unique_receipt" class="mt-4 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 px-5 py-6 text-center text-blue-600 transition hover:border-blue-400 hover:bg-blue-100 cursor-pointer" role="button">
                                <svg class="mb-2 h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m8 4a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h7.586a2 2 0 011.414.586l4.414 4.414A2 2 0 0120 9.414V19z" />
                                </svg>
                                <span class="text-sm font-semibold">Clique para anexar o comprovante</span>
                                <span class="mt-1 text-xs text-blue-500" data-upload-filename="payment_unique_receipt" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                            </label>
                            <input
                                type="file"
                                id="payment_unique_receipt"
                                accept=".pdf,.png,.jpg,.jpeg,.webp"
                                class="hidden"
                                data-field-name="comprovante_pagamento_unico"
                                data-upload-display="payment_unique_receipt"
                                <?php echo $paymentMethod === 'Pagamento único' ? 'name="comprovante_pagamento_unico"' : 'disabled'; ?>
                            >
                        </div>
                    </div>
                </div>

                <div class="space-y-6 <?php echo $paymentMethod === 'Pagamento parcelado' ? '' : 'hidden'; ?>" data-payment-section="Pagamento parcelado">
                    <h3 class="text-md font-semibold text-gray-800">Pagamento parcelado</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="valor_entrada">Valor da 1ª parcela</label>
                            <input
                                type="text"
                                id="valor_entrada"
                                value="<?php echo htmlspecialchars($entryDisplay); ?>"
                                class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                data-entry-value
                                data-field-name="valor_entrada"
                                <?php echo $paymentMethod === 'Pagamento parcelado' ? 'name="valor_entrada"' : 'disabled'; ?>
                                placeholder="R$ 0,00"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="payment_installment_date_1">Data da 1ª parcela</label>
                            <input
                                type="date"
                                id="payment_installment_date_1"
                                value="<?php echo htmlspecialchars($paymentDateOne); ?>"
                                class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                data-field-name="data_pagamento_1"
                                <?php echo $paymentMethod === 'Pagamento parcelado' ? 'name="data_pagamento_1"' : 'disabled'; ?>
                            >
                        </div>
                        <div>
                            <label for="payment_installment_receipt_1" class="mt-4 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-green-300 bg-green-50 px-5 py-6 text-center text-green-600 transition hover:border-green-400 hover:bg-green-100 cursor-pointer" role="button">
                                <svg class="mb-2 h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="text-sm font-semibold">Enviar comprovante da primeira parcela</span>
                                <span class="mt-1 text-xs text-green-600" data-upload-filename="payment_installment_receipt_1" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                            </label>
                            <input
                                type="file"
                                id="payment_installment_receipt_1"
                                accept=".pdf,.png,.jpg,.jpeg,.webp"
                                class="hidden"
                                data-field-name="comprovante_pagamento_entrada"
                                data-upload-display="payment_installment_receipt_1"
                                <?php echo $paymentMethod === 'Pagamento parcelado' ? 'name="comprovante_pagamento_entrada"' : 'disabled'; ?>
                            >
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="valor_restante">Valor restante</label>
                            <input
                                type="text"
                                id="valor_restante"
                                value="<?php echo htmlspecialchars($parceladoRestDisplay); ?>"
                                class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm bg-gray-100"
                                data-balance-input
                                data-default-value="<?php echo htmlspecialchars($parceladoRestDisplay); ?>"
                                readonly
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="payment_installment_date_2">Data da 2ª parcela</label>
                            <input
                                type="date"
                                id="payment_installment_date_2"
                                value="<?php echo htmlspecialchars($paymentDateTwo); ?>"
                                class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                data-field-name="data_pagamento_2"
                                <?php echo $paymentMethod === 'Pagamento parcelado' ? 'name="data_pagamento_2"' : 'disabled'; ?>
                            >
                        </div>
                        <div>
                            <label for="payment_installment_receipt_2" class="mt-4 flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-purple-300 bg-purple-50 px-5 py-6 text-center text-purple-600 transition hover:border-purple-400 hover:bg-purple-100 cursor-pointer" role="button">
                                <svg class="mb-2 h-6 w-6 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="text-sm font-semibold">Enviar comprovante da segunda parcela</span>
                                <span class="mt-1 text-xs text-purple-600" data-upload-filename="payment_installment_receipt_2" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
                            </label>
                            <input
                                type="file"
                                id="payment_installment_receipt_2"
                                accept=".pdf,.png,.jpg,.jpeg,.webp"
                                class="hidden"
                                data-field-name="comprovante_pagamento_saldo"
                                data-upload-display="payment_installment_receipt_2"
                                <?php echo $paymentMethod === 'Pagamento parcelado' ? 'name="comprovante_pagamento_saldo"' : 'disabled'; ?>
                            >
                        </div>
                    </div>
                </div>

                <div class="space-y-6 <?php echo $paymentMethod === 'Pagamento mensal' ? '' : 'hidden'; ?>" data-payment-section="Pagamento mensal">
                    <h3 class="text-md font-semibold text-gray-800">Pagamento mensal</h3>
                    <p class="text-sm text-gray-600">Informe apenas a data prevista para o pagamento mensal.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700" for="payment_monthly_date">Data do pagamento</label>
                            <input
                                type="date"
                                id="payment_monthly_date"
                                value="<?php echo htmlspecialchars($paymentDateOne); ?>"
                                class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                data-field-name="data_pagamento_1"
                                <?php echo $paymentMethod === 'Pagamento mensal' ? 'name="data_pagamento_1"' : 'disabled'; ?>
                            >
                        </div>
                    </div>
                </div>

                <div class="rounded-md bg-gray-50 p-5 space-y-3">
                    <p class="text-sm text-gray-700"><strong>Valor total:</strong> <span data-total-display><?php echo htmlspecialchars($displayTotal); ?></span></p>
                    <p class="text-sm text-gray-700"><strong>Saldo restante:</strong> <span data-balance-display><?php echo htmlspecialchars($balanceDisplay); ?></span></p>
                </div>
            </div>
        </fieldset>

        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <a href="processos.php?action=convert_to_service_deadline&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Voltar</a>
            <div class="flex items-center space-x-3">
                <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white text-sm font-semibold shadow-sm hover:bg-green-700 focus:outline-none">
                    Finalizar conversão
                </button>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/service-conversion.js"></script>
