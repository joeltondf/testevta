<?php
class DashboardProcessFormatter
{
    public const SETTINGS_KEY = 'tv_panel_settings';

    private const DEFAULT_COLORS = [
        'overdue' => 'bg-red-200 text-red-800',
        'due_today' => 'bg-red-200 text-red-800',
        'due_soon' => 'bg-yellow-200 text-yellow-800',
        'on_track' => 'text-green-600',
        'completed' => 'bg-green-100 text-green-800',
        'inactive' => 'text-gray-500',
        'no_deadline' => 'text-gray-500',
    ];

    public static function normalizeStatusInfo(?string $status): array
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

    public static function getRowClass(string $statusNormalized): string
    {
        return match ($statusNormalized) {
            'orçamento', 'orçamento pendente' => 'bg-blue-50 hover:bg-blue-100',
            'serviço pendente' => 'bg-orange-50 hover:bg-orange-100',
            'serviço em andamento' => 'bg-cyan-50 hover:bg-cyan-100',
            'aguardando pagamento' => 'bg-indigo-50 hover:bg-indigo-100',
            'concluído' => 'bg-purple-50 hover:bg-purple-100',
            'cancelado' => 'bg-red-50 hover:bg-red-100',
            default => 'hover:bg-gray-50',
        };
    }

    public static function buildDeadlineDescriptor(array $process, array $colors = []): array
    {
        $colors = array_merge(self::DEFAULT_COLORS, $colors);
        $statusInfo = self::normalizeStatusInfo($process['status_processo'] ?? '');
        $statusNormalized = $statusInfo['normalized'];

        $descriptor = [
            'label' => 'A definir',
            'class' => $colors['no_deadline'],
            'state' => 'no_deadline',
            'days' => null,
            'deadlineDate' => null,
        ];

        if ($statusNormalized === 'concluído') {
            $descriptor['label'] = 'Concluído';
            $descriptor['class'] = $colors['completed'];
            $descriptor['state'] = 'completed';
            return $descriptor;
        }

        if (in_array($statusNormalized, ['cancelado', 'orçamento', 'orçamento pendente', 'aguardando pagamento'], true)) {
            $descriptor['label'] = 'N/A';
            $descriptor['class'] = $colors['inactive'];
            $descriptor['state'] = 'inactive';
            return $descriptor;
        }

        $deadlineDate = self::extractDeadlineDate($process);
        if ($deadlineDate === null) {
            return $descriptor;
        }

        $descriptor['deadlineDate'] = $deadlineDate;

        $today = new DateTimeImmutable('today');
        $diff = $today->diff($deadlineDate);
        $daysRemaining = (int) $diff->format('%r%a');
        $descriptor['days'] = $daysRemaining;

        if ($daysRemaining < 0) {
            $descriptor['label'] = abs($daysRemaining) . ' dia(s) vencido(s)';
            $descriptor['class'] = $colors['overdue'];
            $descriptor['state'] = 'overdue';
        } elseif ($daysRemaining === 0) {
            $descriptor['label'] = 'Vence hoje';
            $descriptor['class'] = $colors['due_today'];
            $descriptor['state'] = 'due_today';
        } elseif ($daysRemaining <= 3) {
            $descriptor['label'] = $daysRemaining . ' dias';
            $descriptor['class'] = $colors['due_soon'];
            $descriptor['state'] = 'due_soon';
        } else {
            $descriptor['label'] = $daysRemaining . ' dias';
            $descriptor['class'] = $colors['on_track'];
            $descriptor['state'] = 'on_track';
        }

        return $descriptor;
    }

    public static function getServiceBadges(?string $services): array
    {
        $map = [
            'Tradução' => ['label' => 'Trad.', 'class' => 'bg-blue-100 text-blue-800'],
            'CRC' => ['label' => 'CRC', 'class' => 'bg-teal-100 text-teal-800'],
            'Apostilamento' => ['label' => 'Apost.', 'class' => 'bg-purple-100 text-purple-800'],
            'Postagem' => ['label' => 'Post.', 'class' => 'bg-orange-100 text-orange-800'],
            'Outros' => ['label' => 'Out.', 'class' => 'bg-gray-100 text-gray-800'],
        ];

        $badges = [];
        $servicesList = array_filter(array_map('trim', explode(',', (string) $services)));

        foreach ($servicesList as $service) {
            if (isset($map[$service])) {
                $badges[] = $map[$service];
            }
        }

        return $badges;
    }

    public static function extractDeadlineDate(array $process): ?DateTimeImmutable
    {
        if (!empty($process['traducao_prazo_data'])) {
            return self::createImmutableDate($process['traducao_prazo_data']);
        }

        if (!empty($process['traducao_prazo_dias']) && !empty($process['data_inicio_traducao'])) {
            $start = self::createImmutableDate($process['data_inicio_traducao']);
            if ($start !== null) {
                return $start->modify('+' . (int) $process['traducao_prazo_dias'] . ' days');
            }
        }

        if (!empty($process['data_previsao_entrega'])) {
            return self::createImmutableDate($process['data_previsao_entrega']);
        }

        return null;
    }

    private static function createImmutableDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return null;
        }
    }
}
