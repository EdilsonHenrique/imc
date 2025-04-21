<?php
require_once 'config.php';
require_once 'funcoes.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID da venda não fornecido']);
    exit;
}

$conn = conectarBanco();
$idVenda = intval($_GET['id']);

try {
    // Obter dados da venda
    $sqlVenda = "SELECT v.*, c.nome as cliente 
                FROM vendas v
                JOIN clientes c ON v.id_cliente = c.id_cliente
                WHERE v.id_venda = ?";
    $stmtVenda = $conn->prepare($sqlVenda);
    $stmtVenda->bind_param("i", $idVenda);
    $stmtVenda->execute();
    $venda = $stmtVenda->get_result()->fetch_assoc();

    if (!$venda) {
        echo json_encode(['error' => 'Venda não encontrada']);
        exit;
    }

    // Obter itens da venda
    $sqlItens = "SELECT i.*, p.nome as produto 
                FROM itens_venda i
                JOIN produtos p ON i.id_produto = p.id_produto
                WHERE i.id_venda = ?";
    $stmtItens = $conn->prepare($sqlItens);
    $stmtItens->bind_param("i", $idVenda);
    $stmtItens->execute();
    $itens = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);

    $venda['itens'] = $itens;

    echo json_encode($venda);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao obter venda: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
