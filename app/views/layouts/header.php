<?php
// /app/views/layouts/header.php (VERSÃO FINAL COM ACESSO DE VENDEDOR CORRIGIDO)

global $pdo;

// Inclui os models necessários
require_once __DIR__ . '/../../models/Configuracao.php';
require_once __DIR__ . '/../../models/Prospeccao.php';
require_once __DIR__ . '/../../models/Notificacao.php'; // Adicionado para acesso ao model

$configModel = new Configuracao($pdo);
$prospeccaoModel = new Prospeccao($pdo);
$notificacaoModel = new Notificacao($pdo);

$theme_color = $configModel->get('theme_color');
$system_logo = $configModel->get('system_logo');
$bodyClass = isset($bodyClass) ? trim($bodyClass) : 'bg-slate-100 text-slate-800';

// --- LÓGICA DE PERMISSÕES ---
$user_perfil = $_SESSION['user_perfil'] ?? 'guest';
$crm_access = in_array($user_perfil, ['admin', 'gerencia', 'supervisor', 'vendedor', 'sdr']);
$finance_access = in_array($user_perfil, ['admin', 'gerencia', 'financeiro']);
$admin_access = in_array($user_perfil, ['admin', 'gerencia', 'supervisor']);
$is_vendedor = ($user_perfil === 'vendedor');
$is_sdr = ($user_perfil === 'sdr');

// --- LÓGICA PARA MENU ATIVO ---
$currentPage = basename($_SERVER['PHP_SELF']);
$isCrmPage = (strpos($_SERVER['PHP_SELF'], '/crm/') !== false);
$isCrmClientesPage = $isCrmPage && strpos($_SERVER['PHP_SELF'], '/crm/clientes/') !== false;
$isProspeccoesSection = $isCrmPage && strpos($_SERVER['PHP_SELF'], '/crm/prospeccoes/') !== false;
$isProspeccoesList = $isProspeccoesSection && strpos($_SERVER['PHP_SELF'], 'lista.php') !== false;
$isProspeccoesKanban = $isProspeccoesSection && strpos($_SERVER['PHP_SELF'], 'kanban.php') !== false;
$isProspeccoesOther = $isProspeccoesSection && !$isProspeccoesList && !$isProspeccoesKanban;
$financePages = ['financeiro.php', 'fluxo_caixa.php', 'vendas.php'];
$isFinancePage = in_array($currentPage, $financePages);
$homeUrl = APP_URL . '/dashboard.php';
if ($is_vendedor) {
    $homeUrl = APP_URL . '/dashboard_vendedor.php';
} elseif ($is_sdr) {
    $homeUrl = APP_URL . '/sdr_dashboard.php';
}

// --- LÓGICA DE NOTIFICAÇÕES UNIFICADAS ---
$count_notificacoes = 0;
$count_retornos = 0;
$lista_retornos_dropdown = [];
$notificationGroup = Notificacao::resolveGroupForProfile($user_perfil);

if (isset($_SESSION['user_id'])) {
    // 1. Conta e busca notificações padrão
    $notificationFeed = $notificacaoModel->getAlertFeed((int)$_SESSION['user_id'], $notificationGroup, 15, true, 'UTC');
    $count_notificacoes = $notificationFeed['total'] ?? 0;

    // 2. Conta e busca retornos de prospecção (LÓGICA AJUSTADA)
    // Apenas para perfis de gestão e vendedores
    if (in_array($user_perfil, ['gerencia', 'supervisor', 'vendedor', 'sdr'])) {
        $dias_para_alerta_menu = (int)$configModel->get('dias_alerta_retorno', 30);
        $status_excluidos = ['Convertido', 'Fechamento', 'Pausar', 'Descartado'];

        // Força a filtragem por usuário para que os alertas apareçam apenas para o criador,
        // passando 'vendedor' como perfil para a função do model.
        $force_user_filter_profile = $user_perfil === 'sdr' ? 'sdr' : 'vendedor';

        $count_retornos = $prospeccaoModel->countProspectsForReturn($dias_para_alerta_menu, $status_excluidos, $_SESSION['user_id'], $force_user_filter_profile);
        if ($count_retornos > 0) {
            $lista_retornos_dropdown = $prospeccaoModel->getUrgentProspectsForReturn(10, $dias_para_alerta_menu, $status_excluidos, $_SESSION['user_id'], $force_user_filter_profile);
        }
    }
}

// Redirecionamento do dashboard para vendedores
if ($is_vendedor && $currentPage === 'dashboard.php') {
    header("Location: " . APP_URL . "/dashboard_vendedor.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/style.css">
    
    <style>
        :root {
            --theme-color: <?php echo htmlspecialchars($theme_color ?? '#0A2540'); ?>;
        }
        .border-theme-color { border-color: var(--theme-color); }
        .bg-theme-color { background-color: var(--theme-color); }
        .text-theme-color { color: var(--theme-color); }
    </style>

</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
    <nav class="bg-white shadow-md border-b-4 border-theme-color">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="<?php echo $homeUrl; ?>" class="flex-shrink-0">
                        <?php if (!empty($system_logo) && file_exists(__DIR__ . '/../../../' . $system_logo)): ?>
                            <img src="<?php echo htmlspecialchars(APP_URL . '/' . $system_logo); ?>" alt="Logo" class="h-12 w-auto">
                        <?php else: ?>
                            <span class="text-xl font-bold text-theme-color"><?php echo APP_NAME; ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        
                        <?php if ($is_vendedor): ?>
                            <a href="<?php echo APP_URL; ?>/dashboard_vendedor.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'dashboard_vendedor.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Meu Dashboard</a>
                            <a href="<?php echo APP_URL; ?>/relatorio_vendedor.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'relatorio_vendedor.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Meus Relatórios</a>
                        <?php elseif ($is_sdr): ?>
                            <a href="<?php echo APP_URL; ?>/sdr_dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'sdr_dashboard.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Painel SDR</a>
                            <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo $isCrmClientesPage ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Leads</a>
                            <a href="<?php echo APP_URL; ?>/crm/prospeccoes/kanban.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo $isProspeccoesKanban ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Kanban Geral</a>
                            <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo $isProspeccoesList || $isProspeccoesOther ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Prospeções</a>
                        <?php else: ?>
                            <a href="<?php echo APP_URL; ?>/dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'dashboard.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Home</a>
                            <a href="<?php echo APP_URL; ?>/clientes.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'clientes.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Clientes</a>
                        <?php endif; ?>

                        <?php if ($crm_access && !$is_sdr): ?>
                        <div class="relative">
                            <button id="crm-menu-button" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($isCrmPage) ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">
                                <span>CRM</span>
                                <i class="fas fa-chevron-down text-xs ml-1"></i>
                            </button>
                            <div id="crm-menu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 hidden">
                                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/kanban.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Kanban</a>
                                <a href="<?php echo APP_URL; ?>/crm/agendamentos/calendario.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Agendamentos</a>
                                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Leads</a>
                                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Prospecções</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($finance_access): ?>
                        <div class="relative">
                            <button id="finance-menu-button" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($isFinancePage) ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">
                                <span>Financeiro</span>
                                <i class="fas fa-chevron-down text-xs ml-1"></i>
                            </button>
                            <div id="finance-menu" class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-20 hidden">
                                <a href="<?php echo APP_URL; ?>/fluxo_caixa.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Controle Financeiro</a>
                                <a href="<?php echo APP_URL; ?>/financeiro.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Relatório de Serviços</a>
                                <a href="<?php echo APP_URL; ?>/vendas.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Relatório de Vendas</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($admin_access): ?>
                            <a href="<?php echo APP_URL; ?>/gerente_dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo ($currentPage == 'gerente_dashboard.php') ? 'bg-theme-color text-white font-bold' : 'text-gray-600 hover:bg-gray-200'; ?>">Painel Gestão</a>

                            <a href="<?php echo APP_URL; ?>/admin.php" class="px-3 py-2 rounded-md text-sm font-bold <?php echo ($currentPage == 'admin.php') ? 'bg-theme-color text-white' : 'text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800'; ?>">Administração</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (in_array($user_perfil, ['admin', 'gerencia', 'supervisor', 'vendedor', 'sdr'])): ?>

                <div class="hidden md:flex items-center space-x-4">
                    <?php $markAllDisabled = ($count_notificacoes ?? 0) === 0; ?>
                    <div
                        class="notification-bell"
                        data-component="notification-dropdown"
                        data-api-url="/api/notifications"
                        data-mark-read-url="/api/notifications/mark-read"
                        data-per-page="10"
                        data-polling-interval="30000"
                        data-unread-only="1"
                    >
                        <button
                            type="button"
                            class="notification-trigger"
                            data-role="trigger"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="Notificações"
                        >
                            <i class="fas fa-bell fa-lg"></i>
                            <span
                                class="badge"
                                id="notif-count"
                                data-role="badge"
                                <?php echo ($count_notificacoes ?? 0) > 0 ? '' : 'hidden'; ?>
                            >
                                <?php echo (int) ($count_notificacoes ?? 0); ?>
                            </span>
                        </button>
                        <div
                            class="dropdown"
                            id="notif-dropdown"
                            data-role="dropdown"
                            style="display:none"
                            aria-hidden="true"
                        >
                            <div class="notification-dropdown__header">
                                <span>Notificações</span>
                                <button
                                    type="button"
                                    data-action="mark-all"
                                    class="<?php echo $markAllDisabled ? 'is-disabled' : ''; ?>"
                                    <?php echo $markAllDisabled ? 'disabled' : ''; ?>
                                >
                                    Marcar todas como lidas
                                </button>
                            </div>
                            <div class="notification-dropdown__list" data-role="notification-list">
                                <div class="notification-dropdown__status">Carregando notificações...</div>
                            </div>
                            <div class="notification-dropdown__footer">
                                <a href="<?php echo APP_URL; ?>/notificacoes.php">Ver todas as notificações</a>
                            </div>
                            <?php if (in_array($user_perfil, ['gerencia', 'supervisor', 'vendedor'])): ?>
                                <div class="notification-dropdown__section-divider"></div>
                                <div class="notification-dropdown__section-header">
                                    <span>Prospecções para Retorno</span>
                                    <?php if (($count_retornos ?? 0) > 0): ?>
                                        <span class="text-xs text-gray-500"><?php echo (int) $count_retornos; ?> pendentes</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($lista_retornos_dropdown)): ?>
                                    <div class="notification-dropdown__section-empty">Nenhuma prospecção para retorno.</div>
                                <?php else: ?>
                                    <div class="notification-dropdown__section-list">
                                        <?php foreach ($lista_retornos_dropdown as $retorno): ?>
                                            <div class="notification-dropdown__section-item">
                                                <a
                                                    href="<?php echo APP_URL; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) $retorno['id']; ?>"
                                                    class="section-item__link"
                                                >
                                                    <p class="section-item__name"><?php echo htmlspecialchars($retorno['nome_prospecto']); ?></p>
                                                    <p class="section-item__meta"><?php echo (int) $retorno['dias_sem_contato']; ?> dias sem contato</p>
                                                </a>
                                                <button
                                                    type="button"
                                                    onclick="dismissProspectAlert(this, <?php echo (int) $retorno['id']; ?>)"
                                                    title="Pausar alerta de prospecção"
                                                >
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="notification-dropdown__footer">
                                    <a href="<?php echo APP_URL; ?>/crm/prospeccoes/retornos.php">Ver todos os retornos</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>


                    <span class="text-gray-300">|</span>
                    <?php endif; ?>

                    <a href="<?php echo APP_URL; ?>/login.php?action=logout" class="bg-red-500 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">Sair</a>
                </div>


                <div class="-mr-2 flex md:hidden">
                    <button id="mobile-menu-button" type="button" class="bg-gray-200 inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-white hover:bg-theme-color focus:outline-none" aria-controls="mobile-menu" aria-expanded="false">
                        <span class="sr-only">Abrir menu principal</span>
                        <i id="hamburger-icon" class="fas fa-bars h-6 w-6 block"></i>
                        <i id="close-icon" class="fas fa-times h-6 w-6 hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="md:hidden hidden" id="mobile-menu"> 
             </div>
    </nav>

    <main class="max-w-[95%] mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p><?php echo $_SESSION['success_message']; ?></p>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo $_SESSION['error_message']; ?></p>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
  <?php if (isset($_SESSION['warning_message'])): ?>
      <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
          <p><?php echo htmlspecialchars($_SESSION['warning_message']); ?></p>
      </div>
      <?php unset($_SESSION['warning_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['info_message'])): ?>
      <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
          <p><?php echo htmlspecialchars($_SESSION['info_message']); ?></p>
      </div>
      <?php unset($_SESSION['info_message']); ?>
  <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    mobileMenuButton.addEventListener('click', function () {
        mobileMenu.classList.toggle('hidden');
        hamburgerIcon.classList.toggle('hidden');
        closeIcon.classList.toggle('hidden');
    });

    const crmMenuButton = document.getElementById('crm-menu-button');
    const crmMenu = document.getElementById('crm-menu');
    const financeMenuButton = document.getElementById('finance-menu-button');
    const financeMenu = document.getElementById('finance-menu');

    const dropdowns = [
        { button: crmMenuButton, menu: crmMenu },
        { button: financeMenuButton, menu: financeMenu },
    ];

    dropdowns.forEach(({ button, menu }) => {
        if (!button || !menu) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            dropdowns.forEach(({ menu: otherMenu }) => {
                if (otherMenu && otherMenu !== menu) {
                    otherMenu.classList.add('hidden');
                }
            });
            menu.classList.toggle('hidden');
        });
    });

    window.addEventListener('click', function (event) {
        dropdowns.forEach(({ button, menu }) => {
            if (menu && !menu.classList.contains('hidden')) {
                if (!menu.contains(event.target) && (button && !button.contains(event.target))) {
                    menu.classList.add('hidden');
                }
            }
        });
    });
});

function dismissProspectAlert(element, prospectId) {
    if (!confirm("Deseja realmente pausar os alertas para esta prospecção?")) {
        return;
    }

    const url = '<?php echo APP_URL; ?>/crm/prospeccoes/atualizar_status_kanban.php';
    const data = {
        prospeccao_id: prospectId,
        novo_status: 'Pausar'
    };

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Recarrega a página para atualizar a lista de alertas
            location.reload();
        } else {
            alert("Falha ao pausar o alerta: " + (result.message || 'Erro desconhecido.'));
        }
    })
    .catch(error => {
        console.error('Erro ao dispensar alerta:', error);
        alert('Ocorreu um erro de comunicação ao tentar pausar o alerta.');
    });
}
</script>
</body>
</html>