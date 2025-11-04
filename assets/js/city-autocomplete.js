(function (global) {
    'use strict';

    class CityAutocomplete {
        constructor(options) {
            this.input = options.input;
            this.ufInput = options.ufInput;
            this.list = options.list;
            this.statusElement = options.statusElement;
            this.wrapper = options.wrapper || this.input.closest('[data-city-autocomplete]');
            this.fetchCities = options.fetchCities || this.defaultFetch;
            this.minChars = options.minChars || 3;
            this.debounceDelay = options.debounceDelay || 300;
            this.debounceTimer = null;
            this.abortController = null;
            this.cities = [];
            this.activeIndex = -1;
            this.currentRequestKey = null;
            this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
            this.onClearSelection = typeof options.onClear === 'function' ? options.onClear : null;

            this.handleInput = this.handleInput.bind(this);
            this.handleKeyDown = this.handleKeyDown.bind(this);
            this.handleDocumentClick = this.handleDocumentClick.bind(this);
        }

        init() {
            if (!this.input || !this.list || !this.statusElement) {
                return this;
            }

            this.input.setAttribute('aria-autocomplete', 'list');
            this.input.setAttribute('aria-controls', this.list.id);
            this.input.setAttribute('aria-haspopup', 'listbox');
            this.input.setAttribute('autocomplete', 'off');
            this.updateAriaExpanded(false);

            document.addEventListener('click', this.handleDocumentClick);
            this.input.addEventListener('input', this.handleInput);
            this.input.addEventListener('keydown', this.handleKeyDown);

            return this;
        }

        handleInput(event) {
            const value = event.target.value || '';
            const { term, uf } = this.parseQuery(value);

            this.notifySelectionCleared();

            if (term.length < this.minChars) {
                this.closeList();
                this.clearMessage();
                return;
            }

            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = setTimeout(() => {
                this.performSearch(term, uf);
            }, this.debounceDelay);
        }

        handleKeyDown(event) {
            if (!this.isListVisible()) {
                return;
            }

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.moveActive(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.moveActive(-1);
                    break;
                case 'Enter':
                    if (this.activeIndex >= 0) {
                        event.preventDefault();
                        this.selectCity(this.cities[this.activeIndex]);
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    this.closeList();
                    break;
                default:
                    break;
            }
        }

        handleDocumentClick(event) {
            if (!this.wrapper) {
                return;
            }

            if (!this.wrapper.contains(event.target)) {
                this.closeList();
            }
        }

        async performSearch(term, uf) {
            if (this.abortController && typeof this.abortController.abort === 'function') {
                this.abortController.abort();
            }

            this.abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const requestKey = `${term}|${uf || ''}|${Date.now()}`;
            this.currentRequestKey = requestKey;
            this.showLoading();

            try {
                const result = await this.fetchCities(term, uf, this.abortController ? this.abortController.signal : undefined);
                if (this.currentRequestKey !== requestKey) {
                    return;
                }

                const cities = Array.isArray(result) ? result : [];
                this.cities = cities;

                if (cities.length === 0) {
                    this.renderNoResults();
                    return;
                }

                this.renderList(cities);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                const message = error && error.message ? error.message : 'Não foi possível carregar as cidades.';
                this.showToast(message);
                this.showMessage('Não foi possível carregar as cidades. Tente novamente.');
            }
        }

        renderList(cities) {
            this.list.innerHTML = '';
            this.clearMessage();
            cities.forEach((city, index) => {
                const option = document.createElement('div');
                option.id = `${this.list.id}-option-${index}`;
                option.setAttribute('role', 'option');
                option.setAttribute('tabindex', '-1');
                option.className = 'px-3 py-2 cursor-pointer hover:bg-blue-100 focus:bg-blue-100';
                option.textContent = `${city.cNome} — ${city.cUF}`;
                option.dataset.index = String(index);

                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    this.selectCity(city);
                });

                option.addEventListener('mouseenter', () => {
                    this.setActive(index);
                });

                this.list.appendChild(option);
            });

            this.activeIndex = -1;
            this.openList();
        }

        renderNoResults() {
            this.closeList();
            this.showMessage('Nenhuma cidade encontrada.');
        }

        moveActive(delta) {
            if (this.cities.length === 0) {
                return;
            }

            let newIndex = this.activeIndex + delta;
            if (newIndex < 0) {
                newIndex = this.cities.length - 1;
            } else if (newIndex >= this.cities.length) {
                newIndex = 0;
            }

            this.setActive(newIndex);
        }

        setActive(index) {
            this.activeIndex = index;
            const options = this.list.querySelectorAll('[role="option"]');
            options.forEach((option, optionIndex) => {
                if (optionIndex === index) {
                    option.classList.add('bg-blue-100');
                    option.setAttribute('aria-selected', 'true');
                    option.scrollIntoView({ block: 'nearest' });
                    this.input.setAttribute('aria-activedescendant', option.id);
                } else {
                    option.classList.remove('bg-blue-100');
                    option.removeAttribute('aria-selected');
                }
            });
        }

        selectCity(city) {
            if (!city) {
                return;
            }

            this.input.value = city.cNome;
            if (this.ufInput) {
                this.ufInput.value = city.cUF;
            }

            if (typeof this.onSelect === 'function') {
                this.onSelect(city);
            }

            this.closeList();
        }

        notifySelectionCleared() {
            if (typeof this.onClearSelection === 'function') {
                this.onClearSelection();
            }
        }

        openList() {
            this.list.classList.remove('hidden');
            this.list.classList.add('block');
            this.updateAriaExpanded(true);
        }

        closeList() {
            this.list.classList.add('hidden');
            this.list.classList.remove('block');
            this.updateAriaExpanded(false);
            this.input.removeAttribute('aria-activedescendant');
        }

        isListVisible() {
            return !this.list.classList.contains('hidden');
        }

        showLoading() {
            this.list.innerHTML = '';
            this.openList();
            this.showMessage('Carregando cidades...');
        }

        showMessage(message) {
            this.statusElement.textContent = message;
            this.statusElement.classList.remove('hidden');
        }

        clearMessage() {
            this.statusElement.textContent = '';
            this.statusElement.classList.add('hidden');
        }

        updateAriaExpanded(expanded) {
            this.input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (this.wrapper) {
                this.wrapper.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        }

        parseQuery(value) {
            const trimmed = value.trim();
            if (trimmed === '') {
                return { term: '', uf: null };
            }

            const slashIndex = trimmed.lastIndexOf('/');
            if (slashIndex > 0) {
                const potentialUf = trimmed.slice(slashIndex + 1).trim();
                const potentialTerm = trimmed.slice(0, slashIndex).trim();
                if (/^[A-Za-z]{2}$/.test(potentialUf)) {
                    return { term: potentialTerm, uf: potentialUf.toUpperCase() };
                }
            }

            return { term: trimmed, uf: null };
        }

        async defaultFetch(term, uf, signal) {
            const params = new URLSearchParams({ termo: term });
            if (uf) {
                params.append('uf', uf);
            }

            const response = await fetch(`/api/cidades.php?${params.toString()}`, { signal });
            if (!response.ok) {
                let message = 'Erro ao consultar cidades na Omie.';
                try {
                    const data = await response.json();
                    if (data && data.message) {
                        message = data.message;
                    }
                } catch (err) {
                    message = 'Erro ao consultar cidades na Omie.';
                }
                throw new Error(message);
            }

            const payload = await response.json();
            if (payload && payload.success) {
                return payload.cidades || [];
            }

            throw new Error(payload && payload.message ? payload.message : 'Erro ao consultar cidades na Omie.');
        }

        showToast(message) {
            if (typeof document === 'undefined') {
                return;
            }

            const containerId = 'toast-container';
            let container = document.getElementById(containerId);
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                container.style.position = 'fixed';
                container.style.top = '1rem';
                container.style.right = '1rem';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.textContent = message;
            toast.style.backgroundColor = '#dc2626';
            toast.style.color = '#fff';
            toast.style.padding = '0.75rem 1rem';
            toast.style.marginBottom = '0.5rem';
            toast.style.borderRadius = '0.375rem';
            toast.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
            toast.style.fontSize = '0.875rem';

            container.appendChild(toast);
            setTimeout(() => {
                if (toast.parentNode === container) {
                    container.removeChild(toast);
                }
            }, 4000);
        }
    }

    CityAutocomplete.DEFAULTS = {
        minChars: 3,
        debounceDelay: 300,
    };

    CityAutocomplete.init = function (config) {
        const instance = new CityAutocomplete(config);
        instance.init();
        return instance;
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { CityAutocomplete };
    } else {
        global.CityAutocomplete = CityAutocomplete;
    }
})(typeof window !== 'undefined' ? window : globalThis);

