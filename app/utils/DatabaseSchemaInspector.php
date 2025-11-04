<?php

declare(strict_types=1);

class DatabaseSchemaInspector
{
    private static array $columnCache = [];

    public static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $tableKey = strtolower($table);
        if (!isset(self::$columnCache[$tableKey])) {
            self::$columnCache[$tableKey] = self::fetchColumns($pdo, $table);
        }

        return in_array(strtolower($column), self::$columnCache[$tableKey], true);
    }

    private static function fetchColumns(PDO $pdo, string $table): array
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare(sprintf('SHOW COLUMNS FROM `%s`', $table));
            $stmt->execute();
            $columns = array_map(static fn ($row) => strtolower($row['Field']), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->prepare(sprintf('PRAGMA table_info(`%s`)', $table));
            $stmt->execute();
            $columns = array_map(static fn ($row) => strtolower($row['name']), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_name = :table');
            $stmt->execute(['table' => $table]);
            $columns = array_map(static fn ($row) => strtolower($row['column_name']), $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        return $columns;
    }
}

