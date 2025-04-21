<?php
require 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Cria o banco de dados
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Banco de dados criado com sucesso.<br>";
} else {
    echo "Erro ao criar banco: " . $conn->error . "<br>";
}

$conn->select_db(DB_NAME);

// Criação das tabelas
$tables = [
    "CREATE TABLE IF NOT EXISTS clientes (
        id_cliente INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        telefone VARCHAR(20),
        email VARCHAR(100),
        endereco VARCHAR(200),
        data_cadastro DATE NOT NULL,
        observacoes TEXT
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS produtos (
        id_produto INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10,2) NOT NULL,
        estoque INT NOT NULL,
        ativo BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS vendas (
        id_venda INT AUTO_INCREMENT PRIMARY KEY,
        id_cliente INT,
        data_venda DATETIME NOT NULL,
        valor_total DECIMAL(10,2) NOT NULL,
        status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS itens_venda (
        id_item INT AUTO_INCREMENT PRIMARY KEY,
        id_venda INT NOT NULL,
        id_produto INT NOT NULL,
        quantidade INT NOT NULL,
        preco_unitario DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_venda) REFERENCES vendas(id_venda),
        FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS pagamentos (
        id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
        id_venda INT NOT NULL,
        data_pagamento DATE NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        forma_pagamento ENUM('dinheiro', 'cartao_debito', 'cartao_credito', 'pix', 'transferencia'),
        observacoes TEXT,
        FOREIGN KEY (id_venda) REFERENCES vendas(id_venda)
    ) ENGINE=InnoDB"
];

foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Tabela criada com sucesso.<br>";
    } else {
        echo "Erro ao criar tabela: " . $conn->error . "<br>";
    }
}

$conn->close();
echo "Instalação concluída!";
?>