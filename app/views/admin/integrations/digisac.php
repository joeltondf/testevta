<?php
if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'master'], true)) {
    http_response_code(403);
    echo '<div class="alert alert-error">Acesso negado.</div>';
    return;
}

$settings = $settings ?? [];
$enabled = (int)($settings['digisac_enabled'] ?? 0) === 1;
$sendUrgent = (int)($settings['digisac_send_on_urgent_lead'] ?? 1) === 1;
$sendSlaWarning = (int)($settings['digisac_send_on_sla_warning'] ?? 1) === 1;
$sendSlaOverdue = (int)($settings['digisac_send_on_sla_overdue'] ?? 0) === 1;
$sendConverted = (int)($settings['digisac_send_on_converted'] ?? 0) === 1;
$sendFeedback = (int)($settings['digisac_send_on_feedback_request'] ?? 0) === 1;
$departmentId = htmlspecialchars((string)($settings['digisac_default_department_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$webhookUrl = rtrim(defined('APP_URL') ? APP_URL : '', '/') . '/app/webhooks/digisac.php';
?>
<div class="card">
    <h2>⚙️ Configuração Digisac (WhatsApp)</h2>
    <form id="digisacConfigForm" method="POST">
        <div class="form-group">
            <label>Status da Integração</label>
            <div class="toggle-switch">
                <input type="checkbox" id="digisac_enabled" name="enabled" <?php echo $enabled ? 'checked' : ''; ?>>
                <label for="digisac_enabled">Ativar Digisac</label>
            </div>
            <small class="help-text">Ative para começar a enviar WhatsApp</small>
        </div>
        <div class="form-group">
            <label>API Token *</label>
            <div class="input-with-action">
                <input type="password" name="api_token" id="api_token" placeholder="Digite o token da API Digisac">
                <button type="button" onclick="toggleTokenVisibility()">
                    <i class="fas fa-eye"></i> Mostrar
                </button>
            </div>
        </div>
        <div class="form-group">
            <label>Department ID</label>
            <input type="text" name="department_id" placeholder="ID do departamento padrão (opcional)" value="<?php echo $departmentId; ?>">
        </div>
        <div class="form-group">
            <label>URL do Webhook</label>
            <div class="input-with-action">
                <input type="text" readonly value="<?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" onclick="copyWebhookUrl()">
                    <i class="fas fa-copy"></i> Copiar
                </button>
            </div>
            <small>Configure este webhook no painel Digisac</small>
        </div>
        <hr>
        <h3>Quando Enviar WhatsApp?</h3>
        <div class="checkbox-group">
            <label>
                <input type="checkbox" name="send_on_urgent_lead" <?php echo $sendUrgent ? 'checked' : ''; ?>> Novo lead urgente recebido pelo vendedor
            </label>
            <label>
                <input type="checkbox" name="send_on_sla_warning" <?php echo $sendSlaWarning ? 'checked' : ''; ?>> Alerta de SLA próximo do vencimento
            </label>
            <label>
                <input type="checkbox" name="send_on_sla_overdue" <?php echo $sendSlaOverdue ? 'checked' : ''; ?>> SLA vencido (urgente)
            </label>
            <label>
                <input type="checkbox" name="send_on_converted" <?php echo $sendConverted ? 'checked' : ''; ?>> Lead convertido (notificar SDR)
            </label>
            <label>
                <input type="checkbox" name="send_on_feedback_request" <?php echo $sendFeedback ? 'checked' : ''; ?>> Solicitação de feedback (7 dias após transferência)
            </label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="testConnection(event)">
                <i class="fas fa-vial"></i> Testar Conexão
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </div>
    </form>
    <div id="connectionTest" style="display:none" class="alert">
    </div>
</div>
<div class="card mt-4">
    <h3>📊 Últimas Mensagens Enviadas</h3>
    <table id="messagesLog">
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Destinatário</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
<script src="/assets/js/admin/digisac-config.js"></script>
