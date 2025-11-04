<?php
$notifications = $alertFeed['notifications'] ?? [];
$totalAlerts = (int)($alertFeed['total'] ?? 0);
$isManager = in_array($_SESSION['user_perfil'] ?? '', ['admin', 'gerencia', 'supervisor'], true);

if (!function_exists('format_alert_type_label')) {
    function format_alert_type_label(string $alertType): string
    {
        $map = [
            'processo_pendente_orcamento' => 'Orçamento pendente',
            'processo_pendente_servico' => 'Serviço pendente',
            'processo_orcamento_recusado' => 'Orçamento recusado',
            'processo_orcamento_enviado' => 'Orçamento enviado',
            'processo_servico_pendente' => 'Serviço aguardando execução',
            'processo_cancelado' => 'Processo cancelado',
            'processo_servico_aprovado' => 'Serviço aprovado',
            'processo_orcamento_aprovado' => 'Orçamento aprovado',
            'processo_orcamento_cancelado' => 'Orçamento cancelado',
            'prospeccao_exclusao' => 'Solicitação de exclusão',
            'prospeccao_generica' => 'Prospeção',
            'processo_generico' => 'Processo',
            'notificacao_generica' => 'Alerta',
        ];

        return $map[$alertType] ?? ucfirst(str_replace('_', ' ', $alertType));
    }
}

if (!function_exists('format_notification_group_label')) {
    function format_notification_group_label(string $group): string
    {
        return $group === 'vendedor' ? 'vendedores' : 'gestão';
    }
}
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 bg-gray-50">
    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">Painel de Notificações</h1>
        <p class="mt-1 text-sm text-gray-500">
            Alertas ativos direcionados ao grupo de <?php echo htmlspecialchars(format_notification_group_label($grupoDestino ?? 'gerencia')); ?>.
        </p>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <?php if (empty($notifications)): ?>
            <div class="p-8 text-center text-gray-500">
                Nenhuma notificação pendente para o seu grupo.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mensagem
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Recebida em
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                                $link = $notification['link'] ?? '#';
                                $displayDate = $notification['display_date'] ?? '';
                                $alertType = $notification['tipo_alerta'] ?? 'notificacao_generica';
                                $rowHighlight = empty($notification['lida']) ? 'bg-yellow-50' : '';
                            ?>
                            <tr class="<?php echo $rowHighlight; ?>">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($notification['mensagem'] ?? ''); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                                        <?php echo htmlspecialchars(format_alert_type_label($alertType)); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo htmlspecialchars($displayDate); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <?php $referenceId = (int)($notification['referencia_id'] ?? 0); ?>
                                        <?php if ($isManager && $referenceId > 0 && $alertType === 'processo_pendente_orcamento'): ?>
                                            <div class="flex flex-col gap-2 w-full">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <a href="<?php echo APP_URL; ?>/processos.php?action=aprovar_orcamento&id=<?php echo $referenceId; ?>" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                                                        Aprovar orçamento
                                                    </a>
                                                    <form action="<?php echo APP_URL; ?>/processos.php?action=recusar_orcamento" method="POST" class="flex flex-wrap items-center justify-end gap-2">
                                                        <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                        <label for="motivo_recusa_<?php echo $referenceId; ?>" class="sr-only">Motivo do cancelamento</label>
                                                        <input
                                                            id="motivo_recusa_<?php echo $referenceId; ?>"
                                                            type="text"
                                                            name="motivo_recusa"
                                                            class="w-full sm:w-56 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                            placeholder="Motivo do cancelamento"
                                                            required
                                                        >
                                                        <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-red-600 text-white shadow hover:bg-red-700">
                                                            Cancelar orçamento
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php elseif ($isManager && $referenceId > 0 && $alertType === 'processo_pendente_servico'): ?>
                                            <div class="flex flex-wrap justify-end gap-2 w-full">
                                                <form action="<?php echo APP_URL; ?>/processos.php?action=change_status" method="POST" class="inline-flex">
                                                    <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                    <input type="hidden" name="status_processo" value="Serviço em Andamento">
                                                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                                                        Aprovar serviço
                                                    </button>
                                                </form>
                                                <form action="<?php echo APP_URL; ?>/processos.php?action=change_status" method="POST" class="inline-flex">
                                                    <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                    <input type="hidden" name="status_processo" value="Orçamento Pendente">
                                                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-yellow-600 text-white shadow hover:bg-yellow-700">
                                                        Solicitar ajustes
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($link) && $link !== '#'): ?>
                                            <a href="<?php echo htmlspecialchars(APP_URL . $link); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                                Abrir
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo APP_URL; ?>/notificacoes.php?action=markRead&id=<?php echo (int)($notification['id'] ?? 0); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-100">
                                            Marcar como lida
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 text-sm text-gray-500">
                <?php echo $totalAlerts === 1 ? '1 notificação pendente.' : $totalAlerts . ' notificações pendentes.'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
