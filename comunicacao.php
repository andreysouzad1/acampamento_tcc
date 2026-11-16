<?php
include("conexao.php");

// Se houver uma requisição via AJAX para atualizar o status do checkbox
if (isset($_POST['id']) && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $status = intval($_POST['status']);
    
    $update_sql = "UPDATE inscricoes SET status_envio = $status WHERE id = $id";
    $conn->query($update_sql);
    exit;
}

// Consulta utilizando os nomes exatos das colunas da tua tabela
$sql = "SELECT id, nome, nome_contato_emergencia, contato_emergencia, status_envio FROM inscricoes ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Comunicação - No Limite com Cristo</title>
    <style>
        :root {
            --primary-red: #dc2626;
            --whatsapp-green: #25d366;
            --whatsapp-dark: #1ebe5d;
            --text-main: #333333;
            --text-muted: #64748b;
            --border-color: #ccc;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        /* Container principal padronizado com 950px igual ao dashboard e index */
        .container {
            max-width: 1200px;
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Menu superior limpo idêntico às outras telas */
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

        .page-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: #333;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 6px 6px 0 0;
            border: 1px solid var(--border-color);
            border-bottom: none;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box input {
            padding: 8px 12px;
            width: 260px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
        }

        .card-table {
            background: #ffffff;
            border-radius: 0 0 6px 6px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        th {
            background: #f8fafc;
            color: #555;
            padding: 12px 14px;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            color: #333;
            vertical-align: middle;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        /* Estilo da linha quando enviada */
        tr.enviado {
            background-color: #f0fdf4 !important;
            opacity: 0.75;
        }
        tr.enviado td {
            text-decoration: line-through;
            color: #64748b;
        }
        tr.enviado td .btn-whatsapp,
        tr.enviado td input[type="checkbox"] {
            text-decoration: none;
            opacity: 1;
        }

        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--whatsapp-green);
        }

        .btn-whatsapp {
            background-color: var(--whatsapp-green);
            color: #ffffff;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-whatsapp:hover {
            background-color: var(--whatsapp-dark);
        }

        .no-phone {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">

   <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="index.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php" class="active">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>
    <div class="page-header">
        <h2>Central de Comunicação & Emergência</h2>
        <p>Envie a mensagem padrão via WhatsApp e marque os contatos. O estado fica guardado no sistema.</p>
    </div>

    <div class="action-bar">
        <strong>Lista de Contatos de Emergência</strong>
        <div class="search-box">
            <input type="text" id="filtroContato" placeholder="Pesquisar participante ou responsável..." onkeyup="filtrarTabela()">
        </div>
    </div>

    <div class="card-table">
        <table id="tabelaContatos">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">✓</th>
                    <th>Participante</th>
                    <th>Contato de Emergência</th>
                    <th>Telefone</th>
                    <th style="text-align: right;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php
                            $id_participante = $row['id'];
                            $nome_part = htmlspecialchars($row['nome']);
                            
                            $nome_resp = !empty($row['nome_contato_emergencia']) ? htmlspecialchars($row['nome_contato_emergencia']) : 'Responsável';
                            $tel_original = !empty($row['contato_emergencia']) ? htmlspecialchars($row['contato_emergencia']) : '';
                            
                            $tel_limpo = preg_replace('/[^0-9]/', '', $tel_original);
                            $ja_enviado = $row['status_envio'] == 1;

                            $mensagem_unica = "Olá, {$nome_resp}! Aqui é da equipe do Acampamento No Limite com Cristo. Entramos em contato referente ao/à participante *{$nome_part}*.";
                            $link_wpp = "https://wa.me/55{$tel_limpo}?text=" . urlencode($mensagem_unica);
                        ?>

                        <tr class="<?php echo $ja_enviado ? 'enviado' : ''; ?>" data-busca="<?php echo strtolower($nome_part . ' ' . $nome_resp); ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" <?php echo $ja_enviado ? 'checked' : ''; ?> onchange="atualizarStatus(this, <?php echo $id_participante; ?>)">
                            </td>
                            <td><strong><?php echo $nome_part; ?></strong></td>
                            <td><?php echo $nome_resp; ?></td>
                            <td><?php echo $tel_original ?: '<span class="no-phone">Não informado</span>'; ?></td>
                            <td style="text-align: right;">
                                <?php if (!empty($tel_limpo)): ?>
                                    <a href="<?php echo $link_wpp; ?>" target="_blank" class="btn-whatsapp" onclick="marcarAutomatico(this, <?php echo $id_participante; ?>)">
                                        💬 Enviar WhatsApp
                                    </a>
                                <?php else: ?>
                                    <span class="no-phone">Sem número</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">Nenhum participante inscrito encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function atualizarStatus(checkbox, idParticipante) {
    const tr = checkbox.closest("tr");
    const status = checkbox.checked ? 1 : 0;

    if (checkbox.checked) {
        tr.classList.add("enviado");
    } else {
        tr.classList.remove("enviado");
    }

    fetch('comunicacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${idParticipante}&status=${status}`
    });
}

function marcarAutomatico(botao, idParticipante) {
    const tr = botao.closest("tr");
    const checkbox = tr.querySelector("input[type='checkbox']");
    if (checkbox && !checkbox.checked) {
        checkbox.checked = true;
        atualizarStatus(checkbox, idParticipante);
    }
}

function filtrarTabela() {
    const termo = document.getElementById("filtroContato").value.toLowerCase();
    const linhas = document.querySelectorAll("#tabelaContatos tbody tr");

    linhas.forEach(linha => {
        const dados = linha.getAttribute("data-busca");
        if (dados) {
            linha.style.display = dados.includes(termo) ? "" : "none";
        }
    });
}
</script>

</body>
</html>
<?php $conn->close(); ?>