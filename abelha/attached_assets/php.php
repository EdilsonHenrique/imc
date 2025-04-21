<?php
// Conexão com o banco de dados
$conn = new mysqli('localhost', 'root', '', 'bd_abelhinha');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para cadastrar cliente
function cadastrarCliente($nome, $telefone, $email, $endereco) {
    global $conn;
    $sql = "INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro) 
            VALUES (?, ?, ?, ?, CURDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nome, $telefone, $email, $endereco);
    return $stmt->execute();
}

// Função para registrar venda
function registrarVenda($id_cliente, $itens) {
    global $conn;
    
    // Calcula total
    $total = 0;
    foreach ($itens as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }
    
    // Inicia transação
    $conn->begin_transaction();
    
    try {
        // Insere venda
        $sql_venda = "INSERT INTO vendas (id_cliente, data_venda, valor_total) 
                      VALUES (?, NOW(), ?)";
        $stmt = $conn->prepare($sql_venda);
        $stmt->bind_param("id", $id_cliente, $total);
        $stmt->execute();
        $id_venda = $conn->insert_id;
        
        // Insere itens
        foreach ($itens as $item) {
            $sql_item = "INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario)
                          VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_item);
            $stmt->bind_param("iiid", $id_venda, $item['id_produto'], $item['quantidade'], $item['preco']);
            $stmt->execute();
            
            // Atualiza estoque
            $sql_estoque = "UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?";
            $stmt = $conn->prepare($sql_estoque);
            $stmt->bind_param("ii", $item['quantidade'], $item['id_produto']);
            $stmt->execute();
        }
        
        $conn->commit();
        return $id_venda;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
?>