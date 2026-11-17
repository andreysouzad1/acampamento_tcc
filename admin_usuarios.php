<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_superadmin($conn);

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'criar') {
        $nome = trim((string) ($_POST['nome_completo'] ?? ''));
        $usuario = strtolower(trim((string) ($_POST['usuario'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $perfil = (string) ($_POST['perfil'] ?? 'AdminComum');
        $senha = (string) ($_POST['senha'] ?? '');
        $identificadorValido = strlen($usuario) >= 4
            && strlen($usuario) <= 254
            && !preg_match('/\s/', $usuario)
            && (filter_var($usuario, FILTER_VALIDATE_EMAIL) || preg_match('/^[a-z0-9._]+$/', $usuario));

        if ($nome === '' || strlen($nome) > 150 || !$identificadorValido
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || !in_array($perfil, ['SuperAdmin', 'AdminComum'], true) || strlen($senha) < 12) {
            $erro = 'Confira os dados informados. A senha deve ter pelo menos 12 caracteres.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1');
            $stmt->bind_param('ss', $usuario, $email);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($exists) {
                $erro = 'Não foi possível cadastrar: usuário ou e-mail já está em uso.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $mustChangePassword = 1;
                $stmt = $conn->prepare(
                    'INSERT INTO usuarios (usuario, senha, nome_completo, email, perfil, status, exige_troca_senha) VALUES (?, ?, ?, ?, ?, "Ativo", ?)'
                );
                $stmt->bind_param('sssssi', $usuario, $hash, $nome, $email, $perfil, $mustChangePassword);
                $stmt->execute();
                $stmt->close();
                audit_event(
                    $conn,
                    'administrativo',
                    (int) $_SESSION['usuario_id'],
                    (string) $_SESSION['usuario_nome'],
                    'Criação de usuário',
                    'Conta ' . $usuario . ' criada com perfil ' . $perfil . ' e status Ativo.',
                    'sucesso'
                );
                $mensagem = 'Usuário cadastrado. A conta deverá trocar a senha no primeiro acesso.';
            }
        }
    } elseif ($acao === 'status') {
        $targetId = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $novoStatus = (string) ($_POST['novo_status'] ?? '');
        if (!$targetId || !in_array($novoStatus, ['Ativo', 'Inativo'], true)) {
            $erro = 'Não foi possível alterar o status da conta.';
        } else {
            $stmt = $conn->prepare('SELECT id, usuario, perfil, status FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $targetId);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$target) {
                $erro = 'Usuário não encontrado.';
            } elseif ((int) $target['id'] === (int) $_SESSION['usuario_id'] && $novoStatus === 'Inativo') {
                $erro = 'Não é permitido inativar a própria conta.';
            } else {
                $lastActiveSuperAdmin = false;
                if ($target['perfil'] === 'SuperAdmin' && $target['status'] === 'Ativo' && $novoStatus === 'Inativo') {
                    $count = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE perfil = 'SuperAdmin' AND status = 'Ativo'")->fetch_assoc();
                    $lastActiveSuperAdmin = (int) $count['total'] <= 1;
                }
                if ($lastActiveSuperAdmin) {
                    $erro = 'A última conta SuperAdmin ativa não pode ser inativada.';
                } elseif ($target['status'] !== $novoStatus) {
                    $stmt = $conn->prepare('UPDATE usuarios SET status = ? WHERE id = ?');
                    $stmt->bind_param('si', $novoStatus, $targetId);
                    $stmt->execute();
                    $stmt->close();
                    audit_event(
                        $conn,
                        'administrativo',
                        (int) $_SESSION['usuario_id'],
                        (string) $_SESSION['usuario_nome'],
                        $novoStatus === 'Ativo' ? 'Ativação de usuário' : 'Inativação de usuário',
                        'Conta ' . $target['usuario'] . ': ' . $target['status'] . ' → ' . $novoStatus . '.',
                        'sucesso'
                    );
                    $mensagem = 'Status da conta atualizado.';
                }
            }
        }
    }
}

$usuarios = $conn->query(
    'SELECT id, nome_completo, usuario, email, perfil, status, ultimo_acesso FROM usuarios ORDER BY nome_completo, usuario'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Usuários - Acampamento "No Limite com Cristo"</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1000px; /* Padronizado com as demais telas */
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Menu superior padronizado idêntico às demais telas */
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

        /* Alertas */
        .notice { 
            margin-bottom: 20px; 
            padding: 12px 15px; 
            border-radius: 6px; 
            background: #dcfce7; 
            color: #166534; 
            font-size: 13px;
            font-weight: bold;
            text-align: center;
        } 
        .notice.error { 
            background: #fee2e2; 
            color: #b91c1c; 
        }

        /* Painéis e Formulários */
        .panel { 
            margin-bottom: 25px; 
            padding: 20px; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            background: #ffffff; 
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 15px; 
        } 
        label { 
            display: block; 
            margin-bottom: 6px; 
            color: #475569; 
            font-size: 13px; 
            font-weight: bold; 
        }
        input, select { 
            width: 100%; 
            height: 38px; 
            padding: 0 10px; 
            border: 1px solid #cbd5e1; 
            border-radius: 4px; 
            background: #f8fafc; 
            color: #334155; 
            font: inherit; 
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
        }
        input:focus, select:focus {
            border-color: #3b82f6;
            background: #fff;
        }
        button { 
            height: 38px; 
            padding: 0 20px; 
            border: 0; 
            border-radius: 4px; 
            background: #3b82f6; 
            color: white; 
            font: inherit; 
            font-weight: bold; 
            font-size: 13px;
            cursor: pointer; 
            transition: background 0.2s;
        } 
        button:hover {
            background: #2563eb;
        }
        form.new-user button { 
            margin-top: 15px; 
        }

        /* Tabela */
        .table-wrap { 
            overflow-x: auto; 
        } 
        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
            font-size: 13px; 
        } 
        th, td { 
            padding: 12px 10px; 
            border-bottom: 1px solid #e2e8f0; 
            vertical-align: middle;
            color: #334155;
        } 
        th { 
            background: #f8fafc;
            color: #475569; 
            font-size: 11px; 
            font-weight: bold;
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            white-space: nowrap; 
        } 
        tr:hover {
            background-color: #f8fafc;
        }
        td small { 
            display: block; 
            margin-top: 3px; 
            color: #64748b; 
            font-size: 12px; 
        } 
        .badge { 
            display: inline-block; 
            padding: 3px 8px; 
            border-radius: 4px; 
            background: #e2e8f0; 
            color: #334155; 
            font-size: 11px; 
            font-weight: bold;
        } 
        .badge.off { 
            background: #fee2e2; 
            color: #b91c1c; 
        }
        .inline { 
            display: flex; 
            align-items: center; 
            gap: 6px; 
        } 
        .inline select { 
            width: auto; 
            height: 32px; 
            padding: 0 6px;
        } 
        .inline button { 
            height: 32px; 
            padding: 0 10px; 
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

        @media (max-width: 760px) { 
            .grid { grid-template-columns: 1fr; } 
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
        <a href="buscar_mala.php">Malas</a>
        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'SuperAdmin'): ?>
            <a href="admin_usuarios.php" class="active">Usuários</a>
            <a href="logs_auditoria.php">Auditoria</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <!-- Cabeçalho Centralizado Padronizado -->
    <div class="page-header">
        <h1>Controle de Usuários</h1>
        <p>Acesso exclusivo para SuperAdmin · cadastros e offboarding</p>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="notice" role="status"><?php echo escape_html($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro !== ''): ?>
        <div class="notice error" role="alert"><?php echo escape_html($erro); ?></div>
    <?php endif; ?>

    <!-- Painel de Cadastro -->
    <section class="panel">
        <div class="section-title">Cadastrar Novo Usuário</div>
        <form class="new-user" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
            <input type="hidden" name="acao" value="criar">
            <div class="grid">
                <div>
                    <label for="nome_completo">Nome completo</label>
                    <input id="nome_completo" name="nome_completo" maxlength="150" required placeholder="Nome e sobrenome">
                </div>
                <div>
                    <label for="usuario">Usuário corporativo</label>
                    <input id="usuario" name="usuario" minlength="4" maxlength="254" pattern="[a-z0-9._]+" autocapitalize="none" required placeholder="Ex: joao.silva">
                </div>
                <div>
                    <label for="email">E-mail corporativo</label>
                    <input id="email" name="email" type="email" maxlength="254" required placeholder="email@exemplo.com">
                </div>
                <div>
                    <label for="perfil">Perfil</label>
                    <select id="perfil" name="perfil">
                        <option value="AdminComum">AdminComum</option>
                        <option value="SuperAdmin">SuperAdmin</option>
                    </select>
                </div>
                <div>
                    <label for="senha">Senha inicial (mín. 12 caracteres)</label>
                    <input id="senha" name="senha" type="password" minlength="12" autocomplete="new-password" required placeholder="••••••••••••">
                </div>
            </div>
            <button type="submit">Cadastrar Conta</button>
        </form>
    </section>

    <!-- Painel da Listagem -->
    <section class="panel">
        <div class="section-title">Contas Cadastradas (<?php echo (int) $usuarios->num_rows; ?>)</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Último acesso</th>
                        <th style="text-align: right;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($user = $usuarios->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo escape_html($user['nome_completo']); ?></strong></td>
                        <td><?php echo escape_html($user['usuario']); ?></td>
                        <td><?php echo escape_html($user['email'] ?? '—'); ?></td>
                        <td><span class="badge"><?php echo escape_html($user['perfil']); ?></span></td>
                        <td><span class="badge <?php echo $user['status'] === 'Ativo' ? '' : 'off'; ?>"><?php echo escape_html($user['status']); ?></span></td>
                        <td><?php echo $user['ultimo_acesso'] ? escape_html($user['ultimo_acesso']) : 'Nunca'; ?></td>
                        <td style="text-align: right;">
                            <form class="inline" method="post" style="justify-content: flex-end;">
                                <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="usuario_id" value="<?php echo (int) $user['id']; ?>">
                                <select name="novo_status" aria-label="Status de <?php echo escape_html($user['usuario']); ?>">
                                    <option value="Ativo" <?php echo $user['status'] === 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="Inativo" <?php echo $user['status'] === 'Inativo' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                                <button type="submit">Salvar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
<?php $conn->close(); ?>