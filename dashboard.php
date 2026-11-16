<?php
include("conexao.php");

// LÓGICA DE EXCLUSÃO: Se houver uma requisição para excluir um ID
if (isset($_GET['acao']) && $_GET['acao'] == 'excluir' && isset($_GET['id'])) {
    $id_excluir = intval($_GET['id']);
    
    $sql_excluir = "DELETE FROM inscricoes WHERE id = $id_excluir";
    if ($conn->query($sql_excluir) === TRUE) {
        header("Location: dashboard.php");
        exit();
    } else {
        $erro_exclusao = "Erro ao excluir o registro: " . $conn->error;
    }
}

// Consulta para buscar todas as inscrições ordenadas da mais recente para a mais antiga
$sql = "SELECT * FROM inscricoes ORDER BY id DESC";
$result = $conn->query($sql);

// Estatísticas Gerais
$total_inscritos = $result->num_rows;

// Pagamentos Pendentes
$sql_pendentes = "SELECT COUNT(*) as total FROM inscricoes WHERE status_pagamento = 'Pendente'";
$total_pendentes = $conn->query($sql_pendentes)->fetch_assoc()['total'];

// Estatísticas por Gênero (Compatível com Homem/Mulher)
$sql_genero = "SELECT genero, COUNT(*) as total FROM inscricoes GROUP BY genero";
$res_genero = $conn->query($sql_genero);
$totais_genero = [];
while($g = $res_genero->fetch_assoc()) {
    $totais_genero[trim($g['genero'])] = $g['total'];
}

$fem = (isset($totais_genero['Feminino']) ? $totais_genero['Feminino'] : 0) + 
       (isset($totais_genero['Mulher']) ? $totais_genero['Mulher'] : 0) +
       (isset($totais_genero['mulher']) ? $totais_genero['mulher'] : 0);

$masc = (isset($totais_genero['Masculino']) ? $totais_genero['Masculino'] : 0) + 
        (isset($totais_genero['Homem']) ? $totais_genero['Homem'] : 0) +
        (isset($totais_genero['homem']) ? $totais_genero['homem'] : 0);

$perc_fem = $total_inscritos > 0 ? round(($fem / $total_inscritos) * 100) : 0;
$perc_masc = $total_inscritos > 0 ? round(($masc / $total_inscritos) * 100) : 0;

// Estatísticas por Bairro e Estado
$sql_bairros = "SELECT bairro, estado, COUNT(*) as total FROM inscricoes GROUP BY bairro, estado ORDER BY total DESC";
$res_bairros = $conn->query($sql_bairros);
$lista_bairros = [];
$maior_bairro_total = 1; 

while($b = $res_bairros->fetch_assoc()) {
    $nome_bairro = !empty(trim($b['bairro'])) ? trim($b['bairro']) : 'Não Informado';
    $nome_estado = !empty(trim($b['estado'])) ? trim($b['estado']) : 'PR';
    
    $chave_localizacao = $nome_bairro . " - " . $nome_estado;
    $lista_bairros[$chave_localizacao] = intval($b['total']);
}

if (!empty($lista_bairros)) {
    $maior_bairro_total = max($lista_bairros);
}

// Estatísticas de Tamanhos de Camisetas
$sql_camisas = "SELECT tamanho_camiseta, COUNT(*) as total FROM inscricoes GROUP BY tamanho_camiseta";
$res_camisas = $conn->query($sql_camisas);
$totais_camisas = ['P' => 0, 'M' => 0, 'G' => 0, 'GG' => 0, 'G1' => 0, 'G2' => 0, 'G3' => 0];
while($c = $res_camisas->fetch_assoc()) {
    $tamanho = trim($c['tamanho_camiseta']);
    if (isset($totais_camisas[$tamanho])) {
        $totais_camisas[$tamanho] = $c['total'];
    }
}

// Participantes com Restrições Alimentares
$sql_restricao = "SELECT COUNT(*) as total FROM inscricoes WHERE restricao_alimentar = 'Sim'";
$total_restricoes = $conn->query($sql_restricao)->fetch_assoc()['total'];

// Participantes com Alergias
$sql_alergia = "SELECT COUNT(*) as total FROM inscricoes WHERE alergia = 'Sim'";
$total_alergias = $conn->query($sql_alergia)->fetch_assoc()['total'];

// Participantes com Medicamento Controlado
$sql_med = "SELECT COUNT(*) as total FROM inscricoes WHERE medicamento_controlado = 'Sim'";
$total_med = $conn->query($sql_med)->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Gestão - Acampamento</title>
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
        .top-nav a:hover, .top-nav a.active {
            background-color: #cbd5e1;
            color: #0f172a;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .metric-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .metric-card h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card .big-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .collapsible-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 {
            margin: 0;
            font-size: 15px;
            color: #333;
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
            transition: background 0.2s;
        }
        .btn-toggle:hover {
            background-color: #cbd5e1;
        }

        /* Definido como 'none' para iniciar oculto ao carregar a página */
        .card-content {
            margin-top: 15px;
            display: none;
        }

        .progress-bar-container {
            display: flex;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            background: #e2e8f0;
            gap: 2px;
            margin-bottom: 10px;
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
            gap: 10px;
            text-align: center;
        }
        .shirt-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 6px;
        }
        .shirt-box span {
            display: block;
            font-size: 12px;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .shirt-box strong {
            font-size: 18px;
            color: #0f172a;
        }

        .neighborhood-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            border-radius: 6px;
            height: 14px;
            width: 100%;
            overflow: hidden;
        }
        .neighborhood-bar-fill {
            background: #0ea5e9;
            height: 100%;
            border-radius: 6px;
            transition: width 0.4s ease;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 6px 6px 0 0;
            border: 1px solid #ccc;
            border-bottom: none;
            gap: 15px;
            flex-wrap: wrap;
        }
        .search-box input {
            padding: 8px 12px;
            width: 260px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
        }

        .card-table {
            background: #ffffff;
            border-radius: 0 0 6px 6px;
            border: 1px solid #ccc;
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
            border-bottom: 1px solid #ccc;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        tr:hover { background-color: #f9f9f9; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-alert { background: #fee2e2; color: #b91c1c; }
        .badge-normal { background: #f1f5f9; color: #64748b; }
        .codigo-badge { font-family: monospace; background: #e2e8f0; padding: 3px 6px; border-radius: 4px; font-weight: bold; }
        
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
    </style>
</head>
<body>

<div class="container">

   <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="index.php">Nova Inscrição</a>
        <a href="dashboard.php"class="active">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <h2>Painel de Gestão - Inscrições</h2>
    
    <?php if (isset($erro_exclusao)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-weight: bold;">
            <?php echo $erro_exclusao; ?>
        </div>
    <?php endif; ?>

    <div class="metrics-grid" style="margin-top: 25px;">
        <div class="metric-card">
            <h4>Total de Inscritos</h4>
            <div class="big-number"><?php echo $total_inscritos; ?></div>
            <span style="font-size: 11px; color: #666; margin-top: 5px;">Pendentes: <strong style="color: #b45309;"><?php echo $total_pendentes; ?></strong></span>
        </div>
        <div class="metric-card">
            <h4>Uso de Medicamento</h4>
            <div class="big-number" style="color: #7c3aed;"><?php echo $total_med; ?></div>
            <span style="font-size: 11px; color: #666; margin-top: 5px;">Participantes monitorados</span>
        </div>
        <div class="metric-card">
            <h4>Alergias Registradas</h4>
            <div class="big-number" style="color: #dc2626;"><?php echo $total_alergias; ?></div>
            <span style="font-size: 11px; color: #666; margin-top: 5px;">Requer atenção médica</span>
        </div>
        <div class="metric-card">
            <h4>Restrições Alimentares</h4>
            <div class="big-number" style="color: #d97706;"><?php echo $total_restricoes; ?></div>
            <span style="font-size: 11px; color: #666; margin-top: 5px;">Avisos enviados à cozinha</span>
        </div>
    </div>

    <!-- 1º: Distribuição por Gênero (Oculto por padrão) -->
    <div class="collapsible-card">
        <div class="card-header">
            <h3>Distribuição por Gênero <span style="font-weight: normal; font-size: 12px; color: #666;">(Total: <?php echo $total_inscritos; ?> participantes)</span></h3>
            <button class="btn-toggle" onclick="toggleCard('genero-content', this)">Exibir</button>
        </div>
        <div id="genero-content" class="card-content">
            <div class="progress-bar-container" style="margin-top: 10px;">
                <div class="progress-fem" style="width: <?php echo $perc_fem; ?>%;" title="Mulheres: <?php echo $fem; ?> (<?php echo $perc_fem; ?>%)"></div>
                <div class="progress-masc" style="width: <?php echo $perc_masc; ?>%;" title="Homens: <?php echo $masc; ?> (<?php echo $perc_masc; ?>%)"></div>
            </div>
            <div class="gender-legend">
                <div class="legend-item" style="color: #db2777;">
                    <span style="background: #ec4899;"></span> Mulheres: <strong><?php echo $fem; ?></strong> (<?php echo $perc_fem; ?>%)
                </div>
                <div class="legend-item" style="color: #2563eb;">
                    <span style="background: #3b82f6;"></span> Homens: <strong><?php echo $masc; ?></strong> (<?php echo $perc_masc; ?>%)
                </div>
            </div>
        </div>
    </div>

    <!-- 2º: Distribuição de Participantes por Bairro - Estado (Oculto por padrão) -->
    <div class="collapsible-card">
        <div class="card-header">
            <h3>Distribuição de Participantes por Bairro - Estado <span style="font-weight: normal; font-size: 12px; color: #666;">(Mapeamento regional)</span></h3>
            <button class="btn-toggle" onclick="toggleCard('bairros-content', this)">Exibir</button>
        </div>
        <div id="bairros-content" class="card-content">
            <div class="neighborhood-list" style="margin-top: 10px;">
                <?php if (!empty($lista_bairros)): ?>
                    <?php foreach($lista_bairros as $localizacao => $qtd): ?>
                        <?php 
                            $largura_barra = round(($qtd / $maior_bairro_total) * 100);
                        ?>
                        <div class="neighborhood-row">
                            <div class="neighborhood-info">
                                <span><?php echo htmlspecialchars($localizacao); ?></span>
                                <span><?php echo $qtd; ?> participante(s)</span>
                            </div>
                            <div class="neighborhood-bar-bg">
                                <div class="neighborhood-bar-fill" style="width: <?php echo $largura_barra; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 13px; color: #64748b; text-align: center; margin: 10px 0;">Nenhum bairro cadastrado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3º: Controle de Tamanhos de Camisetas (Oculto por padrão) -->
    <div class="collapsible-card">
        <div class="card-header">
            <h3>Controle de Tamanhos de Camisetas <span style="font-weight: normal; font-size: 12px; color: #666;">(Total de pedidos contabilizados)</span></h3>
            <button class="btn-toggle" onclick="toggleCard('camisas-content', this)">Exibir</button>
        </div>
        <div id="camisas-content" class="card-content">
            <div class="shirt-grid" style="margin-top: 10px;">
                <?php foreach($totais_camisas as $tamanho => $quantidade): ?>
                    <div class="shirt-box">
                        <span><?php echo $tamanho; ?></span>
                        <strong><?php echo $quantidade; ?></strong>
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
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['cpf']); ?></td>
                            <td><?php echo htmlspecialchars($row['genero']); ?></td>
                            <td><span class="codigo-badge"><?php echo htmlspecialchars($row['codigo_mala']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['tamanho_camiseta'] ?? '-'); ?></strong></td>
                            
                            <td>
                                <?php if (!empty($row['restricao_alimentar']) && $row['restricao_alimentar'] == 'Sim'): ?>
                                    <span class="badge badge-alert">Sim: <?php echo htmlspecialchars($row['descricao_alimentar']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-normal">Não</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['alergia']) && $row['alergia'] == 'Sim'): ?>
                                    <span class="badge badge-alert">Sim: <?php echo htmlspecialchars($row['descricao_alergia']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-normal">Não</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo !empty($row['medicamento_controlado']) ? htmlspecialchars($row['medicamento_controlado']) : 'Não'; ?></td>

                            <td>
                                <?php if ($row['status_pagamento'] == 'Pendente'): ?>
                                    <span class="badge badge-pending">Pendente</span>
                                <?php else: ?>
                                    <span class="badge badge-paid">Pago</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: center;">
                                <a href="editar.php?id=<?php echo $row['id']; ?>" class="btn-acao btn-editar">Editar</a>
                                <a href="dashboard.php?acao=excluir&id=<?php echo $row['id']; ?>" class="btn-acao btn-excluir" onclick="return confirm('Tem certeza que deseja excluir o cadastro ID <?php echo $row['id']; ?>?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 30px; color: #777;">Nenhuma inscrição encontrada na base de dados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function toggleCard(contentId, buttonElement) {
    let contentDiv = document.getElementById(contentId);
    if (contentDiv.style.display === "none" || contentDiv.style.display === "") {
        contentDiv.style.display = "block";
        buttonElement.textContent = "Ocultar";
    } else {
        contentDiv.style.display = "none";
        buttonElement.textContent = "Exibir";
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