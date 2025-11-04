<?php
// /config.php (Versão Corrigida e Melhorada)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

// 1. Configurações de Conexão com a Base de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u371107598_dadosnovalis');
define('DB_USER', 'u371107598_usernovalis');
define('DB_PASS', '@Amora051307');
// ADICIONAMOS A CONSTANTE QUE FALTAVA
define('DB_CHARSET', 'utf8mb4'); 


// 2. Configurações Gerais da Aplicação
define('APP_URL', 'https://app.vettadocumental.com');
define('APP_NAME', 'Vetta Documental');

// 3. Iniciar a Sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 4. Conexão PDO com a Base de Dados
try {
    // AGORA USAMOS A CONSTANTE DB_CHARSET NA CONEXÃO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Em caso de erro, encerra a execução com uma mensagem clara.
    die("Erro na conexão com a base de dados: " . $e->getMessage());
}

// Configurações de E-mail
define('EMAIL_HOST', 'smtp.hostinger.com'); // Ex: smtp.example.com
define('EMAIL_USERNAME', 'app@cliente.pro');
define('EMAIL_PASSWORD', '@Amora051307');
define('EMAIL_PORT', 465); // Ou a porta que seu servidor usa (ex: 465)
define('EMAIL_FROM_ADDRESS', 'app@cliente.pro');
define('EMAIL_FROM_NAME', 'Vetta Documental');
?>