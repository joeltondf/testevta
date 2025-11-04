<?php
// /vendas.php - Relatório de Desempenho de Vendas

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/VendasController.php';

// Instancia o controller
$controller = new VendasController($pdo);

// A única ação agora é exibir o relatório (index)
$controller->index();