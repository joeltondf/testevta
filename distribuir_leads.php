<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia', 'supervisor']);
require_once __DIR__ . '/app/controllers/LeadDistributionController.php';

$controller = new LeadDistributionController($pdo);
$controller->auto();
