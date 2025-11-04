<?php
session_start();

// O config.php deve vir primeiro para estabelecer a conexão PDO
require_once 'config.php'; 

// Agora incluímos os arquivos de controle de acesso
require_once 'app/core/auth_check.php';
require_once 'app/core/access_control.php'; // Esta linha agora encontrará o arquivo

// Garante que APENAS vendedores acessem esta página
require_permission(['vendedor']);

// Inclui o controller específico do dashboard do vendedor
require_once 'app/controllers/VendedorDashboardController.php';

// Usa a variável $pdo que foi criada no config.php
$controller = new VendedorDashboardController($pdo);
$controller->index();