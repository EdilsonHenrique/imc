<?php
require_once 'config.php';

// Função para obter lista de clientes com busca opcional
function getClientes($busca = '', $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT * FROM clientes";
    
    if (!empty($busca)) {
        $busca = $conn->real_escape_string($busca);
        $sql .= " WHERE nome LIKE '%$busca%' OR email LIKE '%$busca%' OR telefone LIKE '%$busca%'";
    }
    
    $sql .= " ORDER BY nome";
    $result = $conn->query($sql);
    $clientes = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    }
    
    if ($closeConn) {
        $conn->close();
    }
    
    return $clientes;
}

// Função para obter lista de produtos com busca opcional
function getProdutos($busca = '', $somenteAtivos = false, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT * FROM produtos";
    
    $where = [];
    
    if (!empty($busca)) {
        $busca = $conn->real_escape_string($busca);
        $where[] = "(nome LIKE '%$busca%' OR descricao LIKE '%$busca%')";
    }
    
    if ($somenteAtivos) {
        $where[] = "ativo = TRUE";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY nome";
    $result = $conn->query($sql);
    $produtos = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $produtos[] = $row;
        }
    }
    
    if ($closeConn) {
        $conn->close();
    }
    
    return $produtos;
}

// Função para obter lista de vendas com filtros opcionais
function getVendas($filtroCliente = null, $filtroData = null, $filtroStatus = null, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT v.*, c.nome as cliente 
            FROM vendas v
            JOIN clientes c ON v.id_cliente = c.id_cliente";
    
    $where = [];
    
    if (!empty($filtroCliente)) {
        $filtroCliente = $conn->real_escape_string($filtroCliente);
        $where[] = "v.id_cliente = '$filtroCliente'";
    }
    
    if (!empty($filtroData)) {
        $filtroData = $conn->real_escape_string($filtroData);
        $where[] = "DATE(v.data_venda) = '$filtroData'";
    }
    
    if (!empty($filtroStatus)) {
        $filtroStatus = $conn->real_escape_string($filtroStatus);
        $where[] = "v.status = '$filtroStatus'";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY v.data_venda DESC";
    $result = $conn->query($sql);
    $vendas = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $vendas[] = $row;
        }
    }
    
    if ($closeConn) {
        $conn->close();
    }
    
    return $vendas;
}

// Função para obter valor total pago em uma venda
function getValorPago($id_venda, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE id_venda = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($closeConn) {
        $conn->close();
    }
    
    return (float)$row['total'];
}

// Função para obter valor total de uma venda
function getValorTotalVenda($id_venda, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT valor_total FROM vendas WHERE id_venda = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($closeConn) {
        $conn->close();
    }
    
    return (float)$row['valor_total'];
}

// Função para formatar valor monetário
function formataMoeda($valor) {
    return number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formataData($data, $formato = 'd/m/Y') {
    $timestamp = strtotime($data);
    return date($formato, $timestamp);
}

// Função para formatar forma de pagamento
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

// Função para formatar status da venda
function formatStatus($status) {
    $statusMapping = [
        'pendente' => 'Pendente',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado'
    ];
    return $statusMapping[$status] ?? $status;
}

// Função para validar data
function validarData($data) {
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

// Função para obter os pagamentos
function getPagamentos($filtroVenda = null, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = conectarBanco();
        $closeConn = true;
    }
    
    $sql = "SELECT p.*, v.id_venda, c.nome as cliente 
           FROM pagamentos p
           JOIN vendas v ON p.id_venda = v.id_venda
           JOIN clientes c ON v.id_cliente = c.id_cliente";
    
    if (!empty($filtroVenda)) {
        $filtroVenda = $conn->real_escape_string($filtroVenda);
        $sql .= " WHERE p.id_venda = '$filtroVenda'";
    }
    
    $sql .= " ORDER BY p.data_pagamento DESC";
    $result = $conn->query($sql);
    $pagamentos = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $pagamentos[] = $row;
        }
    }
    
    if ($closeConn) {
        $conn->close();
    }
    
    return $pagamentos;
}

/**
 * Prepara uma instrução SQL para execução segura
 * @param mixed $conn A conexão com o banco de dados
 * @param string $sql A consulta SQL com parâmetros
 * @return mixed Retorna a instrução preparada
 */
function prepararSQL($conn, $sql) {
    // Detectar ambiente
    $isReplit = getenv('REPL_ID') !== false || getenv('PGHOST') !== false;
    
    if ($isReplit) {
        // PostgreSQL
        if (method_exists($conn, 'get_raw_resource')) {
            return pg_prepare($conn->get_raw_resource(), "", $sql);
        } else {
            return pg_prepare($conn, "", $sql);
        }
    } else {
        // MySQL
        return $conn->prepare($sql);
    }
}
?>
