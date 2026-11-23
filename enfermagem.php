<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/relatorio_setor.php';
initialize_auth($conn);
require_login($conn);

$erro = '';
$inscritos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['acao'] ?? '') !== 'exportar_pdf') {
        http_response_code(400);
        $erro = 'Solicitação inválida.';
    } else {
        try {
            $inscritos = setor_inscritos($conn, 'enfermagem');
            $sincronizadoEm = new DateTimeImmutable();
            $registrosPdf = [];
            foreach ($inscritos as $inscrito) {
                $contato = trim((string) ($inscrito['nome_contato_emergencia'] ?? ''));
                $telefone = trim((string) ($inscrito['contato_emergencia'] ?? ''));
                $registrosPdf[] = [
                    'nome' => (string) $inscrito['nome'],
                    'campos' => [
                        'Nascimento / idade: ' . setor_data_nascimento($inscrito['data_nascimento'] ?? null),
                        'Medicamento controlado: ' . (setor_flag_ativa($inscrito['medicamento_controlado'] ?? null) ? 'Sim' : '—'),
                        'Restrição alimentar: ' . setor_descricao($inscrito['restricao_alimentar'] ?? null, $inscrito['descricao_alimentar'] ?? null),
                        'Alergias: ' . setor_descricao($inscrito['alergia'] ?? null, $inscrito['descricao_alergia'] ?? null),
                        'Contato de emergência: ' . trim($contato . ($contato !== '' && $telefone !== '' ? ' - ' : '') . $telefone)
                    ]
                ];
            }

            audit_event(
                $conn,
                'relatorio_setor',
                (int) $_SESSION['usuario_id'],
                (string) $_SESSION['usuario_nome'],
                'Exportação PDF - Enfermagem',
                'Relatório sincronizado; total de inscritos: ' . count($inscritos) . '.',
                'sucesso'
            );
            $pdf = setor_pdf_bytes('Enfermagem', $sincronizadoEm->format('d/m/Y H:i:s'), $registrosPdf);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="enfermagem-' . $sincronizadoEm->format('Ymd-His') . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: no-store, private');
            echo $pdf;
            $conn->close();
            exit();
        } catch (mysqli_sql_exception $e) {
            error_log('Falha ao gerar relatório do setor de Enfermagem: ' . $e->getMessage());
            http_response_code(500);
            $erro = 'Não foi possível gerar o relatório. Tente novamente ou contate o administrador.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $inscritos = setor_inscritos($conn, 'enfermagem');
    } catch (mysqli_sql_exception $e) {
        error_log('Falha ao consultar dados do setor de Enfermagem: ' . $e->getMessage());
        http_response_code(500);
        $erro = 'Não foi possível carregar os dados. Tente novamente ou contate o administrador.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enfermagem - Acampamento "No Limite com Cristo"</title>
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
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .card-header-title {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }
        .card-header h3 {
            margin: 0;
            font-size: 15px;
            color: #1e293b;
            font-weight: bold;
        }
        .total {
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
        }
        .card-actions form {
            margin: 0;
        }

        /* Botão Padronizado */
        .btn-primary {
            height: 38px;
            padding: 0 16px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #2563eb;
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

        .restricted-note {
            color: #64748b;
            font-size: 12px;
            margin: 0 0 15px 0;
        }

        /* Tabela Padronizada */
        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.5;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: bold;
            white-space: nowrap;
        }
        tbody tr:last-child td {
            border-bottom: 0;
        }
        .empty-state {
            color: #64748b;
            text-align: center;
            padding: 24px 12px;
            font-size: 13px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }
            .container {
                padding: 20px;
            }
            .section-card {
                padding: 16px;
            }
            .card-actions,
            .card-actions form,
            .btn-primary {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="navigation.css">
</head>
<body>
<main class="container">
    <nav class="top-nav" aria-label="Navegação principal">
        <a href="home.php">Início</a>
        <a href="formulario.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <?php if (user_can_access_page($conn, 'enfermagem')): ?><a href="enfermagem.php" class="active">Enfermagem</a><?php endif; ?>
        <?php if (user_can_access_page($conn, 'cozinha')): ?><a href="cozinha.php">Cozinha</a><?php endif; ?>
        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'SuperAdmin'): ?>
            <a href="admin_usuarios.php">Usuários</a>
            <a href="logs_auditoria.php">Auditoria</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Sair</a>
    </nav>

    <header class="page-header">
        <h1>Equipe de Enfermagem</h1>
        <p>Inscritos com medicamentos controlados, alergias ou restrições informadas</p>
    </header>

    <?php if ($erro !== ''): ?><div class="alert-error" role="alert"><?php echo escape_html($erro); ?></div><?php endif; ?>

    <section class="section-card">
        <div class="card-header">
            <div class="card-header-title">
                <h3>Informações de Saúde</h3>
                <span class="total"><?php echo count($inscritos); ?> inscrito(s)</span>
            </div>
            <div class="card-actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="acao" value="exportar_pdf">
                    <button class="btn-primary" type="submit">Sincronizar e Baixar PDF</button>
                </form>
            </div>
        </div>
        <p class="restricted-note">A lista é consultada diretamente no banco. O PDF refaz a consulta no momento da exportação.</p>
        <?php if (!$inscritos): ?>
            <div class="empty-state">Nenhum inscrito corresponde aos critérios de enfermagem.</div>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Nascimento / idade</th>
                            <th>Medicamento controlado</th>
                            <th>Restrição alimentar</th>
                            <th>Alergias</th>
                            <th>Contato de emergência</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($inscritos as $inscrito): ?>
                        <tr>
                            <td><?php echo escape_html((string) $inscrito['nome']); ?></td>
                            <td><?php echo escape_html(setor_data_nascimento($inscrito['data_nascimento'] ?? null)); ?></td>
                            <td><?php echo setor_flag_ativa($inscrito['medicamento_controlado'] ?? null) ? 'Sim' : '—'; ?></td>
                            <td><?php echo escape_html(setor_descricao($inscrito['restricao_alimentar'] ?? null, $inscrito['descricao_alimentar'] ?? null)); ?></td>
                            <td><?php echo escape_html(setor_descricao($inscrito['alergia'] ?? null, $inscrito['descricao_alergia'] ?? null)); ?></td>
                            <td><?php
                                $contato = trim((string) ($inscrito['nome_contato_emergencia'] ?? ''));
                                $telefone = trim((string) ($inscrito['contato_emergencia'] ?? ''));
                                echo escape_html(trim($contato . ($contato !== '' && $telefone !== '' ? ' - ' : '') . $telefone) ?: '—');
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
<?php $conn->close(); ?>