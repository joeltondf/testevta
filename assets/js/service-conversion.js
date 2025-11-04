(function () {
    'use strict';

    const parseCurrency = (value) => {
        if (value === null || value === undefined) {
            return null;
        }
        let normalized = String(value).trim();
        if (normalized === '') {
            return null;
        }
        normalized = normalized.replace(/[^0-9,.-]/g, '');
        if (normalized === '' || normalized === '-' || normalized === '.' || normalized === ',') {
            return null;
        }
        const commaPos = normalized.lastIndexOf(',');
        const dotPos = normalized.lastIndexOf('.');
        let decimalSeparator = null;
        if (commaPos > -1 && dotPos > -1) {
            decimalSeparator = commaPos > dotPos ? ',' : '.';
        } else if (commaPos > -1) {
            decimalSeparator = ',';
        } else if (dotPos > -1) {
            decimalSeparator = '.';
        }
        if (decimalSeparator !== null) {
            const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
            normalized = normalized.split(thousandSeparator).join('');
            normalized = normalized.replace(decimalSeparator, '.');
        } else {
            normalized = normalized.replace(/[.,]/g, '');
        }
        const result = parseFloat(normalized);
        return Number.isNaN(result) ? null : result;
    };

    const formatCurrency = (value) => {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return 'Não informado';
        }
        return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const setupDeadlineForm = (form) => {
        const typeInputs = form.querySelectorAll('[data-deadline-type]');
        const daysWrapper = form.querySelector('#deadline-days-wrapper');
        const dateWrapper = form.querySelector('#deadline-date-wrapper');

        const toggleDeadlineInputs = () => {
            const selected = form.querySelector('[data-deadline-type]:checked');
            const type = selected ? selected.value : 'dias';
            if (daysWrapper) {
                daysWrapper.classList[type === 'dias' ? 'remove' : 'add']('hidden');
            }
            if (dateWrapper) {
                dateWrapper.classList[type === 'data' ? 'remove' : 'add']('hidden');
            }
        };

        typeInputs.forEach((input) => input.addEventListener('change', toggleDeadlineInputs));
        toggleDeadlineInputs();
    };

    const setupPaymentForm = (form) => {
        const methodSelect = form.querySelector('[data-payment-method]');
        const totalInput = form.querySelector('[data-total-value]');
        const entryInput = form.querySelector('[data-entry-value]');
        const totalDisplay = form.querySelector('[data-total-display]');
        const balanceDisplay = form.querySelector('[data-balance-display]');
        const sections = form.querySelectorAll('[data-payment-section]');
        const balanceInput = form.querySelector('[data-balance-input]');
        const fileInputs = form.querySelectorAll('input[type="file"][data-upload-display]');

        const updateFileTileDisplay = (input) => {
            if (!input) {
                return;
            }
            const target = input.dataset.uploadDisplay;
            if (!target) {
                return;
            }
            const display = form.querySelector(`[data-upload-filename="${target}"]`);
            if (!display) {
                return;
            }
            const placeholder = display.dataset.placeholder || 'Nenhum arquivo selecionado';
            const files = Array.from(input.files || []);
            if (files.length > 0) {
                display.textContent = files.length === 1 ? files[0].name : `${files.length} arquivos selecionados`;
            } else {
                display.textContent = placeholder;
            }
        };

        const toggleFieldState = (field, enabled) => {
            if (!field) {
                return;
            }

            if (enabled) {
                field.disabled = false;
                field.name = field.dataset.fieldName || field.name;
            } else {
                field.disabled = true;
                field.removeAttribute('name');
                if (field.matches('[data-entry-value]')) {
                    field.value = '';
                }
                if (field.type === 'file') {
                    field.value = '';
                    updateFileTileDisplay(field);
                }
            }
        };

        const updateSummary = () => {
            const method = methodSelect ? methodSelect.value : 'Pagamento único';
            const total = totalInput ? parseCurrency(totalInput.value) : null;
            const entry = entryInput && !entryInput.disabled ? parseCurrency(entryInput.value) : null;

            if (totalDisplay) {
                totalDisplay.textContent = total !== null ? formatCurrency(total) : 'Não informado';
            }

            if (balanceDisplay) {
                if (method === 'Pagamento parcelado') {
                    if (total !== null && entry !== null) {
                        const balance = Math.max(total - entry, 0);
                        balanceDisplay.textContent = formatCurrency(balance);
                        if (balanceInput) {
                            balanceInput.value = formatCurrency(balance);
                        }
                    } else {
                        balanceDisplay.textContent = '-';
                        if (balanceInput) {
                            balanceInput.value = balanceInput.dataset.defaultValue || 'R$ 0,00';
                        }
                    }
                } else {
                    balanceDisplay.textContent = total !== null ? formatCurrency(0) : '-';
                    if (balanceInput) {
                        balanceInput.value = balanceInput.dataset.defaultValue || 'R$ 0,00';
                    }
                }
            }
        };

        const toggleSections = () => {
            const method = methodSelect ? methodSelect.value : 'Pagamento único';
            sections.forEach((section) => {
                const isActive = section.dataset.paymentSection === method;
                section.classList.toggle('hidden', !isActive);
                section.querySelectorAll('[data-field-name]').forEach((field) => {
                    toggleFieldState(field, isActive);
                });
            });
            updateSummary();
        };

        if (methodSelect) {
            methodSelect.addEventListener('change', toggleSections);
        }

        if (totalInput) {
            totalInput.addEventListener('input', updateSummary);
        }

        if (entryInput) {
            entryInput.addEventListener('input', updateSummary);
        }

        fileInputs.forEach((input) => {
            input.addEventListener('change', () => updateFileTileDisplay(input));
            updateFileTileDisplay(input);
        });

        toggleSections();
        updateSummary();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const deadlineForm = document.querySelector('[data-conversion-step="deadline"]');
        if (deadlineForm) {
            setupDeadlineForm(deadlineForm);
        }

        const paymentForm = document.querySelector('[data-conversion-step="payment"]');
        if (paymentForm) {
            setupPaymentForm(paymentForm);
        }
    });
})();
