<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_superadmin($conn);

$mensagem = '';
$erro = '';
$paginas = accessible_pages();

function paginas_solicitadas(array $disponiveis): array
{
    $solicitadas = $_POST['paginas'] ?? [];
    if (!is_array($solicitadas)) {
        return [];
    }
    return array_values(array_intersect(array_keys($disponiveis), array_filter($solicitadas, 'is_string')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $nome = is_string($_POST['nome_completo'] ?? null) ? trim($_POST['nome_completo']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
        $perfil = is_string($_POST['perfil'] ?? null) ? $_POST['perfil'] : 'Admin';
        $selecionadas = paginas_solicitadas($paginas);
        if (
            $usuario === '' || $nome === '' || strlen($senha) < 12 || strlen($senha) > 200
            || !in_array($perfil, ['Admin', 'SuperAdmin', 'Colaborador'], true)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $erro = 'Preencha os campos corretamente. A senha inicial deve ter pelo menos 12 caracteres.';
        } else {
            try {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                $stmt = $conn->prepare("INSERT INTO usuarios (usuario, nome_completo, email, senha, perfil, status, exige_troca_senha) VALUES (?, ?, NULLIF(?, ''), ?, ?, 'Ativo', 1)");
                $stmt->bind_param('sssss', $usuario, $nome, $email, $hash, $perfil);
                $stmt->execute();
                $userId = (int) $conn->insert_id;
                $stmt->close();
                $permissionStmt = $conn->prepare('INSERT INTO permissoes_paginas (usuario_id, pagina) VALUES (?, ?)');
                foreach ($selecionadas as $pagina) {
                    $permissionStmt->bind_param('is', $userId, $pagina);
                    $permissionStmt->execute();
                }
                $permissionStmt->close();
                audit_event($conn, 'usuario', (int) $_SESSION['usuario_id'], $usuario, 'Criação de usuário', 'Novo usuário adicionado.', 'sucesso');
                $conn->commit();
                $mensagem = 'Usuário cadastrado com sucesso!';
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $erro = 'Erro ao cadastrar usuário (verifique se o login ou e-mail já existem).';
            }
        }
    } elseif ($acao === 'permissoes') {
        $userId = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $selecionadas = paginas_solicitadas($paginas);
        if (!$userId) {
            $erro = 'Usuário inválido.';
        } else {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('SELECT usuario, perfil FROM usuarios WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$target) {
                    throw new RuntimeException('Usuário não encontrado.');
                }
                if ($target['perfil'] !== 'SuperAdmin') {
                    $stmt = $conn->prepare('DELETE FROM permissoes_paginas WHERE usuario_id = ?');
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $stmt->close();
                    $permissionStmt = $conn->prepare('INSERT INTO permissoes_paginas (usuario_id, pagina) VALUES (?, ?)');
                    foreach ($selecionadas as $pagina) {
                        $permissionStmt->bind_param('is', $userId, $pagina);
                        $permissionStmt->execute();
                    }
                    $permissionStmt->close();
                }
                audit_event($conn, 'usuario', (int) $_SESSION['usuario_id'], $target['usuario'], 'Alteração de permissões', 'Permissões de páginas atualizadas.', 'sucesso');
                $conn->commit();
                $mensagem = 'Permissões atualizadas com sucesso.';
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $erro = 'Não foi possível atualizar as permissões do usuário.';
            } catch (RuntimeException $e) {
                $conn->rollback();
                $erro = $e->getMessage();
            }
        }
    } elseif ($acao === 'status' || $acao === 'excluir') {
        $userId = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId) {
            $erro = 'Usuário inválido.';
        } elseif ((int) $userId === (int) $_SESSION['usuario_id']) {
            $erro = 'Não é permitido alterar ou excluir o status da própria conta.';
        } else {
            try {
                $stmt = $conn->prepare('SELECT usuario, status FROM usuarios WHERE id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$target) {
                    $erro = 'Usuário não encontrado.';
                } elseif ($acao === 'status') {
                    $novoStatus = $target['status'] === 'Ativo' ? 'Inativo' : 'Ativo';
                    $conn->begin_transaction();
                    $stmt = $conn->prepare('UPDATE usuarios SET status = ? WHERE id = ?');
                    $stmt->bind_param('si', $novoStatus, $userId);
                    $stmt->execute();
                    $stmt->close();
                    audit_event($conn, 'usuario', (int) $_SESSION['usuario_id'], $target['usuario'], 'Alteração de status', 'Status alterado para ' . $novoStatus . '.', 'sucesso');
                    $conn->commit();
                    $mensagem = 'Status do usuário atualizado.';
                } else {
                    $conn->begin_transaction();
                    $stmt = $conn->prepare('DELETE FROM usuarios WHERE id = ?');
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $stmt->close();
                    audit_event($conn, 'usuario', (int) $_SESSION['usuario_id'], $target['usuario'], 'Exclusão de usuário', 'Conta removida pelo SuperAdmin.', 'sucesso');
                    $conn->commit();
                    $mensagem = 'Usuário excluído com sucesso.';
                }
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $erro = $acao === 'excluir'
                    ? 'Não foi possível excluir o usuário porque há registros vinculados ou uma restrição no banco de dados.'
                    : 'Não foi possível atualizar o status do usuário.';
            }
        }
    }
}

$usuarios = $conn->query("SELECT id, usuario, nome_completo, email, perfil, status, ultimo_acesso FROM usuarios ORDER BY id DESC");
$permissoesPorUsuario = [];
$permissionRows = $conn->query('SELECT usuario_id, pagina FROM permissoes_paginas');
while ($permission = $permissionRows->fetch_assoc()) {
    $permissoesPorUsuario[(int) $permission['usuario_id']][] = $permission['pagina'];
}

$paginaAtual = basename($_SERVER['PHP_SELF'] ?? 'admin_usuarios.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - No Limite com Cristo</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 32px 16px;
        }
        .container {
            max-width: 1020px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.06), 0 4px 10px -2px rgba(0, 0, 0, 0.04);
            padding: 32px 36px 36px;
        }
        .top-nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
        }
        .nav-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 14px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: #334155;
            background-color: #e2e8f0;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .nav-pill:hover {
            background-color: #cbd5e1;
            color: #0f172a;
        }
        .nav-pill.active {
            background-color: #2563eb;
            color: #ffffff;
        }
        .nav-pill.logout {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .nav-pill.logout:hover {
            background-color: #fecaca;
            color: #991b1b;
        }
        .page-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .page-header h1 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.015em;
        }
        .page-header p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }
        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 22px 24px;
            background-color: #ffffff;
            margin-bottom: 24px;
        }
        .section-title {
            margin: 0 0 18px;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .field input,
        .field select {
            width: 100%;
            height: 42px;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            color: #111827;
            background-color: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .field input:focus,
        .field select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }
        .field-submit {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .permissions-box {
            grid-column: 1 / -1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            background-color: #f9fafb;
            margin-top: 2px;
        }
        .permissions-box legend {
            padding: 0 6px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .checkbox-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 20px;
            margin-top: 4px;
        }
        .checkbox-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: #1f2937;
            cursor: pointer;
        }
        .checkbox-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            margin: 0;
            accent-color: #2563eb;
            cursor: pointer;
        }
        .helper-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }
        .btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
            height: 42px;
            padding: 0 18px;
            font-size: 14px;
            width: 100%;
        }
        .btn-primary:hover:not(:disabled) {
            background-color: #1d4ed8;
        }
        .btn-sm {
            padding: 6px 11px;
            font-size: 12px;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover:not(:disabled) {
            background-color: #cbd5e1;
            color: #0f172a;
        }
        .btn-danger {
            background-color: #dc2626;
            color: #ffffff;
        }
        .btn-danger:hover:not(:disabled) {
            background-color: #b91c1c;
        }
        .btn-save-perm {
            background-color: #2563eb;
            color: #ffffff;
            padding: 7px 12px;
            font-size: 12px;
            margin-top: 10px;
            width: 100%;
        }
        .btn-save-perm:hover:not(:disabled) {
            background-color: #1d4ed8;
        }
        .msg {
            padding: 12px 16px;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 500;
        }
        .err {
            padding: 12px 16px;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 500;
        }
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 11px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            white-space: nowrap;
        }
        tbody tr:hover {
            background-color: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
        }
        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-inactive {
            background-color: #f1f5f9;
            color: #475569;
        }
        .actions-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .actions-row form {
            margin: 0;
        }
        details.perm-dropdown {
            width: 100%;
        }
        details.perm-dropdown > summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #2563eb;
            cursor: pointer;
            user-select: none;
        }
        details.perm-dropdown > summary::-webkit-details-marker {
            display: none;
        }
        details.perm-dropdown > summary::before {
            content: "▸";
            font-size: 11px;
            transition: transform 0.15s ease;
        }
        details.perm-dropdown[open] > summary::before {
            transform: rotate(90deg);
        }
        .perm-panel {
            margin-top: 8px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f8fafc;
            min-width: 210px;
        }
        .perm-panel label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            color: #1f2937;
            padding: 3px 0;
            white-space: nowrap;
            cursor: pointer;
        }
        .perm-panel input[type="checkbox"] {
            margin: 0;
            accent-color: #2563eb;
        }
        .perm-note {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
            white-space: normal;
        }
        .footer-link {
            margin-top: 8px;
        }
        .footer-link a {
            color: #2563eb;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
        }
        .footer-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 720px) {
            .container { padding: 22px 18px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <nav class="top-nav" aria-label="Navegação principal">
        <a href="home.php" class="nav-pill<?php echo $paginaAtual === 'home.php' ? ' active' : ''; ?>">Início</a>
        <a href="inscricao.php" class="nav-pill<?php echo $paginaAtual === 'inscricao.php' ? ' active' : ''; ?>">Nova Inscrição</a>
        <a href="painel.php" class="nav-pill<?php echo $paginaAtual === 'painel.php' ? ' active' : ''; ?>">Painel Geral</a>
        <a href="comunicacao.php" class="nav-pill<?php echo $paginaAtual === 'comunicacao.php' ? ' active' : ''; ?>">Comunicação</a>
        <a href="buscar_mala.php" class="nav-pill<?php echo $paginaAtual === 'buscar_mala.php' ? ' active' : ''; ?>">Malas</a>
        <a href="admin_usuarios.php" class="nav-pill<?php echo $paginaAtual === 'admin_usuarios.php' ? ' active' : ''; ?>">Usuários</a>
        <a href="auditoria.php" class="nav-pill<?php echo $paginaAtual === 'auditoria.php' ? ' active' : ''; ?>">Auditoria</a>
        <a href="logout.php" class="nav-pill logout">Sair</a>
    </nav>

    <div class="page-header">
        <h1>Gerenciamento de Usuários do Sistema</h1>
        <p>Cadastre novos acessos, defina permissões por tela e gerencie contas ativas</p>
    </div>

    <?php if ($mensagem): ?><div class="msg"><?php echo escape_html($mensagem); ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="err"><?php echo escape_html($erro); ?></div><?php endif; ?>

    <section class="section-card">
        <h2 class="section-title">Cadastrar Novo Acesso</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
            <input type="hidden" name="acao" value="criar">

            <div class="field">
                <label for="nome_completo">Nome Completo</label>
                <input id="nome_completo" type="text" name="nome_completo" required>
            </div>

            <div class="field">
                <label for="usuario">Nome de Usuário (login)</label>
                <input id="usuario" type="text" name="usuario" required>
            </div>

            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" required maxlength="254">
            </div>

            <div class="field">
                <label for="senha">Senha Inicial</label>
                <input id="senha" type="password" name="senha" required minlength="12" maxlength="200" autocomplete="new-password">
            </div>

            <div class="field">
                <label for="perfil">Perfil</label>
                <select id="perfil" name="perfil">
                    <option value="Admin">Admin</option>
                    <option value="SuperAdmin">SuperAdmin</option>
                    <option value="Colaborador">Colaborador</option>
                </select>
            </div>

            <div class="field-submit">
                <button type="submit" class="btn btn-primary">Salvar Usuário</button>
            </div>

            <fieldset class="permissions-box">
                <legend>Permissões de Acesso (Páginas / Telas)</legend>
                <div class="checkbox-list">
                    <?php foreach ($paginas as $pagina => $rotulo): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="paginas[]" value="<?php echo escape_html($pagina); ?>" checked>
                            <span><?php echo escape_html($rotulo); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="helper-text">SuperAdmins mantêm acesso total independentemente destas marcações.</div>
            </fieldset>
        </form>
    </section>

    <section class="section-card">
        <h2 class="section-title">Usuários Cadastrados</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Nome</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Último Acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $usuarios->fetch_assoc()): ?>
                        <?php $userId = (int) $row['id']; ?>
                        <tr>
                            <td><?php echo $userId; ?></td>
                            <td><?php echo escape_html($row['usuario']); ?></td>
                            <td><?php echo escape_html($row['nome_completo']); ?></td>
                            <td><?php echo escape_html($row['perfil']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status'] === 'Ativo' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo escape_html($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo escape_html($row['ultimo_acesso'] ?? 'Nunca'); ?></td>
                            <td>
                                <div class="actions-cell">
                                    <details class="perm-dropdown">
                                        <summary>Permissões</summary>
                                        <form method="post" class="perm-panel">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                            <input type="hidden" name="acao" value="permissoes">
                                            <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                                            <?php foreach ($paginas as $pagina => $rotulo): ?>
                                                <label>
                                                    <input type="checkbox" name="paginas[]" value="<?php echo escape_html($pagina); ?>"
                                                        <?php echo $row['perfil'] === 'SuperAdmin' || in_array($pagina, $permissoesPorUsuario[$userId] ?? [], true) ? 'checked' : ''; ?>
                                                        <?php echo $row['perfil'] === 'SuperAdmin' ? 'disabled' : ''; ?>>
                                                    <span><?php echo escape_html($rotulo); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if ($row['perfil'] === 'SuperAdmin'): ?>
                                                <small class="perm-note">Acesso total para SuperAdmin.</small>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-save-perm">Salvar permissões</button>
                                        </form>
                                    </details>

                                    <div class="actions-row">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                            <input type="hidden" name="acao" value="status">
                                            <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" <?php echo $userId === (int) $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                <?php echo $row['status'] === 'Ativo' ? 'Inativar' : 'Ativar'; ?>
                                            </button>
                                        </form>

                                        <form method="post" onsubmit="return confirm('Confirma a exclusão permanente deste usuário? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo $userId === (int) $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer-link">
        <a href="home.php">&larr; Voltar ao Painel Principal</a>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>