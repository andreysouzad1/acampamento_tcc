<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);

function h(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function t(?string $value): string
{
    return trim((string) ($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    verify_csrf();
    $idExcluir = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($idExcluir) {
        $stmt = $conn->prepare('SELECT id FROM inscricoes WHERE id = ?');
        $stmt->bind_param('i', $idExcluir);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM inscricoes WHERE id = ?');
        $stmt->bind_param('i', $idExcluir);
        $stmt->execute();
        $stmt->close();
        if ($exists) {
            audit_event(
                $conn,
                'administrativo',
                (int) $_SESSION['usuario_id'],
                (string) $_SESSION['usuario_nome'],
                'Exclusão de inscrição',
                'Registro de inscrição ' . $idExcluir . ' excluído.',
                'sucesso'
            );
        }
        header('Location: dashboard.php');
        exit();
    }
}

// Consulta para buscar todas as inscrições ordenadas da mais recente para a mais antiga
$sql = "SELECT * FROM inscricoes ORDER BY id DESC";
$result = $conn->query($sql);

// Estatísticas Gerais
$total_inscritos = $result->num_rows;

// Pagamentos Pendentes
$sql_pendentes = "SELECT COUNT(*) as total FROM inscricoes WHERE status_pagamento = 'Pendente'";
$total_pendentes = (int) ($conn->query($sql_pendentes)->fetch_assoc()['total'] ?? 0);

// Estatísticas por Gênero (Compatível com Homem/Mulher)
$sql_genero = "SELECT genero, COUNT(*) as total FROM inscricoes GROUP BY genero";
$res_genero = $conn->query($sql_genero);
$totais_genero = [];
while ($g = $res_genero->fetch_assoc()) {
    $chaveGenero = t($g['genero'] ?? '');
    if ($chaveGenero !== '') {
        $totais_genero[$chaveGenero] = (int) ($g['total'] ?? 0);
    }
}

$fem = ($totais_genero['Feminino'] ?? 0)
     + ($totais_genero['Mulher'] ?? 0)
     + ($totais_genero['mulher'] ?? 0);

$masc = ($totais_genero['Masculino'] ?? 0)
      + ($totais_genero['Homem'] ?? 0)
      + ($totais_genero['homem'] ?? 0);

$perc_fem = $total_inscritos > 0 ? round(($fem / $total_inscritos) * 100) : 0;
$perc_masc = $total_inscritos > 0 ? round(($masc / $total_inscritos) * 100) : 0;

// Estatísticas por Bairro e Estado
$sql_bairros = "SELECT bairro, estado, COUNT(*) as total FROM inscricoes GROUP BY bairro, estado ORDER BY total DESC";
$res_bairros = $conn->query($sql_bairros);
$lista_bairros = [];
$maior_bairro_total = 1;

while ($b = $res_bairros->fetch_assoc()) {
    $bairroLimpo = t($b['bairro'] ?? '');
    $estadoLimpo = t($b['estado'] ?? '');

    $nome_bairro = $bairroLimpo !== '' ? $bairroLimpo : 'Não Informado';
    $nome_estado = $estadoLimpo !== '' ? $estadoLimpo : 'PR';

    $chave_localizacao = $nome_bairro . ' - ' . $nome_estado;
    $lista_bairros[$chave_localizacao] = (int) ($b['total'] ?? 0);
}

if (!empty($lista_bairros)) {
    $maior_bairro_total = max($lista_bairros);
}

// Estatísticas de Tamanhos de Camisetas
$sql_camisas = "SELECT tamanho_camiseta, COUNT(*) as total FROM inscricoes GROUP BY tamanho_camiseta";
$res_camisas = $conn->query($sql_camisas);
$totais_camisas = ['P' => 0, 'M' => 0, 'G' => 0, 'GG' => 0, 'G1' => 0, 'G2' => 0, 'G3' => 0];
while ($c = $res_camisas->fetch_assoc()) {
    $tamanho = t($c['tamanho_camiseta'] ?? '');
    if ($tamanho !== '' && isset($totais_camisas[$tamanho])) {
        $totais_camisas[$tamanho] = (int) ($c['total'] ?? 0);
    }
}

// Participantes com Restrições Alimentares
$sql_restricao = "SELECT COUNT(*) as total FROM inscricoes WHERE restricao_alimentar = 'Sim'";
$total_restricoes = (int) ($conn->query($sql_restricao)->fetch_assoc()['total'] ?? 0);

// Participantes com Alergias
$sql_alergia = "SELECT COUNT(*) as total FROM inscricoes WHERE alergia = 'Sim'";
$total_alergias = (int) ($conn->query($sql_alergia)->fetch_assoc()['total'] ?? 0);

// Participantes com Medicamento Controlado
$sql_med = "SELECT COUNT(*) as total FROM inscricoes WHERE medicamento_controlado = 'Sim'";
$total_med = (int) ($conn->query($sql_med)->fetch_assoc()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Geral - Acampamento "No Limite com Cristo"</title>
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

        /* Menu superior de navegação padronizado */
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

        /* Cabeçalho centralizado padronizado */
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

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .metric-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .metric-card h4 {
            margin: 0 0 8px 0;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        .metric-card .big-number {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
        }

        /* Três botões rápidos alinhados lado a lado */
        .quick-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .btn-quick {
            background-color: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .btn-quick:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }
        .btn-quick.is-active {
            background-color: #3b82f6;
            border-color: #2563eb;
            color: #ffffff;
        }

        /* Painel dinâmico exibido apenas quando um dos botões é clicado */
        .dynamic-panel {
            display: none;
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .panel-section {
            display: none;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .panel-header h3 {
            margin: 0;
            font-size: 14px;
            color: #1e293b;
            font-weight: bold;
        }
        .btn-hide {
            background-color: #e2e8f0;
            color: #334155;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-hide:hover {
            background-color: #cbd5e1;
            color: #0f172a;
        }

        .progress-bar-container {
            display: flex;
            height: 16px;
            border-radius: 8px;
            overflow: hidden;
            background: #e2e8f0;
            gap: 2px;
            margin-bottom: 8px;
        }
        .progress-fem {
            background: #ec4899;
            height: 100%;
        }
        .progress-masc {
            background: #3b82f6;
            height: 100%;
        }
        .gender-legend {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
        }
        .legend-item span {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .shirt-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            text-align: center;
        }
        .shirt-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px;
            border-radius: 6px;
        }
        .shirt-box span {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .shirt-box strong {
            font-size: 16px;
            color: #1e293b;
        }

        .neighborhood-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .neighborhood-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .neighborhood-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
            color: #334155;
        }
        .neighborhood-bar-bg {
            background: #e2e8f0;
            border-radius: 4px;
            height: 10px;
            width: 100%;
            overflow: hidden;
        }
        .neighborhood-bar-fill {
            background: #3b82f6;
            height: 100%;
            border-radius: 4px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 6px 6px 0 0;
            border: 1px solid #e2e8f0;
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
        }
        .search-box input:focus {
            border-color: #3b82f6;
        }

        .card-table {
            background: #ffffff;
            border-radius: 0 0 6px 6px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-bottom: 25px;
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
            color: #475569;
            padding: 12px 14px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            color: #334155;
            vertical-align: middle;
        }
        tr:hover { background-color: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-alert { background: #fee2e2; color: #b91c1c; }
        .badge-normal { background: #f1f5f9; color: #64748b; }
        .codigo-badge { font-family: monospace; background: #e2e8f0; padding: 3px 6px; border-radius: 4px; font-weight: bold; color: #334155; }
        
        .btn-acao {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            margin-right: 4px;
            display: inline-block;
        }
        .btn-editar { background: #3b82f6; color: white; }
        .btn-editar:hover { background: #2563eb; }
        .btn-excluir { background: #dc2626; color: white; }
        .btn-excluir:hover { background: #b91c1c; }
        button.btn-acao { border: 0; cursor: pointer; }
    </style>
    <link rel="stylesheet" href="navigation.css">
</head>
<body>

<div class="container">

    <!-- Menu Superior Padronizado -->
    <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="formulario.php">Nova Inscrição</a>
        <a href="dashboard.php" class="active">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <?php if (user_can_access_page($conn, 'enfermagem')): ?><a href="enfermagem.php">Enfermagem</a><?php endif; ?>
        <?php if (user_can_access_page($conn, 'cozinha')): ?><a href="cozinha.php">Cozinha</a><?php endif; ?>
        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'SuperAdmin'): ?>
            <a href="admin_usuarios.php">Usuários</a>
            <a href="logs_auditoria.php">Auditoria</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <!-- Cabeçalho Centralizado Padronizado -->
    <div class="page-header">
        <h1>Painel Geral de Gestão</h1>
        <p>Acompanhe as métricas, restrições e base de dados completa das inscrições</p>
    </div>
    
    <?php if (isset($erro_exclusao)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-weight: bold;">
            <?php echo h($erro_exclusao); ?>
        </div>
    <?php endif; ?>

    <div class="metrics-grid">
        <div class="metric-card">
            <h4>Total de Inscritos</h4>
            <div class="big-number"><?php echo (int) $total_inscritos; ?></div>
            <span style="font-size: 11px; color: #64748b; margin-top: 5px;">Pendentes: <strong style="color: #b45309;"><?php echo (int) $total_pendentes; ?></strong></span>
        </div>
        <div class="metric-card">
            <h4>Uso de Medicamento</h4>
            <div class="big-number" style="color: #7c3aed;"><?php echo (int) $total_med; ?></div>
            <span style="font-size: 11px; color: #64748b; margin-top: 5px;">Participantes monitorados</span>
        </div>
        <div class="metric-card">
            <h4>Alergias Registradas</h4>
            <div class="big-number" style="color: #dc2626;"><?php echo (int) $total_alergias; ?></div>
            <span style="font-size: 11px; color: #64748b; margin-top: 5px;">Requer atenção médica</span>
        </div>
        <div class="metric-card">
            <h4>Restrições Alimentares</h4>
            <div class="big-number" style="color: #d97706;"><?php echo (int) $total_restricoes; ?></div>
            <span style="font-size: 11px; color: #64748b; margin-top: 5px;">Avisos enviados à cozinha</span>
        </div>
    </div>

    <!-- Três botões compactos alinhados lado a lado -->
    <div class="quick-filters" aria-label="Atalhos de estatísticas">
        <button type="button" id="quick-genero" class="btn-quick" onclick="alternarSecao('secao-genero', this)">Gênero</button>
        <button type="button" id="quick-bairros" class="btn-quick" onclick="alternarSecao('secao-bairros', this)">Bairros</button>
        <button type="button" id="quick-camisas" class="btn-quick" onclick="alternarSecao('secao-camisas', this)">Camisetas</button>
    </div>

    <!-- Painel dinâmico exibido apenas quando um botão é selecionado -->
    <div id="painel-estatisticas" class="dynamic-panel">
        <!-- Gênero -->
        <div id="secao-genero" class="panel-section">
            <div class="panel-header">
                <h3>Distribuição por Gênero <span style="font-weight: normal; font-size: 12px; color: #64748b;">(Total: <?php echo (int) $total_inscritos; ?> participantes)</span></h3>
                <button type="button" class="btn-hide" onclick="ocultarPainel()">Ocultar</button>
            </div>
            <div class="progress-bar-container">
                <div class="progress-fem" style="width: <?php echo (int) $perc_fem; ?>%;" title="Mulheres: <?php echo (int) $fem; ?> (<?php echo (int) $perc_fem; ?>%)"></div>
                <div class="progress-masc" style="width: <?php echo (int) $perc_masc; ?>%;" title="Homens: <?php echo (int) $masc; ?> (<?php echo (int) $perc_masc; ?>%)"></div>
            </div>
            <div class="gender-legend">
                <div class="legend-item" style="color: #db2777;">
                    <span style="background: #ec4899;"></span> Mulheres: <strong><?php echo (int) $fem; ?></strong> (<?php echo (int) $perc_fem; ?>%)
                </div>
                <div class="legend-item" style="color: #2563eb;">
                    <span style="background: #3b82f6;"></span> Homens: <strong><?php echo (int) $masc; ?></strong> (<?php echo (int) $perc_masc; ?>%)
                </div>
            </div>
        </div>

        <!-- Bairros -->
        <div id="secao-bairros" class="panel-section">
            <div class="panel-header">
                <h3>Distribuição por Bairro - Estado <span style="font-weight: normal; font-size: 12px; color: #64748b;">(Mapeamento regional)</span></h3>
                <button type="button" class="btn-hide" onclick="ocultarPainel()">Ocultar</button>
            </div>
            <div class="neighborhood-list">
                <?php if (!empty($lista_bairros)): ?>
                    <?php foreach ($lista_bairros as $localizacao => $qtd): ?>
                        <?php
                            $largura_barra = $maior_bairro_total > 0 ? round(($qtd / $maior_bairro_total) * 100) : 0;
                        ?>
                        <div class="neighborhood-row">
                            <div class="neighborhood-info">
                                <span><?php echo h($localizacao); ?></span>
                                <span><?php echo (int) $qtd; ?> participante(s)</span>
                            </div>
                            <div class="neighborhood-bar-bg">
                                <div class="neighborhood-bar-fill" style="width: <?php echo (int) $largura_barra; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 13px; color: #64748b; text-align: center; margin: 10px 0;">Nenhum bairro cadastrado.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Camisetas -->
        <div id="secao-camisas" class="panel-section">
            <div class="panel-header">
                <h3>Controle de Tamanhos de Camisetas <span style="font-weight: normal; font-size: 12px; color: #64748b;">(Total de pedidos contabilizados)</span></h3>
                <button type="button" class="btn-hide" onclick="ocultarPainel()">Ocultar</button>
            </div>
            <div class="shirt-grid">
                <?php foreach ($totais_camisas as $tamanho => $quantidade): ?>
                    <div class="shirt-box">
                        <span><?php echo h($tamanho); ?></span>
                        <strong><?php echo (int) $quantidade; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="action-bar">
        <strong>Base de Dados Completa - Inscrições</strong>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Pesquisar por nome, CPF ou mala..." onkeyup="filtrarTabela()">
        </div>
    </div>

    <div class="card-table">
        <table id="tabelaInscritos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Gênero</th>
                    <th>Código da Mala</th>
                    <th>Camisa</th>
                    <th>Restrição Alimentar</th>
                    <th>Alergia</th>
                    <th>Med. Controlado</th>
                    <th>Status Pagamento</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_inscritos > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                            <td><strong><?php echo h($row['nome'] ?? ''); ?></strong></td>
                            <td><?php echo h($row['cpf'] ?? ''); ?></td>
                            <td><?php echo h($row['genero'] ?? ''); ?></td>
                            <td><span class="codigo-badge"><?php echo h($row['codigo_mala'] ?? ''); ?></span></td>
                            <td><strong><?php echo h(($row['tamanho_camiseta'] ?? '') !== '' ? $row['tamanho_camiseta'] : '-'); ?></strong></td>
                            
                            <td>
                                <?php if (($row['restricao_alimentar'] ?? '') === 'Sim'): ?>
                                    <span class="badge badge-alert">Sim: <?php echo h($row['descricao_alimentar'] ?? ''); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-normal">Não</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (($row['alergia'] ?? '') === 'Sim'): ?>
                                    <span class="badge badge-alert">Sim: <?php echo h($row['descricao_alergia'] ?? ''); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-normal">Não</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo h(($row['medicamento_controlado'] ?? '') !== '' ? $row['medicamento_controlado'] : 'Não'); ?></td>

                            <td>
                                <?php if (($row['status_pagamento'] ?? '') === 'Pendente'): ?>
                                    <span class="badge badge-pending">Pendente</span>
                                <?php else: ?>
                                    <span class="badge badge-paid">Pago</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: center;">
                                <a href="editar.php?id=<?php echo (int) ($row['id'] ?? 0); ?>" class="btn-acao btn-editar">Editar</a>
                                <form method="post" style="display:inline" onsubmit="return confirm('Tem certeza que deseja excluir o cadastro ID <?php echo (int) ($row['id'] ?? 0); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                    <button type="submit" class="btn-acao btn-excluir">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 30px; color: #64748b;">Nenhuma inscrição encontrada na base de dados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function ocultarPainel() {
    const painel = document.getElementById("painel-estatisticas");
    document.querySelectorAll(".panel-section").forEach(sec => sec.style.display = "none");
    document.querySelectorAll(".btn-quick").forEach(btn => btn.classList.remove("is-active"));
    if (painel) {
        painel.style.display = "none";
    }
}

function alternarSecao(secaoId, botaoClicado) {
    const painel = document.getElementById("painel-estatisticas");
    const secao = document.getElementById(secaoId);
    const jaAtiva = botaoClicado.classList.contains("is-active");

    ocultarPainel();

    if (!jaAtiva && painel && secao) {
        secao.style.display = "block";
        painel.style.display = "block";
        botaoClicado.classList.add("is-active");
    }
}

function filtrarTabela() {
    let input = document.getElementById("searchInput");
    let filter = input.value.toLowerCase();
    let table = document.getElementById("tabelaInscritos");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let tdNome = tr[i].getElementsByTagName("td")[1];
        let tdCpf = tr[i].getElementsByTagName("td")[2];
        let tdMala = tr[i].getElementsByTagName("td")[4];
        
        let php_check = tdNome || tdCpf || tdMala;
        if (php_check) {
            let txtNome = tdNome ? tdNome.textContent || tdNome.innerText : "";
            let txtCpf = tdCpf ? tdCpf.textContent || tdCpf.innerText : "";
            let txtMala = tdMala ? tdMala.textContent || tdMala.innerText : "";
            
            if (txtNome.toLowerCase().indexOf(filter) > -1 || 
                txtCpf.toLowerCase().indexOf(filter) > -1 || 
                txtMala.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
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