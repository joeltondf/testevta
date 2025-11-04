<?php
// visualizar_anexo.php

require_once 'config.php';

// Pega o ID do anexo da URL
$anexo_id = $_GET['id'] ?? null;

if (!$anexo_id) {
    die("Anexo não especificado.");
}

// Busca os detalhes do anexo no banco de dados
try {
    $stmt = $pdo->prepare("SELECT * FROM processo_anexos WHERE id = ?");
    $stmt->execute([$anexo_id]);
    $anexo = $stmt->fetch();

    if (!$anexo) {
        die("Anexo não encontrado.");
    }

    $caminho_arquivo = $anexo['caminho_arquivo'];
    $extensao = strtolower(pathinfo($caminho_arquivo, PATHINFO_EXTENSION));

    // Constrói a URL completa e pública do arquivo no seu servidor
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $url_completa_do_arquivo = $protocolo . $_SERVER['HTTP_HOST'] . '/' . $caminho_arquivo;

    // Define os tipos de arquivo que o visualizador da Microsoft suporta
    $tipos_office = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'];

    if (in_array($extensao, $tipos_office)) {
        // Se for um arquivo do Office, redireciona para o visualizador da Microsoft
        $url_visualizador = "https://view.officeapps.live.com/op/view.aspx?src=" . urlencode($url_completa_do_arquivo);
        header('Location: ' . $url_visualizador);
        exit();
    } else {
        // Para outros tipos (PDF, imagens, etc.), abre o arquivo diretamente
        header('Location: ' . $url_completa_do_arquivo);
        exit();
    }

} catch (Exception $e) {
    die("Erro ao acessar o banco de dados: " . $e->getMessage());
}