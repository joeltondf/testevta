<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';
require_once __DIR__ . '/../../app/services/LeadDistributor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
    exit();
}

$clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
if (!$clienteId) {
    $_SESSION['error_message'] = 'Lead associado é obrigatório.';
    header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
    exit();
}

$paymentProfileRaw = $_POST['perfil_pagamento'] ?? '';
$paymentProfile = is_string($paymentProfileRaw) ? mb_strtolower(trim($paymentProfileRaw), 'UTF-8') : '';
$allowedPaymentProfiles = ['mensalista', 'avista'];

if (!in_array($paymentProfile, $allowedPaymentProfiles, true)) {
    $_SESSION['error_message'] = 'Selecione um perfil de pagamento válido.';
    header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userPerfil = $_SESSION['user_perfil'] ?? '';
$userNome = $_SESSION['user_nome'] ?? 'Usuário';

$prospectionModel = new Prospeccao($pdo);
$userModel = new User($pdo);

try {
    $stmtCliente = $pdo->prepare(
        'SELECT c.id,
                c.nome_cliente,
                c.nome_responsavel,
                c.crmOwnerId,
                owner.nome_completo AS crm_owner_name
         FROM clientes c
         LEFT JOIN users owner ON owner.id = c.crmOwnerId
         WHERE c.id = :id AND c.is_prospect = 1'
    );
    $stmtCliente->bindValue(':id', $clienteId, PDO::PARAM_INT);
    $stmtCliente->execute();
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $_SESSION['error_message'] = 'Lead não encontrado ou já convertido.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }

    if ($prospectionModel->hasActiveProspectionForClient($clienteId)) {
        $_SESSION['error_message'] = 'Este lead já possui uma prospecção ativa.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }

    $predefinedVendorId = (int) ($cliente['crmOwnerId'] ?? 0);
    if ($predefinedVendorId <= 0 || $predefinedVendorId === 17) {
        $predefinedVendorId = null;
    }

    if ($predefinedVendorId === null) {
        $lastProspection = $prospectionModel->findLatestProspectionByClient($clienteId);
        if ($lastProspection && !empty($lastProspection['responsavel_id'])) {
            $predefinedVendorId = (int) $lastProspection['responsavel_id'];
        }
    }

    if ($predefinedVendorId !== null && $userModel->findActiveVendorById($predefinedVendorId) === null) {
        $predefinedVendorId = null;
    }

    $isVendor = $userPerfil === 'vendedor';
    $isSdr = $userPerfil === 'sdr';

    if ($isVendor && $userId > 0) {
        $responsavelId = $userId;
    } elseif ($predefinedVendorId !== null) {
        $responsavelId = $predefinedVendorId;
    } else {
        $responsavelId = null;
    }

    $sdrId = $isSdr ? $userId : null;
    $shouldDistributeNow = $responsavelId === null && $predefinedVendorId === null && $isSdr;

    if ($isVendor && $cliente['crmOwnerId'] && (int) $cliente['crmOwnerId'] !== $userId) {
        $_SESSION['error_message'] = 'Você não tem permissão para utilizar este lead.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }

    $nomeProspecto = trim((string) ($cliente['nome_responsavel'] ?? ''));
    if ($nomeProspecto === '') {
        $nomeProspecto = trim((string) ($cliente['nome_cliente'] ?? ''));
    }
    if ($nomeProspecto === '') {
        $nomeProspecto = 'Lead #' . $clienteId;
    }

    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare(
        'INSERT INTO prospeccoes (
            cliente_id,
            nome_prospecto,
            data_prospeccao,
            responsavel_id,
            sdrId,
            feedback_inicial,
            valor_proposto,
            status,
            leadCategory,
            perfil_pagamento,
            data_ultima_atualizacao
        ) VALUES (
            :cliente_id,
            :nome_prospecto,
            NOW(),
            :responsavel_id,
            :sdr_id,
            :feedback_inicial,
            :valor_proposto,
            :status,
            :lead_category,
            :perfil_pagamento,
            NOW()
        )'
    );

    $stmtInsert->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmtInsert->bindValue(':nome_prospecto', $nomeProspecto, PDO::PARAM_STR);
    if ($responsavelId !== null) {
        $stmtInsert->bindValue(':responsavel_id', $responsavelId, PDO::PARAM_INT);
    } else {
        $stmtInsert->bindValue(':responsavel_id', null, PDO::PARAM_NULL);
    }
    if ($sdrId !== null) {
        $stmtInsert->bindValue(':sdr_id', $sdrId, PDO::PARAM_INT);
    } else {
        $stmtInsert->bindValue(':sdr_id', null, PDO::PARAM_NULL);
    }
    $stmtInsert->bindValue(':feedback_inicial', '', PDO::PARAM_STR);
    $stmtInsert->bindValue(':valor_proposto', 0, PDO::PARAM_INT);
    $stmtInsert->bindValue(':status', 'Novo', PDO::PARAM_STR);
    $stmtInsert->bindValue(':lead_category', 'Entrada', PDO::PARAM_STR);
    $stmtInsert->bindValue(':perfil_pagamento', $paymentProfile, PDO::PARAM_STR);

    $stmtInsert->execute();

    $prospectionId = (int) $pdo->lastInsertId();
    $prospectionModel->logInteraction($prospectionId, $userId, sprintf('Prospecção criada por %s.', $userNome));

    $distributionSummary = null;
    $distributionWarning = null;

    if ($responsavelId !== null) {
        $operatorId = $isSdr ? $userId : null;
        $prospectionModel->registerLeadDistribution($prospectionId, $responsavelId, $operatorId);

        $vendorData = $userModel->findActiveVendorById($responsavelId);
        $vendorName = $vendorData['nome_completo'] ?? ($cliente['crm_owner_name'] ?? 'Vendedor');

        if ($isVendor && $responsavelId === $userId) {
            $interactionText = sprintf('Lead vinculado automaticamente ao vendedor %s.', $userNome);
        } elseif ($predefinedVendorId !== null) {
            $interactionText = sprintf('Lead mantido com o vendedor %s conforme cadastro.', $vendorName);
        } else {
            $interactionText = sprintf('Lead atribuído manualmente ao vendedor %s.', $vendorName);
        }

        $prospectionModel->logInteraction($prospectionId, $userId, $interactionText);
        $distributionSummary = ['vendorName' => $vendorName];
    } elseif ($shouldDistributeNow) {
        try {
            $leadDistributor = new LeadDistributor($pdo);
            $distributionSummary = $leadDistributor->distributeToNextSalesperson($prospectionId, $userId);
            $interactionText = sprintf('Lead distribuído automaticamente para %s via round-robin.', $distributionSummary['vendorName']);
            $prospectionModel->logInteraction($prospectionId, $userId, $interactionText);
        } catch (Throwable $distributionException) {
            $distributionWarning = $distributionException->getMessage();
            $prospectionModel->logInteraction(
                $prospectionId,
                $userId,
                'Falha na distribuição automática: ' . $distributionWarning
            );
        }
    }

    $pdo->commit();

    if ($distributionWarning !== null) {
        $_SESSION['success_message'] = 'Prospecção criada, porém a distribuição automática não pôde ser concluída: ' . $distributionWarning;
    } elseif ($distributionSummary !== null) {
        $_SESSION['success_message'] = 'Prospecção criada e atribuída a ' . ($distributionSummary['vendorName'] ?? 'vendedor');
    } else {
        $_SESSION['success_message'] = 'Prospecção criada e aguardando atribuição de vendedor.';
    }

    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId);
    exit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Erro ao salvar a prospecção: ' . $exception->getMessage();
    header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
    exit();
}
