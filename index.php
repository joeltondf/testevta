<?php
// /index.php

/**
 * Este ficheiro é o ponto de entrada principal do site.
 * * A sua única responsabilidade é verificar se o utilizador está a tentar
 * aceder à raiz do domínio e, nesse caso, redirecioná-lo para
 * a página de login.
 * * Isto garante que não há erros de "página não encontrada" ou "acesso proibido"
 * quando o domínio principal é acedido.
 */

// Redireciona o utilizador para a página de login.
header('Location: login.php');

// Interrompe a execução do script para garantir que nada mais é processado após o redirecionamento.
exit();
?>