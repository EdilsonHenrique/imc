<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

// Verifica se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método inválido';
    header('Location: index.php');
    exit;
}

// Conexão com o banco
$conn = conectarBanco();

try {
    // Validações
    if (empty($_POST['venda-cliente']) || empty($_POST['venda-data']) || empty($_POST['produto'])) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }

    // Inicia transação
    $conn->begin_transaction();

    // Pega os valores do formulário
    $idCliente = intval($_POST['venda-cliente']);
    $dataVenda = $_POST['venda-data'];
    $status = $_POST['venda-status'] ?? 'pendente';

    // Calcula total da venda
    $total = 0;
    foreach ($_POST['produto'] as $index => $idProduto) {
        if (!empty($idProduto)) {
            $preco = floatval(str_replace(',', '.', $_POST['preco'][$index]));
            $quantidade = intval($_POST['quantidade'][$index]);
            $total += $preco * $quantidade;
        }
    }

    // Insere a venda
    $stmt = $conn->prepare("INSERT INTO vendas (id_cliente, data_venda, valor_total, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $idCliente, $dataVenda, $total, $status);
    $stmt->execute();
    $idVenda = $conn->insert_id;

    // Insere itens da venda
    $stmtItens = $conn->prepare("INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
    
    foreach ($_POST['produto'] as $index => $idProduto) {
        if (!empty($idProduto)) {
            $idProduto = intval($idProduto);
            $quantidade = intval($_POST['quantidade'][$index]);
            $precoUnitario = floatval(str_replace(',', '.', $_POST['preco'][$index]));
            
            $stmtItens->bind_param("iiid", $idVenda, $idProduto, $quantidade, $precoUnitario);
            $stmtItens->execute();
            
            // Atualiza estoque (opcional)
            // $stmtEstoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?");
            // $stmtEstoque->bind_param("ii", $quantidade, $idProduto);
            // $stmtEstoque->execute();
        }
    }

    // Confirma transação
    $conn->commit();

    $_SESSION['mensagem'] = 'Venda cadastrada com sucesso!';
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    // Em caso de erro, reverte a transação
    $conn->rollback();
    $_SESSION['mensagem'] = 'Erro: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
