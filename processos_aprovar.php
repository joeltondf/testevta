<?php
// /processos_aprovar.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia', 'supervisor']); // Admin, gerência ou supervisão podem aprovar

require_once __DIR__ . '/app/views/layouts/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-16">
    <div class="bg-white shadow-md rounded-lg p-8 text-center border border-dashed border-blue-300">
        <h1 class="text-2xl font-bold text-gray-800 mb-3">Fluxo de Aprovação Desativado</h1>
        <p class="text-gray-600 leading-relaxed">
            Os orçamentos não aguardam mais aprovação da gerência. A partir de agora, sempre que um vendedor criar ou atualizar um orçamento,
            ele será enviado automaticamente ao cliente correspondente.
        </p>
        <p class="text-gray-600 leading-relaxed mt-4">
            Caso precise revisar um orçamento específico, acesse a página do processo correspondente e utilize as ações disponíveis por lá.
        </p>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="inline-flex items-center mt-8 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-md transition-colors">
            <i class="fas fa-tachometer-alt mr-2"></i>Voltar ao dashboard principal
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/app/views/layouts/footer.php';
?>
