<?php
require_once 'config.php';

// Desabilitar a exibição de erros para usuários finais
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Detectar ambiente (Replit vs. Local)
$isReplit = getenv('REPL_ID') !== false || getenv('PGHOST') !== false;

// Conectar ao banco de dados
$conn = conectarBanco();

// Definir SQL baseado no ambiente
if ($isReplit) {
    // PostgreSQL (Replit)
    $tables = [
        "CREATE TABLE IF NOT EXISTS clientes (
            id_cliente SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            telefone VARCHAR(20),
            email VARCHAR(100),
            endereco VARCHAR(200),
            data_cadastro DATE NOT NULL,
            observacoes TEXT
        )",
        
        "CREATE TABLE IF NOT EXISTS produtos (
            id_produto SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            preco DECIMAL(10,2) NOT NULL,
            estoque INT NOT NULL DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE
        )",
        
        "CREATE TABLE IF NOT EXISTS vendas (
            id_venda SERIAL PRIMARY KEY,
            id_cliente INT,
            data_venda TIMESTAMP NOT NULL,
            valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'pendente',
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE RESTRICT
        )",
        
        "CREATE TABLE IF NOT EXISTS itens_venda (
            id_item SERIAL PRIMARY KEY,
            id_venda INT NOT NULL,
            id_produto INT NOT NULL,
            quantidade INT NOT NULL,
            preco_unitario DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE,
            FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
        )",
        
        "CREATE TABLE IF NOT EXISTS pagamentos (
            id_pagamento SERIAL PRIMARY KEY,
            id_venda INT NOT NULL,
            data_pagamento DATE NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            forma_pagamento VARCHAR(20),
            observacoes TEXT,
            FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE
        )"
    ];

    // Criar as tabelas
    $success = true;
    foreach ($tables as $sql) {
        $result = pg_query($conn->conn, $sql);
        if (!$result) {
            echo "<p>Erro ao criar tabela: " . pg_last_error($conn->conn) . "</p>";
            $success = false;
        }
    }

    // Inserir dados de exemplo (opcional)
    if ($success) {
        // Verificar se já existem clientes
        $result = pg_query($conn->conn, "SELECT COUNT(*) as total FROM clientes");
        $row = pg_fetch_assoc($result);
        
        if ($row['total'] == 0) {
            // Adicionar cliente exemplo
            $sql = "INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro) 
                    VALUES ('Cliente Exemplo', '(11) 98765-4321', 'cliente@exemplo.com', 'Rua Exemplo, 123', CURRENT_DATE)";
            pg_query($conn->conn, $sql);
            
            // Adicionar produto exemplo
            $sql = "INSERT INTO produtos (nome, descricao, preco, estoque, ativo) 
                    VALUES ('Bolo de Chocolate', 'Delicioso bolo de chocolate com cobertura', 45.90, 10, TRUE)";
            pg_query($conn->conn, $sql);
        }
    }
} else {
    // MySQL (Local XAMPP)
    // Primeiro verificar se o banco de dados existe, caso contrário criar
    try {
        // Conectar ao MySQL sem selecionar banco de dados
        $host = 'localhost';
        $user = 'root';
        $password = '';
        
        $mysqli_temp = new mysqli($host, $user, $password);
        
        if ($mysqli_temp->connect_error) {
            die("Erro de conexão ao MySQL: " . $mysqli_temp->connect_error);
        }
        
        // Criar banco de dados se não existir
        $result = $mysqli_temp->query("CREATE DATABASE IF NOT EXISTS abelhinha_doce");
        if (!$result) {
            die("Erro ao criar banco de dados: " . $mysqli_temp->error);
        }
        
        // Selecionar o banco de dados
        $mysqli_temp->select_db('abelhinha_doce');
        
        // Agora criar as tabelas
        $tables = [
            "CREATE TABLE IF NOT EXISTS clientes (
                id_cliente INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                telefone VARCHAR(20),
                email VARCHAR(100),
                endereco VARCHAR(200),
                data_cadastro DATE NOT NULL,
                observacoes TEXT
            )",
            
            "CREATE TABLE IF NOT EXISTS produtos (
                id_produto INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT,
                preco DECIMAL(10,2) NOT NULL,
                estoque INT NOT NULL DEFAULT 0,
                ativo TINYINT(1) DEFAULT 1
            )",
            
            "CREATE TABLE IF NOT EXISTS vendas (
                id_venda INT AUTO_INCREMENT PRIMARY KEY,
                id_cliente INT,
                data_venda TIMESTAMP NOT NULL,
                valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(20) DEFAULT 'pendente',
                FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE RESTRICT
            )",
            
            "CREATE TABLE IF NOT EXISTS itens_venda (
                id_item INT AUTO_INCREMENT PRIMARY KEY,
                id_venda INT NOT NULL,
                id_produto INT NOT NULL,
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE,
                FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
            )",
            
            "CREATE TABLE IF NOT EXISTS pagamentos (
                id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
                id_venda INT NOT NULL,
                data_pagamento DATE NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                forma_pagamento VARCHAR(20),
                observacoes TEXT,
                FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE
            )"
        ];

        // Criar as tabelas
        $success = true;
        foreach ($tables as $sql) {
            $result = $mysqli_temp->query($sql);
            if (!$result) {
                echo "<p>Erro ao criar tabela: " . $mysqli_temp->error . "</p>";
                $success = false;
            }
        }

        // Inserir dados de exemplo (opcional)
        if ($success) {
            // Verificar se já existem clientes
            $result = $mysqli_temp->query("SELECT COUNT(*) as total FROM clientes");
            $row = $result->fetch_assoc();
            
            if ($row['total'] == 0) {
                // Adicionar cliente exemplo
                $sql = "INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro) 
                        VALUES ('Cliente Exemplo', '(11) 98765-4321', 'cliente@exemplo.com', 'Rua Exemplo, 123', CURDATE())";
                $mysqli_temp->query($sql);
                
                // Adicionar produto exemplo
                $sql = "INSERT INTO produtos (nome, descricao, preco, estoque, ativo) 
                        VALUES ('Bolo de Chocolate', 'Delicioso bolo de chocolate com cobertura', 45.90, 10, 1)";
                $mysqli_temp->query($sql);
            }
        }
        
        // Fechar conexão temporária
        $mysqli_temp->close();
    } catch (Exception $e) {
        die("Erro ao configurar banco de dados MySQL: " . $e->getMessage());
    }
}

echo "<p>Instalação concluída com sucesso!</p>";
echo "<p><a href='index.php'>Ir para o sistema</a></p>";

// Fechar conexão
if (method_exists($conn, 'close')) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Abelhinha Doce</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f9f4e8;
            color: #5a3e36;
        }
        
        h1 {
            color: #5a3e36;
            border-bottom: 2px solid #f8c537;
            padding-bottom: 10px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        p {
            margin-bottom: 10px;
        }
        
        a {
            display: inline-block;
            background-color: #f8c537;
            color: #5a3e36;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        a:hover {
            background-color: #e6b52e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalação do Sistema Abelhinha Doce</h1>
    </div>
</body>
</html>
