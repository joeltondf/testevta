(function () {
    const dashboardEl = document.getElementById('vendor-received-leads');
    if (!dashboardEl) {
        return;
    }

    const baseUrl = dashboardEl.getAttribute('data-base-url') || '';
    const apiEndpoints = {
        pending: `${baseUrl}/api/handoffs/pending`,
        in_progress: `${baseUrl}/api/handoffs/in-progress`,
        converted: `${baseUrl}/api/handoffs/converted`,
        lost: `${baseUrl}/api/handoffs/lost`
    };

    const tabButtons = Array.from(dashboardEl.querySelectorAll('.tab-button'));
    const leadLists = Array.from(dashboardEl.querySelectorAll('.lead-list'));
    const counters = {
        pending: dashboardEl.querySelector('[data-counter="pending"]'),
        in_progress: dashboardEl.querySelector('[data-counter="in_progress"]'),
        converted: dashboardEl.querySelector('[data-counter="converted"]'),
        lost: dashboardEl.querySelector('[data-counter="lost"]')
    };

    const metricsEls = {
        pending: document.getElementById('metric-pending'),
        conversion: document.getElementById('metric-conversion'),
        responseTime: document.getElementById('metric-response-time')
    };

    const refreshButton = document.getElementById('refreshLeadsButton');
    const rejectModal = document.getElementById('rejectLeadModal');
    const rejectForm = document.getElementById('rejectLeadForm');
    const rejectReason = document.getElementById('rejectReason');
    const rejectModalClose = document.getElementById('rejectModalClose');
    const rejectModalCancel = document.getElementById('rejectModalCancel');

    const feedbackModal = typeof window.FeedbackModal === 'function'
        ? new window.FeedbackModal('feedbackModal')
        : null;
    const countdownIntervals = new Map();
    let activeTab = 'pending';
    let leadPendingAction = null;

    function normalizeBoolean(value) {
        if (typeof value === 'boolean') {
            return value;
        }
        if (typeof value === 'number') {
            return value !== 0;
        }
        if (typeof value === 'string') {
            const normalized = value.trim().toLowerCase();
            return ['true', '1', 'sim', 'yes', 'on'].includes(normalized);
        }
        return false;
    }

    function getEndpoint(tab) {
        return apiEndpoints[tab] || apiEndpoints.pending;
    }

    function formatDate(value) {
        if (!value) {
            return '--';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatTimeFromMinutes(minutes) {
        if (minutes === null || minutes === undefined || Number.isNaN(Number(minutes))) {
            return '--';
        }

        const totalMinutes = Math.round(Number(minutes));
        if (totalMinutes < 60) {
            return `${totalMinutes} min`;
        }

        const hours = Math.floor(totalMinutes / 60);
        const remainingMinutes = totalMinutes % 60;
        return `${hours}h ${remainingMinutes.toString().padStart(2, '0')}min`;
    }

    function classifyUrgency(score) {
        const numericScore = Number(score) || 0;
        if (numericScore >= 80) {
            return { label: 'Urgência Alta', level: 'high' };
        }
        if (numericScore >= 50) {
            return { label: 'Urgência Média', level: 'medium' };
        }
        return { label: 'Urgência Baixa', level: 'low' };
    }

    function isNewLead(receivedAt) {
        if (!receivedAt) {
            return false;
        }
        const receivedDate = new Date(receivedAt);
        if (Number.isNaN(receivedDate.getTime())) {
            return false;
        }
        const diffMs = Date.now() - receivedDate.getTime();
        const diffHours = diffMs / (1000 * 60 * 60);
        return diffHours < 1;
    }

    function clearCountdowns() {
        countdownIntervals.forEach(intervalId => clearInterval(intervalId));
        countdownIntervals.clear();
    }

    function setupCountdown(element, deadline) {
        if (!element) {
            return;
        }

        const targetDate = deadline ? new Date(deadline) : null;
        if (!targetDate || Number.isNaN(targetDate.getTime())) {
            element.textContent = 'SLA não informado';
            element.classList.remove('countdown-danger', 'countdown-warning');
            return;
        }

        function updateCountdown() {
            const now = new Date();
            const diff = targetDate.getTime() - now.getTime();

            if (diff <= 0) {
                element.textContent = 'SLA expirado';
                element.classList.add('countdown-danger');
                element.classList.remove('countdown-warning');
                return;
            }

            const totalSeconds = Math.floor(diff / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            element.textContent = `SLA em ${hours}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s`;

            element.classList.remove('countdown-danger', 'countdown-warning');
            if (diff < 30 * 60 * 1000) {
                element.classList.add('countdown-danger');
            } else if (diff < 60 * 60 * 1000) {
                element.classList.add('countdown-warning');
            }
        }

        updateCountdown();
        const intervalId = window.setInterval(updateCountdown, 1000);
        countdownIntervals.set(element, intervalId);
    }

    function renderLeadCard(lead) {
        const template = document.getElementById('lead-card-template');
        if (!template) {
            return null;
        }

        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.leadId = lead.id;

        const title = card.querySelector('.lead-title');
        const meta = card.querySelector('.lead-meta');
        const note = card.querySelector('.lead-note');
        const contactTime = card.querySelector('.lead-contact-time');
        const sdrField = card.querySelector('.lead-sdr');
        const scoreField = card.querySelector('.lead-score');
        const receivedField = card.querySelector('.lead-received');
        const slaField = card.querySelector('.lead-sla');
        const urgencyBadge = card.querySelector('.lead-badge.urgency');
        const newBadge = card.querySelector('.lead-badge.new');
        const countdownEl = card.querySelector('[data-countdown]');
        const leadExtra = card.querySelector('.lead-extra');
        const feedbackButton = card.querySelector('[data-action="feedback"]');

        const leadName = lead.nome || lead.nome_prospecto || lead.contact_name || 'Lead sem nome';
        const company = lead.empresa || lead.company || lead.organization || '';
        const labelName = company ? `${leadName} · ${company}` : leadName;
        title.textContent = labelName;

        const sdrName = lead.sdr || lead.sdr_name || lead.qualifier || 'SDR não informado';
        const urgencyScore = lead.urgencia_score ?? lead.urgency_score ?? lead.score_urgencia ?? lead.urgency ?? 0;
        const urgencyInfo = classifyUrgency(urgencyScore);

        meta.textContent = `Qualificado por ${sdrName}`;
        note.textContent = lead.notas_handoff || lead.handoff_notes || lead.summary || 'Sem notas registradas.';
        contactTime.textContent = `Melhor horário: ${lead.melhor_horario_contato || lead.best_time_to_contact || '--'}`;
        sdrField.textContent = sdrName;
        scoreField.textContent = `${urgencyScore || 0}`;
        receivedField.textContent = formatDate(lead.received_at || lead.created_at || lead.data_recebimento);
        slaField.textContent = formatDate(lead.sla_deadline || lead.sla_prazo || lead.deadline);

        urgencyBadge.textContent = urgencyInfo.label;
        card.classList.remove('urgency-high', 'urgency-medium', 'urgency-low');
        card.classList.add(`urgency-${urgencyInfo.level}`);

        if (isNewLead(lead.received_at || lead.created_at)) {
            newBadge.hidden = false;
        }

        const countdownDeadline = lead.sla_deadline || lead.sla_prazo || lead.deadline;
        setupCountdown(countdownEl, countdownDeadline);

        card.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.closest('.lead-actions')) {
                return;
            }
            const expanded = !leadExtra.hasAttribute('hidden');
            if (expanded) {
                leadExtra.setAttribute('hidden', '');
                card.classList.remove('expanded');
            } else {
                leadExtra.removeAttribute('hidden');
                card.classList.add('expanded');
            }
        });

        const acceptButton = card.querySelector('[data-action="accept"]');
        const rejectButton = card.querySelector('[data-action="reject"]');
        const chatButton = card.querySelector('[data-action="chat"]');
        const timelineButton = card.querySelector('[data-action="timeline"]');

        acceptButton.addEventListener('click', (event) => {
            event.stopPropagation();
            acceptLead(lead.id);
        });

        rejectButton.addEventListener('click', (event) => {
            event.stopPropagation();
            openRejectModal(lead.id);
        });

        chatButton.addEventListener('click', (event) => {
            event.stopPropagation();
            openExternalLink(lead.chat_url || lead.links?.chat);
        });

        timelineButton.addEventListener('click', (event) => {
            event.stopPropagation();
            openExternalLink(lead.timeline_url || lead.links?.timeline);
        });

        if (feedbackButton) {
            const shouldDisplayFeedback = determineFeedbackEligibility(lead);
            if (shouldDisplayFeedback && feedbackModal) {
                feedbackButton.hidden = false;
                feedbackButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    feedbackModal.open(lead.id, { leadName: labelName });
                });
            } else {
                feedbackButton.remove();
            }
        }

        return card;
    }

    function determineFeedbackEligibility(lead) {
        if (!lead) {
            return false;
        }

        const scoreFields = [
            lead.quality_score,
            lead.feedback_score,
            lead.qualidade,
            lead.feedback?.score,
        ];

        const hasScore = scoreFields.some((value) => {
            const numericValue = Number(value);
            return !Number.isNaN(numericValue) && numericValue > 0;
        });

        if (hasScore) {
            return false;
        }

        if (lead.feedback_available !== undefined) {
            return normalizeBoolean(lead.feedback_available);
        }

        if (lead.feedback_due !== undefined) {
            return normalizeBoolean(lead.feedback_due);
        }

        if (lead.feedback_requested !== undefined) {
            return normalizeBoolean(lead.feedback_requested);
        }

        const status = String(lead.status || lead.handoff_status || '').toLowerCase();
        if (['converted', 'won', 'lost', 'perdido'].includes(status)) {
            return true;
        }

        const now = Date.now();
        const dueAt = lead.feedback_due_at || lead.feedback_deadline;
        if (dueAt) {
            const dueDate = new Date(dueAt);
            if (!Number.isNaN(dueDate.getTime()) && dueDate.getTime() <= now) {
                return true;
            }
        }

        const acceptedAt = lead.accepted_at || lead.feedback_reference_date || lead.updated_at;
        if (acceptedAt) {
            const acceptedDate = new Date(acceptedAt);
            if (!Number.isNaN(acceptedDate.getTime())) {
                const diffMs = now - acceptedDate.getTime();
                const diffDays = diffMs / (1000 * 60 * 60 * 24);
                return diffDays >= 7;
            }
        }

        return false;
    }

    function openExternalLink(url) {
        if (!url) {
            window.alert('Nenhum link disponível para esta ação.');
            return;
        }
        window.open(url, '_blank');
    }

    async function loadLeads(tab) {
        const endpoint = getEndpoint(tab);
        const listElement = leadLists.find(list => list.dataset.tabContent === tab);
        if (!listElement) {
            return;
        }

        activeTab = tab;
        listElement.innerHTML = '<div class="lead-placeholder">Carregando leads...</div>';
        listElement.classList.add('loading');

        clearCountdowns();

        try {
            const response = await fetch(endpoint, {
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`Erro ao carregar leads (${response.status})`);
            }

            const payload = await response.json();
            const leads = payload.leads || payload.data || [];
            const metrics = payload.metrics || payload.meta?.metrics || null;

            listElement.classList.remove('loading');
            listElement.innerHTML = '';

            if (leads.length === 0) {
                listElement.innerHTML = '<div class="lead-placeholder">Nenhum lead encontrado para este estágio.</div>';
            } else {
                const fragment = document.createDocumentFragment();
                leads
                    .sort((a, b) => {
                        const urgencyA = Number(a.urgencia_score ?? a.urgency_score ?? a.score_urgencia ?? 0);
                        const urgencyB = Number(b.urgencia_score ?? b.urgency_score ?? b.score_urgencia ?? 0);
                        return urgencyB - urgencyA;
                    })
                    .forEach((lead) => {
                        const card = renderLeadCard(lead);
                        if (card) {
                            fragment.appendChild(card);
                        }
                    });
                listElement.appendChild(fragment);
            }

            counters[tab].textContent = leads.length;
            updateCounters();
            if (metrics) {
                updateMetrics(metrics);
            }
        } catch (error) {
            listElement.classList.remove('loading');
            listElement.innerHTML = `<div class="lead-placeholder error">${error.message}</div>`;
        }
    }

    async function acceptLead(id) {
        if (!id) {
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/handoffs/accept`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id })
            });

            if (!response.ok) {
                throw new Error('Não foi possível aceitar o lead.');
            }

            showToast('Lead aceito com sucesso!');
            await loadLeads(activeTab);
        } catch (error) {
            showToast(error.message || 'Falha ao aceitar o lead.', true);
        }
    }

    async function rejectLead(id, reason) {
        if (!id || !reason) {
            showToast('Informe o motivo da rejeição.', true);
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/handoffs/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id, reason })
            });

            if (!response.ok) {
                throw new Error('Não foi possível rejeitar o lead.');
            }

            closeRejectModal();
            showToast('Lead rejeitado com sucesso.');
            await loadLeads(activeTab);
        } catch (error) {
            showToast(error.message || 'Falha ao rejeitar o lead.', true);
        }
    }

    function updateCounters() {
        const pendingValue = parseInt(counters.pending?.textContent || '0', 10);
        if (metricsEls.pending) {
            metricsEls.pending.textContent = pendingValue;
        }
    }

    function updateMetrics(metrics) {
        if (metrics.pending !== undefined && metricsEls.pending) {
            metricsEls.pending.textContent = metrics.pending;
        }
        if (metrics.conversion_rate !== undefined && metricsEls.conversion) {
            metricsEls.conversion.textContent = `${Number(metrics.conversion_rate).toFixed(1)}%`;
        }
        if (metrics.avg_response_time !== undefined && metricsEls.responseTime) {
            metricsEls.responseTime.textContent = formatTimeFromMinutes(metrics.avg_response_time);
        }
    }

    function switchTab(tab) {
        tabButtons.forEach((button) => {
            const isActive = button.dataset.tab === tab;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', String(isActive));
        });

        leadLists.forEach((list) => {
            const isActive = list.dataset.tabContent === tab;
            list.classList.toggle('active', isActive);
            if (isActive) {
                list.removeAttribute('hidden');
            } else {
                list.setAttribute('hidden', '');
            }
        });

        loadLeads(tab);
    }

    function openRejectModal(leadId) {
        leadPendingAction = leadId;
        rejectReason.value = '';
        rejectModal?.classList.add('open');
        rejectModal?.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => rejectReason.focus(), 100);
    }

    function closeRejectModal() {
        leadPendingAction = null;
        rejectModal?.classList.remove('open');
        rejectModal?.setAttribute('aria-hidden', 'true');
    }

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = `vendor-toast ${isError ? 'error' : 'success'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(() => toast.classList.add('visible'), 10);
        window.setTimeout(() => {
            toast.classList.remove('visible');
            window.setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    if (feedbackModal) {
        feedbackModal.setOnSubmitted(() => {
            loadLeads(activeTab);
        });
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.tab;
            if (tab && tab !== activeTab) {
                switchTab(tab);
            }
        });
    });

    refreshButton?.addEventListener('click', () => {
        loadLeads(activeTab);
    });

    rejectModalClose?.addEventListener('click', closeRejectModal);
    rejectModalCancel?.addEventListener('click', closeRejectModal);

    rejectForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!leadPendingAction) {
            showToast('Nenhum lead selecionado.', true);
            return;
        }
        const reason = rejectReason.value.trim();
        rejectLead(leadPendingAction, reason);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && rejectModal?.classList.contains('open')) {
            closeRejectModal();
        }
    });

    loadLeads(activeTab);
})();
