(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll('[data-tv-panel]');
        if (!containers.length) {
            return;
        }

        const formatDateTime = (date) => {
            return new Intl.DateTimeFormat('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(date);
        };

        const initializePanel = (container) => {
            const endpoint = container.dataset.endpoint;
            const refreshInterval = parseInt(container.dataset.refreshInterval, 10) || 60;
            const tableBody = container.querySelector('[data-tv-table-body]');
            const clockDisplay = container.querySelector('[data-tv-clock]');
            const lastUpdateDisplay = container.querySelector('[data-tv-last-update]');
            const totalDisplay = container.querySelector('[data-tv-total]');
            const intervalDisplay = container.querySelector('[data-tv-interval]');

            if (intervalDisplay) {
                intervalDisplay.textContent = Math.max(1, Math.round(refreshInterval / 60));
            }

            const updateClock = () => {
                if (clockDisplay) {
                    clockDisplay.textContent = formatDateTime(new Date());
                }
            };

            updateClock();
            setInterval(updateClock, 1000);

            const updateLastUpdate = (timestamp) => {
                if (!lastUpdateDisplay) {
                    return;
                }

                const parsedDate = timestamp ? new Date(timestamp) : new Date();
                if (Number.isNaN(parsedDate.getTime())) {
                    lastUpdateDisplay.textContent = '—';
                    return;
                }

                lastUpdateDisplay.textContent = formatDateTime(parsedDate);
            };

            const refreshTable = () => {
                if (!endpoint || !tableBody) {
                    return;
                }

                fetch(endpoint, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || data.success !== true) {
                            return;
                        }

                        if (typeof data.html === 'string') {
                            tableBody.innerHTML = data.html;
                        }

                        if (totalDisplay && typeof data.total === 'number') {
                            totalDisplay.textContent = data.total;
                        }

                        updateLastUpdate(data.generated_at);
                    })
                    .catch(() => {
                        updateLastUpdate(null);
                    });
            };

            refreshTable();
            setInterval(refreshTable, refreshInterval * 1000);
        };

        containers.forEach((container) => {
            initializePanel(container);
        });
    });
})();
