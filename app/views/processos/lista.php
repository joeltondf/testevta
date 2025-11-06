<?php
// /app/views/processos/lista.php

if (!function_exists('process_list_normalize_status')) {
    function process_list_normalize_status(?string $status): array
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

require_once __DIR__ . '/../layouts/header.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Lista de Serviços</h1>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">OS</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Título</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cliente</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Data de Entrada</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Previsão de Entrega</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Valor Total</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
                <tbody>
                    <?php if (empty($processos)): ?>
                        <tr>
                            <td colspan="8" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">Nenhum processo encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($processos as $processo): ?>
                            <?php
                            $statusInfo = process_list_normalize_status($processo['status_processo'] ?? '');
                            $statusLabel = $statusInfo['label'];
                            $statusNormalized = $statusInfo['normalized'];

                            $statusClasses = 'bg-gray-200 text-gray-900';
                            switch ($statusNormalized) {
                                case 'orçamento':
                                    $statusClasses = 'bg-yellow-200 text-yellow-900';
                                    break;
                                case 'orçamento pendente':
                                    $statusClasses = 'bg-yellow-300 text-yellow-900';
                                    break;
                                case 'serviço pendente':
                                    $statusClasses = 'bg-orange-200 text-orange-900';
                                    break;
                                case 'serviço em andamento':
                                    $statusClasses = 'bg-cyan-200 text-cyan-900';
                                    break;
                                case 'aguardando pagamento':
                                    $statusClasses = 'bg-indigo-200 text-indigo-900';
                                    break;
                                case 'concluído':
                                    $statusClasses = 'bg-green-200 text-green-900';
                                    break;
                                case 'cancelado':
                                    $statusClasses = 'bg-red-200 text-red-900';
                                    break;
                            }
                            ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs">
                                    <p class="text-gray-900 whitespace-no-wrap font-semibold"><?php echo htmlspecialchars($processo['orcamento_numero'] ?? 'N/A'); ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars(mb_strtoupper($processo['titulo'] ?? '')); ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars(mb_strtoupper($processo['nome_cliente'] ?? '')); ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs whitespace-nowrap">
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full <?php echo $statusClasses; ?>">
                                        <span class="relative"><?php echo htmlspecialchars($statusLabel); ?></span>
                                    </span>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs text-center">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo $processo['data_entrada'] ? date('d/m/Y', strtotime($processo['data_entrada'])) : 'N/A'; ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs text-center">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo $processo['data_previsao_entrega'] ? date('d/m/Y', strtotime($processo['data_previsao_entrega'])) : 'N/A'; ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs text-right">
                                    <p class="text-gray-900 whitespace-no-wrap font-medium">
                                        <?php echo $processo['valor_total'] ? 'R$ ' . number_format($processo['valor_total'], 2, ',', '.') : 'N/A'; ?>
                                    </p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-xs text-center">
                                    <a href="processos.php?action=view&id=<?php echo $processo['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Ver Detalhes">
                                        Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

        </table>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>