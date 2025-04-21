<?php
require_once 'config.php';
require_once 'funcoes.php';

if (!isset($_GET['id'])) {
    die('ID do produto não fornecido');
}

$conn = conectarBanco();
$id = intval($_GET['id']);

$sql = "SELECT * FROM produtos WHERE id_produto = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Produto não encontrado');
}

$produto = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($produto);

$stmt->close();
$conn->close();
?>