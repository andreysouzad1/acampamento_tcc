<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);

$inscricao = null;
$erro = "";
$sucesso_classe = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_classe'])) {
    verify_csrf();
    $id_inscricao = intval($_POST['id_inscricao']);
    $nova_classe = $conn->real_escape_string($_POST['classe_mala']);
    
    $sql_update = "UPDATE inscricoes SET classe_mala = '$nova_classe' WHERE id = $id_inscricao";
    if ($conn->query($sql_update) === TRUE) {
        $sucesso_classe = "Classe atualizada com sucesso!";
        audit_event(
            $conn,
            'administrativo',
            (int) $_SESSION['usuario_id'],
            (string) $_SESSION['usuario_nome'],
            'Alteração de classe de bagagem',
            'Classe da bagagem da inscrição ' . $id_inscricao . ' atualizada para ' . $nova_classe . '.',
            'sucesso'
        );
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && (!empty($_POST['busca']) || isset($_POST['id_inscricao']))) {
    verify_csrf();
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
    <title>Rastreio de Malas - Acampamento "No Limite com Cristo"</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 1000px;
            background: #ffffff;
            padding: 30px;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Menu Superior Padronizado */
        .top-nav {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
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
        .top-nav a.logout {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .top-nav a.logout:hover {
            background-color: #fecaca;
        }

        /* Cabeçalho Centralizado Padronizado */
        .page-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-size: 22px;
            color: #1e293b;
            margin: 0 0 6px 0;
            font-weight: bold;
        }
        .page-header p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        /* Cartões Estruturais */
        .section-card {
            width: 100%;
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        .section-card:last-child {
            margin-bottom: 0;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .card-header h3 {
            margin: 0;
            font-size: 15px;
            color: #1e293b;
            font-weight: bold;
        }

        /* Formulário e Inputs */
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
        }
        input[type="text"], select {
            width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            background-color: #f8fafc;
            color: #334155;
            outline: none;
        }
        input[type="text"]:focus, select:focus {
            border-color: #3b82f6;
            background-color: #fff;
        }

        /* Botões Padronizados */
        .btn {
            height: 38px;
            padding: 0 16px;
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
            background: #16a34a;
            color: #ffffff;
            margin-top: 15px;
        }
        .btn-print:hover {
            background: #15803d;
        }
        .btn-toggle {
            background-color: #e2e8f0;
            color: #334155;
            border: none;
            padding: 5px 10px;
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
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
        }

        /* Grid de Resultado */
        .result-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .result-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-list {
            font-size: 13px;
            line-height: 1.8;
            color: #334155;
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
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
            border: 1px solid #cbd5e1;
            padding: 20px;
            border-radius: 6px;
            display: inline-block;
            width: 100%;
            max-width: 300px;
        }
        .etiqueta-header {
            font-size: 15px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .codigo-badge {
            font-family: monospace;
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
            margin: 5px 0;
            color: #334155;
        }
        .classe-badge {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            padding: 3px 10px;
            border-radius: 4px;
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
        <a href="formulario.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php" class="active">Malas</a>
        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'SuperAdmin'): ?>
            <a href="admin_usuarios.php">Usuários</a>
            <a href="logs_auditoria.php">Auditoria</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <!-- Cabeçalho Centralizado Padronizado -->
    <div class="page-header">
        <h1>Rastreio e Gestão de Bagagens</h1>
        <p>Consulte participantes e gerencie etiquetas de identificação de malas</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso_classe)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($sucesso_classe, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- Card de Pesquisa -->
    <div class="section-card">
        <div class="card-header">
            <h3>Localizar Participante</h3>
            <span style="font-size: 12px; color: #64748b;">Busca por CPF, Nome ou Código</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="busca">Dados do Participante:</label>
                <input type="text" id="busca" name="busca" required placeholder="Ex: 000.000.000-00, Nome completo ou MALA-XXXX" value="<?php echo isset($_POST['busca']) ? htmlspecialchars($_POST['busca'], ENT_QUOTES, 'UTF-8') : (isset($_POST['termo_pesquisa_atual']) ? htmlspecialchars($_POST['termo_pesquisa_atual'], ENT_QUOTES, 'UTF-8') : ''); ?>">
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
            <div class="section-card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h3>Dados e Classificação</h3>
                </div>
                
                <div class="info-list">
                    <strong>Inscrito:</strong> <?php echo htmlspecialchars($inscricao['nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <strong>CPF:</strong> <?php echo htmlspecialchars($inscricao['cpf'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <strong>Telefone:</strong> <?php echo htmlspecialchars($inscricao['telefone'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <strong>Status Pagamento:</strong> 
                    <?php if ($inscricao['status_pagamento'] == 'Pendente'): ?>
                        <span class="badge badge-pending">Pendente</span>
                    <?php else: ?>
                        <span class="badge badge-paid">Pago</span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id_inscricao" value="<?php echo (int) $inscricao['id']; ?>">
                    <input type="hidden" name="termo_pesquisa_atual" value="<?php echo htmlspecialchars($_POST['busca'] ?? $_POST['termo_pesquisa_atual'], ENT_QUOTES, 'UTF-8'); ?>">
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

            <!-- Coluna 2: Etiqueta de Bagagem -->
            <div class="section-card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h3>Etiqueta de Identificação</h3>
                    <button type="button" class="btn-toggle" onclick="toggleEtiqueta('etiqueta-box', this)">Ocultar</button>
                </div>

                <div id="etiqueta-box" class="etiqueta-wrapper">
                    <div class="etiqueta-bagagem">
                        <div class="etiqueta-header">Etiqueta de Bagagem</div>
                        <div style="font-size: 13px; color: #475569;">Código: <span class="codigo-badge"><?php echo htmlspecialchars($codigo_mala, ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <div style="font-size: 13px; color: #475569; margin-top: 4px;">Classe: <span class="classe-badge"><?php echo htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8'); ?></span></div>
                        
                        <div class="qr-box">
                            <img src="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Code Mala <?php echo htmlspecialchars($codigo_mala, ENT_QUOTES, 'UTF-8'); ?>">
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