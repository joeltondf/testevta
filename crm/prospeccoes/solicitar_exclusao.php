<?php
// Arquivo: crm/prospeccoes/solicitar_exclusao.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Configuracao.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Notificacao.php';
require_once __DIR__ . '/../../app/services/EmailService.php';

function parseEmailList(?string $rawList): array
{
    if (empty($rawList)) {
        return [];
    }

    $emails = preg_split('/[\n,;]+/', $rawList);

    $filtered = array_filter(array_map('trim', $emails), static function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    return array_values(array_unique($filtered));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo']);
$solicitante_id = $_SESSION['user_id'];
$solicitante_nome = $_SESSION['user_nome'];

if (!$prospeccao_id || empty($motivo)) {
    // Correção 1: Redirecionamento absoluto
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id . '&error=motivo_obrigatorio');
    exit;
}

try {
    $notificationLink = '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id;

    $notificacaoModel = new Notificacao($pdo);

    $stmt = $pdo->prepare("
        SELECT p.id_texto, p.nome_prospecto, c.nome_cliente
        FROM prospeccoes p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$prospeccao_id]);
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prospect) {
        $notificacaoModel->resolverPorReferencia('prospeccao_exclusao', (int)$prospeccao_id);
        $_SESSION['error_message'] = 'Esta prospecção já foi excluída e os alertas foram removidos.';
        header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
        exit;
    }

    $configModel = new Configuracao($pdo);
    $userModel = new User($pdo);

    $managerProfiles = ['admin', 'gerencia', 'supervisor'];
    $managerContacts = $userModel->getActiveContactsByProfiles($managerProfiles);

    $recipientMap = [];
    foreach ($managerContacts as $contact) {
        $recipientMap[$contact['email']] = true;
    }

    $configuredEmails = parseEmailList($configModel->get('alert_emails'));
    foreach ($configuredEmails as $email) {
        $recipientMap[$email] = true;
    }

    $recipientEmails = array_keys($recipientMap);

    $prospectCode = $prospect['id_texto'] ?? ('ID #' . $prospeccao_id);
    $prospectTitle = $prospect['nome_prospecto'] ?? '';
    $prospectLead = $prospect['nome_cliente'] ?? '';
    $prospectLink = APP_URL . $notificationLink;

    $assunto = sprintf('Solicitação de exclusão da prospecção: %s', $prospectCode);

    $solicitanteNomeHtml = htmlspecialchars($solicitante_nome, ENT_QUOTES, 'UTF-8');
    $prospectCodeHtml = htmlspecialchars($prospectCode, ENT_QUOTES, 'UTF-8');
    $prospectTitleHtml = htmlspecialchars($prospectTitle, ENT_QUOTES, 'UTF-8');
    $prospectLeadHtml = htmlspecialchars($prospectLead, ENT_QUOTES, 'UTF-8');
    $prospectLinkHtml = htmlspecialchars($prospectLink, ENT_QUOTES, 'UTF-8');
    $motivoHtml = nl2br(htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'));

    $corpoEmail = <<<HTML
        <p>Olá,</p>
        <p>O colaborador <strong>{$solicitanteNomeHtml}</strong> solicitou a exclusão da prospecção abaixo.</p>
        <ul>
            <li><strong>ID:</strong> {$prospectCodeHtml}</li>
            <li><strong>Título:</strong> {$prospectTitleHtml}</li>
            <li><strong>Lead:</strong> {$prospectLeadHtml}</li>
        </ul>
        <p><strong>Mensagem enviada:</strong></p>
        <p style="padding: 10px; border: 1px solid #eee; background-color: #f9f9f9;">{$motivoHtml}</p>
        <p><a href="{$prospectLinkHtml}" style="display: inline-block; padding: 10px 16px; background-color: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Abrir prospecção</a></p>
        <p>Por favor, analisem e concluam a solicitação diretamente no CRM.</p>
    HTML;

    $emailService = new EmailService($pdo);
    $emailsEnviados = 0;
    $emailsComFalha = [];

    foreach ($recipientEmails as $email) {
        try {
            if ($emailService->sendEmail($email, $assunto, $corpoEmail)) {
                $emailsEnviados++;
            }
        } catch (Exception $exception) {
            $emailsComFalha[] = $email;
            error_log('Erro ao enviar solicitação de exclusão: ' . $exception->getMessage());
        }
    }

    $notificationMessage = sprintf(
        'Solicitação de exclusão da prospecção %s enviada por %s.',
        $prospectCode,
        $solicitante_nome
    );

    foreach ($managerContacts as $contact) {
        $notificacaoModel->criar(
            (int)$contact['id'],
            $solicitante_id,
            $notificationMessage,
            $notificationLink,
            'prospeccao_exclusao',
            (int)$prospeccao_id,
            'gerencia'
        );
    }

    if ($emailsEnviados === 0 && empty($emailsComFalha)) {
        $_SESSION['error_message'] = 'Nenhum destinatário configurado para receber a solicitação. Informe a gerência.';
    } elseif ($emailsEnviados === 0) {
        $_SESSION['error_message'] = 'Não foi possível enviar o e-mail de solicitação. Tente novamente ou contate o administrador.';
    } elseif (!empty($emailsComFalha)) {
        $_SESSION['error_message'] = 'Solicitação registrada, mas alguns e-mails não foram entregues: ' . implode(', ', $emailsComFalha);
    } else {
        $_SESSION['success_message'] = 'Solicitação de exclusão enviada para a gerência.';
    }

    header('Location: ' . APP_URL . $notificationLink);
    exit;

} catch (Exception $e) {
    die("Erro ao processar a solicitação: " . $e->getMessage());
}
?>