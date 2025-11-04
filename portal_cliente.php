<?php
// /portal_cliente.php

session_start();

// 1. Usa o seu sistema de autenticação já existente
require_once 'app/core/auth_check.php';

// 2. Adiciona uma verificação extra para garantir que só clientes acessem
if ($_SESSION['user_perfil'] !== 'cliente') {
    // Se não for cliente, redireciona para o dashboard principal
    header('Location: dashboard.php');
    exit();
}

// 3. Carrega os arquivos necessários
require_once 'config.php';
require_once 'app/controllers/ClienteDashboardController.php';

// 4. Executa o controller
$controller = new ClienteDashboardController($pdo);
$controller->index();