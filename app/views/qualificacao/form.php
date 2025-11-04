<?php
$baseAppUrl = rtrim(APP_URL, '/');
$actionUrl = $baseAppUrl . '/qualificacao.php?action=store&id=' . (int) ($lead['id'] ?? 0);
$backUrl = $baseAppUrl . '/sdr_dashboard.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Qualificação de Lead</h1>
        <p class="text-sm text-gray-500 mt-1">
            Analise os critérios de fit e encaminhe o lead qualificado para o vendedor responsável.
        </p>
    </div>
    <a href="<?php echo $backUrl; ?>" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <form action="<?php echo $actionUrl; ?>" method="POST" id="qualification-form" class="space-y-6">
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Critérios de qualificação</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="fit_icp" class="block text-sm font-medium text-gray-700 mb-1">Fit com ICP</label>
                        <select name="fit_icp" id="fit_icp" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Selecione</option>
                            <option value="Alto">Alto</option>
                            <option value="Médio">Médio</option>
                            <option value="Baixo">Baixo</option>
                        </select>
                    </div>
                    <div>
                        <label for="budget" class="block text-sm font-medium text-gray-700 mb-1">Orçamento</label>
                        <select name="budget" id="budget" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Selecione</option>
                            <option value="Disponível">Disponível</option>
                            <option value="Parcial">Parcial</option>
                            <option value="Indefinido">Indefinido</option>
                        </select>
                    </div>
                    <div>
                        <label for="authority" class="block text-sm font-medium text-gray-700 mb-1">Autoridade</label>
                        <select name="authority" id="authority" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Selecione</option>
                            <option value="Decisor">Decisor</option>
                            <option value="Influenciador">Influenciador</option>
                            <option value="Pesquisador">Pesquisador</option>
                        </select>
                    </div>
                    <div>
                        <label for="timing" class="block text-sm font-medium text-gray-700 mb-1">Tempo</label>
                        <select name="timing" id="timing" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Selecione</option>
                            <option value="Imediato">Imediato</option>
                            <option value="Curto prazo">Curto prazo</option>
                            <option value="Médio prazo">Médio prazo</option>
                            <option value="Longo prazo">Longo prazo</option>
                        </select>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Observações</h2>
                <textarea name="notes" id="notes" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Resuma os principais pontos da conversa."></textarea>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Resultado da análise</h2>
                <div class="flex flex-col sm:flex-row gap-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="radio" name="decision" value="qualificado" class="text-blue-600 focus:ring-blue-500" required>
                        Lead qualificado
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="radio" name="decision" value="descartado" class="text-blue-600 focus:ring-blue-500" required>
                        Lead descartado
                    </label>
                </div>
            </section>

            <section id="handoff-section" class="hidden">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Handoff para vendas</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2 space-y-3">
                        <p class="text-sm font-medium text-gray-700">Próximo vendedor na fila</p>
                        <?php if (!empty($nextVendor)): ?>
                            <div class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-4 py-3" data-vendor-card data-vendor-id="<?php echo (int) $nextVendor['vendorId']; ?>">
                                <div>
                                    <p class="text-sm text-blue-700">Vendedor</p>
                                    <p class="text-lg font-semibold text-blue-900"><?php echo htmlspecialchars($nextVendor['vendorName']); ?></p>
                                    <?php if (!empty($nextVendor['lastAssignedAt'])): ?>
                                        <p class="text-xs text-blue-600 mt-1">Último lead recebido em <?php echo date('d/m/Y H:i', strtotime($nextVendor['lastAssignedAt'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-sync-alt text-blue-500 text-xl"></i>
                            </div>
                        <?php else: ?>
                            <div id="vendor-warning" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                Nenhum vendedor ativo está disponível na fila. Entre em contato com a coordenação antes de finalizar a qualificação.
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="preview_vendor_id" value="<?php echo isset($nextVendor['vendorId']) ? (int) $nextVendor['vendorId'] : ''; ?>">
                    </div>
                    <div>
                        <label for="meeting_title" class="block text-sm font-medium text-gray-700 mb-1">Título da reunião</label>
                        <input type="text" name="meeting_title" id="meeting_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" value="Reunião de Qualificação">
                    </div>
                    <div>
                        <label for="meeting_date" class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input type="date" name="meeting_date" id="meeting_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="meeting_time" class="block text-sm font-medium text-gray-700 mb-1">Hora</label>
                        <input type="time" name="meeting_time" id="meeting_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="meeting_link" class="block text-sm font-medium text-gray-700 mb-1">Link / Local</label>
                        <input type="text" name="meeting_link" id="meeting_link" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="URL ou endereço da reunião">
                    </div>
                    <div>
                        <label for="meeting_notes" class="block text-sm font-medium text-gray-700 mb-1">Notas internas</label>
                        <textarea name="meeting_notes" id="meeting_notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Compartilhe detalhes relevantes para o vendedor."></textarea>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Informe data e hora para gerar o agendamento automaticamente.</p>
            </section>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="<?php echo $backUrl; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Cancelar</a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors" data-submit-button>Salvar qualificação</button>
            </div>
        </form>
    </div>

    <aside class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informações do lead</h2>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="font-medium text-gray-600">Nome do lead</dt>
                <dd class="text-gray-800"><?php echo htmlspecialchars($lead['nome_prospecto'] ?? 'Lead'); ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">Cliente associado</dt>
                <dd class="text-gray-800"><?php echo htmlspecialchars($lead['cliente_nome'] ?? ($lead['nome_cliente'] ?? 'Não informado')); ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">Status atual</dt>
                <dd class="text-gray-800"><?php echo htmlspecialchars($lead['status'] ?? 'Novo'); ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600">Última atualização</dt>
                <dd class="text-gray-800"><?php echo isset($lead['data_ultima_atualizacao']) ? date('d/m/Y H:i', strtotime($lead['data_ultima_atualizacao'])) : 'Não informado'; ?></dd>
            </div>
        </dl>
        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) ($lead['id'] ?? 0); ?>" class="inline-flex items-center mt-5 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm">
            <i class="fas fa-search mr-2"></i> Ver detalhes completos
        </a>
    </aside>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const decisionInputs = document.querySelectorAll('input[name="decision"]');
        const handoffSection = document.getElementById('handoff-section');
        const vendorCard = document.querySelector('[data-vendor-card]');
        const vendorWarning = document.getElementById('vendor-warning');
        const submitButton = document.querySelector('[data-submit-button]');

        const toggleHandoffSection = () => {
            const selectedDecision = document.querySelector('input[name="decision"]:checked');
            if (selectedDecision && selectedDecision.value === 'qualificado') {
                handoffSection.classList.remove('hidden');
                const hasVendor = Boolean(vendorCard && vendorCard.dataset.vendorId !== '');
                if (!hasVendor) {
                    submitButton?.setAttribute('disabled', 'disabled');
                    vendorWarning?.classList.remove('hidden');
                } else {
                    submitButton?.removeAttribute('disabled');
                }
            } else {
                handoffSection.classList.add('hidden');
                submitButton?.removeAttribute('disabled');
            }
        };

        decisionInputs.forEach(input => {
            input.addEventListener('change', toggleHandoffSection);
        });

        toggleHandoffSection();
    });
</script>
