<?php
session_start();

require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/controllers/FinanceiroController.php';
require_once __DIR__ . '/app/core/access_control.php';

require_permission(['admin', 'financeiro', 'gerencia']);

global $pdo;

$controller = new FinanceiroController($pdo);

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

if ($action === 'update_field') {
    $controller->updateFinancialField();
    return;
}

$startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-01');
$endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-t');
$groupBy = filter_input(INPUT_GET, 'group_by', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'month';
$vendedorId = filter_input(INPUT_GET, 'vendedor_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$formaPagamentoId = filter_input(INPUT_GET, 'forma_pagamento_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$clienteId = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-t');
}

$allowedGroupings = ['day', 'month', 'year'];
if (!in_array($groupBy, $allowedGroupings, true)) {
    $groupBy = 'month';
}

$filters = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'group_by' => $groupBy,
    'vendedor_id' => $vendedorId,
    'forma_pagamento_id' => $formaPagamentoId === false ? null : $formaPagamentoId,
    'cliente_id' => $clienteId,
];

$controller->index($filters);
