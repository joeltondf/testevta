(function (window, document) {
    const actionIconMap = {
        created: 'fa-plus-circle',
        qualified: 'fa-rocket',
        transferred: 'fa-arrow-right',
        accepted: 'fa-check-circle',
        rejected: 'fa-times-circle',
        first_contact: 'fa-phone-alt',
        meeting_scheduled: 'fa-calendar',
        proposal_sent: 'fa-file-signature',
        converted: 'fa-trophy',
        lost: 'fa-ban',
        note_added: 'fa-sticky-note'
    };

    const positiveTypes = new Set(['accepted', 'qualified', 'meeting_scheduled', 'proposal_sent', 'converted', 'first_contact']);
    const negativeTypes = new Set(['rejected', 'lost']);

    const state = {
        events: [],
        filteredType: '',
        prospeccaoId: null,
        rootElement: null
    };

    function init(selector = '[data-component="lead-timeline"]') {
        const root = document.querySelector(selector);
        if (!root) {
            return;
        }

        state.rootElement = root;
        state.prospeccaoId = root.dataset.prospeccaoId || null;

        attachEventListeners();

        if (state.prospeccaoId) {
            loadTimeline(state.prospeccaoId);
        }
    }

    function attachEventListeners() {
        if (!state.rootElement) {
            return;
        }

        const filterSelect = state.rootElement.querySelector('#lead-timeline-filter');
        if (filterSelect) {
            filterSelect.addEventListener('change', function (event) {
                filterByType(event.target.value);
            });
        }

        state.rootElement.addEventListener('click', function (event) {
            const target = event.target.closest('[data-action]');
            if (!target) {
                return;
            }

            const action = target.dataset.action;

            switch (action) {
                case 'add-note':
                    addNote();
                    break;
                case 'export-pdf':
                    exportPDF();
                    break;
                case 'toggle-metadata':
                    toggleMetadata(target);
                    break;
                default:
                    break;
            }
        });
    }

    function toggleMetadata(button) {
        const metadataWrapper = button.closest('[data-role="metadata"]');
        if (!metadataWrapper) {
            return;
        }
        const content = metadataWrapper.querySelector('.timeline-metadata__content');
        if (!content) {
            return;
        }

        const isHidden = content.hasAttribute('hidden');
        if (isHidden) {
            content.removeAttribute('hidden');
            button.textContent = 'Ocultar detalhes';
        } else {
            content.setAttribute('hidden', '');
            button.textContent = 'Ver detalhes';
        }
    }

    function loadTimeline(prospeccaoId) {
        if (!prospeccaoId) {
            return Promise.resolve([]);
        }

        const url = `/api/timeline/${encodeURIComponent(prospeccaoId)}`;

        return fetch(url, {
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro ao carregar timeline: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                state.events = Array.isArray(data) ? data : [];
                renderTimeline(state.events);
                return state.events;
            })
            .catch(error => {
                console.error(error);
                renderTimeline([]);
                return [];
            });
    }

    function renderTimeline(events) {
        const container = state.rootElement ? state.rootElement.querySelector('[data-role="timeline-container"]') : null;
        if (!container) {
            return;
        }

        const eventsToRender = (events && events.length) ? events : [];
        const filteredEvents = state.filteredType
            ? eventsToRender.filter(event => event.action_type === state.filteredType)
            : eventsToRender;

        if (!filteredEvents.length) {
            container.innerHTML = '<div class="timeline__empty">Nenhum evento disponível para esta prospecção.</div>';
            return;
        }

        const html = filteredEvents.map(event => buildTimelineItem(event)).join('');
        container.innerHTML = html;
    }

    function buildTimelineItem(event) {
        const actionType = event.action_type || 'note_added';
        const icon = actionIconMap[actionType] || 'fa-info-circle';
        const toneClass = positiveTypes.has(actionType)
            ? 'timeline-item--positive'
            : negativeTypes.has(actionType)
                ? 'timeline-item--negative'
                : 'timeline-item--neutral';
        const description = event.notes || event.description || '';
        const headline = getHeadline(event);
        const timeLabel = formatRelativeTime(event.created_at);
        const metadata = parseMetadata(event.metadata);
        const author = event.user_name || event.user || '';

        return `
            <div class="timeline-item ${toneClass}" data-action="${escapeHtml(actionType)}">
                <div class="timeline-icon">
                    <i class="fas ${escapeHtml(icon)}" aria-hidden="true"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <strong>${escapeHtml(headline)}</strong>
                        <span class="timeline-time">${escapeHtml(timeLabel)}</span>
                    </div>
                    ${description ? `<div class="timeline-body">${formatDescription(description)}</div>` : ''}
                    ${metadata ? buildMetadata(metadata) : ''}
                    ${author ? `<div class="timeline-footer"><small>Por ${escapeHtml(author)}</small></div>` : ''}
                </div>
            </div>
        `;
    }

    function buildMetadata(metadata) {
        const rows = Object.keys(metadata).map(key => {
            const value = metadata[key];
            const normalizedValue = typeof value === 'object' ? JSON.stringify(value, null, 0) : String(value);
            return `
                <div class="timeline-metadata__row">
                    <dt>${escapeHtml(formatLabel(key))}</dt>
                    <dd>${escapeHtml(normalizedValue)}</dd>
                </div>
            `;
        }).join('');

        return `
            <div class="timeline-metadata" data-role="metadata">
                <button type="button" class="timeline-metadata__toggle" data-action="toggle-metadata">Ver detalhes</button>
                <div class="timeline-metadata__content" hidden>
                    <dl>${rows}</dl>
                </div>
            </div>
        `;
    }

    function formatDescription(text) {
        return escapeHtml(String(text)).replace(/\n/g, '<br>');
    }

    function formatLabel(label) {
        return String(label)
            .replace(/_/g, ' ')
            .replace(/\b\w/g, letter => letter.toUpperCase());
    }

    function parseMetadata(metadata) {
        if (!metadata) {
            return null;
        }

        if (typeof metadata === 'string') {
            try {
                const parsed = JSON.parse(metadata);
                return typeof parsed === 'object' && parsed !== null ? parsed : null;
            } catch (error) {
                return { Detalhes: metadata };
            }
        }

        if (typeof metadata === 'object') {
            return metadata;
        }

        return null;
    }

    function getHeadline(event) {
        if (event.description) {
            return event.description;
        }

        if (event.action_type === 'transferred' && (event.target_user_name || event.target_user)) {
            return `Transferido para ${event.target_user_name || event.target_user}`;
        }

        return formatLabel(event.action_type || 'Evento');
    }

    function formatRelativeTime(date) {
        if (!date) {
            return '-';
        }

        const eventDate = date instanceof Date ? date : new Date(date);
        if (Number.isNaN(eventDate.getTime())) {
            return '-';
        }

        const now = new Date();
        const diffMs = now.getTime() - eventDate.getTime();

        if (diffMs <= 0) {
            return 'agora mesmo';
        }

        const diffSeconds = Math.floor(diffMs / 1000);
        const diffMinutes = Math.floor(diffSeconds / 60);
        const diffHours = Math.floor(diffMinutes / 60);
        const diffDays = Math.floor(diffHours / 24);
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);

        if (diffSeconds < 60) {
            return `há ${diffSeconds} segundo${diffSeconds > 1 ? 's' : ''}`;
        }

        if (diffMinutes < 60) {
            return `há ${diffMinutes} minuto${diffMinutes > 1 ? 's' : ''}`;
        }

        if (diffHours < 24) {
            return `há ${diffHours} hora${diffHours > 1 ? 's' : ''}`;
        }

        if (diffDays < 30) {
            return `há ${diffDays} dia${diffDays > 1 ? 's' : ''}`;
        }

        if (diffMonths < 12) {
            return `há ${diffMonths} mês${diffMonths > 1 ? 'es' : ''}`;
        }

        return `há ${diffYears} ano${diffYears > 1 ? 's' : ''}`;
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.innerText = value == null ? '' : value;
        return element.innerHTML;
    }

    function addNote(text) {
        if (!state.prospeccaoId) {
            console.warn('Prospecção não definida para adicionar nota.');
            return Promise.resolve(null);
        }

        const noteText = typeof text === 'string' ? text : window.prompt('Digite a nota que deseja adicionar:');
        if (!noteText || !noteText.trim()) {
            return Promise.resolve(null);
        }

        const payload = {
            action_type: 'note_added',
            description: noteText.trim()
        };

        const url = `/api/timeline/${encodeURIComponent(state.prospeccaoId)}`;

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Não foi possível adicionar a nota.');
                }
                return response.json();
            })
            .then(event => {
                if (event && typeof event === 'object') {
                    state.events.unshift(event);
                    renderTimeline(state.events);
                } else {
                    return loadTimeline(state.prospeccaoId);
                }
                return event;
            })
            .catch(error => {
                console.error(error);
                return null;
            });
    }

    function filterByType(actionType) {
        state.filteredType = actionType || '';
        renderTimeline(state.events);
    }

    function exportPDF() {
        if (!state.prospeccaoId) {
            console.warn('Prospecção não definida para exportação.');
            return Promise.resolve(null);
        }

        const url = `/api/timeline/${encodeURIComponent(state.prospeccaoId)}/export`;

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Não foi possível exportar a timeline.');
                }
                return response.blob();
            })
            .then(blob => {
                const fileURL = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = fileURL;
                link.download = `timeline-${state.prospeccaoId}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(fileURL);
                return true;
            })
            .catch(error => {
                console.error(error);
                return false;
            });
    }

    window.LeadTimeline = {
        init,
        loadTimeline,
        renderTimeline,
        addNote,
        filterByType,
        exportPDF
    };

    document.addEventListener('DOMContentLoaded', function () {
        init();
    });
})(window, document);
