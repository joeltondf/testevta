// ChatWidget: Vanilla JavaScript chat widget for CRM system

class ChatWidget {
    constructor(prospeccaoId, userId, options = {}) {
        this.prospeccaoId = prospeccaoId;
        this.userId = userId;
        this.options = Object.assign(
            {
                conversationEndpoint: '/api/chat/conversation',
                sendEndpoint: '/api/chat/send',
                pollingInterval: 5000,
                container: document.body,
                title: 'Chat',
                otherUserName: 'Contato',
            },
            options
        );

        this.isOpen = false;
        this.messages = [];
        this.unreadCount = 0;
        this.pollInterval = null;
        this.visibilityObserver = null;
        this.seenMessageKeys = new Set();
        this.isLoading = false;

        this._ensureStyles();
        this._buildWidget();
        this.poll();
        this.loadMessages();
    }

    open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.container.classList.add('chat-widget--visible');
        this.container.classList.remove('chat-widget--hidden');
        this.container.setAttribute('aria-hidden', 'false');
        this.loadMessages();
        this.markAsRead();
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.container.classList.add('chat-widget--hidden');
        this.container.classList.remove('chat-widget--visible');
        this.container.setAttribute('aria-hidden', 'true');
    }

    async loadMessages() {
        if (this.isLoading) return;
        this.isLoading = true;
        const url = `${this.options.conversationEndpoint}?prospeccao_id=${encodeURIComponent(
            this.prospeccaoId
        )}`;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Erro ao carregar mensagens (${response.status})`);
            }

            const data = await response.json();
            const messages = this._extractMessages(data);
            this._handleNewMessages(messages);
        } catch (error) {
            console.error('Erro ao carregar mensagens do chat:', error);
        } finally {
            this.isLoading = false;
        }
    }

    async sendMessage(text, isUrgent = false) {
        const trimmed = (text || '').trim();
        if (!trimmed) {
            return;
        }

        const payload = {
            prospeccao_id: this.prospeccaoId,
            user_id: this.userId,
            message: trimmed,
            urgent: Boolean(isUrgent),
        };

        try {
            const response = await fetch(this.options.sendEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(`Erro ao enviar mensagem (${response.status})`);
            }

            const data = await response.json();
            const messages = this._extractMessages(data) || this.messages;
            if (messages && messages.length) {
                this._handleNewMessages(messages, { scroll: true });
            } else {
                await this.loadMessages();
            }

            this.textarea.value = '';
            this.urgentCheckbox.checked = false;
        } catch (error) {
            console.error('Erro ao enviar mensagem do chat:', error);
        }
    }

    poll() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }

        this.pollInterval = setInterval(() => {
            this.loadMessages();
        }, this.options.pollingInterval);
    }

    renderMessages(messages) {
        if (!Array.isArray(messages)) {
            return;
        }

        this.messagesContainer.innerHTML = '';

        messages.forEach((message) => {
            const isOwn = this._isOwnMessage(message);
            const isUrgent = this._isUrgentMessage(message);
            const messageElement = document.createElement('div');
            messageElement.className = [
                'chat-widget__message',
                isOwn ? 'chat-widget__message--own' : 'chat-widget__message--received',
                isUrgent ? 'chat-widget__message--urgent' : '',
            ]
                .filter(Boolean)
                .join(' ');

            const bodyElement = document.createElement('div');
            bodyElement.className = 'chat-widget__message-body';
            bodyElement.textContent = this._getMessageBody(message);

            const metaElement = document.createElement('div');
            metaElement.className = 'chat-widget__message-meta';
            metaElement.textContent = this._formatTimestamp(message);

            messageElement.appendChild(bodyElement);
            messageElement.appendChild(metaElement);
            this.messagesContainer.appendChild(messageElement);
        });

        if (typeof this.messagesContainer.scrollTo === 'function') {
            this.messagesContainer.scrollTo({
                top: this.messagesContainer.scrollHeight,
                behavior: 'smooth',
            });
        } else {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }
    }

    markAsRead() {
        this.unreadCount = 0;
        this._updateBadge();
        this.messages.forEach((message) => {
            const key = this._getMessageKey(message);
            if (key !== null) {
                this.seenMessageKeys.add(key);
            }
        });
    }

    destroy() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }

        if (this.visibilityObserver) {
            this.visibilityObserver.disconnect();
            this.visibilityObserver = null;
        }

        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
    }

    // Private helpers -------------------------------------------------------

    _buildWidget() {
        this.container = document.createElement('div');
        this.container.className = 'chat-widget chat-widget--hidden';
        this.container.setAttribute('aria-hidden', 'true');

        const panel = document.createElement('div');
        panel.className = 'chat-widget__panel';

        const header = document.createElement('div');
        header.className = 'chat-widget__header';

        const title = document.createElement('div');
        title.className = 'chat-widget__title';
        title.textContent = this.options.otherUserName || this.options.title;

        this.badge = document.createElement('span');
        this.badge.className = 'chat-widget__badge';
        this.badge.textContent = '0';
        this.badge.hidden = true;

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'chat-widget__close';
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', () => this.close());

        header.appendChild(title);
        header.appendChild(this.badge);
        header.appendChild(closeButton);

        this.messagesContainer = document.createElement('div');
        this.messagesContainer.className = 'chat-widget__messages';

        const form = document.createElement('form');
        form.className = 'chat-widget__form';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.sendMessage(this.textarea.value, this.urgentCheckbox.checked);
        });

        this.textarea = document.createElement('textarea');
        this.textarea.className = 'chat-widget__textarea';
        this.textarea.rows = 3;
        this.textarea.placeholder = 'Digite sua mensagem...';

        const actions = document.createElement('div');
        actions.className = 'chat-widget__actions';

        const urgentLabel = document.createElement('label');
        urgentLabel.className = 'chat-widget__urgent';

        this.urgentCheckbox = document.createElement('input');
        this.urgentCheckbox.type = 'checkbox';
        this.urgentCheckbox.className = 'chat-widget__urgent-checkbox';

        const urgentText = document.createElement('span');
        urgentText.textContent = 'Urgente';

        urgentLabel.appendChild(this.urgentCheckbox);
        urgentLabel.appendChild(urgentText);

        const sendButton = document.createElement('button');
        sendButton.type = 'submit';
        sendButton.className = 'chat-widget__send';
        sendButton.textContent = 'Enviar';

        actions.appendChild(urgentLabel);
        actions.appendChild(sendButton);

        form.appendChild(this.textarea);
        form.appendChild(actions);

        panel.appendChild(header);
        panel.appendChild(this.messagesContainer);
        panel.appendChild(form);
        this.container.appendChild(panel);

        const parent = this.options.container instanceof Element ? this.options.container : document.body;
        parent.appendChild(this.container);

        this.visibilityObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && this.isOpen) {
                    this.markAsRead();
                }
            });
        }, { threshold: 0.5 });

        this.visibilityObserver.observe(this.container);
    }

    _extractMessages(data) {
        if (!data) return [];

        if (Array.isArray(data)) {
            return data;
        }

        if (Array.isArray(data.messages)) {
            return data.messages;
        }

        if (Array.isArray(data.data)) {
            return data.data;
        }

        if (Array.isArray(data.conversation)) {
            return data.conversation;
        }

        return [];
    }

    _handleNewMessages(messages, options = {}) {
        const previousKeys = new Set(
            this.messages.map((message) => this._getMessageKey(message)).filter((key) => key !== null)
        );
        this.messages = messages;

        if (this.isOpen) {
            this.renderMessages(messages);
            this.markAsRead();
        } else {
            const newUnread = messages.filter((message) => {
                const key = this._getMessageKey(message);
                const isNew = key === null || !previousKeys.has(key);
                return isNew && !this._isOwnMessage(message);
            });

            if (newUnread.length > 0) {
                this.unreadCount += newUnread.length;
                this._updateBadge();
            }
        }

        if (options.scroll) {
            if (typeof this.messagesContainer.scrollTo === 'function') {
                this.messagesContainer.scrollTo({
                    top: this.messagesContainer.scrollHeight,
                    behavior: 'smooth',
                });
            } else {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }
        }
    }

    _updateBadge() {
        if (!this.badge) return;
        if (this.unreadCount > 0) {
            this.badge.textContent = String(this.unreadCount);
            this.badge.hidden = false;
        } else {
            this.badge.hidden = true;
            this.badge.textContent = '0';
        }
    }

    _isOwnMessage(message) {
        const authorId = this._getMessageAuthorId(message);
        return authorId !== null && String(authorId) === String(this.userId);
    }

    _isUrgentMessage(message) {
        return Boolean(message?.urgent || message?.is_urgent || message?.urgente);
    }

    _getMessageAuthorId(message) {
        if (!message) return null;
        if (message.user_id !== undefined && message.user_id !== null) return message.user_id;
        if (message.author_id !== undefined && message.author_id !== null) return message.author_id;
        if (message.sender_id !== undefined && message.sender_id !== null) return message.sender_id;
        return null;
    }

    _getMessageBody(message) {
        return (
            message?.message ||
            message?.body ||
            message?.texto ||
            message?.mensagem ||
            message?.content ||
            ''
        );
    }

    _formatTimestamp(message) {
        const timestamp =
            message?.created_at ||
            message?.updated_at ||
            message?.timestamp ||
            message?.data ||
            message?.date;

        if (!timestamp) return '';

        try {
            const date = timestamp instanceof Date ? timestamp : new Date(timestamp);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            return date.toLocaleString();
        } catch (error) {
            return '';
        }
    }

    _getMessageId(message) {
        if (!message) return null;
        if (message.id !== undefined && message.id !== null) return message.id;
        if (message.message_id !== undefined && message.message_id !== null) return message.message_id;
        if (message.uuid) return message.uuid;
        if (message.created_at) return `${message.created_at}-${this._getMessageAuthorId(message)}`;
        return null;
    }

    _getMessageKey(message) {
        if (!message) return null;
        const id = this._getMessageId(message);
        if (id !== null && id !== undefined) {
            return `id:${String(id)}`;
        }

        const author = this._getMessageAuthorId(message) ?? '';
        const timestamp =
            message?.created_at ||
            message?.updated_at ||
            message?.timestamp ||
            message?.data ||
            message?.date ||
            '';
        const body = this._getMessageBody(message) || '';

        if (!author && !timestamp && !body) {
            return null;
        }

        return `fallback:${author}:${timestamp}:${body}`;
    }

    _ensureStyles() {
        if (document.getElementById('chat-widget-styles')) return;

        const style = document.createElement('style');
        style.id = 'chat-widget-styles';
        style.textContent = `
            .chat-widget {
                position: fixed;
                right: 0;
                bottom: 0;
                width: 100%;
                max-width: 400px;
                z-index: 9999;
                display: flex;
                justify-content: flex-end;
                pointer-events: none;
            }

            .chat-widget__panel {
                background-color: #ffffff;
                border-radius: 12px 0 0 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
                width: 100%;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                transform: translateY(20px);
                opacity: 0;
                transition: transform 0.3s ease, opacity 0.3s ease;
                pointer-events: auto;
            }

            .chat-widget--visible .chat-widget__panel {
                transform: translateY(0);
                opacity: 1;
            }

            .chat-widget--hidden {
                visibility: hidden;
            }

            .chat-widget--visible {
                visibility: visible;
            }

            .chat-widget__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px;
                border-bottom: 1px solid #e5e7eb;
                background-color: #0d6efd;
                color: #ffffff;
                border-radius: 12px 0 0 0;
            }

            .chat-widget__title {
                font-size: 16px;
                font-weight: 600;
            }

            .chat-widget__badge {
                background-color: #dc3545;
                color: #ffffff;
                border-radius: 9999px;
                padding: 2px 8px;
                font-size: 12px;
                font-weight: 600;
                margin-left: 8px;
            }

            .chat-widget__close {
                background: transparent;
                border: none;
                color: #ffffff;
                font-size: 20px;
                cursor: pointer;
                padding: 4px;
                transition: transform 0.2s ease;
            }

            .chat-widget__close:hover {
                transform: scale(1.1);
            }

            .chat-widget__messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                background-color: #f8f9fa;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .chat-widget__message {
                max-width: 85%;
                padding: 10px 14px;
                border-radius: 12px;
                position: relative;
                display: flex;
                flex-direction: column;
                gap: 6px;
                animation: chat-widget-slide-in 0.2s ease;
            }

            .chat-widget__message--own {
                align-self: flex-end;
                background-color: #0d6efd;
                color: #ffffff;
            }

            .chat-widget__message--received {
                align-self: flex-start;
                background-color: #e9ecef;
                color: #212529;
            }

            .chat-widget__message--urgent {
                border: 2px solid #dc3545;
            }

            .chat-widget__message-body {
                word-wrap: break-word;
                white-space: pre-wrap;
                line-height: 1.4;
            }

            .chat-widget__message-meta {
                font-size: 11px;
                opacity: 0.7;
                text-align: right;
            }

            .chat-widget__form {
                border-top: 1px solid #e5e7eb;
                padding: 12px 16px 16px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                background-color: #ffffff;
                border-radius: 0 0 0 12px;
            }

            .chat-widget__textarea {
                resize: none;
                border: 1px solid #ced4da;
                border-radius: 8px;
                padding: 10px;
                font-size: 14px;
                font-family: inherit;
                line-height: 1.4;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .chat-widget__textarea:focus {
                border-color: #0d6efd;
                box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.2);
                outline: none;
            }

            .chat-widget__actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .chat-widget__urgent {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 14px;
            }

            .chat-widget__urgent-checkbox {
                width: 16px;
                height: 16px;
            }

            .chat-widget__send {
                background-color: #0d6efd;
                color: #ffffff;
                border: none;
                border-radius: 8px;
                padding: 10px 18px;
                font-size: 14px;
                cursor: pointer;
                transition: background-color 0.2s ease, transform 0.2s ease;
            }

            .chat-widget__send:hover {
                background-color: #0b5ed7;
                transform: translateY(-1px);
            }

            .chat-widget__send:active {
                transform: translateY(0);
            }

            @keyframes chat-widget-slide-in {
                from {
                    transform: translateY(10px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            @media (max-width: 600px) {
                .chat-widget {
                    max-width: 100%;
                    padding: 0 8px;
                }

                .chat-widget__panel {
                    border-radius: 12px 12px 0 0;
                }

                .chat-widget__header {
                    border-radius: 12px 12px 0 0;
                }

                .chat-widget__form {
                    border-radius: 0 0 12px 12px;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

export default ChatWidget;
