<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);

// Se houver uma requisição via AJAX para atualizar o status do checkbox
if (isset($_POST['id']) && isset($_POST['status'])) {
    verify_csrf();
    $id = intval($_POST['id']);
    $status = intval($_POST['status']);
    
    $update_sql = "UPDATE inscricoes SET status_envio = $status WHERE id = $id";
    $conn->query($update_sql);
    audit_event(
        $conn,
        'administrativo',
        (int) $_SESSION['usuario_id'],
        (string) $_SESSION['usuario_nome'],
        'Alteração de status de comunicação',
        'Status de envio da inscrição ' . $id . ' atualizado para ' . ($status === 1 ? 'enviado' : 'pendente') . '.',
        'sucesso'
    );
    exit;
}

// Consulta utilizando os nomes exatos das colunas da tabela
$sql = "SELECT id, nome, nome_contato_emergencia, contato_emergencia, status_envio FROM inscricoes ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicação - Acampamento "No Limite com Cristo"</title>
    <style>
        :root {
            --whatsapp-green: #25d366;
            --whatsapp-dark: #1ebe5d;
            --text-main: #333333;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: var(--text-main);
        }

        /* Container principal padronizado com 1000px igual às demais telas */
        .container {
            max-width: 1000px;
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
            color: var(--text-muted);
            margin: 0;
        }

        /* Barra de Ação e Pesquisa */
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
        .action-bar strong {
            font-size: 14px;
            color: #1e293b;
        }

        .search-box input {
            padding: 8px 12px;
            width: 260px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
            transition: border-color 0.2s;
        }
        .search-box input:focus {
            border-color: #3b82f6;
        }

        /* Estrutura da Tabela */
        .card-table {
            background: #ffffff;
            border-radius: 0 0 6px 6px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            padding: 12px 14px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            color: #334155;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background-color: #f8fafc;
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
            white-space: nowrap;
        }
        .btn-whatsapp:hover {
            background-color: var(--whatsapp-dark);
        }

        .no-phone {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: bold;
        }

        /* Rodapé Institucional */
        .system-footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 25px;
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
        <a href="comunicacao.php" class="active">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'SuperAdmin'): ?>
            <a href="admin_usuarios.php">Usuários</a>
            <a href="logs_auditoria.php">Auditoria</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <div id="csrf-token" data-token="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" style="display:none;"></div>

    <!-- Cabeçalho Centralizado Padronizado -->
    <div class="page-header">
        <h1>Central de Comunicação & Emergência</h1>
        <p>Envie a mensagem padrão via WhatsApp e marque os contatos. O estado fica guardado no sistema.</p>
    </div>

    <!-- Barra de Ação e Pesquisa -->
    <div class="action-bar">
        <strong>Lista de Contatos de Emergência</strong>
        <div class="search-box">
            <input type="text" id="filtroContato" placeholder="Pesquisar participante ou responsável..." onkeyup="filtrarTabela()">
        </div>
    </div>

    <!-- Tabela -->
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
                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            Nenhum participante inscrito encontrado.
                        </td>
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
        body: `id=${idParticipante}&status=${status}&csrf_token=${encodeURIComponent(document.getElementById('csrf-token').dataset.token)}`
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
<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>