<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">  <!-- LINHA ADICIONADA -->

    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-gray-100 text-gray-900">
    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm uppercase tracking-wide text-gray-400">Portal do Cliente</p>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo APP_NAME; ?></h1>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="font-medium text-gray-700">Olá, <?php echo htmlspecialchars($_SESSION['user_nome']); ?></span>
                <a href="login.php?action=logout" class="inline-flex items-center gap-2 rounded-md border border-red-600 px-3 py-2 font-semibold text-red-600 transition hover:bg-red-50">Sair</a>
            </div>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">