<?php
/**
 * @file /app/controllers/FinanceiroController.php
 */

require_once __DIR__ . '/../models/Processo.php';

class FinanceiroController
{
    private PDO $pdo;
    private Processo $processoModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($this->pdo);
    }

    public function index(array $filters): void
    {
        $csrfToken = $this->getCsrfToken();

        if (!empty($_GET) && isset($_GET['csrf_token']) && !$this->isValidCsrfToken($_GET['csrf_token'])) {
            $_SESSION['error_message'] = 'Sessão expirada. Filtros não aplicados.';
            $filters['start_date'] = date('Y-m-01');
            $filters['end_date'] = date('Y-m-t');
            $filters['group_by'] = 'month';
            $filters['vendedor_id'] = null;
            $filters['forma_pagamento_id'] = null;
            $filters['cliente_id'] = null;
        }

        $modelFilters = [
            'data_inicio' => $filters['start_date'] ?? date('Y-m-01'),
            'data_fim' => $filters['end_date'] ?? date('Y-m-t'),
            'vendedor_id' => $filters['vendedor_id'] ?? null,
            'forma_pagamento_id' => $filters['forma_pagamento_id'] ?? null,
            'cliente_id' => $filters['cliente_id'] ?? null,
        ];

        $groupBy = $filters['group_by'] ?? 'month';

        $processos = $this->processoModel->getFinancialData($modelFilters);
        $overallSummary = $this->processoModel->getOverallFinancialSummary($filters['start_date'], $filters['end_date'], $modelFilters);
        $aggregatedTotals = $this->processoModel->getAggregatedFinancialTotals($filters['start_date'], $filters['end_date'], $modelFilters, $groupBy);

        $vendedores = $this->processoModel->getAllVendedores();
        $formas_pagamento = $this->processoModel->getAllFormasPagamento();
        $clientes = $this->processoModel->getAllClientes();
        $statusFinanceiroOptions = $this->processoModel->getStatusFinanceiroOptions();

        $pageTitle = 'Relatório Financeiro';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/financeiro/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function updateFinancialField(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondJson(405, 'Método não permitido.');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->isValidCsrfToken($token)) {
            $this->respondJson(403, 'Token de segurança inválido ou expirado.');
        }

        $processId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $field = filter_input(INPUT_POST, 'field', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
        $rawValue = $_POST['value'] ?? null;

        if ($processId === null || $field === '' || $rawValue === null) {
            $this->respondJson(400, 'Dados inválidos ou incompletos.');
        }

        $sanitizedValue = null;
        switch ($field) {
            case 'valor_total':
            case 'desconto':
            case 'valor_recebido':
            case 'valor_restante':
                $sanitizedValue = $this->processoModel->parseCurrency((string) $rawValue);
                break;
            case 'data_pagamento':
                $value = trim((string) $rawValue);
                if ($value === '') {
                    $sanitizedValue = null;
                } else {
                    $date = DateTime::createFromFormat('Y-m-d', $value) ?: DateTime::createFromFormat('d/m/Y', $value);
                    if (!$date) {
                        $this->respondJson(400, 'Data informada é inválida.');
                    }
                    $sanitizedValue = $date->format('Y-m-d');
                }
                break;
            case 'forma_pagamento_id':
                $value = filter_var($rawValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $sanitizedValue = $value ?: null;
                break;
            case 'status_financeiro':
                $value = mb_strtolower(trim((string) $rawValue), 'UTF-8');
                $allowedStatuses = array_keys($this->processoModel->getStatusFinanceiroOptions());
                if (!in_array($value, $allowedStatuses, true)) {
                    $this->respondJson(400, 'Status financeiro inválido.');
                }
                $sanitizedValue = $value;
                break;
            default:
                $this->respondJson(400, 'Campo não permitido para atualização.');
        }

        $success = $this->processoModel->updateProcessFinancialField($processId, $field, $sanitizedValue);

        if (!$success) {
            $this->respondJson(500, 'Falha ao atualizar o campo no banco de dados.');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Campo atualizado com sucesso.',
        ]);
        exit;
    }

    private function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function isValidCsrfToken(?string $token): bool
    {
        if ($token === null || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    private function respondJson(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error',
            'message' => $message,
        ]);
        exit;
    }
}
