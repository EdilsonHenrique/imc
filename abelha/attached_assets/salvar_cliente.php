<?php
require 'config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = conectarBanco();
    
    $nome = $conn->real_escape_string($_POST['nome']);
    $telefone = $conn->real_escape_string($_POST['telefone']);
    $email = $conn->real_escape_string($_POST['email']);
    $endereco = $conn->real_escape_string($_POST['endereco']);
    $observacoes = $conn->real_escape_string($_POST['observacoes']);
    
    if (empty($_POST['id_cliente'])) {
        // Novo cliente
        $sql = "INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro, observacoes)
                VALUES ('$nome', '$telefone', '$email', '$endereco', CURDATE(), '$observacoes')";
    } else {
        // Atualizar cliente
        $id = (int)$_POST['id_cliente'];
        $sql = "UPDATE clientes SET 
                nome = '$nome',
                telefone = '$telefone',
                email = '$email',
                endereco = '$endereco',
                observacoes = '$observacoes'
                WHERE id_cliente = $id";
    }
    
    if ($conn->query($sql)) {
        $_SESSION['mensagem'] = 'Cliente salvo com sucesso!';
    } else {
        $_SESSION['mensagem'] = 'Erro ao salvar cliente: ' . $conn->error;
    }
    
    $conn->close();
}

header('Location: index.php');
exit();
?>