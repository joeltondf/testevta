<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/models/Configuracao.php';
require_once __DIR__ . '/app/services/OmieSyncService.php';

if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado via CLI." . PHP_EOL;
    exit(1);
}

$syncMap = [
    'etapas' => 'syncEtapasFaturamento',
    'categorias' => 'syncCategorias',
    'contas' => 'syncContasCorrentes',
    'cenarios' => 'syncCenariosFiscais',
    'produtos' => 'syncProdutos',
];

$resource = $argv[1] ?? 'all';
$configModel = new Configuracao($pdo);
$syncService = new OmieSyncService($pdo, $configModel);

try {
    if ($resource === 'all') {
        echo "Sincronizando todas as tabelas de suporte da Omie..." . PHP_EOL;
        $results = $syncService->syncSupportTables();
        foreach ($results as $key => $payload) {
            $total = (int)($payload['total'] ?? 0);
            echo sprintf("- %s: %d registro(s) atualizado(s).", ucfirst($key), $total) . PHP_EOL;
        }
        echo "Sincronização concluída." . PHP_EOL;
        exit(0);
    }

    if (!array_key_exists($resource, $syncMap)) {
        $allowed = implode(', ', array_merge(['all'], array_keys($syncMap)));
        throw new InvalidArgumentException('Recurso inválido. Utilize um dos seguintes: ' . $allowed . '.');
    }

    $method = $syncMap[$resource];
    $result = $syncService->{$method}();
    $total = (int)($result['total'] ?? 0);
    echo sprintf('%s sincronizado(a) com sucesso. %d registro(s) atualizado(s).', ucfirst($resource), $total) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao sincronizar dados da Omie: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
