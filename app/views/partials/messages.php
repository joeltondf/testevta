<?php
// /app/views/partials/messages.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Exibe a mensagem de sucesso, se houver
if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Sucesso!</p>
        <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
    </div>
    <?php unset($_SESSION['success_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>

<?php
// Exibe a mensagem de erro, se houver
if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Erro!</p>
        <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
    </div>
    <?php unset($_SESSION['error_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>

<?php
// Exibe mensagens informativas, se houver
if (isset($_SESSION['info_message'])): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Informação:</p>
        <p><?php echo htmlspecialchars($_SESSION['info_message']); ?></p>
    </div>
    <?php unset($_SESSION['info_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>

<?php
// Exibe mensagens de aviso, se houver
if (isset($_SESSION['warning_message'])): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Atenção:</p>
        <p><?php echo htmlspecialchars($_SESSION['warning_message']); ?></p>
    </div>
    <?php unset($_SESSION['warning_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>

