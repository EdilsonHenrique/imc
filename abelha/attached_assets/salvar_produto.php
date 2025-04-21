<?php
require_once 'config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = conectarBanco();
    
    // Debug: Verifique os dados recebidos
    error_log(print_r($_POST, true));
    
    $nome = $conn->real_escape_string($_POST['nome'] ?? '');
    $descricao = $conn->real_escape_string($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $estoque = intval($_POST['estoque'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if (empty($_POST['id_produto'])) {
        $sql = "INSERT INTO produtos (nome, descricao, preco, estoque, ativo)
                VALUES ('$nome', '$descricao', $preco, $estoque, $ativo)";
    } else {
        $id = intval($_POST['id_produto']);
        $sql = "UPDATE produtos SET 
                nome = '$nome',
                descricao = '$descricao',
                preco = $preco,
                estoque = $estoque,
                ativo = $ativo
                WHERE id_produto = $id";
    }
    
    // Debug: Mostre a query SQL
    error_log($sql);
    
    if ($conn->query($sql)) {
        $_SESSION['mensagem'] = 'Produto salvo com sucesso!';
    } else {
        $_SESSION['mensagem'] = 'Erro ao salvar produto: ' . $conn->error;
        error_log('Erro SQL: ' . $conn->error);
    }
    
    $conn->close();
}

header('Location: index.php');
exit();
?>