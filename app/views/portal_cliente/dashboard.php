<?php
if (!function_exists('client_portal_normalize_status_info')) {
    function client_portal_normalize_status_info(?string $status): array
    {
        $normalized = mb_strtolower(trim((string) $status));

        if ($normalized === '') {
            return ['normalized' => '', 'label' => 'N/A'];
        }

        $aliases = [
            'orcamento' => 'orçamento',
            'orcamento pendente' => 'orçamento pendente',
            'serviço pendente' => 'serviço pendente',
            'servico pendente' => 'serviço pendente',
            'pendente' => 'serviço pendente',
            'aprovado' => 'serviço pendente',
            'serviço em andamento' => 'serviço em andamento',
            'servico em andamento' => 'serviço em andamento',
            'em andamento' => 'serviço em andamento',
            'aguardando pagamento' => 'aguardando pagamento',
            'finalizado' => 'concluído',
            'finalizada' => 'concluído',
            'concluido' => 'concluído',
            'concluida' => 'concluído',
            'arquivado' => 'cancelado',
            'arquivada' => 'cancelado',
            'recusado' => 'cancelado',
            'recusada' => 'cancelado',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        $labels = [
            'orçamento' => 'Orçamento',
            'orçamento pendente' => 'Orçamento Pendente',
            'serviço pendente' => 'Serviço Pendente',
            'serviço em andamento' => 'Serviço em Andamento',
            'aguardando pagamento' => 'Aguardando pagamento',
            'concluído' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];

        $label = $labels[$normalized] ?? ($status === '' ? 'N/A' : $status);

        return ['normalized' => $normalized, 'label' => $label];
    }
}

if (!function_exists('client_portal_calculate_overview')) {
    function client_portal_calculate_overview(array $processos): array
    {
        $summary = [
            'total' => count($processos),
            'pending' => 0,
            'inProgress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'nextService' => null,
        ];

        foreach ($processos as $processo) {
            $statusInfo = client_portal_normalize_status_info($processo['status_processo'] ?? '');

            switch ($statusInfo['normalized']) {
                case 'serviço em andamento':
                    $summary['inProgress']++;
                    break;
                case 'aguardando pagamento':
                    $summary['pending']++;
                    break;
                case 'concluído':
                    $summary['completed']++;
                    break;
                case 'cancelado':
                    $summary['cancelled']++;
                    break;
                default:
                    $summary['pending']++;
                    break;
            }

            if (empty($processo['data_previsao_entrega'])) {
                continue;
            }

            $deadline = \DateTime::createFromFormat('Y-m-d', (string) $processo['data_previsao_entrega']);
            if (!$deadline) {
                continue;
            }

            $currentNext = $summary['nextService']['deadline'] ?? null;
            if ($currentNext === null || $deadline < $currentNext) {
                $summary['nextService'] = [
                    'deadline' => $deadline,
                    'title' => $processo['titulo'] ?? 'Serviço',
                ];
            }
        }

        return $summary;
    }
}

$processosList = is_array($processos ?? null) ? $processos : [];
$overview = client_portal_calculate_overview($processosList);
$hasProcessos = !empty($processosList);
?>

<section class="flex flex-col gap-10">
    <div class="rounded-2xl bg-gradient-to-r from-indigo-500 via-sky-500 to-cyan-500 p-6 text-white shadow-lg">
        <h2 class="text-xl font-semibold">Acompanhe o seu serviço com tranquilidade</h2>
        <p class="mt-2 max-w-3xl text-sm sm:text-base text-indigo-100">
            Aqui você encontra um resumo do andamento de todos os serviços contratados e pode acompanhar as próximas etapas em tempo real.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-sm font-medium text-gray-500">Total de serviços</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?php echo $overview['total']; ?></p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-sm font-medium text-gray-500">Em análise</p>
            <p class="mt-2 text-3xl font-semibold text-amber-500"><?php echo $overview['pending']; ?></p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-sm font-medium text-gray-500">Em andamento</p>
            <p class="mt-2 text-3xl font-semibold text-indigo-500"><?php echo $overview['inProgress']; ?></p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-sm font-medium text-gray-500">Concluídos</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-500"><?php echo $overview['completed']; ?></p>
        </article>
    </div>

    <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Meus serviços</h3>
                <p class="text-sm text-gray-500">Visualize o status, datas importantes e acompanhe as próximas entregas.</p>
            </div>
            <?php if ($overview['nextService']): ?>
                <div class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-700">
                    <p class="font-medium">Próxima entrega</p>
                    <p>
                        <?php echo htmlspecialchars($overview['nextService']['title']); ?>
                        •
                        <?php echo $overview['nextService']['deadline']->format('d/m/Y'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-gray-500">Serviço</th>
                            <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-gray-500">Recebido em</th>
                            <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-gray-500">Previsão</th>
                            <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (!$hasProcessos): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    Ainda não encontramos serviços associados à sua conta.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($processosList as $processo): ?>
                                <?php
                                    $statusInfo = client_portal_normalize_status_info($processo['status_processo'] ?? '');
                                    $statusLabel = $statusInfo['label'];
                                    $statusNormalized = $statusInfo['normalized'];
                                    $statusClasses = 'bg-gray-100 text-gray-800';

                                    switch ($statusNormalized) {
                                        case 'orçamento':
                                            $statusClasses = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'orçamento pendente':
                                            $statusClasses = 'bg-yellow-200 text-yellow-900';
                                            break;
                                        case 'serviço pendente':
                                            $statusClasses = 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'serviço em andamento':
                                            $statusClasses = 'bg-indigo-100 text-indigo-800';
                                            break;
                                        case 'aguardando pagamento':
                                            $statusClasses = 'bg-indigo-200 text-indigo-900';
                                            break;
                                        case 'concluído':
                                            $statusClasses = 'bg-green-100 text-green-800';
                                            break;
                                        case 'cancelado':
                                            $statusClasses = 'bg-red-100 text-red-800';
                                            break;
                                    }

                                    $createdAt = $processo['data_criacao'] ?? null;
                                    $deadline = $processo['data_previsao_entrega'] ?? null;
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 align-middle font-medium text-gray-800">
                                        <?php echo htmlspecialchars($processo['titulo'] ?? 'Serviço'); ?>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-gray-600">
                                        <?php echo $createdAt ? date('d/m/Y', strtotime($createdAt)) : '—'; ?>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-gray-600">
                                        <?php echo $deadline ? date('d/m/Y', strtotime($deadline)) : '—'; ?>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?php echo $statusClasses; ?>">
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($hasProcessos && $overview['cancelled'] > 0): ?>
            <p class="mt-4 text-xs text-gray-500">
                <?php echo $overview['cancelled']; ?> serviços foram finalizados sem continuidade. Caso tenha dúvidas, entre em contato com nossa equipe.
            </p>
        <?php endif; ?>

        <div class="mt-6 rounded-lg bg-gray-50 px-4 py-5 text-sm text-gray-600">
            <p class="font-medium text-gray-700">Precisa de ajuda?</p>
            <p class="mt-1">Estamos disponíveis pelos canais de suporte habituais para esclarecer qualquer dúvida sobre o andamento do seu serviço.</p>
        </div>
    </section>
</section>
