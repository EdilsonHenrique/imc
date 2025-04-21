<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método inválido';
    header('Location: index.php');
    exit;
}

// Validações
if (empty($_POST['pagamento-venda']) || empty($_POST['pagamento-data']) || 
    empty($_POST['pagamento-valor']) || empty($_POST['pagamento-forma'])) {
    $_SESSION['mensagem'] = 'Preencha todos os campos obrigatórios';
    header('Location: index.php?pagina=pagamentos');
    exit;
}

$conn = conectarBanco();

try {
    $conn->begin_transaction();
    
    // Insere o pagamento
    $stmt = $conn->prepare("INSERT INTO pagamentos 
                          (id_venda, data_pagamento, valor, forma_pagamento, observacoes) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", 
        $_POST['pagamento-venda'],
        $_POST['pagamento-data'],
        str_replace(',', '.', $_POST['pagamento-valor']),
        $_POST['pagamento-forma'],
        $_POST['pagamento-observacoes']
    );
    $stmt->execute();
    
    // Verifica se a venda está totalmente paga
$valorPago = getValorPago($_POST['pagamento-venda']);
$valorTotal = getValorTotalVenda($_POST['pagamento-venda']);

// Adicione logs para depuração (remova após teste)
error_log("Valor pago: $valorPago | Valor total: $valorTotal");

// Use comparação numérica com margem de 0.01 para evitar problemas de arredondamento
if (abs($valorPago - $valorTotal) < 0.01) {
    $stmtVenda = $conn->prepare("UPDATE vendas SET status = 'pago' WHERE id_venda = ?");
    $stmtVenda->bind_param("i", $_POST['pagamento-venda']);
    $stmtVenda->execute();
    
    // Adicione log para confirmar a atualização
    error_log("Status atualizado para pago - Venda ID: " . $_POST['pagamento-venda']);
}
    
    $conn->commit();
    
    $_SESSION['mensagem'] = 'Pagamento registrado com sucesso!';
    header('Location: index.php?pagina=pagamentos');
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem'] = 'Erro ao registrar pagamento: ' . $e->getMessage();
    header('Location: index.php?pagina=pagamentos');
}

function getValorTotalVenda($id_venda) {
    $conn = conectarBanco();
    $sql = "SELECT valor_total FROM vendas WHERE id_venda = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return (float)$row['valor_total'];
}
?>