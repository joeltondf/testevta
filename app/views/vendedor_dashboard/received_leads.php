<?php
$baseAppUrl = rtrim(APP_URL, '/');
?>
<link rel="stylesheet" href="<?php echo $baseAppUrl; ?>/assets/css/components/vendor-dashboard.css">
<div id="vendor-received-leads" class="vendor-dashboard-wrapper" data-base-url="<?php echo $baseAppUrl; ?>">
    <div class="vendor-dashboard-header">
        <div>
            <h1>Leads Recebidos</h1>
            <p>Acompanhe os handoffs encaminhados pelo time de SDR e aja rapidamente para maximizar conversões.</p>
        </div>
        <div class="vendor-dashboard-actions">
            <button type="button" class="refresh-button" id="refreshLeadsButton">
                <i class="fas fa-sync-alt"></i>
                Atualizar
            </button>
        </div>
    </div>

    <div class="vendor-dashboard-layout">
        <div class="vendor-dashboard-main">
            <div class="vendor-dashboard-tabs" role="tablist">
                <button class="tab-button active" data-tab="pending" role="tab" aria-selected="true">
                    Pendentes Aceitação
                    <span class="tab-counter" data-counter="pending">0</span>
                </button>
                <button class="tab-button" data-tab="in_progress" role="tab" aria-selected="false">
                    Em Andamento
                    <span class="tab-counter" data-counter="in_progress">0</span>
                </button>
                <button class="tab-button" data-tab="converted" role="tab" aria-selected="false">
                    Convertidos
                    <span class="tab-counter" data-counter="converted">0</span>
                </button>
                <button class="tab-button" data-tab="lost" role="tab" aria-selected="false">
                    Perdidos
                    <span class="tab-counter" data-counter="lost">0</span>
                </button>
            </div>

            <div class="tab-content">
                <div class="lead-list active" id="lead-list-pending" data-tab-content="pending"></div>
                <div class="lead-list" id="lead-list-in-progress" data-tab-content="in_progress" hidden></div>
                <div class="lead-list" id="lead-list-converted" data-tab-content="converted" hidden></div>
                <div class="lead-list" id="lead-list-lost" data-tab-content="lost" hidden></div>
            </div>
        </div>

        <aside class="vendor-dashboard-sidebar">
            <div class="sidebar-card">
                <h3>Métricas Rápidas</h3>
                <div class="sidebar-metric">
                    <span>Leads pendentes</span>
                    <strong id="metric-pending">0</strong>
                </div>
                <div class="sidebar-metric">
                    <span>Taxa de conversão</span>
                    <strong id="metric-conversion">0%</strong>
                </div>
                <div class="sidebar-metric">
                    <span>Tempo médio resposta</span>
                    <strong id="metric-response-time">--</strong>
                </div>
            </div>

            <div class="sidebar-card">
                <h3>Dicas rápidas</h3>
                <ul>
                    <li>Priorize leads urgentes com SLA próximo do vencimento.</li>
                    <li>Use o chat para manter o lead aquecido até o fechamento.</li>
                    <li>Registre atualizações na timeline para manter o histórico organizado.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<div class="modal-backdrop" id="rejectLeadModal" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rejectLeadTitle">
        <button class="modal-close" type="button" id="rejectModalClose" aria-label="Fechar">
            <i class="fas fa-times"></i>
        </button>
        <h2 id="rejectLeadTitle">Motivo da rejeição</h2>
        <form id="rejectLeadForm">
            <div class="form-group">
                <label for="rejectReason">Explique por que este lead não será atendido</label>
                <textarea id="rejectReason" rows="4" required placeholder="Ex.: Lead duplicado, perfil fora do ICP..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="button-secondary" id="rejectModalCancel">Cancelar</button>
                <button type="submit" class="button-danger">Enviar rejeição</button>
            </div>
        </form>
    </div>
</div>

<template id="lead-card-template">
    <article class="lead-card" data-lead-id="">
        <header class="lead-card-header">
            <div class="lead-main-info">
                <h4 class="lead-title"></h4>
                <p class="lead-meta"></p>
            </div>
            <div class="lead-badges">
                <span class="lead-badge urgency"></span>
                <span class="lead-badge new" hidden>Novo</span>
            </div>
        </header>
        <div class="lead-summary">
            <p class="lead-note"></p>
            <p class="lead-contact-time"></p>
        </div>
        <div class="lead-extra" hidden>
            <dl class="lead-detail-list">
                <div>
                    <dt>Qualificado por</dt>
                    <dd class="lead-sdr"></dd>
                </div>
                <div>
                    <dt>Pontuação de urgência</dt>
                    <dd class="lead-score"></dd>
                </div>
                <div>
                    <dt>Recebido em</dt>
                    <dd class="lead-received"></dd>
                </div>
                <div>
                    <dt>Prazo SLA</dt>
                    <dd class="lead-sla"></dd>
                </div>
            </dl>
        </div>
        <footer class="lead-card-footer">
            <div class="lead-countdown" data-countdown=""></div>
            <div class="lead-actions">
                <button class="button-primary" data-action="accept">Aceitar</button>
                <button class="button-danger" data-action="reject">Rejeitar</button>
                <button class="button-outline" data-action="chat">Ver Chat</button>
                <button class="button-outline" data-action="timeline">Timeline</button>
            </div>
        </footer>
    </article>
</template>

<script src="<?php echo $baseAppUrl; ?>/assets/js/vendor-dashboard.js" defer></script>
