<?php
// Arquivo: crm/prospeccoes/atualizar.php (VERSÃO ATUALIZADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php';
require_once __DIR__ . '/../../app/models/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_perfil = $_SESSION['user_perfil'] ?? '';
$prospectionModel = new Prospeccao($pdo);
$userModel = new User($pdo);

if (!$prospeccao_id) {
    die("ID da prospecção inválido.");
}

try {
    if ($action === 'add_interaction') {
        $observacao = trim($_POST['observacao'] ?? '');
        $tipoInteracao = $_POST['tipo_interacao'] ?? 'nota';
        $tipoPermitidos = ['nota', 'chamada', 'reuniao'];
        if (!in_array($tipoInteracao, $tipoPermitidos, true)) {
            $tipoInteracao = 'nota';
        }

        $resultadoChamada = trim($_POST['resultado'] ?? '');
        if ($tipoInteracao === 'chamada' && $resultadoChamada !== '') {
            $observacao = 'Resultado da chamada: ' . $resultadoChamada . (empty($observacao) ? '' : ' — ' . $observacao);
        }

        if (!empty($observacao)) {
            $sql = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$prospeccao_id, $user_id, $observacao, $tipoInteracao]);
        }

    } elseif ($action === 'assign_vendor') {
        $authorizedProfiles = ['admin', 'gerencia', 'supervisor'];
        if (!in_array($user_perfil, $authorizedProfiles, true)) {
            $_SESSION['error_message'] = 'Você não tem permissão para delegar este lead.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $vendorId = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);
        if (!$vendorId) {
            $_SESSION['error_message'] = 'Selecione um vendedor válido.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $lead = $prospectionModel->getById($prospeccao_id);
        if (!$lead) {
            $_SESSION['error_message'] = 'Prospecção não encontrada.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
            exit();
        }

        $delegationToken = $_POST['delegation_token'] ?? '';
        if ($delegationToken === '') {
            $_SESSION['error_message'] = 'Valide as credenciais de um gestor antes de delegar o lead.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $currentTime = time();
        if (isset($_SESSION['manager_authorization_tokens']) && is_array($_SESSION['manager_authorization_tokens'])) {
            foreach ($_SESSION['manager_authorization_tokens'] as $tokenValue => $tokenData) {
                if (!is_array($tokenData) || ($tokenData['expires_at'] ?? 0) < $currentTime) {
                    unset($_SESSION['manager_authorization_tokens'][$tokenValue]);
                }
            }
            if (empty($_SESSION['manager_authorization_tokens'])) {
                unset($_SESSION['manager_authorization_tokens']);
            }
        }

        if (!isset($_SESSION['manager_authorization_tokens'][$delegationToken])) {
            $_SESSION['error_message'] = 'A autorização do gestor expirou. Valide novamente para continuar.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $delegationData = $_SESSION['manager_authorization_tokens'][$delegationToken];
        if (!is_array($delegationData) || ($delegationData['expires_at'] ?? 0) < $currentTime) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'A autorização do gestor expirou. Valide novamente para continuar.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        if (($delegationData['context'] ?? 'vendor_delegation') !== 'vendor_delegation') {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'A autorização informada não é válida para esta ação.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $managerId = (int) ($delegationData['manager_id'] ?? 0);
        if ($managerId <= 0) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'Não foi possível confirmar o gestor responsável pela autorização.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $managerUser = $userModel->getById($managerId);
        if (!$managerUser || !in_array($managerUser['perfil'], $authorizedProfiles, true) || (int) ($managerUser['ativo'] ?? 1) !== 1) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'O usuário autorizado não possui privilégios de gestão.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $vendorData = $userModel->findActiveVendorById($vendorId);
        if ($vendorData === null) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'Selecione um vendedor ativo válido.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        $currentVendorId = (int) ($lead['responsavel_id'] ?? 0);
        if ($currentVendorId === $vendorId) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['success_message'] = 'O lead já está atribuído a este vendedor.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        if (!$prospectionModel->assignLeadToVendor($prospeccao_id, $vendorId, (int) $managerUser['id'])) {
            unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
            $_SESSION['error_message'] = 'Não foi possível atualizar o responsável pelo lead.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
            exit();
        }

        unset($_SESSION['manager_authorization_tokens'][$delegationToken]);
        $note = sprintf(
            'Lead delegado para %s por %s.',
            $vendorData['nome_completo'],
            $managerUser['nome_completo']
        );
        $prospectionModel->logInteraction($prospeccao_id, (int) $managerUser['id'], $note, 'log_sistema');

        $_SESSION['success_message'] = 'Lead delegado para ' . $vendorData['nome_completo'] . '.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id);
        exit();

    } elseif ($action === 'update_prospect') {

        $allowedLeadCategories = ['Entrada', 'Qualificado', 'Com Orçamento', 'Em Negociação', 'Cliente Ativo', 'Sem Interesse'];
        $leadCategory = $_POST['lead_category'] ?? 'Entrada';
        if (!in_array($leadCategory, $allowedLeadCategories, true)) {
            $leadCategory = 'Entrada';
        }

        $allowedPaymentProfiles = ['mensalista', 'avista'];
        $paymentProfile = $_POST['perfil_pagamento'] ?? '';
        $paymentProfile = in_array($paymentProfile, $allowedPaymentProfiles, true) ? $paymentProfile : null;

        $currentProspection = $prospectionModel->getById($prospeccao_id);
        if (!$currentProspection) {
            $_SESSION['error_message'] = 'Prospecção não encontrada.';
            header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
            exit();
        }

        $newData = [
            'reuniao_compareceu' => isset($_POST['reuniao_compareceu']) ? 1 : 0,
            'lead_category' => $leadCategory,
            'perfil_pagamento' => $paymentProfile
        ];

        $sql_update = "UPDATE prospeccoes SET
                            reuniao_compareceu = :reuniao_compareceu,
                            leadCategory = :lead_category,
                            perfil_pagamento = :perfil_pagamento
                        WHERE id = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->bindValue(':reuniao_compareceu', $newData['reuniao_compareceu'], PDO::PARAM_INT);
        $stmt_update->bindValue(':lead_category', $newData['lead_category'], PDO::PARAM_STR);
        if ($paymentProfile === null) {
            $stmt_update->bindValue(':perfil_pagamento', null, PDO::PARAM_NULL);
        } else {
            $stmt_update->bindValue(':perfil_pagamento', $paymentProfile, PDO::PARAM_STR);
        }
        $stmt_update->bindValue(':id', $prospeccao_id, PDO::PARAM_INT);
        $stmt_update->execute();

        $changes = [];

        $oldAttendance = (int) ($currentProspection['reuniao_compareceu'] ?? 0);
        if ($oldAttendance !== $newData['reuniao_compareceu']) {
            $changes[] = $newData['reuniao_compareceu'] === 1
                ? 'Marcou presença na reunião.'
                : 'Marcado como ausente na reunião.';
        }

        $oldCategory = $currentProspection['leadCategory'] ?? 'Entrada';
        if ($oldCategory !== $leadCategory) {
            $changes[] = sprintf('Categoria do lead alterada de %s para %s.', $oldCategory, $leadCategory);
        }

        $oldPaymentProfile = $currentProspection['perfil_pagamento'] ?? null;
        $profileLabels = [
            null => 'Não informado',
            '' => 'Não informado',
            'mensalista' => 'Possível mensalista',
            'avista' => 'Possível à vista'
        ];
        $normalizedOldProfile = in_array($oldPaymentProfile, $allowedPaymentProfiles, true) ? $oldPaymentProfile : null;
        if ($normalizedOldProfile !== $paymentProfile) {
            $changes[] = sprintf(
                'Perfil de pagamento atualizado de %s para %s.',
                $profileLabels[$normalizedOldProfile ?? null] ?? 'Não informado',
                $profileLabels[$paymentProfile ?? null] ?? 'Não informado'
            );
        }

        if (!empty($changes)) {
            foreach ($changes as $changeNote) {
                $prospectionModel->logInteraction($prospeccao_id, $user_id, $changeNote, 'log_sistema');
            }
        }

    }

    $pdo->prepare("UPDATE prospeccoes SET data_ultima_atualizacao = NOW() WHERE id = ?")->execute([$prospeccao_id]);

    $_SESSION['success_message'] = "Prospecção atualizada com sucesso!";
    header("Location: " . APP_URL . "/crm/prospeccoes/detalhes.php?id=" . $prospeccao_id);
    exit;

} catch (PDOException $e) {
    die("Erro ao atualizar a prospecção: " . $e->getMessage());
}
?>