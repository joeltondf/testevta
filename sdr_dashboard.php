<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['sdr']);
require_once __DIR__ . '/app/controllers/SdrDashboardController.php';

$controller = new SdrDashboardController($pdo);
$action = $_GET['action'] ?? 'index';

if ($action === 'update_lead_status') {
    $controller->updateLeadStatus();
} elseif ($action === 'assign_lead_owner') {
    $controller->assignLeadOwner();
} elseif ($action === 'listar_leads') {
    $controller->listarLeads();
} elseif ($action === 'update_columns') {
    $controller->updateKanbanColumns();
} elseif ($action === 'create_lead') {
    $controller->createLead();
} else {
    $controller->index();
}
