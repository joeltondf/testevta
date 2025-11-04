<?php
/**
 * @file /app/views/processos/orcamento_email.php
 * @description Template específico para envio do orçamento por e-mail com estilos inline.
 */

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

$formatCurrency = fn($value): string => 'R$ ' . number_format($parseMoney($value), 2, ',', '.');

$formatDate = function ($date): string {
    if (empty($date)) {
        return 'N/A';
    }
    return date('d/m/Y', strtotime($date));
};

$logoUrl = null;
if (!empty($system_logo)) {
    $isAbsolute = preg_match('/^https?:\/\//i', (string)$system_logo) === 1;
    $logoPath = $isAbsolute ? $system_logo : APP_URL . '/' . ltrim($system_logo, '/');
    $logoUrl = htmlspecialchars($logoPath);
}

$documentsList = $documents ?? [];
$budgetItems = [];

if (!empty($documentsList)) {
    foreach ($documentsList as $document) {
        $quantity = (int)($document['quantidade'] ?? 1);
        $quantity = $quantity > 0 ? $quantity : 1;
        $unitValue = $parseMoney($document['valor_unitario'] ?? 0);
        $subtotal = $unitValue * $quantity;

        $categoryLabel = $document['categoria'] ?? null;
        $title = $document['tipo_documento'] ?? 'Documento';
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

$infoRows = [];
$deadlineDate = $process['traducao_prazo_data'] ?? $process['data_previsao_entrega'] ?? null;
if (!empty($deadlineDate)) {
    $infoRows[] = ['Prazo estimado', $formatDate($deadlineDate)];
}

if (!empty($process['orcamento_forma_pagamento'])) {
    $infoRows[] = ['Forma de pagamento', htmlspecialchars($process['orcamento_forma_pagamento'])];
}

if (!empty($process['orcamento_parcelas'])) {
    $infoRows[] = ['Parcelamento', htmlspecialchars($process['orcamento_parcelas']) . 'x'];
}

if (!empty($process['orcamento_valor_entrada'])) {
    $infoRows[] = ['Entrada prevista', $formatCurrency($process['orcamento_valor_entrada'])];
}

$bodyStyle = 'margin:0;padding:0;background-color:#f4f4f7;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#1f2933;';
$wrapperStyle = 'width:100%;border-collapse:collapse;';
$containerStyle = 'max-width:600px;margin:24px auto;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;';
$sectionPadding = 'padding:24px;';
$headingStyle = 'margin:0 0 12px 0;font-size:20px;color:#111827;';
$textStyle = 'margin:0 0 8px 0;font-size:14px;color:#4b5563;line-height:1.6;';
$dividerStyle = 'border-bottom:1px solid #e5e7eb;margin:24px 0;';
$tableHeaderStyle = 'padding:12px;background-color:#f9fafb;font-size:12px;font-weight:600;text-transform:uppercase;color:#6b7280;text-align:left;border-bottom:1px solid #e5e7eb;';
$tableCellStyle = 'padding:12px;font-size:14px;color:#1f2933;border-bottom:1px solid #f3f4f6;';
$infoLabelStyle = 'padding:4px 0;color:#6b7280;font-size:13px;';
$infoValueStyle = 'padding:4px 0;color:#111827;font-size:13px;font-weight:600;text-align:right;';
$footerStyle = 'margin-top:24px;font-size:11px;color:#9ca3af;text-align:center;line-height:1.5;';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento <?php echo htmlspecialchars($process['orcamento_numero'] ?? ''); ?></title>
</head>
<body style="<?php echo $bodyStyle; ?>">
<table role="presentation" width="100%" style="<?php echo $wrapperStyle; ?>">
    <tr>
        <td align="center" style="padding:0 16px;">
            <table role="presentation" style="<?php echo $containerStyle; ?>">
                <tr>
                    <td style="<?php echo $sectionPadding; ?>">
                        <table role="presentation" width="100%" style="border-collapse:collapse;">
                            <tr>
                                <td style="vertical-align:top;">
                                    <?php if ($logoUrl): ?>
                                        <img src="<?php echo $logoUrl; ?>" alt="Logo da empresa" style="max-height:64px;width:auto;display:block;">
                                    <?php else: ?>
                                        <p style="font-size:22px;font-weight:700;color:#111827;margin:0;">
                                            <?php echo htmlspecialchars(APP_NAME); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;vertical-align:top;">
                                    <p style="margin:0;font-size:18px;font-weight:700;color:#111827;">Orçamento</p>
                                    <p style="margin:4px 0 0 0;font-size:13px;color:#6b7280;">
                                        Nº <?php echo htmlspecialchars($process['orcamento_numero'] ?? $process['id'] ?? ''); ?>
                                    </p>
                                    <p style="margin:4px 0 0 0;font-size:13px;color:#6b7280;">
                                        <?php echo $formatDate($process['data_criacao'] ?? date('Y-m-d')); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div style="<?php echo $dividerStyle; ?>"></div>

                        <table role="presentation" width="100%" style="border-collapse:collapse;">
                            <tr>
                                <td style="vertical-align:top;width:50%;">
                                    <h2 style="font-size:14px;text-transform:uppercase;color:#6b7280;margin:0 0 8px 0;">De</h2>
                                    <p style="<?php echo $textStyle; ?>font-weight:600;color:#111827;">
                                        <?php echo htmlspecialchars(APP_NAME); ?>
                                    </p>
                                    <?php if (!empty($user['email'])): ?>
                                        <p style="<?php echo $textStyle; ?>margin-top:-4px;">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align:top;width:50%;text-align:right;">
                                    <h2 style="font-size:14px;text-transform:uppercase;color:#6b7280;margin:0 0 8px 0;">Para</h2>
                                    <?php if ($client): ?>
                                        <p style="<?php echo $textStyle; ?>font-weight:600;color:#111827;">
                                            <?php echo htmlspecialchars($client['nome_cliente'] ?? ''); ?>
                                        </p>
                                        <?php if (!empty($client['email'])): ?>
                                            <p style="<?php echo $textStyle; ?>margin-top:-4px;">
                                                <?php echo htmlspecialchars($client['email']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($client['telefone'])): ?>
                                            <p style="<?php echo $textStyle; ?>margin-top:-4px;">
                                                <?php echo htmlspecialchars($client['telefone']); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="<?php echo $textStyle; ?>font-weight:600;color:#111827;">Cliente não informado</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <?php if (!empty($infoRows)): ?>
                            <div style="<?php echo $dividerStyle; ?>margin-top:20px;margin-bottom:20px;"></div>
                            <table role="presentation" width="100%" style="border-collapse:collapse;">
                                <?php foreach ($infoRows as [$label, $value]): ?>
                                    <tr>
                                        <td style="<?php echo $infoLabelStyle; ?>"><?php echo $label; ?></td>
                                        <td style="<?php echo $infoValueStyle; ?>"><?php echo $value; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>

                        <div style="<?php echo $dividerStyle; ?>"></div>

                        <h1 style="<?php echo $headingStyle; ?>">
                            <?php echo htmlspecialchars($process['titulo'] ?? 'Detalhes do orçamento'); ?>
                        </h1>
                        <table role="presentation" width="100%" style="border-collapse:collapse;margin-bottom:12px;">
                            <thead>
                                <tr>
                                    <th style="<?php echo $tableHeaderStyle; ?>">Serviço / Documento</th>
                                    <th style="<?php echo $tableHeaderStyle; ?>text-align:center;">Qtd.</th>
                                    <th style="<?php echo $tableHeaderStyle; ?>text-align:right;">Valor unitário</th>
                                    <th style="<?php echo $tableHeaderStyle; ?>text-align:right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($budgetItems)): ?>
                                <?php foreach ($budgetItems as $item): ?>
                                    <tr>
                                        <td style="<?php echo $tableCellStyle; ?>">
                                            <span style="display:block;font-weight:600;"><?php echo htmlspecialchars($item['title']); ?></span>
                                            <?php if (!empty($item['description'])): ?>
                                                <span style="display:block;color:#6b7280;font-size:12px;margin-top:2px;">
                                                    <?php echo htmlspecialchars($item['description']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="<?php echo $tableCellStyle; ?>text-align:center;">
                                            <?php echo htmlspecialchars($item['quantity']); ?>
                                        </td>
                                        <td style="<?php echo $tableCellStyle; ?>text-align:right;">
                                            <?php echo $formatCurrency($item['unitValue']); ?>
                                        </td>
                                        <td style="<?php echo $tableCellStyle; ?>text-align:right;font-weight:600;">
                                            <?php echo $formatCurrency($item['subtotal']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="<?php echo $tableCellStyle; ?>text-align:center;font-style:italic;color:#6b7280;">
                                        Os detalhes dos documentos não foram informados neste orçamento.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>

                        <table role="presentation" width="100%" style="border-collapse:collapse;margin-top:8px;">
                            <tr>
                                <td style="<?php echo $infoLabelStyle; ?>">Subtotal</td>
                                <td style="<?php echo $infoValueStyle; ?>"><?php echo $formatCurrency($total); ?></td>
                            </tr>
                            <tr>
                                <td style="<?php echo $infoLabelStyle; ?>font-size:14px;font-weight:700;color:#111827;">Total</td>
                                <td style="<?php echo $infoValueStyle; ?>font-size:16px;font-weight:700;color:#111827;">
                                    <?php echo $formatCurrency($total); ?>
                                </td>
                            </tr>
                        </table>

                        <?php if (!empty($process['observacoes'])): ?>
                            <div style="<?php echo $dividerStyle; ?>"></div>
                            <h2 style="font-size:16px;color:#111827;margin:0 0 8px 0;">Observações</h2>
                            <p style="<?php echo $textStyle; ?>white-space:pre-line;">
                                <?php echo htmlspecialchars($process['observacoes']); ?>
                            </p>
                        <?php endif; ?>

                        <div style="<?php echo $dividerStyle; ?>"></div>
                        <h2 style="font-size:16px;color:#111827;margin:0 0 8px 0;">Próximos passos</h2>
                        <p style="<?php echo $textStyle; ?>">
                            Para prosseguir com a formalização do serviço, basta responder a este e-mail confirmando o aceite ou, se preferir, entre em contato conosco para ajustar qualquer detalhe.
                        </p>
                        <p style="<?php echo $textStyle; ?>">
                            Assim que recebermos o seu retorno, nossa equipe dará seguimento imediato ao atendimento.
                        </p>

                        <div style="<?php echo $dividerStyle; ?>"></div>
                        <h2 style="font-size:16px;color:#111827;margin:0 0 8px 0;">Termos e condições</h2>
                        <ul style="margin:0 0 12px 18px;padding:0;color:#4b5563;font-size:13px;line-height:1.6;">
                            <li>O prazo de execução será contabilizado após a confirmação do pagamento da entrada (quando aplicável).</li>
                            <li>Documentos técnicos podem demandar avaliação complementar para precificação final.</li>
                            <li>Este orçamento possui validade de 30 dias a partir da data de envio.</li>
                        </ul>

                        <p style="<?php echo $textStyle; ?>">
                            Permanecemos à disposição para quaisquer dúvidas.
                        </p>

                        <div style="<?php echo $footerStyle; ?>">
                            <p style="margin:0;font-weight:600;"><?php echo htmlspecialchars(APP_NAME); ?></p>
                            <p style="margin:2px 0 0 0;">SRTVS Qd 701, Conj L, BLOCO 1, n° 38, salas 731/733 - Asa Sul - Brasília - DF</p>
                            <p style="margin:2px 0 0 0;">CEP: 70.340-000 | CNPJ: 62.334.673/0001-</p>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
