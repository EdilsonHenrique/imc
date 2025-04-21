<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = conectarBanco();
    
    // Processar dados do formulário
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Converter o preço para o formato correto (ponto como separador decimal)
    $preco = str_replace(',', '.', $_POST['preco'] ?? '0');
    $preco = floatval($preco);
    
    $estoque = intval($_POST['estoque'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 'TRUE' : 'FALSE';
    
    // Validar dados
    if (empty($nome)) {
        $_SESSION['mensagem'] = 'O nome do produto é obrigatório';
        header('Location: index.php');
        exit();
    }
    
    if ($preco <= 0) {
        $_SESSION['mensagem'] = 'O preço deve ser maior que zero';
        header('Location: index.php');
        exit();
    }
    
    try {
        if (empty($_POST['id_produto'])) {
            // Novo produto
            $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, estoque, ativo)
                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdis", $nome, $descricao, $preco, $estoque, $ativo);
        } else {
            // Atualizar produto
            $id = intval($_POST['id_produto']);
            $stmt = $conn->prepare("UPDATE produtos SET 
                    nome = ?,
                    descricao = ?,
                    preco = ?,
                    estoque = ?,
                    ativo = ?
                    WHERE id_produto = ?");
            $stmt->bind_param("ssdisi", $nome, $descricao, $preco, $estoque, $ativo, $id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = 'Produto salvo com sucesso!';
        } else {
            $_SESSION['mensagem'] = 'Erro ao salvar produto: ' . $stmt->error;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['mensagem'] = 'Erro ao salvar produto: ' . $e->getMessage();
    }
    
    $conn->close();
}

header('Location: index.php');
exit();
?>
