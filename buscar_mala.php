<?php
include("conexao.php");

$inscricao = null;
$erro = "";
$sucesso_classe = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_classe'])) {
    $id_inscricao = intval($_POST['id_inscricao']);
    $nova_classe = $conn->real_escape_string($_POST['classe_mala']);
    
    $sql_update = "UPDATE inscricoes SET classe_mala = '$nova_classe' WHERE id = $id_inscricao";
    if ($conn->query($sql_update) === TRUE) {
        $sucesso_classe = "Classe atualizada com sucesso!";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && (!empty($_POST['busca']) || isset($_POST['id_inscricao']))) {
    $termo_busca = isset($_POST['busca']) ? $_POST['busca'] : $_POST['termo_pesquisa_atual'];
    $busca_escape = $conn->real_escape_string($termo_busca);
    
    $busca_limpa = preg_replace('/[^0-9]/', '', $busca_escape);

    $sql = "SELECT * FROM inscricoes WHERE 
            (REPLACE(REPLACE(cpf, '.', ''), '-', '') LIKE '%$busca_limpa%' AND '$busca_limpa' != '') 
            OR codigo_mala = '$busca_escape' 
            OR nome LIKE '%$busca_escape%' 
            ORDER BY (nome = '$busca_escape') DESC, id DESC LIMIT 1";
            
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $inscricao = $result->fetch_assoc();
    } else {
        $erro = "Nenhuma inscrição encontrada com esses dados.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta e Rastreio de Mala - Acampamento</title>
    <style>
        /* Layout Base Padronizado com o Dashboard */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: #333;
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
            margin-top: 0;
        }

        /* Navegação Superior Idêntica ao Dashboard */
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
        .top-nav a:hover, .top-nav a.active {
            background-color: #cbd5e1;
            color: #0f172a;
        }

        /* Cartões Estruturais no Padrão do Painel */
        .section-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-bottom: 25px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .card-header h3 {
            margin: 0;
            font-size: 15px;
            color: #333;
        }

        /* Formulário e Inputs */
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-size: 13px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            background-color: #ffffff;
            outline: none;
        }
        input[type="text"]:focus, select:focus {
            border-color: #3b82f6;
        }

        /* Botões Padronizados */
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }
        .btn-primary {
            background: #3b82f6;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-update {
            background: #d97706;
            color: #ffffff;
            margin-top: 10px;
        }
        .btn-update:hover {
            background: #b45309;
        }
        .btn-print {
            background: #15803d;
            color: #ffffff;
            margin-top: 15px;
        }
        .btn-print:hover {
            background: #166534;
        }
        .btn-toggle {
            background-color: #e2e8f0;
            color: #334155;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-toggle:hover {
            background-color: #cbd5e1;
        }

        /* Alertas */
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
        }
        .alert-success {
            background: #dcfce7;
            color: #15803d;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
        }

        /* Grid de Resultado (Dados à esquerda + Etiqueta à direita) */
        .result-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .result-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-list {
            font-size: 14px;
            line-height: 1.8;
            color: #333;
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-paid { background: #dcfce7; color: #15803d; }

        /* Bloco da Etiqueta */
        .etiqueta-wrapper {
            text-align: center;
        }
        .etiqueta-bagagem {
            background: #ffffff;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 6px;
            display: inline-block;
            width: 100%;
            max-width: 300px;
            box-sizing: border-box;
        }
        .etiqueta-header {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .codigo-badge {
            font-family: monospace;
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 15px;
            display: inline-block;
            margin: 5px 0;
        }
        .classe-badge {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .qr-box {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            display: inline-block;
            background: #fff;
        }
        .qr-box img {
            display: block;
            margin: 0 auto;
        }

        /* Impressão Exclusiva da Etiqueta */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            body * {
                visibility: hidden;
            }
            .etiqueta-bagagem, .etiqueta-bagagem * {
                visibility: visible;
            }
            .etiqueta-bagagem {
                position: absolute;
                left: 50%;
                top: 0;
                transform: translateX(-50%);
                width: 100%;
                max-width: 350px;
                border: 2px solid #000 !important;
                margin: 0;
                padding: 15px;
            }
            .btn-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Menu Superior Padronizado -->
    <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="index.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php" class="active">Malas</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <h2>Rastreio e Gestão de Bagagens</h2>

    <?php if (!empty($erro)): ?>
        <div class="alert-error"><?php echo $erro; ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso_classe)): ?>
        <div class="alert-success"><?php echo $sucesso_classe; ?></div>
    <?php endif; ?>

    <!-- Card de Pesquisa -->
    <div class="section-card">
        <div class="card-header">
            <h3>Localizar Participante</h3>
            <span style="font-size: 12px; color: #666;">Busca por CPF, Nome ou Código</span>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="busca">Dados do Participante:</label>
                <input type="text" id="busca" name="busca" required placeholder="Ex: 000.000.000-00, Nome completo ou MALA-XXXX" value="<?php echo isset($_POST['busca']) ? htmlspecialchars($_POST['busca']) : (isset($_POST['termo_pesquisa_atual']) ? htmlspecialchars($_POST['termo_pesquisa_atual']) : ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Pesquisar Inscrição</button>
        </form>
    </div>

    <?php if ($inscricao): ?>
        <?php 
            $codigo_mala = $inscricao['codigo_mala'];
            $classe_atual = $inscricao['classe_mala'];
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($inscricao['nome']);
        ?>
        
        <div class="result-grid">
            <!-- Coluna 1: Dados do Inscrito e Controle de Classe -->
            <div class="section-card">
                <div class="card-header">
                    <h3>Dados do Participante e Classificação</h3>
                </div>
                
                <div class="info-list">
                    <strong>Inscrito:</strong> <?php echo htmlspecialchars($inscricao['nome']); ?><br>
                    <strong>CPF:</strong> <?php echo htmlspecialchars($inscricao['cpf']); ?><br>
                    <strong>Telefone:</strong> <?php echo htmlspecialchars($inscricao['telefone']); ?><br>
                    <strong>Status Pagamento:</strong> 
                    <?php if ($inscricao['status_pagamento'] == 'Pendente'): ?>
                        <span class="badge badge-pending">Pendente</span>
                    <?php else: ?>
                        <span class="badge badge-paid">Pago</span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="id_inscricao" value="<?php echo $inscricao['id']; ?>">
                    <input type="hidden" name="termo_pesquisa_atual" value="<?php echo htmlspecialchars($_POST['busca'] ?? $_POST['termo_pesquisa_atual']); ?>">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="classe_mala">Alterar Classe da Mala:</label>
                        <select name="classe_mala" id="classe_mala">
                            <option value="Classe A" <?php if($classe_atual == 'Classe A') echo 'selected'; ?>>Classe A</option>
                            <option value="Classe B" <?php if($classe_atual == 'Classe B') echo 'selected'; ?>>Classe B</option>
                            <option value="Classe C" <?php if($classe_atual == 'Classe C') echo 'selected'; ?>>Classe C</option>
                            <option value="Classe D" <?php if($classe_atual == 'Classe D') echo 'selected'; ?>>Classe D</option>
                            <option value="Classe E" <?php if($classe_atual == 'Classe E') echo 'selected'; ?>>Classe E</option>
                        </select>
                    </div>
                    <button type="submit" name="atualizar_classe" class="btn btn-update">Atualizar Classe</button>
                </form>
            </div>

            <!-- Coluna 2: Etiqueta de Bagagem (Recolhível e Pronta para Impressão) -->
            <div class="section-card">
                <div class="card-header">
                    <h3>Etiqueta de Identificação</h3>
                    <button type="button" class="btn-toggle" onclick="toggleEtiqueta('etiqueta-box', this)">Ocultar</button>
                </div>

                <div id="etiqueta-box" class="etiqueta-wrapper">
                    <div class="etiqueta-bagagem">
                        <div class="etiqueta-header">Etiqueta de Bagagem</div>
                        <div style="font-size: 13px; color: #555;">Código: <span class="codigo-badge"><?php echo $codigo_mala; ?></span></div>
                        <div style="font-size: 13px; color: #555; margin-top: 4px;">Classe: <span class="classe-badge"><?php echo htmlspecialchars($classe_atual); ?></span></div>
                        
                        <div class="qr-box">
                            <img src="<?php echo $qrUrl; ?>" alt="QR Code Mala <?php echo $codigo_mala; ?>">
                        </div>
                        
                        <div>
                            <button type="button" onclick="window.print()" class="btn btn-print">Imprimir Etiqueta</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
// Máscara de CPF no campo de busca
document.getElementById('busca').addEventListener('input', function (e) {
    let value = e.target.value;
    let apenasNumeros = value.replace(/\D/g, '');
    
    if (apenasNumeros.length <= 11 && !isNaN(value.charAt(0))) {
        value = apenasNumeros.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    }
});

// Função para exibir/ocultar o card da etiqueta
function toggleEtiqueta(id, btn) {
    let el = document.getElementById(id);
    if (el.style.display === "none") {
        el.style.display = "block";
        btn.textContent = "Ocultar";
    } else {
        el.style.display = "none";
        btn.textContent = "Exibir";
    }
}
</script>

</body>
</html>

<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>