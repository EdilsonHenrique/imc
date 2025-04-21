<?php
require_once 'config.php';
require_once 'funcoes.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID do produto não fornecido']);
    exit;
}

$conn = conectarBanco();
$id = intval($_GET['id']);

$sql = "SELECT * FROM produtos WHERE id_produto = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Produto não encontrado']);
    exit;
}

$produto = $result->fetch_assoc();
echo json_encode($produto);

$stmt->close();
$conn->close();
?>
