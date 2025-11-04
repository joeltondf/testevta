<?php
// Inclui o autoloader do Composer, que carrega a biblioteca do Google
require_once __DIR__ . '/vendor/autoload.php';

// O config.php provavelmente já cuida da sessão e de outras configurações
require_once __DIR__ . '/config.php';

// Inclui os arquivos do seu aplicativo
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/models/Cliente.php'; 
require_once __DIR__ . '/app/services/GoogleDriveService.php';


// GARANTIA: Inicia a sessão apenas se o config.php não o fez
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado.');
}

$anexoId = $_GET['id_anexo'] ?? null;
if (!$anexoId) {
    die('ID do anexo não fornecido.');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $processoModel = new Processo($pdo);
    $anexo = $processoModel->getAnexoGdriveById($anexoId);
    $processo = $processoModel->getProcessoDoAnexo($anexoId);

    if (!$anexo || !$processo) {
        die('Anexo ou processo não encontrado.');
    }

    // Lógica de permissão (Admin ou o próprio cliente)
    if ($_SESSION['user_perfil'] !== 'admin' && $processo['cliente_user_id'] != $_SESSION['user_id']) {
        die('Você não tem permissão para acessar este arquivo.');
    }

    // Concede permissão ao cliente para que o iframe funcione
    // Busca o email do cliente (você pode precisar ajustar esta lógica)
    $clienteModel = new Cliente($pdo); 
    $cliente = $clienteModel->getById($processo['cliente_id']);
    
    if ($cliente && !empty($cliente['email'])) {
        $gdriveService = new GoogleDriveService($pdo);
        $gdriveService->addReaderPermission($anexo['gdrive_file_id'], $cliente['email']);
    }

    // Agora, exibe a página com o iframe
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Visualizador de Documento</title>
        <style>
            body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
            iframe { border: none; width: 100%; height: 100%; }
        </style>
    </head>
    <body>
        <iframe src="<?= htmlspecialchars($anexo['embed_link']) ?>"></iframe>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    error_log("Erro no embed de anexo: " . $e->getMessage());
    die('Ocorreu um erro ao tentar exibir o documento.');
}