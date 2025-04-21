<?php
// Esta deve ser a PRIMEIRA linha do arquivo, sem espaços antes
session_start();
require_once 'config.php';
require_once 'funcoes.php';

// Verifica se há mensagens para exibir
$mensagem = '';
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// Obtém dados para o dashboard
$conn = conectarBanco();
$totalVendas = '0,00';
$totalClientes = 0;
$totalPendentes = '0,00';

// Verificar se as tabelas existem
$isReplit = getenv('REPL_ID') !== false || getenv('PGHOST') !== false;
$tableMissingDetected = false;

// Função para verificar se uma tabela existe com segurança
function tableExists($conn, $tableName) {
    global $isReplit;
    
    try {
        if ($isReplit) {
            // PostgreSQL
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = '$tableName'
            )";
            $result = pg_query($conn->conn, $sql);
            if ($result) {
                $row = pg_fetch_row($result);
                return $row[0] == 't';
            }
        } else {
            // MySQL
            $sql = "SHOW TABLES LIKE '$tableName'";
            $result = $conn->query($sql);
            return $result && $result->num_rows > 0;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

// Verificar tabelas essenciais
$requiredTables = ['clientes', 'produtos', 'vendas', 'itens_venda', 'pagamentos'];
foreach ($requiredTables as $table) {
    if (!tableExists($conn, $table)) {
        $tableMissingDetected = true;
        break;
    }
}

// Redirecionar para instalação se alguma tabela estiver faltando
if ($tableMissingDetected) {
    header("Location: install.php");
    exit;
}

// Consulta para total de vendas hoje - com tratamento de erro
try {
    if ($isReplit) {
        $sql = "SELECT SUM(valor_total) as total FROM vendas WHERE DATE(data_venda) = CURRENT_DATE";
    } else {
        $sql = "SELECT SUM(valor_total) as total FROM vendas WHERE DATE(data_venda) = CURDATE()";
    }
    $result = $conn->query($sql);
    if ($result) {
        if ($isReplit && method_exists($result, 'get_raw_result')) {
            $row = $result->fetch_assoc();
        } else {
            $row = $result->fetch_assoc();
        }
        if ($row) {
            $totalVendas = $row['total'] ? formataMoeda($row['total']) : '0,00';
        }
    }
} catch (Exception $e) {
    // Silenciosamente continuar, manter o valor padrão
}

// Consulta para total de clientes - com tratamento de erro
try {
    $sql = "SELECT COUNT(*) as total FROM clientes";
    $result = $conn->query($sql);
    if ($result) {
        if ($isReplit && method_exists($result, 'get_raw_result')) {
            $row = $result->fetch_assoc();
        } else {
            $row = $result->fetch_assoc();
        }
        if ($row) {
            $totalClientes = $row['total'];
        }
    }
} catch (Exception $e) {
    // Silenciosamente continuar, manter o valor padrão
}

// Consulta para total de pagamentos pendentes - com tratamento de erro
try {
    $sql = "SELECT SUM(valor_total) as total FROM vendas WHERE status = 'pendente'";
    $result = $conn->query($sql);
    if ($result) {
        if ($isReplit && method_exists($result, 'get_raw_result')) {
            $row = $result->fetch_assoc();
        } else {
            $row = $result->fetch_assoc();
        }
        if ($row) {
            $totalPendentes = $row['total'] ? formataMoeda($row['total']) : '0,00';
        }
    }
} catch (Exception $e) {
    // Silenciosamente continuar, manter o valor padrão
}

// Obter vendas recentes
$vendas = getVendas(null, null, null, $conn);

// Obter clientes para formulário de vendas
$clientesVenda = getClientes('', $conn);

// Obter produtos para formulário de vendas
$produtosVenda = getProdutos('', true, $conn);

// Obter pagamentos
$pagamentos = getPagamentos(null, $conn);

// Obter vendas pendentes para o formulário de pagamentos
$vendasPendentes = [];
$sqlVendasPendentes = "SELECT v.id_venda, c.nome as cliente, v.valor_total 
                      FROM vendas v
                      JOIN clientes c ON v.id_cliente = c.id_cliente
                      WHERE v.status = 'pendente' 
                      OR v.valor_total > (SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE id_venda = v.id_venda)";
$resultVendasPendentes = $conn->query($sqlVendasPendentes);
if ($resultVendasPendentes) {
    if ($isReplit && method_exists($resultVendasPendentes, 'get_raw_result')) {
        // PostgreSQL
        $vendasPendentes = $resultVendasPendentes->fetch_all();
    } else {
        // MySQL
        while ($row = $resultVendasPendentes->fetch_assoc()) {
            $vendasPendentes[] = $row;
        }
    }
}

// Obter produtos para página de produtos
$produtos = getProdutos('', false, $conn);

// Close the connection at the end
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abelhinha Doce - Sistema de Gerenciamento</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f4e8;
            color: #5a3e36;
        }
        
        header {
            background-color: #f8c537;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        header h1 {
            color: #5a3e36;
            font-size: 28px;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        nav {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        nav ul {
            display: flex;
            list-style: none;
            justify-content: space-around;
        }
        
        nav li a {
            text-decoration: none;
            color: #5a3e36;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        nav li a:hover, nav li a.active {
            background-color: #f8c537;
            color: #fff;
        }
        
        .content {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .content h2 {
            margin-bottom: 20px;
            color: #5a3e36;
            border-bottom: 2px solid #f8c537;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        button {
            background-color: #f8c537;
            color: #5a3e36;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        button:hover {
            background-color: #e6b52e;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background-color: #f8c537;
            color: #5a3e36;
        }
        
        table tr:hover {
            background-color: #f9f4e8;
        }
        
        .actions a {
            color: #5a3e36;
            margin-right: 10px;
            text-decoration: none;
        }
        
        .actions a:hover {
            text-decoration: underline;
        }
        
        .actions a.btn-excluir {
            color: #dc3545;
        }
        
        .actions a.btn-excluir:hover {
            color: #bd2130;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: #5a3e36;
            font-size: 14px;
        }
        
        /* Páginas específicas */
        #dashboard {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .card h3 {
            margin-bottom: 10px;
            color: #5a3e36;
        }
        
        .card .value {
            font-size: 24px;
            font-weight: bold;
            color: #f8c537;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }
        
        .status-badge.pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.pago {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Estilos para relatórios */
        .card-header h4 {
            margin-bottom: 0;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .canvas-container {
            position: relative;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Estilos para cards de resumo */
        .card.bg-light {
            border-left: 4px solid #007bff;
        }
        
        .card.bg-light .text-primary {
            color: #007bff !important;
        }
        
        .card.bg-light .text-success {
            color: #28a745 !important;
        }
        
        /* Estilos para abas de venda */
        .venda-item {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            position: relative;
        }
        
        .venda-item .remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            cursor: pointer;
        }
        
        .total-venda {
            font-size: 1.2em;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
        }
        
        .itens-container {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        #btn-add-item {
            margin-bottom: 20px;
        }
        
        /* Relatórios */
        .relatorio-form {
            background-color: #f9f4e8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .relatorio-form .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .relatorio-form .form-row .form-group {
            flex: 1;
        }
        
        .relatorio-result {
            margin-top: 30px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Confeitaria Abelhinha Doce - Sistema de Gerenciamento</h1>
    </header>
    
    <div class="container">
        <nav>
            <ul>
                <li><a href="#" class="active" id="nav-dashboard">Dashboard</a></li>
                <li><a href="#" id="nav-clientes">Clientes</a></li>
                <li><a href="#" id="nav-produtos">Produtos</a></li>
                <li><a href="#" id="nav-vendas">Vendas</a></li>
                <li><a href="#" id="nav-pagamentos">Pagamentos</a></li>
                <li><a href="#" id="nav-relatorios">Relatórios</a></li>
            </ul>
        </nav>
        
        <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success">
            <?php echo $mensagem; ?>
        </div>
        <?php endif; ?>
        
        <!-- Dashboard -->
        <div class="content" id="dashboard">
            <div class="card">
                <h3>Vendas Hoje</h3>
                <div class="value">R$ <?php echo $totalVendas; ?></div>
            </div>
            
            <div class="card">
                <h3>Clientes Cadastrados</h3>
                <div class="value"><?php echo $totalClientes; ?></div>
            </div>
            
            <div class="card">
                <h3>Pagamentos Pendentes</h3>
                <div class="value">R$ <?php echo $totalPendentes; ?></div>
            </div>
        </div>
        
        <!-- Clientes -->
        <div class="content" id="clientes" style="display: none;">
            <h2>Gerenciamento de Clientes</h2>
            
            <div id="cliente-form">
                <form method="POST" action="salvar_cliente.php">
                    <input type="hidden" id="id_cliente" name="id_cliente" value="">
                    
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="endereco">Endereço:</label>
                        <input type="text" id="endereco" name="endereco">
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações:</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" id="salvar-cliente">Salvar Cliente</button>
                    <button type="button" id="cancelar-cliente" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
                </form>
            </div>
            
            <div style="margin-top: 30px;">
                <input type="text" id="busca-cliente" placeholder="Buscar cliente..." style="padding: 8px; width: 300px;">
                <button type="button" id="btn-buscar-cliente">Buscar</button>
            </div>
            
            <table id="tabela-clientes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientesVenda as $cliente): ?>
                    <tr>
                        <td><?php echo $cliente['id_cliente']; ?></td>
                        <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                        <td><?php echo formataData($cliente['data_cadastro']); ?></td>
                        <td class="actions">
                            <a href="#" onclick="editarCliente(<?php echo $cliente['id_cliente']; ?>)">Editar</a>
                            <a href="#" onclick="verVendasCliente(<?php echo $cliente['id_cliente']; ?>)">Vendas</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Produtos -->
        <div class="content" id="produtos" style="display: none;">
            <h2>Gerenciamento de Produtos</h2>
            
            <div id="produto-form">
                <form method="POST" action="salvar_produto.php">
                    <input type="hidden" id="id_produto" name="id_produto" value="">
                    
                    <div class="form-group">
                        <label for="nome_produto">Nome:</label>
                        <input type="text" id="nome_produto" name="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição:</label>
                        <textarea id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="preco">Preço (R$):</label>
                        <input type="text" id="preco" name="preco" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="estoque">Estoque:</label>
                        <input type="number" id="estoque" name="estoque" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="ativo" name="ativo" value="1" checked>
                            Produto ativo
                        </label>
                    </div>
                    
                    <button type="submit" id="salvar-produto">Salvar Produto</button>
                    <button type="button" id="cancelar-produto" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
                </form>
            </div>
            
            <div style="margin-top: 30px;">
                <input type="text" id="busca-produto" placeholder="Buscar produto..." style="padding: 8px; width: 300px;">
                <button type="button" id="btn-buscar-produto">Buscar</button>
            </div>
            
            <table id="tabela-produtos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td><?php echo $produto['id_produto']; ?></td>
                        <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                        <td>R$ <?php echo formataMoeda($produto['preco']); ?></td>
                        <td><?php echo $produto['estoque']; ?></td>
                        <td><?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                        <td class="actions">
                            <a href="#" onclick="editarProduto(<?php echo $produto['id_produto']; ?>)">Editar</a>
                            <a href="#" onclick="confirmarExclusaoProduto(<?php echo $produto['id_produto']; ?>, '<?php echo htmlspecialchars(addslashes($produto['nome'])); ?>')" class="btn-excluir">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Vendas -->
        <div class="content" id="vendas" style="display: none;">
            <h2>Gerenciamento de Vendas</h2>
            
            <div id="venda-form">
                <form method="POST" action="salvar_venda.php" id="form-venda">
                    <input type="hidden" id="id_venda" name="id_venda" value="">
                    
                    <div class="form-group">
                        <label for="venda-cliente">Cliente:</label>
                        <select id="venda-cliente" name="venda-cliente" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientesVenda as $cliente): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>"><?php echo htmlspecialchars($cliente['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="venda-data">Data:</label>
                        <input type="date" id="venda-data" name="venda-data" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="venda-status">Status:</label>
                        <select id="venda-status" name="venda-status" required>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    
                    <h3>Itens da Venda</h3>
                    <button type="button" id="btn-add-item">Adicionar Item</button>
                    
                    <div class="itens-container" id="itens-container">
                        <div class="venda-item">
                            <div class="form-group">
                                <label>Produto:</label>
                                <select name="produto[]" class="produto-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($produtosVenda as $produto): ?>
                                        <option value="<?php echo $produto['id_produto']; ?>" data-preco="<?php echo $produto['preco']; ?>">
                                            <?php echo htmlspecialchars($produto['nome']); ?> - R$ <?php echo formataMoeda($produto['preco']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Quantidade:</label>
                                <input type="number" name="quantidade[]" class="quantidade-input" value="1" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Preço Unitário (R$):</label>
                                <input type="text" name="preco[]" class="preco-input" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Subtotal (R$):</label>
                                <input type="text" class="subtotal-input" readonly>
                            </div>
                            
                            <span class="remove-item">&times;</span>
                        </div>
                    </div>
                    
                    <div class="total-venda">
                        Total: R$ <span id="total-valor">0,00</span>
                    </div>
                    
                    <button type="submit" id="salvar-venda">Salvar Venda</button>
                    <button type="button" id="cancelar-venda" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
                </form>
            </div>
            
            <div style="margin-top: 30px;">
                <input type="text" id="busca-venda" placeholder="Buscar venda..." style="padding: 8px; width: 300px;">
                <button type="button" id="btn-buscar-venda">Buscar</button>
            </div>
            
            <table id="tabela-vendas">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $venda): ?>
                    <tr>
                        <td><?php echo $venda['id_venda']; ?></td>
                        <td><?php echo formataData($venda['data_venda']); ?></td>
                        <td><?php echo htmlspecialchars($venda['cliente']); ?></td>
                        <td>R$ <?php echo formataMoeda($venda['valor_total']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $venda['status']; ?>">
                                <?php echo formatStatus($venda['status']); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="#" onclick="verVenda(<?php echo $venda['id_venda']; ?>)">Ver</a>
                            <a href="#" onclick="editarVenda(<?php echo $venda['id_venda']; ?>)">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagamentos -->
        <div class="content" id="pagamentos" style="display: none;">
            <h2>Gestão de Pagamentos</h2>
            
            <div id="pagamento-form">
                <form method="POST" action="salvar_pagamento.php">
                    <div class="form-group">
                        <label for="pagamento-venda">Venda:</label>
                        <select id="pagamento-venda" name="pagamento-venda" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($vendasPendentes as $venda): ?>
                                <option value="<?php echo $venda['id_venda']; ?>">
                                    #<?php echo $venda['id_venda']; ?> - 
                                    <?php echo htmlspecialchars($venda['cliente']); ?> - 
                                    R$ <?php echo formataMoeda($venda['valor_total']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pagamento-data">Data:</label>
                        <input type="date" id="pagamento-data" name="pagamento-data" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pagamento-valor">Valor (R$):</label>
                        <input type="text" id="pagamento-valor" name="pagamento-valor" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pagamento-forma">Forma de Pagamento:</label>
                        <select id="pagamento-forma" name="pagamento-forma" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao_debito">Cartão de Débito</option>
                            <option value="cartao_credito">Cartão de Crédito</option>
                            <option value="pix">PIX</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pagamento-observacoes">Observações:</label>
                        <textarea id="pagamento-observacoes" name="pagamento-observacoes" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" id="salvar-pagamento">Registrar Pagamento</button>
                    <button type="button" id="cancelar-pagamento" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
                </form>
            </div>
            
            <h3 style="margin-top: 30px;">Histórico de Pagamentos</h3>
            <table id="tabela-pagamentos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Venda</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Forma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagamentos as $pagamento): ?>
                    <tr>
                        <td><?php echo $pagamento['id_pagamento']; ?></td>
                        <td><?php echo $pagamento['id_venda']; ?></td>
                        <td><?php echo htmlspecialchars($pagamento['cliente']); ?></td>
                        <td><?php echo formataData($pagamento['data_pagamento']); ?></td>
                        <td>R$ <?php echo formataMoeda($pagamento['valor']); ?></td>
                        <td><?php echo formatFormaPagamento($pagamento['forma_pagamento']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Relatórios -->
        <div class="content" id="relatorios" style="display: none;">
            <h2>Relatórios</h2>
            
            <div class="relatorio-form">
                <form id="form-relatorio" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo-relatorio">Tipo de Relatório:</label>
                            <select id="tipo-relatorio" name="tipo" required>
                                <option value="vendas_periodo">Vendas por Período</option>
                                <option value="produtos_mais_vendidos">Produtos Mais Vendidos</option>
                                <option value="clientes_fieis">Clientes Mais Fiéis</option>
                                <option value="pagamentos_forma">Pagamentos por Forma</option>
                                <option value="vendas_status">Vendas por Status</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="data-inicio">Data Início:</label>
                            <input type="date" id="data-inicio" name="data_inicio" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data-fim">Data Fim:</label>
                            <input type="date" id="data-fim" name="data_fim" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="limite">Limite de Itens:</label>
                            <input type="number" id="limite" name="limite" min="1" max="50" value="10">
                        </div>
                    </div>
                    
                    <button type="button" id="btn-gerar-relatorio">Gerar Relatório</button>
                </form>
            </div>
            
            <div id="relatorio-resultado" class="relatorio-result" style="display: none;">
                <h3 id="relatorio-titulo">Resultados</h3>
                <div id="relatorio-resumo" style="margin-bottom: 20px;"></div>
                
                <div class="canvas-container" style="margin-bottom: 30px;">
                    <canvas id="grafico-relatorio"></canvas>
                </div>
                
                <div class="table-responsive">
                    <table id="tabela-relatorio">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Confeitaria Abelhinha Doce - Sistema de Gerenciamento</p>
    </footer>
    
    <script>
        // Navegação entre abas
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos de navegação
            const navLinks = document.querySelectorAll('nav a');
            const contentDivs = document.querySelectorAll('.content');
            
            // Função para mostrar uma aba
            function showTab(id) {
                // Esconder todas as abas
                contentDivs.forEach(div => {
                    div.style.display = 'none';
                });
                
                // Remover classe ativa de todos os links
                navLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Mostrar aba selecionada
                document.getElementById(id).style.display = 'block';
                document.getElementById('nav-' + id).classList.add('active');
            }
            
            // Adicionar eventos de clique aos links
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.id.replace('nav-', '');
                    showTab(id);
                });
            });
            
            // Gestão de clientes
            const clienteForm = document.getElementById('cliente-form');
            const btnCancelarCliente = document.getElementById('cancelar-cliente');
            
            btnCancelarCliente.addEventListener('click', function() {
                document.getElementById('id_cliente').value = '';
                document.getElementById('nome').value = '';
                document.getElementById('telefone').value = '';
                document.getElementById('email').value = '';
                document.getElementById('endereco').value = '';
                document.getElementById('observacoes').value = '';
            });
            
            // Gestão de produtos
            const produtoForm = document.getElementById('produto-form');
            const btnCancelarProduto = document.getElementById('cancelar-produto');
            
            btnCancelarProduto.addEventListener('click', function() {
                document.getElementById('id_produto').value = '';
                document.getElementById('nome_produto').value = '';
                document.getElementById('descricao').value = '';
                document.getElementById('preco').value = '';
                document.getElementById('estoque').value = '0';
                document.getElementById('ativo').checked = true;
            });
            
            // Gestão de vendas
            const btnAddItem = document.getElementById('btn-add-item');
            const itensContainer = document.getElementById('itens-container');
            
            btnAddItem.addEventListener('click', function() {
                const itemTemplate = document.querySelector('.venda-item').cloneNode(true);
                
                // Limpar valores
                const inputs = itemTemplate.querySelectorAll('input');
                inputs.forEach(input => {
                    if (input.classList.contains('quantidade-input')) {
                        input.value = '1';
                    } else {
                        input.value = '';
                    }
                });
                
                const select = itemTemplate.querySelector('select');
                select.selectedIndex = 0;
                
                // Adicionar evento para remover item
                const btnRemove = itemTemplate.querySelector('.remove-item');
                btnRemove.addEventListener('click', function() {
                    itemTemplate.remove();
                    calcularTotalVenda();
                });
                
                // Adicionar eventos aos novos campos
                addEventosItemVenda(itemTemplate);
                
                itensContainer.appendChild(itemTemplate);
            });
            
            // Adicionar eventos aos itens de venda iniciais
            const itensVendaIniciais = document.querySelectorAll('.venda-item');
            itensVendaIniciais.forEach(item => {
                addEventosItemVenda(item);
                
                // Adicionar evento para remover item
                const btnRemove = item.querySelector('.remove-item');
                btnRemove.addEventListener('click', function() {
                    item.remove();
                    calcularTotalVenda();
                });
            });
            
            // Cancelar venda
            const btnCancelarVenda = document.getElementById('cancelar-venda');
            btnCancelarVenda.addEventListener('click', function() {
                document.getElementById('id_venda').value = '';
                document.getElementById('venda-cliente').selectedIndex = 0;
                document.getElementById('venda-data').value = new Date().toISOString().split('T')[0];
                document.getElementById('venda-status').selectedIndex = 0;
                
                // Remover todos os itens exceto o primeiro
                const itens = document.querySelectorAll('.venda-item');
                for (let i = 1; i < itens.length; i++) {
                    itens[i].remove();
                }
                
                // Limpar o primeiro item
                const primeiroItem = document.querySelector('.venda-item');
                primeiroItem.querySelector('select').selectedIndex = 0;
                primeiroItem.querySelector('.quantidade-input').value = '1';
                primeiroItem.querySelector('.preco-input').value = '';
                primeiroItem.querySelector('.subtotal-input').value = '';
                
                // Recalcular total
                calcularTotalVenda();
            });
            
            // Cancelar pagamento
            const btnCancelarPagamento = document.getElementById('cancelar-pagamento');
            btnCancelarPagamento.addEventListener('click', function() {
                document.getElementById('pagamento-venda').selectedIndex = 0;
                document.getElementById('pagamento-data').value = new Date().toISOString().split('T')[0];
                document.getElementById('pagamento-valor').value = '';
                document.getElementById('pagamento-forma').selectedIndex = 0;
                document.getElementById('pagamento-observacoes').value = '';
            });
            
            // Relatórios
            const btnGerarRelatorio = document.getElementById('btn-gerar-relatorio');
            btnGerarRelatorio.addEventListener('click', function() {
                const form = document.getElementById('form-relatorio');
                const formData = new FormData(form);
                
                fetch('gerar_relatorio.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    exibirRelatorio(data);
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao gerar relatório');
                });
            });
            
            // Função para exibir o relatório
            function exibirRelatorio(dados) {
                const resultadoDiv = document.getElementById('relatorio-resultado');
                const tituloH3 = document.getElementById('relatorio-titulo');
                const resumoDiv = document.getElementById('relatorio-resumo');
                const tabelaRelatorio = document.getElementById('tabela-relatorio');
                const theadRelatorio = tabelaRelatorio.querySelector('thead');
                const tbodyRelatorio = tabelaRelatorio.querySelector('tbody');
                
                // Mostrar a div de resultados
                resultadoDiv.style.display = 'block';
                
                // Definir título com base no tipo
                let titulo = 'Relatório';
                switch (dados.tipo) {
                    case 'vendas_periodo':
                        titulo = 'Vendas por Período';
                        break;
                    case 'produtos_mais_vendidos':
                        titulo = 'Produtos Mais Vendidos';
                        break;
                    case 'clientes_fieis':
                        titulo = 'Clientes Mais Fiéis';
                        break;
                    case 'pagamentos_forma':
                        titulo = 'Pagamentos por Forma';
                        break;
                    case 'vendas_status':
                        titulo = 'Vendas por Status';
                        break;
                }
                tituloH3.textContent = titulo;
                
                // Preencher resumo
                let resumoHTML = '';
                if (dados.valor_total) {
                    resumoHTML += `<p><strong>Valor Total:</strong> R$ ${formatarNumero(dados.valor_total)}</p>`;
                }
                if (dados.total_vendas) {
                    resumoHTML += `<p><strong>Total de Vendas:</strong> ${dados.total_vendas}</p>`;
                }
                if (dados.total_clientes) {
                    resumoHTML += `<p><strong>Total de Clientes:</strong> ${dados.total_clientes}</p>`;
                }
                if (dados.total_pagamentos) {
                    resumoHTML += `<p><strong>Total de Pagamentos:</strong> ${dados.total_pagamentos}</p>`;
                }
                if (dados.total_produtos) {
                    resumoHTML += `<p><strong>Total de Produtos:</strong> ${dados.total_produtos}</p>`;
                }
                
                resumoDiv.innerHTML = resumoHTML;
                
                // Limpar tabela anterior
                theadRelatorio.innerHTML = '';
                tbodyRelatorio.innerHTML = '';
                
                // Criar cabeçalho da tabela
                const headerRow = document.createElement('tr');
                
                // Criar colunas com base no tipo de relatório
                let colunas = [];
                switch (dados.tipo) {
                    case 'vendas_periodo':
                        colunas = ['Data', 'Total de Vendas', 'Valor Total'];
                        break;
                    case 'produtos_mais_vendidos':
                        colunas = ['Produto', 'Quantidade', 'Valor Total'];
                        break;
                    case 'clientes_fieis':
                        colunas = ['Cliente', 'Total de Vendas', 'Valor Total'];
                        break;
                    case 'pagamentos_forma':
                        colunas = ['Forma de Pagamento', 'Total de Pagamentos', 'Valor Total'];
                        break;
                    case 'vendas_status':
                        colunas = ['Status', 'Total de Vendas', 'Valor Total'];
                        break;
                }
                
                colunas.forEach(coluna => {
                    const th = document.createElement('th');
                    th.textContent = coluna;
                    headerRow.appendChild(th);
                });
                
                theadRelatorio.appendChild(headerRow);
                
                // Preencher corpo da tabela
                dados.dados.forEach(item => {
                    const row = document.createElement('tr');
                    
                    switch (dados.tipo) {
                        case 'vendas_periodo':
                            row.innerHTML = `
                                <td>${formatarData(item.data)}</td>
                                <td>${item.total_vendas}</td>
                                <td>R$ ${formatarNumero(item.valor_total)}</td>
                            `;
                            break;
                        case 'produtos_mais_vendidos':
                            row.innerHTML = `
                                <td>${item.nome}</td>
                                <td>${item.quantidade_total}</td>
                                <td>R$ ${formatarNumero(item.valor_total)}</td>
                            `;
                            break;
                        case 'clientes_fieis':
                            row.innerHTML = `
                                <td>${item.nome}</td>
                                <td>${item.total_vendas}</td>
                                <td>R$ ${formatarNumero(item.valor_total)}</td>
                            `;
                            break;
                        case 'pagamentos_forma':
                            row.innerHTML = `
                                <td>${formatarFormaPagamento(item.forma_pagamento)}</td>
                                <td>${item.total_pagamentos}</td>
                                <td>R$ ${formatarNumero(item.valor_total)}</td>
                            `;
                            break;
                        case 'vendas_status':
                            row.innerHTML = `
                                <td>${formatarStatus(item.status)}</td>
                                <td>${item.total_vendas}</td>
                                <td>R$ ${formatarNumero(item.valor_total)}</td>
                            `;
                            break;
                    }
                    
                    tbodyRelatorio.appendChild(row);
                });
                
                // Gerar gráfico se houver dados para isso
                if (dados.grafico) {
                    gerarGrafico(dados);
                }
            }
            
            // Função para gerar gráfico
            function gerarGrafico(dados) {
                const ctx = document.getElementById('grafico-relatorio').getContext('2d');
                
                // Destruir gráfico anterior se existir
                if (window.graficoRelatorio) {
                    window.graficoRelatorio.destroy();
                }
                
                // Definir tipo de gráfico com base no tipo de relatório
                let tipo = 'bar';
                if (dados.tipo === 'pagamentos_forma' || dados.tipo === 'vendas_status') {
                    tipo = 'pie';
                }
                
                // Criar gráfico
                window.graficoRelatorio = new Chart(ctx, {
                    type: tipo,
                    data: dados.grafico,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
            
            // Função para formatar número
            function formatarNumero(numero) {
                return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numero);
            }
            
            // Função para formatar data
            function formatarData(data) {
                const partes = data.split('-');
                if (partes.length === 3) {
                    return `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
                return data;
            }
            
            // Função para formatar forma de pagamento
            function formatarFormaPagamento(forma) {
                const formas = {
                    'dinheiro': 'Dinheiro',
                    'cartao_debito': 'Cartão Débito',
                    'cartao_credito': 'Cartão Crédito',
                    'pix': 'PIX',
                    'transferencia': 'Transferência'
                };
                return formas[forma] || forma;
            }
            
            // Função para formatar status
            function formatarStatus(status) {
                const statusMap = {
                    'pendente': 'Pendente',
                    'pago': 'Pago',
                    'cancelado': 'Cancelado'
                };
                return statusMap[status] || status;
            }
        });
        
        // Função para adicionar eventos a um item de venda
        function addEventosItemVenda(item) {
            const select = item.querySelector('.produto-select');
            const qntInput = item.querySelector('.quantidade-input');
            const precoInput = item.querySelector('.preco-input');
            const subtotalInput = item.querySelector('.subtotal-input');
            
            // Evento para quando o produto é selecionado
            select.addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                if (option.value) {
                    const preco = option.getAttribute('data-preco');
                    precoInput.value = formatarNumero(preco);
                    calcularSubtotal(qntInput, precoInput, subtotalInput);
                } else {
                    precoInput.value = '';
                    subtotalInput.value = '';
                }
            });
            
            // Evento para quando a quantidade é alterada
            qntInput.addEventListener('input', function() {
                calcularSubtotal(qntInput, precoInput, subtotalInput);
            });
            
            // Se já tiver um produto selecionado, calcular o subtotal
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const preco = option.getAttribute('data-preco');
                precoInput.value = formatarNumero(preco);
                calcularSubtotal(qntInput, precoInput, subtotalInput);
            }
        }
        
        // Função para calcular o subtotal de um item
        function calcularSubtotal(qntInput, precoInput, subtotalInput) {
            const quantidade = parseInt(qntInput.value) || 0;
            const preco = parseFloat(precoInput.value.replace('.', '').replace(',', '.')) || 0;
            
            const subtotal = quantidade * preco;
            subtotalInput.value = formatarNumero(subtotal);
            
            calcularTotalVenda();
        }
        
        // Função para calcular o total da venda
        function calcularTotalVenda() {
            const subtotais = document.querySelectorAll('.subtotal-input');
            let total = 0;
            
            subtotais.forEach(input => {
                const valor = parseFloat(input.value.replace('.', '').replace(',', '.')) || 0;
                total += valor;
            });
            
            document.getElementById('total-valor').textContent = formatarNumero(total);
        }
        
        // Função para formatar número
        function formatarNumero(numero) {
            return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numero);
        }
        
        // Função para editar cliente
        function editarCliente(id) {
            fetch(`obter_cliente.php?id=${id}`)
                .then(response => response.json())
                .then(cliente => {
                    document.getElementById('id_cliente').value = cliente.id_cliente;
                    document.getElementById('nome').value = cliente.nome;
                    document.getElementById('telefone').value = cliente.telefone;
                    document.getElementById('email').value = cliente.email;
                    document.getElementById('endereco').value = cliente.endereco;
                    document.getElementById('observacoes').value = cliente.observacoes;
                    
                    // Rolagem suave até o formulário
                    document.getElementById('cliente-form').scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao obter dados do cliente');
                });
        }
        
        // Função para ver vendas de um cliente
        function verVendasCliente(id) {
            // Mostrar a aba de vendas
            const navLinks = document.querySelectorAll('nav a');
            const contentDivs = document.querySelectorAll('.content');
            
            contentDivs.forEach(div => {
                div.style.display = 'none';
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById('vendas').style.display = 'block';
            document.getElementById('nav-vendas').classList.add('active');
            
            // Filtrar as vendas para mostrar apenas as do cliente selecionado
            // Aqui precisaria de uma implementação adicional para filtrar as vendas
            alert('Funcionalidade não implementada: Filtrar vendas do cliente ' + id);
        }
        
        // Função para editar produto
        function editarProduto(id) {
            fetch(`obter_produto.php?id=${id}`)
                .then(response => response.json())
                .then(produto => {
                    document.getElementById('id_produto').value = produto.id_produto;
                    document.getElementById('nome_produto').value = produto.nome;
                    document.getElementById('descricao').value = produto.descricao;
                    document.getElementById('preco').value = formatarNumero(produto.preco);
                    document.getElementById('estoque').value = produto.estoque;
                    document.getElementById('ativo').checked = produto.ativo == 1;
                    
                    // Rolagem suave até o formulário
                    document.getElementById('produto-form').scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao obter dados do produto');
                });
        }
        
        // Função para ver detalhes da venda
        function verVenda(id) {
            fetch(`detalhes_venda.php?id=${id}`)
                .then(response => response.json())
                .then(venda => {
                    if (venda.error) {
                        alert(venda.error);
                        return;
                    }
                    
                    const detalhes = `
                        Venda #${venda.id_venda}
                        Data: ${formatarData(venda.data_venda)}
                        Cliente: ${venda.cliente}
                        Valor Total: R$ ${formatarNumero(venda.valor_total)}
                        Status: ${formatarStatus(venda.status)}
                        
                        Itens:
                        ${venda.itens.map(item => `- ${item.produto}: ${item.quantidade} x R$ ${formatarNumero(item.preco_unitario)} = R$ ${formatarNumero(item.quantidade * item.preco_unitario)}`).join('\n')}
                    `;
                    
                    alert(detalhes);
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao obter detalhes da venda');
                });
        }
        
        // Função para editar venda
        function editarVenda(id) {
            fetch(`obter_venda.php?id=${id}`)
                .then(response => response.json())
                .then(venda => {
                    document.getElementById('id_venda').value = venda.id_venda;
                    
                    // Selecionar cliente
                    const selectCliente = document.getElementById('venda-cliente');
                    for (let i = 0; i < selectCliente.options.length; i++) {
                        if (selectCliente.options[i].value == venda.id_cliente) {
                            selectCliente.selectedIndex = i;
                            break;
                        }
                    }
                    
                    // Definir data
                    document.getElementById('venda-data').value = venda.data_venda.split(' ')[0];
                    
                    // Definir status
                    const selectStatus = document.getElementById('venda-status');
                    for (let i = 0; i < selectStatus.options.length; i++) {
                        if (selectStatus.options[i].value === venda.status) {
                            selectStatus.selectedIndex = i;
                            break;
                        }
                    }
                    
                    // Remover todos os itens existentes
                    const itensContainer = document.getElementById('itens-container');
                    while (itensContainer.firstChild) {
                        itensContainer.removeChild(itensContainer.firstChild);
                    }
                    
                    // Adicionar os itens da venda
                    venda.itens.forEach(item => {
                        // Clonar o template do item
                        const itemTemplate = document.createElement('div');
                        itemTemplate.className = 'venda-item';
                        itemTemplate.innerHTML = `
                            <div class="form-group">
                                <label>Produto:</label>
                                <select name="produto[]" class="produto-select" required>
                                    <option value="">Selecione...</option>
                                    ${Array.from(document.querySelectorAll('#vendas select.produto-select:first-child option')).map(opt => {
                                        return `<option value="${opt.value}" data-preco="${opt.getAttribute('data-preco')}" ${opt.value == item.id_produto ? 'selected' : ''}>${opt.textContent}</option>`;
                                    }).join('')}
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Quantidade:</label>
                                <input type="number" name="quantidade[]" class="quantidade-input" value="${item.quantidade}" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Preço Unitário (R$):</label>
                                <input type="text" name="preco[]" class="preco-input" value="${formatarNumero(item.preco_unitario)}" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Subtotal (R$):</label>
                                <input type="text" class="subtotal-input" value="${formatarNumero(item.quantidade * item.preco_unitario)}" readonly>
                            </div>
                            
                            <span class="remove-item">&times;</span>
                        `;
                        
                        // Adicionar evento para remover item
                        const btnRemove = itemTemplate.querySelector('.remove-item');
                        btnRemove.addEventListener('click', function() {
                            itemTemplate.remove();
                            calcularTotalVenda();
                        });
                        
                        // Adicionar eventos aos campos
                        addEventosItemVenda(itemTemplate);
                        
                        itensContainer.appendChild(itemTemplate);
                    });
                    
                    // Atualizar o formulário para usar atualizar_venda.php
                    document.getElementById('form-venda').action = 'atualizar_venda.php';
                    
                    // Calcular total
                    calcularTotalVenda();
                    
                    // Rolagem suave até o formulário
                    document.getElementById('venda-form').scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao obter dados da venda');
                });
        }
        
        // Função para formatar data
        function formatarData(data) {
            if (!data) return '';
            const partes = data.split(/[- :]/);
            if (partes.length >= 3) {
                return `${partes[2]}/${partes[1]}/${partes[0]}`;
            }
            return data;
        }
        
        // Função para formatar status
        function formatarStatus(status) {
            const statusMap = {
                'pendente': 'Pendente',
                'pago': 'Pago',
                'cancelado': 'Cancelado'
            };
            return statusMap[status] || status;
        }
        // Função para confirmar e excluir produto
        function confirmarExclusaoProduto(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o produto "${nome}"?\nEssa ação não poderá ser desfeita.`)) {
                excluirProduto(id);
            }
        }
        
        function excluirProduto(id) {
            fetch(`excluir_produto.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        
                        // Recarregar a página para atualizar a lista
                        location.reload();
                    } else {
                        alert("Erro: " + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir produto.');
                });
        }
    </script>
</body>
</html>
