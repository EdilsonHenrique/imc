<?php
require 'config.php';
require 'funcoes.php';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = conectarBanco();
    
    $nome = $conn->real_escape_string($_POST['nome']);
    $telefone = $conn->real_escape_string($_POST['telefone']);
    $email = $conn->real_escape_string($_POST['email']);
    $endereco = $conn->real_escape_string($_POST['endereco']);
    $observacoes = $conn->real_escape_string($_POST['observacoes']);
    
    if (empty($_POST['id_cliente'])) {
        // Novo cliente
        $sql = "INSERT INTO clientes (nome, telefone, email, endereco, data_cadastro, observacoes)
                VALUES ('$nome', '$telefone', '$email', '$endereco', CURDATE(), '$observacoes')";
    } else {
        // Atualizar cliente
        $id = (int)$_POST['id_cliente'];
        $sql = "UPDATE clientes SET 
                nome = '$nome',
                telefone = '$telefone',
                email = '$email',
                endereco = '$endereco',
                observacoes = '$observacoes'
                WHERE id_cliente = $id";
    }
    
    if ($conn->query($sql) {
        $_SESSION['mensagem'] = 'Cliente salvo com sucesso!';
    } else {
        $_SESSION['mensagem'] = 'Erro ao salvar cliente: ' . $conn->error;
    }
    
    $conn->close();
    header('Location: clientes.php');
    exit();
}

// Obter lista de clientes
$clientes = getClientes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Seu cabeçalho existente -->
</head>
<body>
    <!-- Seu menu existente -->
    
    <div class="content" id="clientes">
        <h2>Gerenciamento de Clientes</h2>
        
        <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success">
            <?php echo $mensagem; ?>
        </div>
        <?php endif; ?>
        
        <div id="cliente-form">
            <form method="POST" action="clientes.php">
                <input type="hidden" id="id_cliente" name="id_cliente" value="">
                
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                
                <!-- Outros campos do formulário -->
                
                <button type="submit" id="salvar-cliente">Salvar Cliente</button>
                <button type="button" id="cancelar-cliente" style="background-color: #ddd; margin-left: 10px;">Cancelar</button>
            </form>
        </div>
        
        <div style="margin-top: 30px;">
            <input type="text" id="busca-cliente" placeholder="Buscar cliente..." style="padding: 8px; width: 300px;">
            <button type="button">Buscar</button>
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
                <?php foreach ($clientes as $cliente): ?>
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
    
    <script>
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
            });
    }
    </script>
</body>
</html>