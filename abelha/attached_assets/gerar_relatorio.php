<?php
require_once 'config.php';
require_once 'funcoes.php';

header('Content-Type: application/json');

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Obter parâmetros
$tipo = $_POST['tipo'] ?? '';
$dataInicio = $_POST['data_inicio'] ?? '';
$dataFim = $_POST['data_fim'] ?? '';
$limite = $_POST['limite'] ?? 10;

// Validar datas
if (!validarData($dataInicio) || !validarData($dataFim)) {
    echo json_encode(['error' => 'Datas inválidas']);
    exit;
}

// Conexão com o banco
$conn = conectarBanco();

try {
    $resultado = [];
    
    switch ($tipo) {
        case 'vendas_periodo':
            $resultado = relatorioVendasPeriodo($conn, $dataInicio, $dataFim);
            break;
            
        case 'produtos_mais_vendidos':
            $resultado = relatorioProdutosMaisVendidos($conn, $dataInicio, $dataFim, $limite);
            break;
            
        case 'clientes_fieis':
            $resultado = relatorioClientesFieis($conn, $dataInicio, $dataFim, $limite);
            break;
            
        case 'pagamentos_forma':
            $resultado = relatorioPagamentosForma($conn, $dataInicio, $dataFim);
            break;
            
        case 'vendas_status':
            $resultado = relatorioVendasStatus($conn, $dataInicio, $dataFim);
            break;
            
        default:
            echo json_encode(['error' => 'Tipo de relatório inválido']);
            exit;
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()]);
}

// Funções específicas para cada relatório
function relatorioVendasPeriodo($conn, $dataInicio, $dataFim) {
    $sql = "SELECT DATE(v.data_venda) as data, 
                   COUNT(*) as total_vendas, 
                   SUM(v.valor_total) as valor_total,
                   GROUP_CONCAT(DISTINCT c.nome SEPARATOR ', ') as clientes
            FROM vendas v
            JOIN clientes c ON v.id_cliente = c.id_cliente
            WHERE v.data_venda BETWEEN ? AND ?
            GROUP BY DATE(v.data_venda)
            ORDER BY data";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $dataInicio, $dataFim);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    // Preparar dados para gráfico
    $grafico = [
        'labels' => array_column($dados, 'data'),
        'datasets' => [
            [
                'label' => 'Valor Total (R$)',
                'data' => array_column($dados, 'valor_total'),
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'borderWidth' => 1
            ]
        ]
    ];
    
    return [
        'tipo' => 'vendas_periodo',
        'dados' => $dados,
        'grafico' => $grafico,
        'total_vendas' => array_sum(array_column($dados, 'total_vendas')),
        'valor_total' => array_sum(array_column($dados, 'valor_total'))
    ];
}

function relatorioProdutosMaisVendidos($conn, $dataInicio, $dataFim, $limite) {
    $sql = "SELECT p.nome, 
                   SUM(iv.quantidade) as quantidade_total,
                   SUM(iv.quantidade * iv.preco_unitario) as valor_total
            FROM itens_venda iv
            JOIN produtos p ON iv.id_produto = p.id_produto
            JOIN vendas v ON iv.id_venda = v.id_venda
            WHERE v.data_venda BETWEEN ? AND ?
            GROUP BY p.id_produto
            ORDER BY quantidade_total DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $dataInicio, $dataFim, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    // Preparar dados para gráfico
    $grafico = [
        'labels' => array_column($dados, 'nome'),
        'datasets' => [
            [
                'label' => 'Quantidade Vendida',
                'data' => array_column($dados, 'quantidade_total'),
                'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                'borderColor' => 'rgba(75, 192, 192, 1)',
                'borderWidth' => 1
            ]
        ]
    ];
    
    return [
        'tipo' => 'produtos_mais_vendidos',
        'dados' => $dados,
        'grafico' => $grafico,
        'total_produtos' => count($dados),
        'valor_total' => array_sum(array_column($dados, 'valor_total'))
    ];
}

function relatorioClientesFieis($conn, $dataInicio, $dataFim, $limite) {
    $sql = "SELECT c.id_cliente, c.nome, 
                   COUNT(v.id_venda) as total_vendas,
                   SUM(v.valor_total) as valor_total
            FROM clientes c
            JOIN vendas v ON c.id_cliente = v.id_cliente
            WHERE v.data_venda BETWEEN ? AND ?
            GROUP BY c.id_cliente
            ORDER BY valor_total DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $dataInicio, $dataFim, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    return [
        'tipo' => 'clientes_fieis',
        'dados' => $dados,
        'total_clientes' => count($dados),
        'valor_total' => array_sum(array_column($dados, 'valor_total'))
    ];
}

function relatorioPagamentosForma($conn, $dataInicio, $dataFim) {
    $sql = "SELECT forma_pagamento, 
                   COUNT(*) as total_pagamentos,
                   SUM(valor) as valor_total
            FROM pagamentos
            WHERE data_pagamento BETWEEN ? AND ?
            GROUP BY forma_pagamento
            ORDER BY valor_total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $dataInicio, $dataFim);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    // Preparar dados para gráfico de pizza
    $grafico = [
        'labels' => array_column($dados, 'forma_pagamento'),
        'datasets' => [
            [
                'data' => array_column($dados, 'valor_total'),
                'backgroundColor' => [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)'
                ],
                'borderColor' => [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                'borderWidth' => 1
            ]
        ]
    ];
    
    return [
        'tipo' => 'pagamentos_forma',
        'dados' => $dados,
        'grafico' => $grafico,
        'total_pagamentos' => array_sum(array_column($dados, 'total_pagamentos')),
        'valor_total' => array_sum(array_column($dados, 'valor_total'))
    ];
}

function relatorioVendasStatus($conn, $dataInicio, $dataFim) {
    $sql = "SELECT status, 
                   COUNT(*) as total_vendas,
                   SUM(valor_total) as valor_total
            FROM vendas
            WHERE data_venda BETWEEN ? AND ?
            GROUP BY status
            ORDER BY valor_total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $dataInicio, $dataFim);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    return [
        'tipo' => 'vendas_status',
        'dados' => $dados,
        'total_vendas' => array_sum(array_column($dados, 'total_vendas')),
        'valor_total' => array_sum(array_column($dados, 'valor_total'))
    ];
}

function validarData($data) {
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}
?>