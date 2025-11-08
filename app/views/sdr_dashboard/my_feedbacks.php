<?php
$pageTitle = $pageTitle ?? 'Feedbacks Recebidos';
$baseAppUrl = rtrim(APP_URL, '/');
$feedbackSummary = $feedbackSummary ?? [
    'total' => 0,
    'average' => null,
    'score_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    'score_distribution_percent' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
];
$feedbackPatterns = $feedbackPatterns ?? [
    'labels' => [],
    'counts' => [],
    'percentages' => [],
];
$recentFeedbacks = $recentFeedbacks ?? [];
?>

<link rel="stylesheet" href="<?php echo $baseAppUrl; ?>/assets/css/components/sdr-dashboard.css">

<div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 space-y-8">
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Feedbacks Recebidos</h1>
            <p class="text-sm text-gray-600">Acompanhe como os vendedores avaliam as qualificações entregues e identifique pontos de melhoria no processo.</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-flex items-center gap-1"><i class="fas fa-star text-yellow-500"></i> Feedbacks coletados</span>
            <span class="inline-flex items-center gap-1"><i class="fas fa-chart-line text-blue-500"></i> Tendências do time</span>
        </div>
    </header>

    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Total de feedbacks</p>
            <p class="sdr-summary-value"><?php echo (int) ($feedbackSummary['total'] ?? 0); ?></p>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Média de avaliação</p>
            <div class="flex items-center gap-2">
                <p class="sdr-summary-value">
                    <?php echo $feedbackSummary['average'] !== null ? number_format((float) $feedbackSummary['average'], 2, ',', '.') : '--'; ?>
                </p>
                <?php if (($feedbackSummary['average'] ?? null) !== null): ?>
                    <div class="flex items-center text-yellow-500">
                        <?php $rounded = round((float) $feedbackSummary['average']); ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?php echo $i <= $rounded ? 'fas fa-star' : 'far fa-star'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Notas 4 e 5</p>
            <?php
                $highScoreCount = (int) (($feedbackSummary['score_distribution'][4] ?? 0) + ($feedbackSummary['score_distribution'][5] ?? 0));
                $total = (int) ($feedbackSummary['total'] ?? 0);
                $highScorePercent = $total > 0 ? round(($highScoreCount / $total) * 100) : 0;
            ?>
            <p class="sdr-summary-value"><?php echo $highScorePercent; ?>%</p>
        </article>
        <article class="sdr-summary-card">
            <p class="sdr-summary-label">Notas 1 e 2</p>
            <?php
                $lowScoreCount = (int) (($feedbackSummary['score_distribution'][1] ?? 0) + ($feedbackSummary['score_distribution'][2] ?? 0));
                $lowScorePercent = $total > 0 ? round(($lowScoreCount / max($total, 1)) * 100) : 0;
            ?>
            <p class="sdr-summary-value"><?php echo $lowScorePercent; ?>%</p>
        </article>
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <header>
            <h2 class="text-lg font-semibold text-gray-800">Distribuição de notas</h2>
            <p class="text-sm text-gray-500">Visualize como os vendedores têm avaliado as oportunidades repassadas.</p>
        </header>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <?php for ($score = 5; $score >= 1; $score--): ?>
                <?php
                    $count = (int) ($feedbackSummary['score_distribution'][$score] ?? 0);
                    $percent = (int) ($feedbackSummary['score_distribution_percent'][$score] ?? 0);
                ?>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <span><?php echo $score; ?> estrela<?php echo $score > 1 ? 's' : ''; ?></span>
                        <span><?php echo $percent; ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500" style="width: <?php echo min(100, max(0, $percent)); ?>%"></div>
                    </div>
                    <span class="text-xs text-gray-400"><?php echo $count; ?> feedback<?php echo $count === 1 ? '' : 's'; ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <header>
            <h2 class="text-lg font-semibold text-gray-800">Padrões identificados</h2>
            <p class="text-sm text-gray-500">Percentual de feedbacks em que cada critério foi apontado como positivo pelo vendedor.</p>
        </header>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($feedbackPatterns['labels'] as $key => $label): ?>
                <?php
                    $percent = (int) ($feedbackPatterns['percentages'][$key] ?? 0);
                    $count = (int) ($feedbackPatterns['counts'][$key] ?? 0);
                ?>
                <article class="p-4 border border-gray-100 rounded-lg space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="text-sm font-semibold text-blue-600"><?php echo $percent; ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500" style="width: <?php echo min(100, max(0, $percent)); ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500"><?php echo $count; ?> ocorrência<?php echo $count === 1 ? '' : 's'; ?> em <?php echo (int) ($feedbackSummary['total'] ?? 0); ?> feedback<?php echo ((int) ($feedbackSummary['total'] ?? 0)) === 1 ? '' : 's'; ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <header class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Últimos feedbacks</h2>
            <p class="text-sm text-gray-500">Os 10 feedbacks mais recentes enviados pelos vendedores.</p>
        </header>
        <?php if (empty($recentFeedbacks)): ?>
            <p class="text-sm text-gray-500">Nenhum feedback registrado até o momento.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($recentFeedbacks as $feedback): ?>
                    <?php
                        $dateLabel = '--';
                        if (!empty($feedback['submitted_at'])) {
                            $timestamp = strtotime($feedback['submitted_at']);
                            if ($timestamp !== false) {
                                $dateLabel = date('d/m/Y H:i', $timestamp);
                            }
                        }
                        $score = (int) ($feedback['score'] ?? 0);
                        $flags = $feedback['flags'] ?? [];
                    ?>
                    <article class="border border-gray-100 rounded-lg p-4 hover:border-blue-200 transition-colors">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-800"><?php echo htmlspecialchars($feedback['lead_name'] ?? 'Lead', ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-xs text-gray-500">Vendedor: <?php echo htmlspecialchars($feedback['vendor_name'] ?? 'Vendedor', ENT_QUOTES, 'UTF-8'); ?> · <?php echo $dateLabel; ?></p>
                            </div>
                            <div class="flex items-center gap-2 text-yellow-500 text-sm">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo $i <= $score ? 'fas fa-star' : 'far fa-star text-gray-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if (!empty($feedback['comments'])): ?>
                            <p class="text-sm text-gray-700 mb-3 leading-relaxed">“<?php echo nl2br(htmlspecialchars($feedback['comments'], ENT_QUOTES, 'UTF-8')); ?>”</p>
                        <?php endif; ?>
                        <?php if (!empty($flags)): ?>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <?php foreach ($flags as $key => $value): ?>
                                    <?php if ($value && isset($feedbackPatterns['labels'][$key])): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-700">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo htmlspecialchars($feedbackPatterns['labels'][$key], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
