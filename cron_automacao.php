<?php
// /cron_automacao.php
// Este script deve ser executado por um Cron Job no servidor.

// Aumenta o tempo de execução para evitar que o script pare no meio
set_time_limit(0); 

// Carrega todos os arquivos necessários
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/models/Configuracao.php';
require_once __DIR__ . '/app/models/AutomacaoModel.php';
require_once __DIR__ . '/app/models/Cliente.php';
require_once __DIR__ . '/app/services/DigicApiService.php';
require_once __DIR__ . '/app/services/EmailService.php';

echo "--- Iniciando script de automação em " . date('Y-m-d H:i:s') . " ---\n";

// Inicializa os objetos
$configModel = new Configuracao($pdo);
$automacaoModel = new AutomacaoModel($pdo);

// =======================================================================
// ETAPA 1: Popular a Fila de Envio com Clientes Elegíveis
// =======================================================================
echo "Etapa 1: Verificando clientes elegíveis para popular a fila...\n";
try {
    $campanhasAtivas = $automacaoModel->getCampanhasAtivas();
    if (empty($campanhasAtivas)) {
        echo "Nenhuma campanha ativa encontrada. Finalizando.\n";
        exit;
    }

    foreach ($campanhasAtivas as $campanha) {
        $gatilhos = json_decode($campanha['crm_gatilhos'], true);
        if (empty($gatilhos) || !is_array($gatilhos)) continue;

        $clientesElegiveis = $automacaoModel->getClientesPorStatusDeProspeccao($gatilhos);

        foreach ($clientesElegiveis as $cliente) {
            $jaEnviado = $automacaoModel->verificarEnvioRecente($campanha['id'], $cliente['id'], $campanha['intervalo_reenvio_dias']);
            $jaNaFila = $automacaoModel->verificarSeEstaNaFila($campanha['id'], $cliente['id']);

            if (!$jaEnviado && !$jaNaFila && !empty($cliente['telefone'])) {
                $automacaoModel->adicionarNaFila($campanha['id'], $cliente['id'], $cliente['telefone']);
                echo " - Cliente #{$cliente['id']} adicionado à fila para a campanha '{$campanha['nome_campanha']}'.\n";
            }
        }
    }
} catch (Exception $e) {
    echo "ERRO na Etapa 1: " . $e->getMessage() . "\n";
}

// =======================================================================
// ETAPA 2: Processar a Fila de Envio (com o limite diário)
// =======================================================================
echo "\nEtapa 2: Processando a fila de envio...\n";

$token = $configModel->get('digisac_api_token');
$apiUrl = $configModel->get('digisac_api_url');
if (empty($apiUrl) || empty($token)) {
    die("ERRO: URL ou Token da API Digisac não configurados.\n");
}

$limiteDiario = 100;
$enviosHoje = $automacaoModel->contarEnviosDeHoje();
$enviosRestantes = $limiteDiario - $enviosHoje;

if ($enviosRestantes <= 0) {
    echo "Limite diário de {$limiteDiario} envios já atingido. Finalizando.\n";
    exit;
}

echo "Limite de envios para hoje: {$enviosRestantes}\n";
$itensDaFila = $automacaoModel->getItensDaFila($enviosRestantes);

if (empty($itensDaFila)) {
    echo "Fila de envio vazia. Finalizando.\n";
    exit;
}

$digicApi = new DigicApiService($apiUrl, $token);
$totalEnviado = 0;

foreach ($itensDaFila as $item) {
    try {
        $params = [];
        $mapeamento = json_decode($item['mapeamento_parametros'], true);
        if (is_array($mapeamento)) {
            ksort($mapeamento);
            foreach ($mapeamento as $pos => $campo) {
                $params[] = $item[$campo] ?? '';
            }
        }

        $sucesso = $digicApi->sendMessageByNumber(
            $item['numero_destino'], 
            $item['digisac_conexao_id'], 
            $item['digisac_template_id'], 
            $params
        );
        
        if ($sucesso) {
            echo " - Mensagem enviada para {$item['numero_destino']}. Sucesso.\n";
            $automacaoModel->logarEnvio($item['campanha_id'], $item['cliente_id'], 'sucesso');
            $totalEnviado++;
        } else {
            echo " - Mensagem para {$item['numero_destino']}. Falha.\n";
            $automacaoModel->logarEnvio($item['campanha_id'], $item['cliente_id'], 'falha', 'Erro na API Digisac');
        }

    } catch (Exception $e) {
        echo "ERRO ao processar item da fila #{$item['fila_id']}: " . $e->getMessage() . "\n";
        $automacaoModel->logarEnvio($item['campanha_id'], $item['cliente_id'], 'falha', $e->getMessage());
    }
    
    // Remove da fila independentemente do resultado para não tentar enviar de novo
    $automacaoModel->removerDaFila($item['fila_id']);
    sleep(2); // Pausa de 2 segundos entre cada envio para não sobrecarregar
}

echo "--- Script de automação finalizado. Total de {$totalEnviado} mensagens enviadas. ---\n";