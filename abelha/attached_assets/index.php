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

    // Consulta para total de vendas hoje
    $sql = "SELECT SUM(valor_total) as total FROM vendas WHERE DATE(data_venda) = CURDATE()";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $totalVendas = $row['total'] ? number_format($row['total'], 2, ',', '.') : '0,00';
    }

    // Consulta para pagamentos
$pagamentos = [];
$sqlPagamentos = "SELECT p.*, v.id_venda, c.nome as cliente 
                 FROM pagamentos p
                 JOIN vendas v ON p.id_venda = v.id_venda
                 JOIN clientes c ON v.id_cliente = c.id_cliente
                 ORDER BY p.data_pagamento DESC";
$resultPagamentos = $conn->query($sqlPagamentos);
if ($resultPagamentos) {
    while ($row = $resultPagamentos->fetch_assoc()) {
        $pagamentos[] = $row;
    }
}

// Consulta para vendas pendentes (para o select)
$vendasPendentes = [];
$sqlVendasPendentes = "SELECT v.id_venda, c.nome as cliente, v.valor_total 
                      FROM vendas v
                      JOIN clientes c ON v.id_cliente = c.id_cliente
                      WHERE v.status = 'pendente' 
                      OR v.valor_total > (SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE id_venda = v.id_venda)";
$resultVendasPendentes = $conn->query($sqlVendasPendentes);
if ($resultVendasPendentes) {
    while ($row = $resultVendasPendentes->fetch_assoc()) {
        $vendasPendentes[] = $row;
    }
}
    // Consulta para total de clientes
    $sql = "SELECT COUNT(*) as total FROM clientes";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $totalClientes = $row['total'];
    }

    // Adicione esta consulta junto com as outras consultas no início do arquivo
$vendas = [];
$sqlVendas = "SELECT v.id_venda, v.data_venda, c.nome as cliente, v.valor_total, v.status 
              FROM vendas v
              JOIN clientes c ON v.id_cliente = c.id_cliente
              ORDER BY v.data_venda DESC";
$resultVendas = $conn->query($sqlVendas);
if ($resultVendas) {
    while ($row = $resultVendas->fetch_assoc()) {
        $vendas[] = $row;
    }
}

    // Adicione esta consulta junto com as outras consultas no início do arquivo
$clientesVenda = [];
$sqlClientes = "SELECT id_cliente, nome FROM clientes ORDER BY nome";
$resultClientes = $conn->query($sqlClientes);
if ($resultClientes) {
    while ($row = $resultClientes->fetch_assoc()) {
        $clientesVenda[] = $row;
    }
}

// Consulta para produtos também (necessário para os itens da venda)
$produtosVenda = [];
$sqlProdutos = "SELECT id_produto, nome, preco FROM produtos WHERE ativo = 1 ORDER BY nome";
$resultProdutos = $conn->query($sqlProdutos);
if ($resultProdutos) {
    while ($row = $resultProdutos->fetch_assoc()) {
        $produtosVenda[] = $row;
    }
}
    // Consulta para pagamentos pendentes
    $sql = "SELECT SUM(valor_total) as total FROM vendas WHERE status = 'pendente'";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $totalPendentes = $row['total'] ? number_format($row['total'], 2, ',', '.') : '0,00';
    }

    // Adicione esta consulta junto com as outras consultas no início do arquivo
$produtos = [];
$sql = "SELECT id_produto, nome, preco, estoque, ativo FROM produtos";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
}

    $conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abelhinha Doce - Sistema de Gerenciamento</title>
    <style>

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
        /* Seu CSS permanece exatamente igual */
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
        /* ... (mantenha todo o restante do CSS igual) ... */
    </style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
 
</script>


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
        
        <div class="content" id="clientes" style="display: none;">
            <h2>Gerenciamento de Clientes</h2>
            
            <?php if (!empty($mensagem)): ?>
            <div class="alert alert-success">
                <?php echo $mensagem; ?>
            </div>
            <?php endif; ?>
            
            <form id="cliente-form" method="POST" action="salvar_cliente.php">
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
                    <?php
                    $clientes = getClientes();
                    foreach ($clientes as $cliente): 
                    ?>
                    <tr>
                        <td><?php echo $cliente['id_cliente']; ?></td>
                        <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></td>
                        <td class="actions">
                            <a href="#" onclick="editarCliente(<?php echo $cliente['id_cliente']; ?>)">Editar</a>
                            <a href="vendas.php?cliente=<?php echo $cliente['id_cliente']; ?>">Vendas</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="content" id="produtos" style="display: none;">
    <h2>Gerenciamento de Produtos</h2>
    
    <?php if (!empty($mensagem)): ?>
    <div class="alert alert-success">
        <?php echo $mensagem; ?>
    </div>
    <?php endif; ?>
    
    <!-- FORMULÁRIO CORRIGIDO - Adicione method e action -->
    <form id="produto-form" method="POST" action="salvar_produto.php">
        <input type="hidden" id="id_produto" name="id_produto" value="">
        
        <div class="form-group">
            <label for="produto-nome">Nome do Produto:</label>
            <input type="text" id="produto-nome" name="nome" required>
        </div>
        
        <div class="form-group">
            <label for="produto-descricao">Descrição:</label>
            <textarea id="produto-descricao" name="descricao" rows="3"></textarea>
        </div>
        
        <div class="form-group">
            <label for="produto-preco">Preço (R$):</label>
            <input type="number" id="produto-preco" name="preco" step="0.01" min="0" required>
        </div>
        
        <div class="form-group">
            <label for="produto-estoque">Estoque:</label>
            <input type="number" id="produto-estoque" name="estoque" min="0" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="produto-ativo" name="ativo" value="1" checked> Ativo
            </label>
        </div>
        
        <!-- BOTÃO CORRIGIDO - Mude para type="submit" -->
        <button type="submit" id="salvar-produto">Salvar Produto</button>
        <button type="button" id="cancelar-produto" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
    </form>
    
    <!-- Restante do seu código... -->
</div>
            
            <div style="margin-top: 30px;">
                <input type="text" id="busca-produto" placeholder="Buscar produto..." style="padding: 8px; width: 300px;">
                <button type="button">Buscar</button>
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
            <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
            <td><?php echo $produto['estoque']; ?></td>
            <td><?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
            <td class="actions">
                <a href="#" onclick="editarProduto(<?php echo $produto['id_produto']; ?>)">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
        
        <div class="content" id="vendas" style="display: none;">
    <h2>Registro de Vendas</h2>
    
    <form id="venda-form" method="POST" action="atualizar_venda.php">
        <div class="form-group">
            <label for="venda-cliente">Cliente:</label>
            <select id="venda-cliente" name="venda-cliente" required>
                <option value="">Selecione um cliente</option>
                <?php foreach ($clientesVenda as $cliente): ?>
                <option value="<?php echo $cliente['id_cliente']; ?>">
                    <?php echo htmlspecialchars($cliente['nome']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="venda-data">Data:</label>
            <input type="date" id="venda-data" name="venda-data" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <h3 style="margin: 20px 0 10px 0;">Itens da Venda</h3>
        
        <div id="itens-venda">
            <div class="item-venda" style="display: flex; gap: 10px; margin-bottom: 10px;">
                <select name="produto[]" style="flex: 3;" class="produto-select" required>
                    <option value="">Selecione um produto</option>
                    <?php foreach ($produtosVenda as $produto): ?>
                    <option value="<?php echo $produto['id_produto']; ?>" data-preco="<?php echo $produto['preco']; ?>">
                        <?php echo htmlspecialchars($produto['nome']); ?> - R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantidade[]" placeholder="Qtd" min="1" value="1" style="flex: 1;" class="quantidade" required>
                <input type="text" name="preco[]" placeholder="Preço" style="flex: 1;" class="preco-item" readonly>
                <button type="button" class="remover-item" style="background-color: #ff6b6b;">×</button>
            </div>
        </div>
        
        <button type="button" id="adicionar-item" style="margin-bottom: 20px;">+ Adicionar Item</button>
        
        <div class="form-group">
            <label for="venda-total">Total:</label>
            <input type="text" id="venda-total" name="venda-total" value="R$ 0,00" readonly>
        </div>
        
        <div class="form-group">
            <label for="venda-status">Status:</label>
            <select id="venda-status" name="venda-status" required>
                <option value="pendente">Pendente</option>
                <option value="pago">Pago</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>
        
        <button type="submit" id="salvar-venda">Salvar Venda</button>
        <button type="button" id="cancelar-venda" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
    </form>
    
    <!-- Restante do código da tabela de vendas... -->
</div>
            </div>
            
            <div style="margin-top: 30px;">
                <input type="text" id="busca-venda" placeholder="Buscar venda..." style="padding: 8px; width: 300px;">
                <button type="button">Buscar</button>
            </div>
            
            <table id="tabela-vendas">
    <thead>
        <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Cliente</th>
            <th>Total</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vendas as $venda): ?>
        <tr>
            <td><?php echo $venda['id_venda']; ?></td>
            <td><?php echo date('d/m/Y', strtotime($venda['data_venda'])); ?></td>
            <td><?php echo htmlspecialchars($venda['cliente']); ?></td>
            <td>R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></td>
            <td>
                <span class="status-badge <?php echo $venda['status']; ?>">
                    <?php echo ucfirst($venda['status']); ?>
                </span>
            </td>
            <td class="actions">
                <a href="#" onclick="detalhesVenda(<?php echo $venda['id_venda']; ?>)">Detalhes</a>
                <a href="#" onclick="editarVenda(<?php echo $venda['id_venda']; ?>)">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
        
        <div class="content" id="pagamentos" style="display: none;">
    <h2>Registro de Pagamentos</h2>
    
    <?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo strpos($mensagem, 'sucesso') !== false ? 'success' : 'error'; ?>">
        <?php echo $mensagem; ?>
    </div>
    <?php endif; ?>
    
    <form id="pagamento-form" method="POST" action="salvar_pagamento.php">
        <div class="form-group">
            <label for="pagamento-venda">Venda:</label>
            <select id="pagamento-venda" name="pagamento-venda" required>
                <option value="">Selecione uma venda</option>
                <?php foreach ($vendasPendentes as $venda): 
                    $valorPago = getValorPago($venda['id_venda']);
                    $valorPendente = $venda['valor_total'] - $valorPago;
                ?>
                <option value="<?php echo $venda['id_venda']; ?>" data-valor-pendente="<?php echo $valorPendente; ?>">
                    Venda #<?php echo $venda['id_venda']; ?> - <?php echo htmlspecialchars($venda['cliente']); ?> 
                    (Total: R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?> | 
                    Pendente: R$ <?php echo number_format($valorPendente, 2, ',', '.'); ?>)
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
            <input type="number" id="pagamento-valor" name="pagamento-valor" step="0.01" min="0.01" required>
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
        
        <button type="submit" id="salvar-pagamento">Salvar Pagamento</button>
        <button type="button" id="cancelar-pagamento" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
    </form>
    
    <div style="margin-top: 30px;">
        <input type="text" id="busca-pagamento" placeholder="Buscar pagamento..." style="padding: 8px; width: 300px;">
        <button type="button" id="btn-buscar-pagamento">Buscar</button>
    </div>
    
    <table id="tabela-pagamentos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Data</th>
                <th>Venda</th>
                <th>Cliente</th>
                <th>Valor</th>
                <th>Forma</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagamentos as $pagamento): ?>
            <tr>
                <td><?php echo $pagamento['id_pagamento']; ?></td>
                <td><?php echo date('d/m/Y', strtotime($pagamento['data_pagamento'])); ?></td>
                <td>Venda #<?php echo $pagamento['id_venda']; ?></td>
                <td><?php echo htmlspecialchars($pagamento['cliente']); ?></td>
                <td>R$ <?php echo number_format($pagamento['valor'], 2, ',', '.'); ?></td>
                <td><?php echo formatFormaPagamento($pagamento['forma_pagamento']); ?></td>
                <td class="actions">
                    <a href="#" onclick="editarPagamento(<?php echo $pagamento['id_pagamento']; ?>)">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        
<div class="content" id="relatorios" style="display: none;">
    <h2>Relatórios</h2>
    
    <div class="filtros-relatorio">
        <div class="form-group">
            <label for="relatorio-tipo">Tipo de Relatório:</label>
            <select id="relatorio-tipo" name="relatorio-tipo" class="form-control">
                <option value="vendas_periodo">Vendas por Período</option>
                <option value="produtos_mais_vendidos">Produtos Mais Vendidos</option>
                <option value="clientes_fieis">Clientes Fiéis</option>
                <option value="pagamentos_forma">Pagamentos por Forma</option>
                <option value="vendas_status">Vendas por Status</option>
            </select>
        </div>
        
        <div id="filtros-adicionais">
            <!-- Filtros dinâmicos serão inseridos aqui -->
        </div>
        
        <button type="button" id="gerar-relatorio" class="btn btn-primary">Gerar Relatório</button>
        <button type="button" id="exportar-relatorio" class="btn btn-secondary">Exportar para Excel</button>
    </div>
    
    <div id="resultado-relatorio" class="mt-4">
        <div class="card">
            <div class="card-body">
                <div class="alert alert-info">
                    Selecione o tipo de relatório e os filtros desejados, então clique em "Gerar Relatório".
                </div>
            </div>
        </div>
    </div>
</div>
    
        <!-- Restante do seu HTML (produtos, vendas, pagamentos, relatórios) -->
        <!-- ... (mantenha as outras seções como estão) ... -->
        
    </div>
    
    <footer>
        <p>Confeitaria Abelhinha Doce &copy; <?php echo date('Y'); ?> - Sistema de Gerenciamento</p>
    </footer>
    
    <script>
        // Navegação entre páginas
        document.querySelectorAll('nav ul li a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove a classe active de todos os links
                document.querySelectorAll('nav ul li a').forEach(l => l.classList.remove('active'));
                
                // Adiciona a classe active ao link clicado
                this.classList.add('active');
                
                // Oculta todos os conteúdos
                document.querySelectorAll('.content').forEach(content => {
                    content.style.display = 'none';
                });
                
                // Mostra o conteúdo correspondente
                const target = this.id.replace('nav-', '');
                document.getElementById(target).style.display = 'block';
            });
        });
        function editarProduto(id) {
    fetch('obter_produto.php?id=' + id)
        .then(response => response.json())
        .then(produto => {
            document.getElementById('id_produto').value = produto.id_produto;
            document.getElementById('produto-nome').value = produto.nome;
            document.getElementById('produto-descricao').value = produto.descricao;
            document.getElementById('produto-preco').value = produto.preco;
            document.getElementById('produto-estoque').value = produto.estoque;
            document.getElementById('produto-ativo').checked = produto.ativo == 1;
            
            document.getElementById('produto-form').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => console.error('Erro:', error));
}
        // Função para editar cliente
        function editarCliente(id) {
            fetch('obter_cliente.php?id=' + id)
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
                .catch(error => console.error('Erro:', error));
        }
        
        // Busca de clientes
        // Melhoria na busca de clientes (adicionar busca em tempo real)
document.getElementById('busca-cliente').addEventListener('input', function() {
    const termo = this.value.toLowerCase();
    const linhas = document.querySelectorAll('#tabela-clientes tbody tr');
    
    linhas.forEach(linha => {
        const textoLinha = linha.textContent.toLowerCase();
        linha.style.display = textoLinha.includes(termo) ? '' : 'none';
    });
    document.getElementById('busca-produto').addEventListener('input', function() {
    const termo = this.value.toLowerCase();
    const linhas = document.querySelectorAll('#tabela-produtos tbody tr');
    
    linhas.forEach(linha => {
        const textoLinha = linha.textContent.toLowerCase();
        linha.style.display = textoLinha.includes(termo) ? '' : 'none';
    });
});
});

// Adicionar máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.substring(0, 11);
    
    if (value.length > 0) {
        value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
        if (value.length > 10) {
            value = value.replace(/(\d)(\d{4})$/, '$1-$2');
        } else {
            value = value.replace(/(\d)(\d{3})$/, '$1-$2');
        }
    }
    e.target.value = value;
});
// Adicionar novo item à venda
document.getElementById('adicionar-item').addEventListener('click', function() {
    const novoItem = document.querySelector('.item-venda').cloneNode(true);
    novoItem.querySelector('.produto-select').value = '';
    novoItem.querySelector('.quantidade').value = 1;
    novoItem.querySelector('.preco-item').value = '';
    document.getElementById('itens-venda').appendChild(novoItem);
    atualizarTotal();
});

// Remover item da venda
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remover-item')) {
        if (document.querySelectorAll('.item-venda').length > 1) {
            e.target.closest('.item-venda').remove();
            atualizarTotal();
        } else {
            alert('A venda deve ter pelo menos um item!');
        }
    }
});

// Atualizar preço quando produto ou quantidade mudar
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('produto-select') || e.target.classList.contains('quantidade')) {
        const item = e.target.closest('.item-venda');
        const produtoSelect = item.querySelector('.produto-select');
        const quantidade = item.querySelector('.quantidade');
        const precoItem = item.querySelector('.preco-item');
        
        if (produtoSelect.value) {
            const precoUnitario = produtoSelect.options[produtoSelect.selectedIndex].dataset.preco;
            const totalItem = parseFloat(precoUnitario) * parseInt(quantidade.value);
            precoItem.value = totalItem.toFixed(2).replace('.', ',');
        } else {
            precoItem.value = '';
        }
        
        atualizarTotal();
    }
});

function detalhesVenda(id) {
    fetch('detalhes_venda.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            // Implemente um modal ou página de detalhes
            alert('Detalhes da Venda #' + id + '\nCliente: ' + data.cliente + 
                  '\nTotal: R$ ' + data.total + '\nStatus: ' + data.status);
        })
        .catch(error => console.error('Erro:', error));
}

function editarVenda(id) {
    // Mostrar loading
    const vendaForm = document.getElementById('venda-form');
    vendaForm.innerHTML = '<div class="text-center">Carregando venda...</div>';

    // Obter dados da venda
    fetch(`obter_venda.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Preencher formulário com os dados da venda
            preencherFormularioVenda(data.venda, data.itens);
            
            // Adicionar hidden field para o ID da venda
            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id_venda';
            idField.value = id;
            vendaForm.prepend(idField);
            
            // Rolagem suave até o formulário
            vendaForm.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Erro:', error);
            vendaForm.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
        });
}

function preencherFormularioVenda(venda, itens) {
    // Limpar formulário
    const vendaForm = document.getElementById('venda-form');
    vendaForm.innerHTML = `
        <input type="hidden" name="id_venda" value="${venda.id_venda}">
        
        <div class="form-group">
            <label for="venda-cliente">Cliente:</label>
            <select id="venda-cliente" name="venda-cliente" required>
                <option value="">Selecione um cliente</option>
                ${document.getElementById('venda-cliente').innerHTML.split('<option value="">Selecione um cliente</option>')[1]}
            </select>
        </div>
        
        <div class="form-group">
            <label for="venda-data">Data:</label>
            <input type="date" id="venda-data" name="venda-data" required>
        </div>
        
        <h3 style="margin: 20px 0 10px 0;">Itens da Venda</h3>
        
        <div id="itens-venda"></div>
        
        <button type="button" id="adicionar-item" style="margin-bottom: 20px;">+ Adicionar Item</button>
        
        <div class="form-group">
            <label for="venda-total">Total:</label>
            <input type="text" id="venda-total" name="venda-total" readonly>
        </div>
        
        <div class="form-group">
            <label for="venda-status">Status:</label>
            <select id="venda-status" name="venda-status" required>
                <option value="pendente">Pendente</option>
                <option value="pago">Pago</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>
        
        <button type="submit" id="salvar-venda">Atualizar Venda</button>
        <button type="button" id="cancelar-venda" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
    `;

    // Preencher dados básicos
    document.getElementById('venda-cliente').value = venda.id_cliente;
    document.getElementById('venda-data').value = venda.data_venda.split(' ')[0];
    document.getElementById('venda-status').value = venda.status;
    
    // Adicionar itens
    const itensContainer = document.getElementById('itens-venda');
    itens.forEach((item, index) => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'item-venda';
        itemDiv.style = 'display: flex; gap: 10px; margin-bottom: 10px;';
        
        itemDiv.innerHTML = `
            <select name="produto[]" style="flex: 3;" class="produto-select" required>
                <option value="">Selecione um produto</option>
                ${document.querySelector('.produto-select').innerHTML.split('<option value="">Selecione um produto</option>')[1]}
            </select>
            <input type="number" name="quantidade[]" placeholder="Qtd" min="1" value="${item.quantidade}" style="flex: 1;" class="quantidade" required>
            <input type="text" name="preco[]" placeholder="Preço" value="${item.preco_unitario.toFixed(2)}" style="flex: 1;" class="preco-item" readonly>
            <button type="button" class="remover-item" style="background-color: #ff6b6b;">×</button>
        `;
        
        // Selecionar o produto correto
        const select = itemDiv.querySelector('.produto-select');
        select.value = item.id_produto;
        
        itensContainer.appendChild(itemDiv);
    });
    
    // Calcular total
    atualizarTotal();
    
    // Reativar eventos
    ativarEventosVenda();
}

// Função para calcular o total da venda
function atualizarTotal() {
    let total = 0;
    document.querySelectorAll('.preco-item').forEach(input => {
        if (input.value) {
            total += parseFloat(input.value.replace(',', '.'));
        }
    });
    
    // Preenche automaticamente o valor pendente
document.getElementById('pagamento-venda').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        const valorPendente = parseFloat(selectedOption.dataset.valorPendente);
        document.getElementById('pagamento-valor').value = valorPendente.toFixed(2);
        document.getElementById('pagamento-valor').max = valorPendente;
    }
});

// Busca de pagamentos
document.getElementById('btn-buscar-pagamento').addEventListener('click', function() {
    const termo = document.getElementById('busca-pagamento').value.toLowerCase();
    const linhas = document.querySelectorAll('#tabela-pagamentos tbody tr');
    
    linhas.forEach(linha => {
        const textoLinha = linha.textContent.toLowerCase();
        linha.style.display = textoLinha.includes(termo) ? '' : 'none';
    });
});
// Controle dos relatórios
document.getElementById('relatorio-tipo').addEventListener('change', function() {
    const tipoRelatorio = this.value;
    const filtrosDiv = document.getElementById('filtros-adicionais');
    
    let htmlFiltros = '';
    
    // Adiciona filtros comuns a vários relatórios
    htmlFiltros += `
        <div class="row">
            <div class="col-md-4 form-group">
                <label for="data-inicio">Data Início:</label>
                <input type="date" id="data-inicio" name="data-inicio" class="form-control">
            </div>
            <div class="col-md-4 form-group">
                <label for="data-fim">Data Fim:</label>
                <input type="date" id="data-fim" name="data-fim" class="form-control">
            </div>`;
    
    // Filtros específicos para cada tipo de relatório
    if (tipoRelatorio === 'produtos_mais_vendidos') {
        htmlFiltros += `
            <div class="col-md-4 form-group">
                <label for="limite-produtos">Quantidade de Produtos:</label>
                <input type="number" id="limite-produtos" name="limite-produtos" value="10" min="1" class="form-control">
            </div>`;
    } else if (tipoRelatorio === 'clientes_fieis') {
        htmlFiltros += `
            <div class="col-md-4 form-group">
                <label for="limite-clientes">Quantidade de Clientes:</label>
                <input type="number" id="limite-clientes" name="limite-clientes" value="10" min="1" class="form-control">
            </div>`;
    }
    
    htmlFiltros += `</div>`;
    
    filtrosDiv.innerHTML = htmlFiltros;
});

// Gerar relatório
document.getElementById('gerar-relatorio').addEventListener('click', function() {
    const tipoRelatorio = document.getElementById('relatorio-tipo').value;
    const dataInicio = document.getElementById('data-inicio').value;
    const dataFim = document.getElementById('data-fim').value;
    
    // Validação básica
    if (!dataInicio || !dataFim) {
        alert('Por favor, preencha as datas de início e fim');
        return;
    }
    
    // Mostrar loading
    const resultadoDiv = document.getElementById('resultado-relatorio');
    resultadoDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Gerando relatório...</div>';
    
    // Chamada AJAX para obter os dados
    fetch('gerar_relatorio.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tipo=${tipoRelatorio}&data_inicio=${dataInicio}&data_fim=${dataFim}`
    })
    .then(response => response.json())
    .then(data => {
        mostrarResultadoRelatorio(data, tipoRelatorio);
    })
    .catch(error => {
        console.error('Erro:', error);
        resultadoDiv.innerHTML = '<div class="alert alert-danger">Ocorreu um erro ao gerar o relatório.</div>';
    });
});

// Função para exibir o resultado do relatório
function mostrarResultadoRelatorio(data, tipoRelatorio) {
    const resultadoDiv = document.getElementById('resultado-relatorio');
    let html = '';
    
    switch(tipoRelatorio) {
        case 'vendas_periodo':
            html = gerarHtmlVendasPeriodo(data);
            break;
        case 'produtos_mais_vendidos':
            html = gerarHtmlProdutosMaisVendidos(data);
            break;
        case 'clientes_fieis':
            html = gerarHtmlClientesFieis(data);
            break;
        case 'pagamentos_forma':
            html = gerarHtmlPagamentosForma(data);
            break;
        case 'vendas_status':
            html = gerarHtmlVendasStatus(data);
            break;
        default:
            html = '<div class="alert alert-warning">Tipo de relatório não reconhecido.</div>';
    }
    
    resultadoDiv.innerHTML = html;
    
    // Inicializar gráficos se existirem
    if (typeof Chart !== 'undefined' && data.grafico) {
        renderizarGrafico(data.grafico);
    }
}

function editarPagamento(id) {
    // Implemente a função de edição conforme necessário
    console.log('Editar pagamento:', id);
}
function gerarHtmlVendasPeriodo(data) {
    let html = `
        <div class="card">
            <div class="card-header">
                <h4>Relatório de Vendas por Período</h4>
                <p class="mb-0">Período: ${document.getElementById('data-inicio').value} à ${document.getElementById('data-fim').value}</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Vendas</h5>
                                <h3 class="text-primary">${data.total_vendas}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h3 class="text-success">R$ ${data.valor_total.toFixed(2).replace('.', ',')}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <canvas id="graficoVendas" height="300"></canvas>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Total de Vendas</th>
                                <th>Valor Total</th>
                                <th>Clientes</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    data.dados.forEach(item => {
        html += `
            <tr>
                <td>${formatarData(item.data)}</td>
                <td>${item.total_vendas}</td>
                <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
                <td>${item.clientes}</td>
            </tr>`;
    });
    
    html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    
    return html;
}

function gerarHtmlProdutosMaisVendidos(data) {
    let html = `
        <div class="card">
            <div class="card-header">
                <h4>Relatório de Produtos Mais Vendidos</h4>
                <p class="mb-0">Período: ${document.getElementById('data-inicio').value} à ${document.getElementById('data-fim').value}</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Produtos</h5>
                                <h3 class="text-primary">${data.total_produtos}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h3 class="text-success">R$ ${data.valor_total.toFixed(2).replace('.', ',')}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <canvas id="graficoProdutos" height="300"></canvas>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Quantidade Vendida</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    data.dados.forEach(item => {
        html += `
            <tr>
                <td>${item.nome}</td>
                <td>${item.quantidade_total}</td>
                <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
            </tr>`;
    });
    
    html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    
    return html;
}

function gerarHtmlClientesFieis(data) {
    let html = `
        <div class="card">
            <div class="card-header">
                <h4>Relatório de Clientes Fiéis</h4>
                <p class="mb-0">Período: ${document.getElementById('data-inicio').value} à ${document.getElementById('data-fim').value}</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Clientes</h5>
                                <h3 class="text-primary">${data.total_clientes}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h3 class="text-success">R$ ${data.valor_total.toFixed(2).replace('.', ',')}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Total de Vendas</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    data.dados.forEach(item => {
        html += `
            <tr>
                <td>${item.nome}</td>
                <td>${item.total_vendas}</td>
                <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
            </tr>`;
    });
    
    html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    
    return html;
}

function gerarHtmlPagamentosForma(data) {
    let html = `
        <div class="card">
            <div class="card-header">
                <h4>Relatório de Pagamentos por Forma</h4>
                <p class="mb-0">Período: ${document.getElementById('data-inicio').value} à ${document.getElementById('data-fim').value}</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Pagamentos</h5>
                                <h3 class="text-primary">${data.total_pagamentos}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h3 class="text-success">R$ ${data.valor_total.toFixed(2).replace('.', ',')}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="graficoPagamentos" height="300"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Forma de Pagamento</th>
                                        <th>Total</th>
                                        <th>Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody>`;
    
    data.dados.forEach(item => {
        html += `
            <tr>
                <td>${formatFormaPagamento(item.forma_pagamento)}</td>
                <td>${item.total_pagamentos}</td>
                <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
            </tr>`;
    });
    
    html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    
    return html;
}

function gerarHtmlVendasStatus(data) {
    let html = `
        <div class="card">
            <div class="card-header">
                <h4>Relatório de Vendas por Status</h4>
                <p class="mb-0">Período: ${document.getElementById('data-inicio').value} à ${document.getElementById('data-fim').value}</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Vendas</h5>
                                <h3 class="text-primary">${data.total_vendas}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h3 class="text-success">R$ ${data.valor_total.toFixed(2).replace('.', ',')}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Total de Vendas</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    data.dados.forEach(item => {
        html += `
            <tr>
                <td><span class="status-badge ${item.status}">${formatStatus(item.status)}</span></td>
                <td>${item.total_vendas}</td>
                <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
            </tr>`;
    });
    
    html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    
    return html;
}

function formatarData(dataStr) {
    const data = new Date(dataStr);
    return data.toLocaleDateString('pt-BR');
}

function formatFormaPagamento(forma) {
    const formas = {
        'dinheiro': 'Dinheiro',
        'cartao_debito': 'Cartão de Débito',
        'cartao_credito': 'Cartão de Crédito',
        'pix': 'PIX',
        'transferencia': 'Transferência'
    };
    return formas[forma] || forma;
}

function formatStatus(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function renderizarGrafico(dadosGrafico) {
    let canvasId = '';
    let chartType = 'bar';
    
    switch(dadosGrafico.tipo) {
        case 'vendas_periodo':
            canvasId = 'graficoVendas';
            break;
        case 'produtos_mais_vendidos':
            canvasId = 'graficoProdutos';
            break;
        case 'pagamentos_forma':
            canvasId = 'graficoPagamentos';
            chartType = 'pie';
            break;
    }
    
    const ctx = document.getElementById(canvasId).getContext('2d');
    new Chart(ctx, {
        type: chartType,
        data: dadosGrafico,
        options: {
            responsive: true,
            scales: chartType === 'bar' ? {
                y: {
                    beginAtZero: true
                }
            } : {}
        }
    });
}
}

    document.getElementById('venda-total').value = 'R$ ' + total.toFixed(2).replace('.', ',');

        
        // Seu código JavaScript para vendas (adicionar itens, calcular total) permanece igual
        // ... (mantenha o restante do JavaScript) ...
        
        // Inicializa a página mostrando o dashboard
        document.getElementById('nav-dashboard').click();

       

    </script>
</body>
</html>