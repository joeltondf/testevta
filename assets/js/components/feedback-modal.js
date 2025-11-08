(function (window, document) {
    'use strict';

    const DEFAULT_MODAL_ID = 'feedbackModal';

    function parseBoolean(value) {
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

    class FeedbackModal {
        constructor(modalId = DEFAULT_MODAL_ID) {
            this.modalElement = document.getElementById(modalId);
            if (!this.modalElement) {
                console.error(`FeedbackModal: elemento com id "${modalId}" não encontrado.`);
                return;
            }

            this.form = this.modalElement.querySelector('form');
            this.starsContainer = this.modalElement.querySelector('.stars');
            this.starElements = Array.from(this.modalElement.querySelectorAll('.stars i'));
            this.checkboxInputs = Array.from(this.modalElement.querySelectorAll('.checkboxes input[type="checkbox"]'));
            this.commentsField = this.modalElement.querySelector('textarea[name="comments"]');
            this.submitButton = this.modalElement.querySelector('button[type="submit"]');
            this.submitText = this.submitButton?.querySelector('.btn-text');
            this.submitSpinner = this.submitButton?.querySelector('.btn-spinner');
            this.errorElement = this.modalElement.querySelector('[data-feedback-error]');
            this.thankYouElement = this.modalElement.querySelector('[data-feedback-thank-you]');
            this.leadNameElement = this.modalElement.querySelector('[data-feedback-lead]');
            this.closeButtons = Array.from(this.modalElement.querySelectorAll('[data-feedback-close]'));
            this.overlay = this.modalElement.querySelector('[data-feedback-overlay]');
            this.ratingValue = 0;
            this.handoffId = null;
            this.onSubmittedCallback = null;
            this.focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
            this.boundHandleKeydown = this.handleKeydown.bind(this);

            this.init();
        }

        init() {
            if (!this.form) {
                console.error('FeedbackModal: formulário não encontrado.');
                return;
            }

            this.form.addEventListener('submit', (event) => this.handleSubmit(event));

            this.starElements.forEach((star) => {
                star.addEventListener('click', () => {
                    const rating = Number(star.dataset.rating) || 0;
                    this.setRating(rating);
                });
                star.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        const rating = Number(star.dataset.rating) || 0;
                        this.setRating(rating);
                    }
                });
            });

            if (this.overlay) {
                this.overlay.addEventListener('click', () => this.close());
            }

            this.closeButtons.forEach((button) => {
                button.addEventListener('click', () => this.close());
            });
        }

        setRating(value) {
            const numericValue = Math.max(0, Math.min(5, Number(value) || 0));
            this.ratingValue = numericValue;
            this.starElements.forEach((star) => {
                const starRating = Number(star.dataset.rating) || 0;
                if (starRating <= numericValue) {
                    star.classList.remove('far');
                    star.classList.add('fas');
                } else {
                    star.classList.remove('fas');
                    star.classList.add('far');
                }
            });
            if (this.errorElement) {
                this.errorElement.textContent = '';
                this.errorElement.hidden = true;
            }
        }

        open(handoffId, context = {}) {
            if (!this.modalElement || !this.form) {
                return;
            }

            this.handoffId = handoffId;
            this.setRating(0);
            this.form.reset();
            this.enableForm();
            if (this.thankYouElement) {
                this.thankYouElement.hidden = true;
            }
            if (this.errorElement) {
                this.errorElement.textContent = '';
                this.errorElement.hidden = true;
            }

            if (this.leadNameElement) {
                const leadName = context.leadName || '';
                this.leadNameElement.textContent = leadName ? `Lead: ${leadName}` : '';
                this.leadNameElement.hidden = leadName === '';
            }

            this.modalElement.style.display = 'block';
            requestAnimationFrame(() => {
                this.modalElement.classList.add('is-visible');
                this.modalElement.setAttribute('aria-hidden', 'false');
            });

            document.addEventListener('keydown', this.boundHandleKeydown);
            window.setTimeout(() => this.focusFirstElement(), 120);
        }

        close() {
            if (!this.modalElement) {
                return;
            }

            this.modalElement.classList.remove('is-visible');
            this.modalElement.setAttribute('aria-hidden', 'true');
            window.setTimeout(() => {
                if (this.modalElement) {
                    this.modalElement.style.display = 'none';
                }
            }, 200);

            document.removeEventListener('keydown', this.boundHandleKeydown);
            this.handoffId = null;
        }

        handleKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(this.modalElement.querySelectorAll(this.focusableSelector))
                .filter((el) => !el.hasAttribute('disabled') && el.offsetParent !== null);
            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        focusFirstElement() {
            if (!this.modalElement) {
                return;
            }

            const focusable = this.modalElement.querySelector(this.focusableSelector);
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus();
            }
        }

        async handleSubmit(event) {
            event.preventDefault();

            if (!this.handoffId) {
                this.showError('Identificador do handoff inválido.');
                return;
            }

            if (this.ratingValue <= 0) {
                this.showError('Selecione uma nota de 1 a 5 estrelas.');
                return;
            }

            const payload = {
                handoff_id: this.handoffId,
                quality_score: this.ratingValue,
                comments: this.commentsField ? this.commentsField.value.trim() : '',
            };

            this.checkboxInputs.forEach((input) => {
                const key = input.name || input.id;
                if (!key) {
                    return;
                }
                payload[key] = parseBoolean(input.checked);
            });

            try {
                this.disableForm();
                const response = await fetch('/api/handoffs/feedback', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    const errorBody = await response.json().catch(() => null);
                    const message = errorBody?.message || 'Não foi possível enviar o feedback.';
                    throw new Error(message);
                }

                this.showThankYou();
                if (typeof this.onSubmittedCallback === 'function') {
                    this.onSubmittedCallback(payload);
                }
            } catch (error) {
                this.showError(error.message || 'Ocorreu um erro ao enviar seu feedback.');
                this.enableForm();
            }
        }

        disableForm() {
            if (this.submitButton) {
                this.submitButton.disabled = true;
            }
            if (this.submitText) {
                this.submitText.style.display = 'none';
            }
            if (this.submitSpinner) {
                this.submitSpinner.hidden = false;
            }
            this.checkboxInputs.forEach((input) => {
                input.disabled = true;
            });
            if (this.commentsField) {
                this.commentsField.disabled = true;
            }
            this.starElements.forEach((star) => {
                star.setAttribute('tabindex', '-1');
            });
        }

        enableForm() {
            if (this.submitButton) {
                this.submitButton.disabled = false;
            }
            if (this.submitText) {
                this.submitText.style.display = '';
            }
            if (this.submitSpinner) {
                this.submitSpinner.hidden = true;
            }
            this.checkboxInputs.forEach((input) => {
                input.disabled = false;
            });
            if (this.commentsField) {
                this.commentsField.disabled = false;
            }
            this.starElements.forEach((star) => {
                star.setAttribute('tabindex', '0');
            });
        }

        showError(message) {
            if (!this.errorElement) {
                window.alert(message);
                return;
            }
            this.errorElement.textContent = message;
            this.errorElement.hidden = false;
        }

        showThankYou() {
            if (this.errorElement) {
                this.errorElement.textContent = '';
                this.errorElement.hidden = true;
            }

            if (this.thankYouElement) {
                this.thankYouElement.hidden = false;
            }

            window.setTimeout(() => {
                this.close();
                this.enableForm();
                if (this.thankYouElement) {
                    this.thankYouElement.hidden = true;
                }
            }, 2200);
        }

        setOnSubmitted(callback) {
            if (typeof callback === 'function') {
                this.onSubmittedCallback = callback;
            }
        }
    }

    window.FeedbackModal = FeedbackModal;
})(window, document);
