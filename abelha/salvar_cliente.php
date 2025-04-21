<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = conectarBanco();
    
    // Usar prepared statements para evitar SQL Injection
    if (empty($_POST['id_cliente'])) {
        // Novo cliente
        $stmt = $conn->prepare("INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro, observacoes)
                VALUES (?, ?, ?, ?, CURRENT_DATE, ?)");
        $stmt->bind_param("sssss", 
            $_POST['nome'],
            $_POST['telefone'],
            $_POST['email'],
            $_POST['endereco'],
            $_POST['observacoes']
        );
    } else {
        // Atualizar cliente
        $stmt = $conn->prepare("UPDATE clientes SET 
                nome = ?,
                telefone = ?,
                email = ?,
                endereco = ?,
                observacoes = ?
                WHERE id_cliente = ?");
        $stmt->bind_param("sssssi", 
            $_POST['nome'],
            $_POST['telefone'],
            $_POST['email'],
            $_POST['endereco'],
            $_POST['observacoes'],
            $_POST['id_cliente']
        );
    }
    
    if ($stmt->execute()) {
        $_SESSION['mensagem'] = 'Cliente salvo com sucesso!';
    } else {
        $_SESSION['mensagem'] = 'Erro ao salvar cliente: ' . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
}

header('Location: index.php');
exit();
?>
