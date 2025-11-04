<?php

require_once __DIR__ . '/../models/Configuracao.php';

class SdrKanbanConfigService
{
    private const CONFIG_KEY = 'sdr_kanban_columns';
    private const REQUIRED_COLUMNS = ['Primeiro Contato', 'Agendamento'];

    private Configuracao $configModel;

    /** @var array<int, string> */
    private array $defaultColumns = [
        'Primeiro Contato',
        'Qualificação',
        'Agendamento',
        'Negociação',
        'Fechamento'
    ];

    public function __construct(PDO $pdo)
    {
        $this->configModel = new Configuracao($pdo);
    }

    /**
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        $rawValue = $this->configModel->get(self::CONFIG_KEY);

        if ($rawValue === null || trim($rawValue) === '') {
            return $this->defaultColumns;
        }

        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded)) {
            return $this->defaultColumns;
        }

        $normalized = $this->sanitizeColumns($decoded);
        $normalized = $this->ensureRequiredColumns($normalized);

        return !empty($normalized) ? $normalized : $this->defaultColumns;
    }

    /**
     * @param array<int, string> $columns
     */
    public function saveColumns(array $columns): bool
    {
        $normalized = $this->sanitizeColumns($columns);
        $normalized = $this->ensureRequiredColumns($normalized);

        if (empty($normalized)) {
            throw new InvalidArgumentException('Informe ao menos uma coluna válida.');
        }

        $jsonValue = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($jsonValue === false) {
            throw new RuntimeException('Não foi possível salvar as colunas do Kanban.');
        }

        return $this->configModel->save(self::CONFIG_KEY, $jsonValue);
    }

    /**
     * @param array<int, mixed> $columns
     * @return array<int, string>
     */
    private function sanitizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            $name = trim((string) $column);
            if ($name === '') {
                continue;
            }

            if (!in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function ensureRequiredColumns(array $columns): array
    {
        $result = $columns;

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            if (!in_array($requiredColumn, $result, true)) {
                $result[] = $requiredColumn;
            }
        }

        return $result;
    }
}
