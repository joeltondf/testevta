(function (window, document) {
    'use strict';

    const config = window.SDR_DASHBOARD_CONFIG || {};

    const state = {
        transfers: [],
        summary: {},
        isLoading: false
    };

    const elements = {
        tableBody: document.getElementById('transfers-table-body'),
        loading: document.getElementById('transfers-loading'),
        error: document.getElementById('transfers-error'),
        emptyState: document.getElementById('transfers-empty-state'),
        totalTransferred: document.getElementById('total-transferred-month'),
        pendingAcceptance: document.getElementById('pending-acceptance'),
        conversionRate: document.getElementById('conversion-rate'),
        avgFirstContact: document.getElementById('avg-first-contact'),
        filtersForm: document.getElementById('transfers-filters-form'),
        vendorSelect: document.getElementById('filter-vendor'),
        statusSelect: document.getElementById('filter-status'),
        startDate: document.getElementById('filter-date-start'),
        endDate: document.getElementById('filter-date-end'),
        exportButton: document.querySelector('[data-action="export-csv"]'),
        resetButton: document.querySelector('[data-action="reset-filters"]'),
        applyButton: document.querySelector('[data-action="apply-filters"]')
    };

    const statusClassMap = {
        pending: 'sdr-status sdr-status--pending',
        accepted: 'sdr-status sdr-status--accepted',
        converted: 'sdr-status sdr-status--converted',
        lost: 'sdr-status sdr-status--lost',
        rejected: 'sdr-status sdr-status--lost'
    };

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }

        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '--';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '--';
        }

        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    function formatStatusBadge(statusRaw) {
        const status = String(statusRaw || '')
            .trim()
            .toLowerCase();

        const labelMap = {
            pending: 'Pendente',
            accepted: 'Aceito',
            converted: 'Convertido',
            lost: 'Perdido',
            rejected: 'Rejeitado'
        };

        const label = labelMap[status] || (status ? statusRaw : 'Indefinido');
        const badgeClass = statusClassMap[status] || 'sdr-status';

        return '<span class="' + badgeClass + '">' + escapeHtml(label) + '</span>';
    }

    function computeSla(transfer) {
        if (!transfer) {
            return { text: '--', isOverdue: false };
        }

        if (transfer.sla_text) {
            return { text: transfer.sla_text, isOverdue: Boolean(transfer.sla_overdue) };
        }

        if (typeof transfer.sla_status === 'string' && transfer.sla_status.trim() !== '') {
            const normalized = transfer.sla_status.trim().toLowerCase();
            const overdue = normalized.includes('atras') || normalized.includes('venc');
            return { text: transfer.sla_status, isOverdue: overdue };
        }

        if (typeof transfer.sla_hours_remaining === 'number' && !Number.isNaN(transfer.sla_hours_remaining)) {
            const hours = transfer.sla_hours_remaining;
            const overdue = hours < 0;
            const absHours = Math.abs(hours);
            const wholeHours = Math.floor(absHours);
            const minutes = Math.round((absHours - wholeHours) * 60);
            const pieces = [];

            if (wholeHours > 0) {
                pieces.push(wholeHours + 'h');
            }
            pieces.push((minutes < 10 ? '0' : '') + minutes + 'min');

            const prefix = overdue ? 'Atrasado há ' : 'Restam ';
            return { text: prefix + pieces.join(' '), isOverdue: overdue };
        }

        if (transfer.sla_deadline) {
            const deadline = new Date(transfer.sla_deadline);
            if (!Number.isNaN(deadline.getTime())) {
                const now = new Date();
                const diffMinutes = Math.round((deadline.getTime() - now.getTime()) / 60000);
                const overdue = diffMinutes < 0;
                const absMinutes = Math.abs(diffMinutes);
                const hours = Math.floor(absMinutes / 60);
                const minutes = absMinutes % 60;
                const timeLabel = (hours > 0 ? hours + 'h ' : '') + minutes + 'min';
                const prefix = overdue ? 'Atrasado há ' : 'Restam ';
                return { text: prefix + timeLabel, isOverdue: overdue };
            }
        }

        return { text: '--', isOverdue: false };
    }

    function computeAverageFirstContact(transfers) {
        const durations = transfers
            .map((transfer) => {
                if (typeof transfer.first_contact_minutes === 'number') {
                    return transfer.first_contact_minutes;
                }

                if (transfer.first_contact_at && transfer.transferred_at) {
                    const firstContact = new Date(transfer.first_contact_at);
                    const transferred = new Date(transfer.transferred_at);

                    if (!Number.isNaN(firstContact.getTime()) && !Number.isNaN(transferred.getTime())) {
                        const diff = firstContact.getTime() - transferred.getTime();
                        return diff / 60000;
                    }
                }

                return null;
            })
            .filter((value) => typeof value === 'number' && value >= 0);

        if (!durations.length) {
            return null;
        }

        const total = durations.reduce((sum, value) => sum + value, 0);
        return total / durations.length;
    }

    function formatDuration(minutesValue) {
        if (minutesValue === null || minutesValue === undefined || Number.isNaN(minutesValue)) {
            return '--';
        }

        const minutes = Math.round(minutesValue);
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.max(minutes - hours * 60, 0);

        if (hours <= 0) {
            return remainingMinutes + ' min';
        }

        return hours + 'h ' + remainingMinutes + 'min';
    }

    function toggleLoading(isLoading) {
        state.isLoading = isLoading;
        if (!elements.loading) {
            return;
        }

        if (isLoading) {
            elements.loading.classList.add('is-visible');
        } else {
            elements.loading.classList.remove('is-visible');
        }
    }

    function toggleEmptyState(show) {
        if (!elements.emptyState) {
            return;
        }

        if (show) {
            elements.emptyState.classList.remove('hidden');
        } else {
            elements.emptyState.classList.add('hidden');
        }
    }

    function showError(message) {
        if (!elements.error) {
            return;
        }

        elements.error.textContent = message;
        elements.error.classList.remove('hidden');
    }

    function clearError() {
        if (!elements.error) {
            return;
        }

        elements.error.textContent = '';
        elements.error.classList.add('hidden');
    }

    async function loadTransfers(filters = {}) {
        if (!config.apiEndpoint) {
            showError('Endpoint de API não configurado.');
            return;
        }

        toggleLoading(true);
        clearError();
        toggleEmptyState(false);

        const params = new URLSearchParams();
        const normalizedFilters = Object.assign({}, filters);

        Object.keys(normalizedFilters).forEach((key) => {
            const value = normalizedFilters[key];
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                params.append(key, value);
            }
        });

        if (!params.has('status') && config.defaultStatus) {
            params.append('status', config.defaultStatus);
        }

        const url = config.apiEndpoint + (params.toString() ? '?' + params.toString() : '');

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Não foi possível carregar os dados.');
            }

            const payload = await response.json();

            if (payload && payload.success === false) {
                throw new Error(payload.message || 'Falha ao carregar transferências.');
            }

            const transfers = Array.isArray(payload?.handoffs)
                ? payload.handoffs
                : Array.isArray(payload?.transfers)
                    ? payload.transfers
                    : Array.isArray(payload?.data)
                        ? payload.data
                        : [];

            state.transfers = transfers;
            state.summary = payload?.summary || {};

            updateSummary();
            renderTable(transfers);
            updateExportButtonState();
        } catch (error) {
            console.error(error);
            showError(error.message || 'Ocorreu um erro inesperado.');
            state.transfers = [];
            renderTable([]);
            updateExportButtonState();
        } finally {
            toggleLoading(false);
        }
    }

    function updateSummary() {
        const { summary, transfers } = state;

        const totalMonth = typeof summary.total_transferred_month === 'number'
            ? summary.total_transferred_month
            : transfers.filter((transfer) => {
                const referenceDate = transfer.transferred_at || transfer.created_at;
                if (!referenceDate) {
                    return false;
                }

                const transferredDate = new Date(referenceDate);
                if (Number.isNaN(transferredDate.getTime())) {
                    return false;
                }

                const now = new Date();
                return transferredDate.getMonth() === now.getMonth() && transferredDate.getFullYear() === now.getFullYear();
            }).length;

        const pendingCount = typeof summary.pending_acceptance === 'number'
            ? summary.pending_acceptance
            : transfers.filter((transfer) => String(transfer.status || '').toLowerCase() === 'pending').length;

        const totalForConversion = typeof summary.total_transferred === 'number'
            ? summary.total_transferred
            : transfers.length;

        const convertedCount = typeof summary.converted === 'number'
            ? summary.converted
            : transfers.filter((transfer) => String(transfer.status || '').toLowerCase() === 'converted').length;

        const conversionRate = typeof summary.conversion_rate === 'number'
            ? summary.conversion_rate
            : (totalForConversion > 0 ? (convertedCount / totalForConversion) * 100 : null);

        const avgMinutes = typeof summary.avg_first_contact_minutes === 'number'
            ? summary.avg_first_contact_minutes
            : computeAverageFirstContact(transfers);

        if (elements.totalTransferred) {
            elements.totalTransferred.textContent = typeof totalMonth === 'number' ? String(totalMonth) : '--';
        }

        if (elements.pendingAcceptance) {
            elements.pendingAcceptance.textContent = typeof pendingCount === 'number' ? String(pendingCount) : '--';
        }

        if (elements.conversionRate) {
            elements.conversionRate.textContent = conversionRate === null || conversionRate === undefined
                ? '--'
                : conversionRate.toFixed(1) + '%';
        }

        if (elements.avgFirstContact) {
            elements.avgFirstContact.textContent = formatDuration(avgMinutes);
        }
    }

    function renderTable(data) {
        if (!elements.tableBody) {
            return;
        }

        elements.tableBody.innerHTML = '';

        if (!Array.isArray(data) || data.length === 0) {
            toggleEmptyState(true);
            return;
        }

        toggleEmptyState(false);

        const fragment = document.createDocumentFragment();

        data.forEach((transfer) => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors';

            const leadName = transfer.lead_name || transfer.nome_prospecto || transfer.lead || 'Lead';
            const companyName = transfer.company_name || transfer.company || transfer.empresa || '';
            const vendorName = transfer.vendor_name || transfer.vendor_nome || transfer.vendedor || '--';
            const transferredAt = transfer.transferred_at || transfer.created_at;
            const chatUrl = transfer.chat_url || transfer.link_chat || '';
            const timelineUrl = transfer.timeline_url || transfer.link_timeline || '';
            const status = transfer.status || transfer.status_atual || '';
            const slaInfo = computeSla(transfer);

            row.innerHTML = '
                <td class="px-6 py-4 whitespace-nowrap" data-label="Lead / Empresa">
                    <div class="text-sm font-semibold text-gray-800">' + escapeHtml(leadName) + '</div>
                    <div class="text-xs text-gray-500">' + (companyName ? escapeHtml(companyName) : 'Empresa não informada') + '</div>
                </td>
                <td class="px-6 py-4" data-label="Vendedor">
                    <span class="text-sm text-gray-700">' + escapeHtml(vendorName || '--') + '</span>
                </td>
                <td class="px-6 py-4" data-label="Data transferência">
                    <span class="text-sm text-gray-700">' + formatDate(transferredAt) + '</span>
                </td>
                <td class="px-6 py-4" data-label="Status">
                    ' + formatStatusBadge(status) + '
                </td>
                <td class="px-6 py-4" data-label="SLA">
                    <span class="text-sm ' + (slaInfo.isOverdue ? 'text-red-600 font-semibold' : 'text-gray-700') + '">' + escapeHtml(slaInfo.text) + '</span>
                </td>
                <td class="px-6 py-4" data-label="Ações">
                    <div class="flex flex-wrap gap-2">
                        ' + (chatUrl ? '<a href="' + escapeHtml(chatUrl) + '" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors" target="_blank" rel="noopener">Ver Chat</a>' : '') + '
                        ' + (timelineUrl ? '<a href="' + escapeHtml(timelineUrl) + '" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors" target="_blank" rel="noopener">Ver Timeline</a>' : '') + '
                    </div>
                </td>
            ';

            fragment.appendChild(row);
        });

        elements.tableBody.appendChild(fragment);
    }

    function applyFilters() {
        if (!elements.filtersForm) {
            return;
        }

        const filters = {
            vendor_id: elements.vendorSelect ? elements.vendorSelect.value : undefined,
            status: elements.statusSelect ? elements.statusSelect.value : undefined,
            start_date: elements.startDate ? elements.startDate.value : undefined,
            end_date: elements.endDate ? elements.endDate.value : undefined
        };

        loadTransfers(filters);
    }

    function resetFilters() {
        if (!elements.filtersForm) {
            return;
        }

        elements.filtersForm.reset();
        if (config.defaultStatus && elements.statusSelect) {
            elements.statusSelect.value = config.defaultStatus;
        }

        applyFilters();
    }

    function buildCsvRow(values) {
        return values
            .map((value) => {
                const safeValue = value === null || value === undefined ? '' : String(value);
                if (safeValue.includes(',') || safeValue.includes('"') || safeValue.includes('\n')) {
                    return '"' + safeValue.replace(/"/g, '""') + '"';
                }
                return safeValue;
            })
            .join(',');
    }

    function exportCSV() {
        if (!state.transfers.length) {
            showError('Não há dados para exportar.');
            return;
        }

        clearError();

        const header = ['Lead', 'Empresa', 'Vendedor', 'Data transferência', 'Status', 'SLA'];
        const rows = state.transfers.map((transfer) => {
            const sla = computeSla(transfer);
            return buildCsvRow([
                transfer.lead_name || transfer.nome_prospecto || transfer.lead || '',
                transfer.company_name || transfer.company || transfer.empresa || '',
                transfer.vendor_name || transfer.vendor_nome || transfer.vendedor || '',
                formatDate(transfer.transferred_at || transfer.created_at),
                transfer.status || transfer.status_atual || '',
                sla.text
            ]);
        });

        const csvContent = [buildCsvRow(header)].concat(rows).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const today = new Date();
        const stamp = today.toISOString().slice(0, 10);

        link.setAttribute('href', url);
        link.setAttribute('download', 'leads-transferidos-' + stamp + '.csv');
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function updateExportButtonState() {
        if (!elements.exportButton) {
            return;
        }

        if (state.transfers.length === 0) {
            elements.exportButton.setAttribute('disabled', 'disabled');
            elements.exportButton.classList.add('disabled');
        } else {
            elements.exportButton.removeAttribute('disabled');
            elements.exportButton.classList.remove('disabled');
        }
    }

    function registerEvents() {
        if (elements.applyButton) {
            elements.applyButton.addEventListener('click', (event) => {
                event.preventDefault();
                applyFilters();
            });
        }

        if (elements.resetButton) {
            elements.resetButton.addEventListener('click', (event) => {
                event.preventDefault();
                resetFilters();
            });
        }

        if (elements.filtersForm) {
            elements.filtersForm.addEventListener('submit', (event) => {
                event.preventDefault();
                applyFilters();
            });
        }

        if (elements.exportButton) {
            elements.exportButton.addEventListener('click', (event) => {
                event.preventDefault();
                exportCSV();
            });
        }
    }

    function initializeDashboard() {
        registerEvents();

        if (config.defaultStatus && elements.statusSelect) {
            elements.statusSelect.value = config.defaultStatus;
        }

        applyFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDashboard);
    } else {
        initializeDashboard();
    }

    window.loadTransfers = loadTransfers;
    window.renderTable = renderTable;
    window.applyFilters = applyFilters;
    window.exportCSV = exportCSV;
})(window, document);
