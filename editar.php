<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);

// Verifica se o ID foi passado na URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = intval($_GET['id']);

// Se o formulário foi submetido para atualizar os dados
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();
    $nome = $_POST['nome'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    $cep = $_POST['cep'] ?? '';
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';

    $medicamento_controlado = $_POST['medicamento_controlado'] ?? '';
    $restricao_alimentar = $_POST['restricao_alimentar'] ?? '';
    $descricao_alimentar = $_POST['descricao_alimentar'] ?? null;
    $alergia = $_POST['alergia'] ?? '';
    $descricao_alergia = $_POST['descricao_alergia'] ?? null;
    $tamanho_camiseta = $_POST['tamanho_camiseta'] ?? '';

    $contato_emergencia = $_POST['contato_emergencia'] ?? '';
    $nome_contato_emergencia = $_POST['nome_contato_emergencia'] ?? '';

    // Query de atualização no banco de dados
    $sql_update = "UPDATE inscricoes SET 
                    nome = '$nome', 
                    cpf = '$cpf', 
                    data_nascimento = '$data_nascimento', 
                    genero = '$genero', 
                    telefone = '$telefone', 
                    email = '$email', 
                    cep = '$cep', 
                    rua = '$rua', 
                    numero = '$numero', 
                    complemento = '$complemento', 
                    bairro = '$bairro', 
                    cidade = '$cidade', 
                    estado = '$estado', 
                    medicamento_controlado = '$medicamento_controlado', 
                    restricao_alimentar = '$restricao_alimentar', 
                    descricao_alimentar = " . ($descricao_alimentar ? "'$descricao_alimentar'" : "NULL") . ", 
                    alergia = '$alergia', 
                    descricao_alergia = " . ($descricao_alergia ? "'$descricao_alergia'" : "NULL") . ", 
                    tamanho_camiseta = '$tamanho_camiseta', 
                    contato_emergencia = '$contato_emergencia', 
                    nome_contato_emergencia = '$nome_contato_emergencia' 
                  WHERE id = $id";

    if ($conn->query($sql_update) === TRUE) {
        audit_event(
            $conn,
            'administrativo',
            (int) $_SESSION['usuario_id'],
            (string) $_SESSION['usuario_nome'],
            'Edição de inscrição',
            'Dados da inscrição ' . $id . ' alterados.',
            'sucesso'
        );
        header("Location: dashboard.php");
        exit();
    } else {
        $erro = "Erro ao atualizar: " . $conn->error;
    }
}

// Busca os dados atuais do inscrito para preencher o formulário
$sql = "SELECT * FROM inscricoes WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "Inscrição não encontrada!";
    exit();
}

$inscrito = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Inscrição - Acampamento</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .top-nav {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        .top-nav a {
            background-color: #e2e8f0;
            color: #334155;
            padding: 8px 14px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .top-nav a:hover {
            background-color: #cbd5e1;
            color: #0f172a;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #ffffff;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        .conditional-field {
            margin-top: 10px;
            padding-left: 15px;
            border-left: 3px solid #dc2626;
        }
        button {
            width: 100%;
            background-color: #3b82f6;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-nav">
        <a href="dashboard.php">Voltar ao Painel</a>
    </div>

    <h2>Editar Inscrição (ID: <?php echo $inscrito['id']; ?>)</h2>
    
    <?php if (isset($erro)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
            <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form action="editar.php?id=<?php echo $inscrito['id']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="form-group">
            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($inscrito['nome']); ?>" required>
        </div>

        <div class="form-group">
            <label for="cpf">CPF *</label>
            <input type="text" id="cpf" name="cpf" maxlength="14" value="<?php echo htmlspecialchars($inscrito['cpf']); ?>" required>
        </div>

        <div class="form-group">
            <label>Gênero *</label>
            <div class="radio-group">
                <label><input type="radio" name="genero" value="Mulher" <?php echo ($inscrito['genero'] == 'Mulher') ? 'checked' : ''; ?> required> Mulher</label>
                <label><input type="radio" name="genero" value="Homem" <?php echo ($inscrito['genero'] == 'Homem') ? 'checked' : ''; ?>> Homem</label>
            </div>
        </div>

        <div class="form-group">
            <label for="data_nascimento">Data de Aniversário *</label>
            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo htmlspecialchars($inscrito['data_nascimento']); ?>" required>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone *</label>
            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($inscrito['telefone']); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($inscrito['email']); ?>" required>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">
        <h3 style="font-size: 16px; color: #444; margin-bottom: 15px;">Endereço</h3>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="cep">CEP *</label>
                <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($inscrito['cep']); ?>" required>
            </div>
            <div class="form-group" style="flex: 2;">
                <label for="rua">Rua / Logradouro *</label>
                <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($inscrito['rua']); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="numero">Número *</label>
                <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($inscrito['numero']); ?>" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($inscrito['complemento']); ?>">
            </div>
            <div class="form-group" style="flex: 2;">
                <label for="bairro">Bairro *</label>
                <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($inscrito['bairro']); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="cidade">Cidade *</label>
                <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($inscrito['cidade']); ?>" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="estado">Estado (UF) *</label>
                <input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($inscrito['estado']); ?>" required>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

        <div class="form-group">
            <label>Faz uso de medicamento controlado?</label>
            <div class="radio-group">
                <label><input type="radio" name="medicamento_controlado" value="Sim" <?php echo ($inscrito['medicamento_controlado'] == 'Sim') ? 'checked' : ''; ?> required> Sim</label>
                <label><input type="radio" name="medicamento_controlado" value="Não" <?php echo ($inscrito['medicamento_controlado'] == 'Não') ? 'checked' : ''; ?>> Não</label>
            </div>
        </div>

        <div class="form-group">
            <label>Possui alguma restrição alimentar?</label>
            <div class="radio-group">
                <label><input type="radio" name="restricao_alimentar" value="Sim" onclick="toggleCampo('restricao', true)" <?php echo ($inscrito['restricao_alimentar'] == 'Sim') ? 'checked' : ''; ?> required> Sim</label>
                <label><input type="radio" name="restricao_alimentar" value="Não" onclick="toggleCampo('restricao', false)" <?php echo ($inscrito['restricao_alimentar'] == 'Não') ? 'checked' : ''; ?>> Não</label>
            </div>
            <div id="grupo_descricao_alimentar" class="conditional-field" style="display: <?php echo ($inscrito['restricao_alimentar'] == 'Sim') ? 'block' : 'none'; ?>;">
                <label for="descricao_alimentar">Qual restrição alimentar?</label>
                <input type="text" id="descricao_alimentar" name="descricao_alimentar" value="<?php echo htmlspecialchars($inscrito['descricao_alimentar'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Possui alguma alergia?</label>
            <div class="radio-group">
                <label><input type="radio" name="alergia" value="Sim" onclick="toggleCampo('alergia', true)" <?php echo ($inscrito['alergia'] == 'Sim') ? 'checked' : ''; ?> required> Sim</label>
                <label><input type="radio" name="alergia" value="Não" onclick="toggleCampo('alergia', false)" <?php echo ($inscrito['alergia'] == 'Não') ? 'checked' : ''; ?>> Não</label>
            </div>
            <div id="grupo_descricao_alergia" class="conditional-field" style="display: <?php echo ($inscrito['alergia'] == 'Sim') ? 'block' : 'none'; ?>;">
                <label for="descricao_alergia">Qual alergia?</label>
                <input type="text" id="descricao_alergia" name="descricao_alergia" value="<?php echo htmlspecialchars($inscrito['descricao_alergia'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="tamanho_camiseta">Tamanho da Camiseta *</label>
            <select id="tamanho_camiseta" name="tamanho_camiseta" required>
                <option value="P" <?php echo ($inscrito['tamanho_camiseta'] == 'P') ? 'selected' : ''; ?>>P</option>
                <option value="M" <?php echo ($inscrito['tamanho_camiseta'] == 'M') ? 'selected' : ''; ?>>M</option>
                <option value="G" <?php echo ($inscrito['tamanho_camiseta'] == 'G') ? 'selected' : ''; ?>>G</option>
                <option value="GG" <?php echo ($inscrito['tamanho_camiseta'] == 'GG') ? 'selected' : ''; ?>>GG</option>
                <option value="G1" <?php echo ($inscrito['tamanho_camiseta'] == 'G1') ? 'selected' : ''; ?>>G1</option>
                <option value="G2" <?php echo ($inscrito['tamanho_camiseta'] == 'G2') ? 'selected' : ''; ?>>G2</option>
                <option value="G3" <?php echo ($inscrito['tamanho_camiseta'] == 'G3') ? 'selected' : ''; ?>>G3</option>
            </select>
        </div>

        <div class="form-group">
            <label for="contato_emergencia">Contato de Emergência *</label>
            <input type="text" id="contato_emergencia" name="contato_emergencia" value="<?php echo htmlspecialchars($inscrito['contato_emergencia']); ?>" required>
        </div>

        <div class="form-group">
            <label for="nome_contato_emergencia">Nome do Contato de Emergência *</label>
            <input type="text" id="nome_contato_emergencia" name="nome_contato_emergencia" value="<?php echo htmlspecialchars($inscrito['nome_contato_emergencia']); ?>" required>
        </div>

        <button type="submit">Salvar Alterações</button>

    </form>
</div>

<script>
    function toggleCampo(tipo, mostrar) {
        let grupo = document.getElementById('grupo_descricao_' + (tipo === 'restricao' ? 'alimentar' : 'alergia'));
        let input = document.getElementById(tipo === 'restricao' ? 'descricao_alimentar' : 'descricao_alergia');

        if (mostrar) {
            grupo.style.display = 'block';
            input.required = true;
        } else {
            grupo.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }
</script>

</body>
</html>

<?php $conn->close(); ?>