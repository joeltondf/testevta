function digisacApiUrl(action) {
    const base = '/app/api/admin/digisac.php';
    if (!action) {
        return base;
    }
    const separator = base.includes('?') ? '&' : '?';
    return `${base}${separator}action=${encodeURIComponent(action)}`;
}

function toggleTokenVisibility() {
    const input = document.getElementById('api_token');
    if (!input) {
        return;
    }

    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
}

function copyWebhookUrl() {
    const field = document.querySelector('input[readonly][value*="webhooks/digisac.php"]');
    if (!field) {
        return;
    }

    field.select();
    document.execCommand('copy');
}

function testConnection(event) {
    const button = event ? event.currentTarget : null;
    const tokenInput = document.getElementById('api_token');
    if (!tokenInput || tokenInput.value.trim() === '') {
        alert('Informe o API Token primeiro');
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    }

    fetch(digisacApiUrl('test-connection'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({api_token: tokenInput.value.trim()}),
    })
        .then((response) => response.json())
        .then((data) => {
            const resultDiv = document.getElementById('connectionTest');
            if (!resultDiv) {
                return;
            }
            resultDiv.style.display = 'block';
            if (data.success) {
                resultDiv.className = 'alert alert-success';
                resultDiv.innerHTML = '✅ Conexão OK! API Digisac respondeu corretamente.';
            } else {
                resultDiv.className = 'alert alert-error';
                resultDiv.innerHTML = `❌ Erro: ${data.error || 'Falha na comunicação com a API'}`;
            }
        })
        .catch((error) => {
            console.error('Erro ao testar Digisac:', error);
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-vial"></i> Testar Conexão';
            }
        });
}

const digisacForm = document.getElementById('digisacConfigForm');
if (digisacForm) {
    digisacForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(digisacForm);
        const payload = Object.fromEntries(formData.entries());

        fetch(digisacApiUrl('save-config'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Configurações salvas com sucesso!', 'success');
                    } else {
                        alert('Configurações salvas com sucesso!');
                    }
                } else {
                    const errorMessage = data.error || 'Erro desconhecido ao salvar configurações.';
                    if (typeof showToast === 'function') {
                        showToast('Erro ao salvar: ' + errorMessage, 'error');
                    } else {
                        alert('Erro ao salvar: ' + errorMessage);
                    }
                }
            })
            .catch((error) => {
                console.error('Erro ao salvar configuração Digisac:', error);
                if (typeof showToast === 'function') {
                    showToast('Erro inesperado ao salvar configurações.', 'error');
                } else {
                    alert('Erro inesperado ao salvar configurações.');
                }
            });
    });
}
