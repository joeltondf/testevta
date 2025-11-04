<?php
/**
 * @file /app/core/auth_check.php
 * @description Verifica se o usuário está logado e se tem permissão para acessar a página atual.
 * Este script é incluído no início de todas as páginas protegidas.
 */

// Inicia a sessão se ainda não foi iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================================================================
// 1. VERIFICAÇÃO DE LOGIN
// =======================================================================

// Pega o nome do arquivo da página atual (ex: 'login.php')
$currentPage = basename($_SERVER['PHP_SELF']);

// Lista de páginas que são públicas (NÃO precisam de login).
$publicPages = ['login.php'];

// Se a página atual NÃO ESTÁ na lista de páginas públicas E o usuário NÃO está logado...
if (!in_array($currentPage, $publicPages) && !isset($_SESSION['user_id'])) {
    // ...redireciona para a página de login e encerra o script.
    header('Location: ' . APP_URL . '/login.php');
    exit();
}


// =======================================================================
// 2. CONTROLE DE ACESSO POR PERFIL (LÓGICA FINAL)
// =======================================================================

// Se o usuário ESTÁ logado, verificamos suas permissões.
if (isset($_SESSION['user_id'])) {
    
    $userProfile = $_SESSION['user_perfil'];

    // --- REGRA 1: ACESSO ESTRITO DO VENDEDOR ---
    if ($userProfile === 'vendedor') {

        // Lista de páginas na raiz do site que um vendedor PODE acessar.
        $allowedRootPages = [
        'dashboard.php', // << ADICIONADO: Permite processar ações como a de excluir notificação.
        'dashboard_vendedor.php',
        'relatorio_vendedor.php',
        'login.php', // Para a ação de logout
        'clientes.php', // << ADICIONADO: Permite o acesso à edição de cliente
        'processos.php' // << ADICIONADO: Permite o acesso à criação de orçamento
        ];

        // Verificação 1: A página atual está na lista de permissões da raiz?
        $isAllowedRootPage = in_array($currentPage, $allowedRootPages);

        // Verificação 2: A página atual está dentro do diretório do CRM?
        // Usamos strpos no caminho completo para evitar conflitos de nomes de arquivos.
        $isInCrmFolder = (strpos($_SERVER['PHP_SELF'], '/crm/') !== false);

        // Se o vendedor tentar acessar uma página que NÃO é permitida na raiz E NÃO está na pasta do CRM...
        if (!$isAllowedRootPage && !$isInCrmFolder) {
            // ...ele é imediatamente redirecionado para o seu dashboard seguro.
            header('Location: ' . APP_URL . '/dashboard_vendedor.php');
            exit();
        }
    }

    if ($userProfile === 'sdr') {

        $allowedRootPages = [
            'sdr_dashboard.php',
            'qualificacao.php',
            'login.php'
        ];

        $isAllowedRootPage = in_array($currentPage, $allowedRootPages, true);
        $isInCrmFolder = (strpos($_SERVER['PHP_SELF'], '/crm/') !== false);

        if (!$isAllowedRootPage && !$isInCrmFolder) {
            header('Location: ' . APP_URL . '/sdr_dashboard.php');
            exit();
        }
    }
    
    // --- REGRA 2: PROTEÇÃO DAS PÁGINAS DO VENDEDOR ---
    // Se um usuário que NÃO É vendedor tentar acessar as páginas exclusivas do vendedor...
    $vendorOnlyPages = ['dashboard_vendedor.php', 'relatorio_vendedor.php'];
    if ($userProfile !== 'vendedor' && in_array($currentPage, $vendorOnlyPages)) {
        // ...ele é redirecionado para o seu próprio dashboard principal.
        header('Location: ' . APP_URL . '/dashboard.php');
        exit();
    }

    $sdrOnlyPages = ['sdr_dashboard.php', 'qualificacao.php'];
    if ($userProfile !== 'sdr' && in_array($currentPage, $sdrOnlyPages, true)) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit();
    }
}
