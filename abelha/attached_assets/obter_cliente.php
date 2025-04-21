<?php
require 'config.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn = conectarBanco();
    
    $sql = "SELECT * FROM clientes WHERE id_cliente = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $cliente = $result->fetch_assoc();
        echo json_encode($cliente);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente não encontrado']);
    }
    
    $conn->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'ID não fornecido']);
}
?>