<?php
/**
 * @file /cron_alertas_vencidos.php
 * @description Script para ser executado via CRON para enviar alertas de serviços vencidos.
 */

// Silencia a saída normal, útil para CRON
ob_start();

// Carrega as configurações principais e a conexão com o banco
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/models/Configuracao.php';
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/services/EmailService.php';

// --- Início da Lógica do Script ---

$configModel = new Configuracao($pdo);

// 1. VERIFICA SE O GATILHO ESTÁ ATIVO NAS CONFIGURAÇÕES
$alertaAtivo = $configModel->get('alert_servico_vencido_enabled');
if ($alertaAtivo !== '1') {
    echo "Alerta de serviços vencidos está desativado. Encerrando script.\n";
    exit(); // Encerra o script se o alerta não estiver ativado
}

$destinatarios = $configModel->get('alert_emails');
if (empty($destinatarios)) {
    echo "Nenhum e-mail de alerta configurado. Encerrando script.\n";
    exit(); // Encerra se não houver para quem enviar
}

// 2. BUSCA OS SERVIÇOS VENCIDOS NO BANCO DE DADOS
$processoModel = new Processo($pdo);
$servicosVencidos = $processoModel->getServicosVencidos(); // Precisaremos criar este método no Model

if (empty($servicosVencidos)) {
    echo "Nenhum serviço vencido encontrado hoje. Encerrando script.\n";
    exit(); // Encerra se não houver serviços vencidos
}

// 3. MONTA E ENVIA O E-MAIL DE ALERTA

$assunto = "Alerta: " . count($servicosVencidos) . " Serviço(s) com Prazo Vencido";

// Inicia a construção do corpo do e-mail
$corpo = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f7; }
        .container { width: 100%; max-width: 700px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { background-color: #dc3545; color: #ffffff; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .content p { color: #555555; line-height: 1.6; }
        .process-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .process-table th { background-color: #f2f2f2; padding: 10px; border: 1px solid #ddd; text-align: left; }
        .process-table td { padding: 10px; border: 1px solid #ddd; }
        .process-table .vencido { color: #dc3545; font-weight: bold; }
        .footer { background-color: #f4f4f7; padding: 20px; text-align: center; color: #888888; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Alerta de Prazos Vencidos</h1>
        </div>
        <div class='content'>
            <p>Olá! O sistema identificou os seguintes serviços cujo prazo de entrega está vencido e que ainda não foram marcados como 'Concluído'.</p>
            <table class='process-table'>
                <thead>
                    <tr>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Data do Prazo</th>
                        <th>Status Atual</th>
                    </tr>
                </thead>
                <tbody>";

// Adiciona uma linha na tabela para cada serviço vencido
foreach ($servicosVencidos as $processo) {
    $dataPrazo = date('d/m/Y', strtotime($processo['traducao_prazo_data']));
    $corpo .= "
        <tr>
            <td><a href='https://" . $_SERVER['HTTP_HOST'] . "/processos.php?action=view&id={$processo['id']}'>{$processo['orcamento_numero']}</a></td>
            <td>" . htmlspecialchars($processo['nome_cliente']) . "</td>
            <td class='vencido'>{$dataPrazo}</td>
            <td>" . htmlspecialchars($processo['status_processo']) . "</td>
        </tr>
    ";
}

$corpo .= "
                </tbody>
            </table>
        </div>
        <div class='footer'>
            Este é um relatório automático gerado em " . date('d/m/Y H:i:s') . ".
        </div>
    </div>
</body>
</html>
";

// 4. ENVIA O E-MAIL USANDO O NOSSO SERVIÇO
try {
    $emailService = new EmailService($pdo);
    $emailService->sendEmail($destinatarios, $assunto, $corpo);
    echo "E-mail de alerta de vencidos enviado com sucesso para: " . $destinatarios . "\n";
} catch (Exception $e) {
    echo "ERRO AO ENVIAR E-MAIL: " . $e->getMessage() . "\n";
    error_log("CRON VENCIDOS: " . $e->getMessage());
}

// Limpa o buffer e loga a saída (opcional, bom para depuração)
$output = ob_get_clean();
file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Alertas Vencidos: " . $output . "\n", FILE_APPEND);

?>