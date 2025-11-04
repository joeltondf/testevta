<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

$filename = 'modelo_importacao_leads.csv';
$headers = [
    'Nome do Lead / Empresa',
    'Nome do Lead Principal',
    'E-mail',
    'Telefone'
];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$resource = fopen('php://output', 'w');

if ($resource === false) {
    http_response_code(500);
    echo 'Não foi possível gerar o arquivo de modelo.';
    exit;
}

fputcsv($resource, $headers, ';');

fclose($resource);
exit;
