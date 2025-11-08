class TransferLeadModal {
    constructor(modalId = 'transferLeadModal') {
        this.modalElement = document.getElementById(modalId);
        if (!this.modalElement) {
            console.error(`TransferLeadModal: modal element with id "${modalId}" not found.`);
            return;
        }

        this.form = this.modalElement.querySelector('#transferLeadForm');
        this.vendorSelect = this.modalElement.querySelector('#vendor_id');
        this.notesTextarea = this.modalElement.querySelector('#handoff_notes');
        this.notesCounter = this.modalElement.querySelector('#notes_char_count');
        this.vendorError = this.modalElement.querySelector('#vendor_error');
        this.notesError = this.modalElement.querySelector('#notes_error');
        this.submitButton = this.modalElement.querySelector('#transferSubmitBtn');
        this.submitText = this.submitButton?.querySelector('.btn-text');
        this.submitSpinner = this.submitButton?.querySelector('.btn-spinner');
        this.overlay = this.modalElement.querySelector('.modal-overlay');
        this.closeButtons = this.modalElement.querySelectorAll('.modal-close');
        this.urgencyRadios = Array.from(this.modalElement.querySelectorAll('.radio-urgency input[type="radio"]'));
        this.onSuccessCallback = null;
        this.focusableElementsSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
        this.boundHandleKeydown = this.handleKeydown.bind(this);

        this.modalElement.setAttribute('role', 'dialog');
        this.modalElement.setAttribute('aria-modal', 'true');
        this.modalElement.setAttribute('aria-hidden', 'true');

        this.init();
    }

    init() {
        if (!this.form) {
            console.error('TransferLeadModal: form element not found.');
            return;
        }

        this.form.addEventListener('submit', (event) => this.handleSubmit(event));

        if (this.submitButton) {
            this.submitButton.addEventListener('click', (event) => {
                event.preventDefault();
                this.form.requestSubmit();
            });
        }

        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.close());
        }

        this.closeButtons.forEach((button) => {
            button.addEventListener('click', () => this.close());
        });

        if (this.notesTextarea) {
            this.notesTextarea.addEventListener('input', () => this.updateCharCount());
        }

        this.urgencyRadios.forEach((radio) => {
            radio.addEventListener('change', () => this.updateUrgencyStyles());
        });

        document.addEventListener('keydown', this.boundHandleKeydown);

        this.updateUrgencyStyles();
    }

    open(prospeccaoId, prospeccaoNome) {
        if (!this.modalElement) return;

        this.modalElement.style.display = 'block';
        requestAnimationFrame(() => {
            this.modalElement.classList.add('is-visible');
            this.modalElement.setAttribute('aria-hidden', 'false');
        });

        const idField = this.modalElement.querySelector('#transfer_prospeccao_id');
        const nameField = this.modalElement.querySelector('#transfer_prospeccao_nome');
        if (idField) {
            idField.value = prospeccaoId || '';
        }
        if (nameField) {
            nameField.textContent = prospeccaoNome || '';
        }

        this.clearFieldErrors();
        this.updateCharCount();
        this.enableForm();
        this.updateUrgencyStyles();

        this.loadVendors();

        setTimeout(() => this.focusFirstElement(), 150);
    }

    close() {
        if (!this.modalElement) return;

        this.modalElement.classList.remove('is-visible');
        this.modalElement.setAttribute('aria-hidden', 'true');
        setTimeout(() => {
            this.modalElement.style.display = 'none';
        }, 200);

        if (this.form) {
            this.form.reset();
        }
        this.clearFieldErrors();
        this.updateCharCount();
        this.enableForm();
        this.updateUrgencyStyles();
    }

    async loadVendors() {
        if (!this.vendorSelect) return;

        this.vendorSelect.innerHTML = '<option value="">Carregando...</option>';
        this.vendorSelect.disabled = true;

        try {
            const response = await fetch('/api/vendors/active', {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Falha ao carregar vendedores.');
            }

            const data = await response.json();
            const vendors = Array.isArray(data) ? data : data?.vendors || [];

            if (!Array.isArray(vendors) || vendors.length === 0) {
                this.vendorSelect.innerHTML = '<option value="">Nenhum vendedor disponível</option>';
                this.vendorSelect.disabled = true;
                return;
            }

            const recommendedIndices = this.getRecommendedVendorIndices(vendors);
            this.vendorSelect.innerHTML = '<option value="">Selecione um vendedor</option>';

            vendors.forEach((vendor, index) => {
                const option = document.createElement('option');
                const vendorId = vendor?.id ?? vendor?.vendor_id ?? vendor?.ID;
                if (vendorId === undefined || vendorId === null || vendorId === '') {
                    return;
                }
                option.value = vendorId;
                option.textContent = vendor.name || vendor.nome || vendor.full_name || `Vendedor #${vendorId}`;

                if (recommendedIndices.has(index)) {
                    option.dataset.recommended = 'true';
                    option.textContent += ' — Recomendado';
                }

                this.vendorSelect.appendChild(option);
            });

            if (this.vendorSelect.options.length === 1) {
                this.vendorSelect.innerHTML = '<option value="">Nenhum vendedor disponível</option>';
                this.vendorSelect.disabled = true;
                return;
            }

            this.vendorSelect.disabled = false;
            this.focusFirstElement();
        } catch (error) {
            console.error('TransferLeadModal loadVendors error:', error);
            this.vendorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
            this.showError('Não foi possível carregar a lista de vendedores. Tente novamente mais tarde.');
        }
    }

    getRecommendedVendorIndices(vendors) {
        if (!Array.isArray(vendors)) return new Set();

        const explicitRecommendations = new Set();
        vendors.forEach((vendor, index) => {
            if (vendor?.recommended || vendor?.is_recommended || vendor?.highlight) {
                explicitRecommendations.add(index);
            }
        });

        if (explicitRecommendations.size >= 3) {
            return new Set(Array.from(explicitRecommendations).slice(0, 3));
        }

        const vendorsWithScore = vendors.map((vendor, index) => {
            const score = vendor.recommendation_score ?? vendor.score ?? vendor.performance_score ?? vendor.conversion_rate ?? 0;
            return { index, score: Number(score) || 0 };
        });

        vendorsWithScore.sort((a, b) => b.score - a.score);

        const recommended = new Set(explicitRecommendations);
        vendorsWithScore.forEach(({ index }) => {
            if (recommended.size < 3) {
                recommended.add(index);
            }
        });

        return recommended;
    }

    async handleSubmit(event) {
        event.preventDefault();
        if (!this.form) return;

        const validation = this.validate();
        this.clearFieldErrors();

        if (!validation.valid) {
            validation.errors.forEach(({ field, message }) => this.setFieldError(field, message));
            this.showError('Corrija os erros destacados antes de prosseguir.');
            return;
        }

        const bestTimeValue = this.form.querySelector('#best_time')?.value?.trim() || null;
        const formData = {
            prospeccao_id: this.form.querySelector('#transfer_prospeccao_id')?.value,
            vendor_id: this.vendorSelect.value,
            handoff_notes: this.notesTextarea.value.trim(),
            urgency: this.form.querySelector('input[name="urgency"]:checked')?.value,
            best_time: bestTimeValue
        };

        const payload = {
            prospeccao_id: formData.prospeccao_id,
            vendor_id: formData.vendor_id,
            handoff_notes: formData.handoff_notes,
            urgency: formData.urgency
        };

        try {
            this.setLoading(true);
            const response = await fetch('/api/handoffs/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const responseBody = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = responseBody?.message || 'Não foi possível transferir o lead.';
                throw new Error(message);
            }

            this.showSuccess('Lead transferido com sucesso!');
            if (typeof this.onSuccessCallback === 'function') {
                this.onSuccessCallback(responseBody?.handoff_id || null, {
                    response: responseBody,
                    formData
                });
            }
            this.close();
        } catch (error) {
            console.error('TransferLeadModal handleSubmit error:', error);
            this.showError(error.message || 'Ocorreu um erro ao transferir o lead.');
        } finally {
            this.setLoading(false);
        }
    }

    validate() {
        const errors = [];

        if (!this.vendorSelect || !this.vendorSelect.value) {
            errors.push({ field: 'vendor_id', message: 'Selecione um vendedor.' });
        }

        const urgency = this.form?.querySelector('input[name="urgency"]:checked')?.value;
        if (!urgency) {
            errors.push({ field: 'urgency', message: 'Selecione uma urgência.' });
        }

        const notes = this.notesTextarea?.value?.trim() || '';
        if (notes.length < 50) {
            errors.push({ field: 'handoff_notes', message: 'Informe pelo menos 50 caracteres nas notas.' });
        }

        return {
            valid: errors.length === 0,
            errors
        };
    }

    setFieldError(field, message) {
        if (field === 'vendor_id' && this.vendorError) {
            this.vendorError.textContent = message;
        }
        if (field === 'handoff_notes' && this.notesError) {
            this.notesError.textContent = message;
        }
        if (field === 'urgency') {
            const group = this.modalElement.querySelector('.radio-group');
            if (group) {
                let errorEl = group.querySelector('.field-error');
                if (!errorEl) {
                    errorEl = document.createElement('span');
                    errorEl.className = 'field-error';
                    group.appendChild(errorEl);
                }
                errorEl.textContent = message;
            }
        }
    }

    clearFieldErrors() {
        if (this.vendorError) this.vendorError.textContent = '';
        if (this.notesError) this.notesError.textContent = '';
        this.modalElement.querySelectorAll('.radio-group .field-error').forEach((el) => el.remove());
    }

    setLoading(isLoading) {
        if (!this.form) return;
        const formElements = this.form.querySelectorAll('input, select, textarea, button');
        formElements.forEach((element) => {
            if (element !== this.submitButton) {
                element.disabled = isLoading;
            }
        });

        if (this.submitButton) {
            this.submitButton.disabled = isLoading;
        }
        if (this.submitText && this.submitSpinner) {
            this.submitText.style.display = isLoading ? 'none' : 'inline-flex';
            this.submitSpinner.style.display = isLoading ? 'inline-flex' : 'none';
        }
    }

    enableForm() {
        if (!this.form) return;
        this.form.querySelectorAll('input, select, textarea, button').forEach((element) => {
            element.disabled = false;
        });
        if (this.submitText && this.submitSpinner) {
            this.submitText.style.display = 'inline-flex';
            this.submitSpinner.style.display = 'none';
        }
    }

    updateCharCount() {
        if (!this.notesTextarea || !this.notesCounter) return;
        const length = this.notesTextarea.value.trim().length;
        this.notesCounter.textContent = length;
    }

    updateUrgencyStyles() {
        const selectedValue = this.form?.querySelector('input[name="urgency"]:checked')?.value;
        this.modalElement.querySelectorAll('.radio-urgency').forEach((wrapper) => {
            if (selectedValue && wrapper.querySelector(`input[value="${selectedValue}"]`)) {
                wrapper.classList.add('is-selected');
            } else {
                wrapper.classList.remove('is-selected');
            }
        });
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.modalElement?.classList.contains('is-visible')) {
            this.close();
        }
        if (event.key === 'Tab' && this.modalElement?.classList.contains('is-visible')) {
            this.trapFocus(event);
        }
    }

    focusFirstElement() {
        if (this.vendorSelect && !this.vendorSelect.disabled) {
            this.vendorSelect.focus();
            return;
        }
        const firstFocusable = this.modalElement.querySelector(this.focusableElementsSelector);
        firstFocusable?.focus();
    }

    trapFocus(event) {
        const focusableElements = Array.from(this.modalElement.querySelectorAll(this.focusableElementsSelector))
            .filter((el) => !el.hasAttribute('disabled') && el.getAttribute('tabindex') !== '-1');

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            }
        } else if (document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showToast(message, type = 'success') {
        document.querySelectorAll('.transfer-toast').forEach((toast) => toast.remove());
        const toast = document.createElement('div');
        toast.className = `transfer-toast transfer-toast--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.textContent = message;

        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    setOnSuccess(callback) {
        this.onSuccessCallback = typeof callback === 'function' ? callback : null;
    }
}

window.TransferLeadModal = TransferLeadModal;

