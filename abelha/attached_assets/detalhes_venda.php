<?php
require_once 'config.php';
require_once 'funcoes.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID não fornecido']);
    exit;
}

$conn = conectarBanco();
$id = intval($_GET['id']);

// Consulta principal da venda
$sql = "SELECT v.*, c.nome as cliente 
        FROM vendas v
        JOIN clientes c ON v.id_cliente = c.id_cliente
        WHERE v.id_venda = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Venda não encontrada']);
    exit;
}

$venda = $result->fetch_assoc();

// Consulta dos itens da venda
$sqlItens = "SELECT i.*, p.nome as produto 
             FROM itens_venda i
             JOIN produtos p ON i.id_produto = p.id_produto
             WHERE i.id_venda = ?";
$stmtItens = $conn->prepare($sqlItens);
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$itens = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);

$venda['itens'] = $itens;

echo json_encode($venda);
?>