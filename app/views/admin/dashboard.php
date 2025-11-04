<?php
// Inclui o cabeçalho do layout
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Painel de Administração</h1>
    <p class="text-gray-600">Bem-vindo à área de gerenciamento do sistema.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

    <a href="admin.php?action=processos" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-cyan-600">Gerenciar Processos</h3>
            <p class="text-gray-600 mt-2">Visualizar, criar e editar todos os processos e orçamentos do sistema.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-folder-open text-cyan-200 text-5xl"></i>
        </div>
    </a>
    
    <a href="admin.php?action=users" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-blue-600">Gerenciar Usuários</h3>
            <p class="text-gray-600 mt-2">Adicionar, editar e remover as contas de acesso dos colaboradores.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-users-cog text-blue-200 text-5xl"></i>
        </div>
    </a>

    
    <a href="admin.php?action=vendedores" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-purple-600">Gerenciar Vendedores</h3>
            <p class="text-gray-600 mt-2">Administrar a lista de vendedores e suas comissões.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-user-tie text-purple-200 text-5xl"></i>
        </div>
    </a>
    
    <a href="admin.php?action=tradutores" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-green-600">Gerenciar Tradutores</h3>
            <p class="text-gray-600 mt-2">Administrar a lista de tradutores e suas informações de contato.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-language text-green-200 text-5xl"></i>
        </div>
    </a>

    <a href="admin.php?action=omie_settings" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-indigo-600">Integração Omie</h3>
            <p class="text-gray-600 mt-2">Gerencie as credenciais e parâmetros da integração com a Omie.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-cogs text-indigo-200 text-5xl"></i>
        </div>
    </a>
    <a href="admin.php?action=settings" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-amber-600">Configurações Gerais</h3>
            <p class="text-gray-600 mt-2">Defina percentuais e ajustes administrativos do CRM.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-sliders-h text-amber-200 text-5xl"></i>
        </div>
    </a>
    <a href="admin.php?action=omie_support&amp;type=produtos" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-sky-600">Produtos Omie</h3>
            <p class="text-gray-600 mt-2">Sincronize e edite apenas os produtos e serviços integrados à Omie.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-boxes text-sky-200 text-5xl"></i>
        </div>
    </a>
    <a href="admin.php?action=tv_panel" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-rose-600">Painel de TV</h3>
            <p class="text-gray-600 mt-2">Visualize todos os processos em tela cheia para monitores corporativos.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-tv text-rose-200 text-5xl"></i>
        </div>
    </a>
    <a href="admin.php?action=smtp_settings" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-red-500">E-mails de Notificação</h3>
            <p class="text-gray-600 mt-2">Gerenciar para quem os alertas automáticos do sistema são enviados.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-envelope-open-text text-red-200 text-5xl"></i>
        </div>
    </a>

    <a href="admin.php?action=config" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-orange-500">Aparência do Sistema</h3>
            <p class="text-gray-600 mt-2">Personalizar as cores e a logo do sistema para se adequar à sua marca.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-palette text-orange-200 text-5xl"></i>
        </div>
    </a>

    <a href="admin.php?action=automacao_campanhas" class="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col justify-between">
        <div>
            <h3 class="text-xl font-bold text-teal-600">Campanhas de Automação</h3>
            <p class="text-gray-600 mt-2">Criar e editar as campanhas e templates de mensagens automáticas para o CRM.</p>
        </div>
        <div class="text-right mt-4">
            <i class="fas fa-robot text-teal-200 text-5xl"></i>
        </div>
    </a>
</div>