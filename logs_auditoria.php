<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_superadmin($conn);

$logs = $conn->query(
    'SELECT tipo, usuario, acao, detalhes, ip, dispositivo, status, criado_em
     FROM logs_auditoria ORDER BY id DESC LIMIT 500'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria - Acampamento "No Limite com Cristo"</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1000px;
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Barra de Navegação Superior Padronizada */
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
            color: #64748b;
            margin: 0;
        }

        /* Estrutura da Tabela */
        .table-wrapper {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }
        .table-scroll {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }
        th, td {
            padding: 12px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f8fafc;
        }
        td {
            max-width: 280px;
            overflow-wrap: anywhere;
            color: #334155;
        }
        td small {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        /* Badges de Status */
        .badge-status {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-status.fail {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* Aviso de Imutabilidade */
        .immutable-note {
            padding: 12px 15px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
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
    <link rel="stylesheet" href="navigation.css">
</head>
<body>

<div class="container">

    <!-- Menu Superior Padronizado -->
    <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="formulario.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <a href="enfermagem.php">Enfermagem</a>
        <a href="cozinha.php">Cozinha</a>
        <a href="admin_usuarios.php">Usuários</a>
        <a href="logs_auditoria.php" class="active">Auditoria</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <!-- Cabeçalho Centralizado Padronizado -->
    <div class="page-header">
        <h1>Logs de Auditoria</h1>
        <p>Eventos de autenticação e alterações administrativas · registros mais recentes</p>
    </div>

    <!-- Tabela de Logs -->
    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Data e hora</th>
                        <th>Tipo / resultado</th>
                        <th>Usuário</th>
                        <th>Ação / detalhes</th>
                        <th>IP</th>
                        <th>Dispositivo</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($logs && $logs->num_rows > 0): ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo escape_html($log['criado_em']); ?></td>
                            <td>
                                <strong><?php echo escape_html($log['tipo']); ?></strong><br>
                                <span class="badge-status <?php echo $log['status'] === 'falha' ? 'fail' : ''; ?>">
                                    <?php echo escape_html($log['status']); ?>
                                </span>
                            </td>
                            <td><?php echo escape_html($log['usuario']); ?></td>
                            <td>
                                <strong><?php echo escape_html($log['acao']); ?></strong>
                                <small><?php echo escape_html($log['detalhes']); ?></small>
                            </td>
                            <td style="white-space: nowrap;"><?php echo escape_html($log['ip']); ?></td>
                            <td><?php echo escape_html($log['dispositivo']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 25px; color: #64748b;">
                            Nenhum registro de auditoria encontrado até o momento.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="immutable-note">
            Exibindo até 500 eventos. Os registros são somente de inclusão e protegidos contra atualização e exclusão pela aplicação e pelo banco de dados.
        </div>
    </div>
</div>

</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>