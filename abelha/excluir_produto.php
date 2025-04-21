<?php
require_once 'config.php';
require_once 'funcoes.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $resposta = ["success" => false, "message" => "ID do produto não fornecido"];
    header('Content-Type: application/json');
    echo json_encode($resposta);
    exit;
}

$id_produto = $_GET['id'];

// Conectar ao banco de dados
$conn = conectarBanco();

// Identificar ambiente
$isReplit = getenv('REPL_ID') !== false || getenv('PGHOST') !== false;

try {
    if ($isReplit) {
        // PostgreSQL
        // Verificar se o produto está sendo usado em alguma venda
        $sql = "SELECT COUNT(*) as total FROM itens_venda WHERE id_produto = $1";
        $result = pg_query_params($conn->get_raw_resource(), $sql, [$id_produto]);
        $row = pg_fetch_assoc($result);
        
        if ($row['total'] > 0) {
            // Produto está em uso, então apenas desativamos
            $sqlUpdate = "UPDATE produtos SET ativo = FALSE WHERE id_produto = $1";
            $resultUpdate = pg_query_params($conn->get_raw_resource(), $sqlUpdate, [$id_produto]);
            
            if ($resultUpdate) {
                $resposta = [
                    "success" => true, 
                    "message" => "Produto foi desativado pois está sendo usado em vendas.",
                    "apenas_desativado" => true
                ];
            } else {
                throw new Exception(pg_last_error($conn->get_raw_resource()));
            }
        } else {
            // Produto não está sendo usado, podemos excluir
            $sqlDelete = "DELETE FROM produtos WHERE id_produto = $1";
            $resultDelete = pg_query_params($conn->get_raw_resource(), $sqlDelete, [$id_produto]);
            
            if ($resultDelete) {
                $resposta = ["success" => true, "message" => "Produto excluído com sucesso!"];
            } else {
                throw new Exception(pg_last_error($conn->get_raw_resource()));
            }
        }
    } else {
        // MySQL
        // Verificar se o produto está sendo usado em alguma venda
        $sql = "SELECT COUNT(*) as total FROM itens_venda WHERE id_produto = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            // Produto está em uso, então apenas desativamos
            $sqlUpdate = "UPDATE produtos SET ativo = 0 WHERE id_produto = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("i", $id_produto);
            
            if ($stmtUpdate->execute()) {
                $resposta = [
                    "success" => true, 
                    "message" => "Produto foi desativado pois está sendo usado em vendas.",
                    "apenas_desativado" => true
                ];
            } else {
                throw new Exception($conn->error);
            }
        } else {
            // Produto não está sendo usado, podemos excluir
            $sqlDelete = "DELETE FROM produtos WHERE id_produto = ?";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->bind_param("i", $id_produto);
            
            if ($stmtDelete->execute()) {
                $resposta = ["success" => true, "message" => "Produto excluído com sucesso!"];
            } else {
                throw new Exception($conn->error);
            }
        }
    }
} catch (Exception $e) {
    $resposta = ["success" => false, "message" => "Erro ao excluir produto: " . $e->getMessage()];
}

// Fechar conexão
$conn->close();

// Enviar resposta
header('Content-Type: application/json');
echo json_encode($resposta);
?>