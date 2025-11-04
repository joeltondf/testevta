<?php
// /app/views/auth/login_form.php

require_once __DIR__ . '/../../models/Configuracao.php';

global $pdo;

$system_logo = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $configModel = new Configuracao($pdo);
    $retrieved_logo = $configModel->get('system_logo');

    if (!empty($retrieved_logo)) {
        $logo_path = __DIR__ . '/../../../' . $retrieved_logo;
        if (file_exists($logo_path)) {
            $system_logo = $retrieved_logo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>

    <!-- Adiciona o Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-full max-w-md">
        <div class="bg-white p-8 rounded-lg shadow-lg">
            <div class="mb-4 text-center">
                <?php if (!empty($system_logo)): ?>
                    <img src="<?php echo htmlspecialchars(APP_URL . '/' . $system_logo); ?>" alt="<?php echo htmlspecialchars(APP_NAME); ?> logo" class="mx-auto h-12 w-auto">
                <?php else: ?>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2"><?php echo APP_NAME; ?></h1>
                <?php endif; ?>
            </div>
            <p class="text-center text-gray-600 mb-8">Por favor, insira as suas credenciais para acessar ao sistema.</p>

            <?php
            // Bloco para exibir mensagens de erro
            if (isset($_SESSION['error_message'])) {
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
            // Bloco para exibir mensagens de sucesso
            if (isset($_SESSION['success_message'])) {
                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <form action="login.php?action=login" method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-6">
                    <label for="senha" class="block text-gray-700 text-sm font-bold mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                        Entrar
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
