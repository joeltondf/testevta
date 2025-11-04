<?php
// Arquivo: crm/prospeccoes/retornos.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

// --- CONFIGURAÇÃO DO ALERTA ---
$dias_para_alerta = 30;

// --- LÓGICA DA CONSULTA SQL ---
$sql_base = "SELECT 
                p.*, 
                u.nome_completo as responsavel_nome,
                DATEDIFF(NOW(), p.data_ultima_atualizacao) as dias_sem_contato 
            FROM prospeccoes p
            LEFT JOIN users u ON p.responsavel_id = u.id";

$where_conditions = [];
$params = [];

$where_conditions[] = "p.status NOT IN ('Convertido', 'Descartado', 'Inativo', 'Pausa')";
$where_conditions[] = "p.data_ultima_atualizacao <= NOW() - INTERVAL :dias DAY";
$params[':dias'] = $dias_para_alerta;

if (in_array($_SESSION['user_perfil'], ['vendedor'])) {
    $where_conditions[] = "p.responsavel_id = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
}

$sql_final = $sql_base . " WHERE " . implode(" AND ", $where_conditions) . " ORDER BY p.data_ultima_atualizacao ASC";

try {
    $stmt = $pdo->prepare($sql_final);
    $stmt->execute($params);
    $prospeccoes_para_retorno = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar prospecções para retorno: " . $e->getMessage());
}

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="border-b border-gray-200 pb-4 mb-4">
    <h1 class="text-2xl font-bold text-gray-800">Prospecções para Retorno</h1>
    <p class="mt-1 text-sm text-gray-500">
        Listando prospecções ativas sem interação há mais de <span class="font-semibold"><?php echo $dias_para_alerta; ?></span> dias.
    </p>
</div>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prospecto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsável</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Atualização</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Dias Sem Contato</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($prospeccoes_para_retorno) > 0): ?>
                    <?php foreach ($prospeccoes_para_retorno as $prospeccao): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($prospeccao['nome_prospecto']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($prospeccao['responsavel_nome'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($prospeccao['data_ultima_atualizacao'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-red-600">
                                <?php echo $prospeccao['dias_sem_contato']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <!-- Correção: Caminho absoluto para o link 'Ver Detalhes' -->
                                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/detalhes.php?id=<?php echo $prospeccao['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Ver Detalhes</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="py-4">
                                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Parabéns!</h3>
                                <p class="mt-1 text-sm text-gray-500">Nenhuma prospecção precisa de retorno no momento.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>