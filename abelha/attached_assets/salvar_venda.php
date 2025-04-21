<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

// Verifica se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método inválido';
    header('Location: index.php'); // Ou o nome do seu arquivo principal
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

    // Calcula total da venda
    $total = 0;
    foreach ($_POST['preco'] as $preco) {
        $total += (float) str_replace(['.', ','], ['', '.'], $preco);
    }

    // Insere a venda
    $stmt = $conn->prepare("INSERT INTO vendas (id_cliente, data_venda, valor_total, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $_POST['venda-cliente'], $_POST['venda-data'], $total, $_POST['venda-status']);
    $stmt->execute();
    $idVenda = $conn->insert_id;

    // Insere itens da venda
    $stmtItens = $conn->prepare("INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
    
    foreach ($_POST['produto'] as $index => $idProduto) {
        if (!empty($idProduto)) {
            $precoUnitario = (float) str_replace(['.', ','], ['', '.'], $_POST['preco'][$index]);
            $stmtItens->bind_param("iiid", $idVenda, $idProduto, $_POST['quantidade'][$index], $precoUnitario);
            $stmtItens->execute();
        }
    }

    // Confirma transação
    $conn->commit();

    $_SESSION['mensagem'] = 'Venda cadastrada com sucesso!';
    header('Location: index.php'); // Ou o nome do seu arquivo principal
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem'] = 'Erro: ' . $e->getMessage();
    header('Location: index.php'); // Ou o nome do seu arquivo principal
    exit;
}