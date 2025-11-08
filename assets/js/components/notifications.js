class NotificationDropdown {
    constructor(options = {}) {
        const defaults = {
            bellSelector: '.notification-bell',
            triggerSelector: '[data-role="trigger"]',
            dropdownSelector: '[data-role="dropdown"]',
            listSelector: '[data-role="notification-list"]',
            badgeSelector: '[data-role="badge"]',
            markAllSelector: '[data-action="mark-all"]',
            apiUrl: '/api/notifications',
            markReadUrl: '/api/notifications/mark-read',
            perPage: 10,
            pollingInterval: 30000,
            unreadOnly: true,
        };

        this.options = Object.assign({}, defaults, options);
        this.container = typeof this.options.container === 'string'
            ? document.querySelector(this.options.container)
            : this.options.container || document.querySelector(this.options.bellSelector);

        if (!this.container) {
            return;
        }

        this.trigger = this.container.querySelector(this.options.triggerSelector);
        this.dropdown = this.container.querySelector(this.options.dropdownSelector);
        this.list = this.container.querySelector(this.options.listSelector);
        this.badge = this.container.querySelector(this.options.badgeSelector);
        this.markAllButton = this.container.querySelector(this.options.markAllSelector);

        this.notifications = [];
        this.unreadCount = 0;
        this.isOpen = false;
        this.isLoading = false;
        this.pollTimer = null;
        this.hasError = false;

        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.toggleDropdown = this.toggleDropdown.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleDropdownClick = this.handleDropdownClick.bind(this);

        this.configureFromDataset();
        this.bindEvents();
        this.load();
        this.startPolling();
    }

    configureFromDataset() {
        if (!this.container) {
            return;
        }

        const { dataset } = this.container;
        if (dataset.apiUrl) {
            this.options.apiUrl = dataset.apiUrl;
        }
        if (dataset.markReadUrl) {
            this.options.markReadUrl = dataset.markReadUrl;
        }
        if (dataset.perPage) {
            const perPage = parseInt(dataset.perPage, 10);
            if (!Number.isNaN(perPage) && perPage >= 0) {
                this.options.perPage = perPage;
            }
        }
        if (dataset.pollingInterval) {
            const interval = parseInt(dataset.pollingInterval, 10);
            if (!Number.isNaN(interval) && interval >= 5000) {
                this.options.pollingInterval = interval;
            }
        }
        if (dataset.unreadOnly) {
            this.options.unreadOnly = dataset.unreadOnly === '1' || dataset.unreadOnly === 'true';
        }
    }

    bindEvents() {
        if (this.trigger) {
            this.trigger.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleDropdown();
            });
        } else {
            this.container.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleDropdown();
            });
        }

        if (this.dropdown) {
            this.dropdown.addEventListener('click', this.handleDropdownClick);
        }

        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleKeyDown);
    }

    async load() {
        if (!this.dropdown || this.isLoading) {
            return;
        }

        this.isLoading = true;
        this.hasError = false;
        this.render();

        const params = new URLSearchParams();
        if (this.options.unreadOnly) {
            params.append('unread_only', '1');
        }
        params.append('per_page', String(this.options.perPage));

        const url = `${this.options.apiUrl}?${params.toString()}`;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Erro ao carregar notificações (${response.status})`);
            }

            const data = await response.json();
            if (!data || data.success === false) {
                const message = data && data.message ? data.message : 'Erro ao carregar notificações.';
                throw new Error(message);
            }

            this.notifications = Array.isArray(data.notifications) ? data.notifications : [];
            if (typeof data.unread_count === 'number') {
                this.unreadCount = data.unread_count;
            } else {
                this.unreadCount = this.notifications.filter((notification) => !notification.lida).length;
            }

            this.render();
        } catch (error) {
            console.error(error);
            this.hasError = true;
            this.render();
        } finally {
            this.isLoading = false;
        }
    }

    render() {
        if (!this.list) {
            return;
        }

        this.updateBadge();
        this.updateMarkAllButton();

        this.list.innerHTML = '';

        if (this.isLoading) {
            this.list.appendChild(this.createStatusMessage('Carregando notificações...'));
            return;
        }

        if (this.hasError) {
            this.list.appendChild(this.createStatusMessage('Não foi possível carregar as notificações.', 'error'));
            return;
        }

        if (!Array.isArray(this.notifications) || this.notifications.length === 0) {
            this.list.appendChild(this.createStatusMessage('Nenhuma notificação no momento.'));
            return;
        }

        this.notifications.forEach((notification) => {
            const item = this.createNotificationItem(notification);
            this.list.appendChild(item);
        });
    }

    createStatusMessage(message, type = 'info') {
        const element = document.createElement('div');
        element.className = `notification-dropdown__status notification-dropdown__status--${type}`;
        element.textContent = message;
        return element;
    }

    createNotificationItem(notification) {
        const { id, mensagem, link, tipo_alerta: tipoAlerta, relative_time: relativeTime, remetente_nome: remetenteNome } = notification;
        const isUnread = String(notification.lida) !== '1';

        const item = document.createElement('a');
        item.href = this.normalizeLink(link);
        item.className = [
            'notification-item',
            isUnread ? 'notification-item--unread' : 'notification-item--read',
            tipoAlerta ? `notification-item--${this.slugify(tipoAlerta)}` : '',
        ]
            .filter(Boolean)
            .join(' ');
        item.setAttribute('data-notification-id', String(id));
        item.setAttribute('data-notification-link', this.normalizeLink(link));

        const iconWrapper = document.createElement('div');
        iconWrapper.className = 'notification-item__icon';

        const icon = document.createElement('i');
        icon.className = `fas ${this.resolveIconClass(tipoAlerta)}`;
        iconWrapper.appendChild(icon);

        const content = document.createElement('div');
        content.className = 'notification-item__content';

        const title = document.createElement('p');
        title.className = 'notification-item__message';
        title.textContent = mensagem || 'Notificação';

        const meta = document.createElement('p');
        meta.className = 'notification-item__meta';
        const parts = [];
        if (remetenteNome) {
            parts.push(remetenteNome);
        }
        if (relativeTime) {
            parts.push(relativeTime);
        }
        meta.textContent = parts.join(' • ');

        content.appendChild(title);
        if (meta.textContent) {
            content.appendChild(meta);
        }

        item.appendChild(iconWrapper);
        item.appendChild(content);

        return item;
    }

    async markAsRead(notificationId) {
        const id = parseInt(notificationId, 10);
        if (!id) {
            return false;
        }

        try {
            const response = await fetch(this.options.markReadUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ notification_id: id }),
            });

            if (!response.ok) {
                throw new Error(`Erro ao marcar notificação como lida (${response.status})`);
            }

            const data = await response.json();
            if (data && data.success === false) {
                throw new Error(data.message || 'Erro ao marcar notificação como lida.');
            }

            if (this.options.unreadOnly) {
                this.notifications = this.notifications.filter((notification) => notification.id !== id);
            } else {
                this.notifications = this.notifications.map((notification) =>
                    notification.id === id
                        ? Object.assign({}, notification, { lida: 1 })
                        : notification
                );
            }
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            this.render();
            return true;
        } catch (error) {
            console.error(error);
            return false;
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch(this.options.markReadUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'all' }),
            });

            if (!response.ok) {
                throw new Error(`Erro ao marcar notificações como lidas (${response.status})`);
            }

            const data = await response.json();
            if (data && data.success === false) {
                throw new Error(data.message || 'Erro ao marcar notificações como lidas.');
            }

            if (this.options.unreadOnly) {
                this.notifications = [];
            } else {
                this.notifications = this.notifications.map((notification) =>
                    Object.assign({}, notification, { lida: 1 })
                );
            }
            this.unreadCount = 0;
            this.render();
            return true;
        } catch (error) {
            console.error(error);
            return false;
        }
    }

    async poll() {
        if (!this.options.pollingInterval) {
            return;
        }

        const params = new URLSearchParams();
        params.append('unread_only', '1');
        params.append('per_page', '0');

        const url = `${this.options.apiUrl}?${params.toString()}`;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Erro ao obter contagem de notificações (${response.status})`);
            }

            const data = await response.json();
            if (data && data.success === false) {
                throw new Error(data.message || 'Erro ao obter contagem de notificações.');
            }

            if (typeof data.unread_count === 'number') {
                const previousCount = this.unreadCount;
                this.unreadCount = data.unread_count;
                this.updateBadge();
                this.updateMarkAllButton();

                if (this.isOpen && previousCount !== this.unreadCount) {
                    await this.load();
                }
            }
        } catch (error) {
            console.error(error);
        }
    }

    startPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }

        if (!this.options.pollingInterval) {
            return;
        }

        this.pollTimer = setInterval(() => {
            this.poll();
        }, this.options.pollingInterval);
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    toggleDropdown() {
        if (!this.dropdown) {
            return;
        }

        if (this.isOpen) {
            this.closeDropdown();
            return;
        }

        this.openDropdown();
    }

    openDropdown() {
        if (!this.dropdown) {
            return;
        }

        this.dropdown.style.display = 'block';
        this.dropdown.setAttribute('aria-hidden', 'false');
        if (this.trigger) {
            this.trigger.setAttribute('aria-expanded', 'true');
        }
        this.isOpen = true;
        this.load();
    }

    closeDropdown() {
        if (!this.dropdown) {
            return;
        }

        this.dropdown.style.display = 'none';
        this.dropdown.setAttribute('aria-hidden', 'true');
        if (this.trigger) {
            this.trigger.setAttribute('aria-expanded', 'false');
        }
        this.isOpen = false;
    }

    handleDocumentClick(event) {
        if (!this.container || !this.dropdown) {
            return;
        }

        if (!this.container.contains(event.target)) {
            this.closeDropdown();
        }
    }

    handleKeyDown(event) {
        if (event.key === 'Escape') {
            this.closeDropdown();
        }
    }

    handleDropdownClick(event) {
        const markAllButton = event.target.closest('[data-action="mark-all"]');
        if (markAllButton) {
            event.preventDefault();
            event.stopPropagation();
            if (!markAllButton.disabled) {
                this.markAllAsRead();
            }
            return;
        }

        const item = event.target.closest('[data-notification-id]');
        if (!item) {
            return;
        }

        const notificationId = item.getAttribute('data-notification-id');
        const targetLink = item.getAttribute('data-notification-link');
        if (!notificationId || !targetLink) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        this.markAsRead(notificationId).finally(() => {
            this.closeDropdown();
            if (targetLink && targetLink !== '#') {
                window.location.href = targetLink;
            }
        });
    }

    updateBadge() {
        if (!this.badge) {
            return;
        }

        const count = Number.isFinite(this.unreadCount) ? this.unreadCount : 0;
        this.badge.textContent = String(count);
        if (count <= 0) {
            this.badge.setAttribute('hidden', '');
        } else {
            this.badge.removeAttribute('hidden');
        }
    }

    updateMarkAllButton() {
        if (!this.markAllButton) {
            return;
        }

        const hasUnread = this.unreadCount > 0;
        this.markAllButton.disabled = !hasUnread;
        this.markAllButton.classList.toggle('is-disabled', !hasUnread);
    }

    normalizeLink(link) {
        if (!link || typeof link !== 'string') {
            return '#';
        }

        if (/^https?:\/\//i.test(link)) {
            return link;
        }

        if (link.startsWith('/')) {
            return link;
        }

        return `/${link}`;
    }

    resolveIconClass(tipoAlerta) {
        if (!tipoAlerta) {
            return 'fa-bell';
        }

        const map = {
            lead_received: 'fa-user-plus',
            lead_accepted: 'fa-check-circle',
            lead_rejected: 'fa-times-circle',
            lead_lost: 'fa-times-circle',
            lead_contacted: 'fa-phone-alt',
            lead_converted: 'fa-handshake',
            sla_warning: 'fa-exclamation-triangle',
            new_message: 'fa-comment-dots',
            feedback_requested: 'fa-clipboard-check',
            processo_aprovado: 'fa-clipboard-check',
            processo_recusado: 'fa-exclamation-circle',
            processo_pendente: 'fa-hourglass-half',
        };

        return map[tipoAlerta] || 'fa-bell';
    }

    slugify(value) {
        return String(value)
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-');
    }
}

window.NotificationDropdown = NotificationDropdown;

document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.notification-bell');
    if (!containers || containers.length === 0) {
        return;
    }

    containers.forEach((container) => {
        if (!container.__notificationDropdownInstance) {
            container.__notificationDropdownInstance = new NotificationDropdown({ container });
        }
    });
});
