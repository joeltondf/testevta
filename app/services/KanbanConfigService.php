<?php

require_once __DIR__ . "/../models/Configuracao.php";


class KanbanConfigService
{
    private const CONFIG_KEY = 'kanban_columns';

    private Configuracao $configModel;

    /** @var array<string> */
    private array $defaultColumns = [
        'Contato ativo',
        'Primeiro contato',
        'Segundo contato',
        'Terceiro contato',
        'Reunião agendada',
        'Proposta enviada',
        'Fechamento',
        'Pausar'
    ];

    public function __construct(PDO $pdo)
    {
        $this->configModel = new Configuracao($pdo);
    }

    /**
     * @return array<string>
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

        $normalized = [];

        foreach ($decoded as $column) {
            $name = trim((string)$column);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        return !empty($normalized) ? $normalized : $this->defaultColumns;
    }

    /**
     * @param array<string> $columns
     */
    public function saveColumns(array $columns): bool
    {
        $normalized = [];

        foreach ($columns as $column) {
            $name = trim((string)$column);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        if (empty($normalized)) {
            throw new InvalidArgumentException('Informe ao menos uma coluna válida para o Kanban.');
        }

        $jsonValue = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($jsonValue === false) {
            throw new RuntimeException('Não foi possível serializar as colunas do Kanban.');
        }

        return $this->configModel->save(self::CONFIG_KEY, $jsonValue);
    }

    /**
     * @return array<string>
     */
    public function getDefaultColumns(): array
    {
        return $this->defaultColumns;
    }
}
