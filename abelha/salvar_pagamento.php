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
    header('Location: index.php');
    exit;
}

$conn = conectarBanco();

try {
    $conn->begin_transaction();
    
    // Converter o valor para formato correto
    $valor = floatval(str_replace(',', '.', $_POST['pagamento-valor']));
    
    // Validar o valor do pagamento
    if ($valor <= 0) {
        throw new Exception('O valor do pagamento deve ser maior que zero');
    }
    
    // Obter valor total da venda
    $idVenda = intval($_POST['pagamento-venda']);
    $valorTotal = getValorTotalVenda($idVenda);
    $valorPago = getValorPago($idVenda);
    $valorPendente = $valorTotal - $valorPago;
    
    // Verificar se o valor não excede o valor pendente
    if ($valor > $valorPendente) {
        throw new Exception('O valor do pagamento não pode exceder o valor pendente da venda (R$ ' . formataMoeda($valorPendente) . ')');
    }
    
    // Insere o pagamento
    $stmt = $conn->prepare("INSERT INTO pagamentos 
                          (id_venda, data_pagamento, valor, forma_pagamento, observacoes) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", 
        $idVenda,
        $_POST['pagamento-data'],
        $valor,
        $_POST['pagamento-forma'],
        $_POST['pagamento-observacoes']
    );
    $stmt->execute();
    
    // Verificar se a venda está totalmente paga
    $valorPagoAtualizado = $valorPago + $valor;
    
    // Use comparação numérica com margem de 0.01 para evitar problemas de arredondamento
    if (abs($valorPagoAtualizado - $valorTotal) < 0.01) {
        $stmtVenda = $conn->prepare("UPDATE vendas SET status = 'pago' WHERE id_venda = ?");
        $stmtVenda->bind_param("i", $idVenda);
        $stmtVenda->execute();
    }
    
    $conn->commit();
    
    $_SESSION['mensagem'] = 'Pagamento registrado com sucesso!';
    header('Location: index.php');
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem'] = 'Erro ao registrar pagamento: ' . $e->getMessage();
    header('Location: index.php');
}
?>
