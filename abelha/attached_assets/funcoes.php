<?php
require 'config.php';

function getClientes($busca = '') {
    $conn = conectarBanco();
    $sql = "SELECT * FROM clientes";
    
    if (!empty($busca)) {
        $busca = $conn->real_escape_string($busca);
        $sql .= " WHERE nome LIKE '%$busca%' OR email LIKE '%$busca%' OR telefone LIKE '%$busca%'";
    }
    
    $sql .= " ORDER BY nome";
    $result = $conn->query($sql);
    $clientes = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    }
    
    $conn->close();
    return $clientes;
}

function getValorPago($id_venda) {
    $conn = conectarBanco();
    $sql = "SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE id_venda = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return (float)$row['total'];
}


function formatFormaPagamento($forma) {
    $formas = [
        'dinheiro' => 'Dinheiro',
        'cartao_debito' => 'Cartão Débito',
        'cartao_credito' => 'Cartão Crédito',
        'pix' => 'PIX',
        'transferencia' => 'Transferência'
    ];
    return $formas[$forma] ?? $forma;
}
// Adicione outras funções conforme necessário
?>