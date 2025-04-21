<?php
require_once 'config.php';
require_once 'funcoes.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método inválido';
    header('Location: vendas.php');
    exit;
}

// Validações básicas
if (empty($_POST['id_venda']) || empty($_POST['venda-cliente']) || empty($_POST['venda-data']) || 
    empty($_POST['produto']) || empty($_POST['quantidade'])) {
    $_SESSION['mensagem'] = 'Preencha todos os campos obrigatórios';
    header('Location: vendas.php');
    exit;
}

$conn = conectarBanco();

try {
    $conn->begin_transaction();
    
    $idVenda = intval($_POST['id_venda']);
    $idCliente = intval($_POST['venda-cliente']);
    $dataVenda = $_POST['venda-data'];
    $status = $_POST['venda-status'];
    
    // Calcular total
    $total = 0;
    foreach ($_POST['preco'] as $index => $preco) {
        $total += floatval(str_replace(',', '.', $preco)) * intval($_POST['quantidade'][$index]);
    }
    
    // Atualizar venda
    $stmtVenda = $conn->prepare("UPDATE vendas SET 
                                id_cliente = ?, 
                                data_venda = ?, 
                                valor_total = ?, 
                                status = ? 
                                WHERE id_venda = ?");
    $stmtVenda->bind_param("issdi", $idCliente, $dataVenda, $total, $status, $idVenda);
    $stmtVenda->execute();
    
    // Remover itens antigos
    $stmtDelete = $conn->prepare("DELETE FROM itens_venda WHERE id_venda = ?");
    $stmtDelete->bind_param("i", $idVenda);
    $stmtDelete->execute();
    
    // Inserir novos itens
    $stmtItens = $conn->prepare("INSERT INTO itens_venda 
                                (id_venda, id_produto, quantidade, preco_unitario) 
                                VALUES (?, ?, ?, ?)");
    
    foreach ($_POST['produto'] as $index => $idProduto) {
        if (!empty($idProduto)) {
            $quantidade = intval($_POST['quantidade'][$index]);
            $precoUnitario = floatval(str_replace(',', '.', $_POST['preco'][$index]));
            
            $stmtItens->bind_param("iiid", $idVenda, $idProduto, $quantidade, $precoUnitario);
            $stmtItens->execute();
        }
    }
    
    $conn->commit();
    
    $_SESSION['mensagem'] = 'Venda atualizada com sucesso!';
    header('Location: vendas.php');
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem'] = 'Erro ao atualizar venda: ' . $e->getMessage();
    header('Location: vendas.php');
}
?>