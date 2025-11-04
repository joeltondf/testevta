<?php
// /relatorio_vendedor.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['vendedor']);

require_once 'app/controllers/VendedorReportController.php';

$controller = new VendedorReportController($pdo);
$controller->index();