<?php
require_once 'config.php';
require_once 'funcoes.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn = conectarBanco();
    
    // Usar prepared statement para evitar SQL Injection
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cliente = $result->fetch_assoc();
        echo json_encode($cliente);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente não encontrado']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'ID não fornecido']);
}
?>
