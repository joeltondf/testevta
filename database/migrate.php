<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/migrations/20240920120000_prepare_omie_integration.php';
require_once __DIR__ . '/migrations/20241010120000_ensure_cliente_integration_code.php';

try {
    $migration = new PrepareOmieIntegrationMigration($pdo);
    $migration->up();

    $clienteMigration = new EnsureClienteIntegrationCodeMigration($pdo);
    $clienteMigration->up();

    if (PHP_SAPI === 'cli') {
        echo "Omie integration migrations executed successfully." . PHP_EOL;
    }
} catch (Throwable $exception) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    }
    throw $exception;
}
