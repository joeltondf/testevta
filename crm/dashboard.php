<?php
// Arquivo: crm/dashboard.php (VERSÃO CORRIGIDA E INTEGRADA)

// 1. LÓGICA PHP PRIMEIRO
// =================================================================
require_once __DIR__ . '/../config.php'; // Sobe um nível para a raiz
require_once __DIR__ . '/../app/core/auth_check.php';

// --- LÓGICA DOS FILTROS ---
$filter_sdr_id = filter_input(INPUT_GET, 'sdr_id', FILTER_VALIDATE_INT);
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($filter_sdr_id) {
    $where_clauses[] = "p.responsavel_id = :sdr_id";
    $params[':sdr_id'] = $filter_sdr_id;
}
if (!empty($filter_start_date)) {
    $where_clauses[] = "p.data_prospeccao >= :start_date";
    $params[':start_date'] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $end_date_inclusive = date('Y-m-d', strtotime($filter_end_date . ' +1 day'));
    $where_clauses[] = "p.data_prospeccao < :end_date";
    $params[':end_date'] = $end_date_inclusive;
}

$where_sql = implode(" AND ", $where_clauses);

try {
    // --- KPIs de Prospecções (afetados pelos filtros) ---
    $stmt_criados = $pdo->prepare("SELECT COUNT(*) FROM prospeccoes p WHERE $where_sql");
    $stmt_criados->execute($params);
    $total_criados = $stmt_criados->fetchColumn();

    $stmt_convertidos = $pdo->prepare("SELECT COUNT(*) FROM prospeccoes p WHERE status = 'Convertido' AND $where_sql");
    $stmt_convertidos->execute($params);
    $total_convertidos = $stmt_convertidos->fetchColumn();
    
    $taxa_conversao = ($total_criados > 0) ? ($total_convertidos / $total_criados) * 100 : 0;
    
    $stmt_valor_ganho = $pdo->prepare("SELECT SUM(valor_proposto) FROM prospeccoes p WHERE status = 'Convertido' AND $where_sql");
    $stmt_valor_ganho->execute($params);
    $valor_total_ganho = $stmt_valor_ganho->fetchColumn();

    // --- GRÁFICOS ---

    // Gráfico de Status
    $stmt_grafico_status = $pdo->prepare("SELECT status, COUNT(*) as total FROM prospeccoes p WHERE $where_sql GROUP BY status");
    $stmt_grafico_status->execute($params);
    $dados_grafico_status = $stmt_grafico_status->fetchAll(PDO::FETCH_ASSOC);
    $labels_status = array_column($dados_grafico_status, 'status');
    $valores_status = array_column($dados_grafico_status, 'total');

    // Gráfico de SDRs (Responsáveis) - CORRIGIDO
    $stmt_grafico_sdr = $pdo->prepare("
        SELECT u.nome_completo, COUNT(p.id) as total 
        FROM users u 
        LEFT JOIN prospeccoes p ON u.id = p.responsavel_id AND ($where_sql) 
        WHERE u.perfil IN ('vendedor', 'gerencia', 'supervisor') -- Correção: Tabela 'users' e coluna 'perfil'
        GROUP BY u.id, u.nome_completo 
        ORDER BY total DESC
    ");
    $stmt_grafico_sdr->execute($params);
    $dados_grafico_sdr = $stmt_grafico_sdr->fetchAll(PDO::FETCH_ASSOC);
    $labels_sdr = array_column($dados_grafico_sdr, 'nome_completo');
    $valores_sdr = array_column($dados_grafico_sdr, 'total');
    
    // Lista de Responsáveis para o filtro - CORRIGIDO
    $stmt_users = $pdo->prepare("SELECT id, nome_completo FROM users WHERE perfil IN ('vendedor', 'gerencia', 'supervisor') ORDER BY nome_completo"); // Correção: Tabela 'users' e coluna 'perfil'
    $stmt_users->execute();
    $responsaveis_filtro = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // Gráfico de Canal de Origem - CORRIGIDO
    $stmt_grafico_canal = $pdo->prepare("SELECT canal_origem, COUNT(*) as total FROM clientes WHERE canal_origem IS NOT NULL AND canal_origem != '' AND is_prospect = 1 GROUP BY canal_origem ORDER BY total DESC");
    $stmt_grafico_canal->execute();
    $dados_grafico_canal = $stmt_grafico_canal->fetchAll(PDO::FETCH_ASSOC);
    $labels_canal = array_column($dados_grafico_canal, 'canal_origem');
    $valores_canal = array_column($dados_grafico_canal, 'total');

} catch (PDOException $e) { 
    die("Erro ao buscar dados do dashboard: " . $e->getMessage()); 
}

// 2. HTML DEPOIS
// =================================================================
require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="border-b border-gray-200 pb-5 mb-5">
    <h1 class="text-3xl font-bold leading-tight text-gray-900">Dashboard de Performance</h1>
    <form method="GET" action="dashboard.php" class="mt-4 p-4 bg-gray-50 rounded-lg border">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="start_date" class="text-sm font-medium text-gray-700">Data Início</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label for="end_date" class="text-sm font-medium text-gray-700">Data Fim</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label for="sdr_id" class="text-sm font-medium text-gray-700">Responsável</label>
                <select name="sdr_id" id="sdr_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="">Todos</option>
                    <?php foreach ($responsaveis_filtro as $sdr): ?>
                        <option value="<?php echo $sdr['id']; ?>" <?php echo ($sdr['id'] == $filter_sdr_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sdr['nome_completo']); // Correção: 'nome_completo' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 w-full">Filtrar</button>
            </div>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <div class="bg-white overflow-hidden shadow rounded-lg p-5">
        <dt class="text-sm font-medium text-gray-500 truncate">Leads Criados no Período</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $total_criados; ?></dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg p-5">
        <dt class="text-sm font-medium text-gray-500 truncate">Taxa de Conversão</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo number_format($taxa_conversao, 1, ',', '.'); ?>%</dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg p-5">
        <dt class="text-sm font-medium text-gray-500 truncate">Leads Convertidos</dt>
        <dd class="mt-1 text-3xl font-semibold text-green-600"><?php echo $total_convertidos; ?></dd>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg p-5">
        <dt class="text-sm font-medium text-gray-500 truncate">Valor Total Ganho</dt>
        <dd class="mt-1 text-3xl font-semibold text-green-600">R$ <?php echo number_format($valor_total_ganho ?? 0, 2, ',', '.'); ?></dd>
    </div>
</div>

<div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
    <div class="bg-white overflow-hidden shadow rounded-lg p-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Prospecções por Status</h3>
        <div class="mt-4" style="height: 300px;"><canvas id="graficoStatus"></canvas></div>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg p-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Prospecções por Responsável</h3>
        <div class="mt-4" style="height: 300px;"><canvas id="graficoResponsavel"></canvas></div>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg p-6 lg:col-span-2">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Leads por Canal de Origem</h3>
        <div class="mt-4" style="height: 300px;"><canvas id="graficoCanais"></canvas></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', (event) => {
    const chartColors = ['rgba(59, 130, 246, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(107, 114, 128, 0.7)', 'rgba(139, 92, 246, 0.7)', 'rgba(236, 72, 153, 0.7)', 'rgba(34, 211, 238, 0.7)'];
    
    // Gráfico de Status
    const ctxStatus = document.getElementById('graficoStatus').getContext('2d');
    new Chart(ctxStatus, { type: 'doughnut', data: { labels: <?php echo json_encode($labels_status); ?>, datasets: [{ label: 'Status', data: <?php echo json_encode($valores_status); ?>, backgroundColor: chartColors, borderColor: '#fff', borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false } });
    
    // Gráfico de Responsáveis
    const ctxResponsavel = document.getElementById('graficoResponsavel').getContext('2d');
    new Chart(ctxResponsavel, { type: 'bar', data: { labels: <?php echo json_encode($labels_sdr); ?>, datasets: [{ label: 'Leads por Responsável', data: <?php echo json_encode($valores_sdr); ?>, backgroundColor: 'rgba(239, 68, 68, 0.7)', }] }, options: { indexAxis: 'y', scales: { y: { beginAtZero: true } }, responsive: true, maintainAspectRatio: false } });
    
    // Gráfico de Canais
    const ctxCanais = document.getElementById('graficoCanais').getContext('2d');
    new Chart(ctxCanais, { type: 'bar', data: { labels: <?php echo json_encode($labels_canal); ?>, datasets: [{ label: 'Leads por Canal', data: <?php echo json_encode($valores_canal); ?>, backgroundColor: 'rgba(59, 130, 246, 0.7)', }] }, options: { scales: { y: { beginAtZero: true } }, responsive: true, maintainAspectRatio: false } });
});
</script>

<?php 
// 3. Inclui o footer no final
require_once __DIR__ . '/../app/views/layouts/footer.php'; 
?>