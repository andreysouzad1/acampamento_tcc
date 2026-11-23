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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Acampamento "No Limite com Cristo"</title>
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

        /* Menu Superior Padronizado */
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

        /* Cartões Estruturais */
        .section-card {
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
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .form-group {
            margin-bottom: 0;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 13px;
            background-color: #f8fafc;
            color: #334155;
            outline: none;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: #3b82f6;
            background-color: #fff;
        }

        .permissions-box {
            grid-column: 1 / -1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 15px;
        }
        .permissions-box legend {
            font-weight: bold;
            color: #475569;
            font-size: 13px;
            padding: 0 6px;
        }
        .permissions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
        }
        .permissions-list label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            font-weight: normal;
            font-size: 13px;
            color: #334155;
            cursor: pointer;
        }
        .permissions-list input[type="checkbox"] {
            margin: 0;
        }
        .helper-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
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
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-primary {
            background: #3b82f6;
            color: #ffffff;
        }
        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
        }
        .btn-sm {
            height: auto;
            width: auto;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-secondary {
            background: #64748b;
            color: #ffffff;
        }
        .btn-secondary:hover:not(:disabled) {
            background: #475569;
        }
        .btn-danger {
            background: #b91c1c;
            color: #ffffff;
        }
        .btn-danger:hover:not(:disabled) {
            background: #991b1b;
        }
        .btn-save-perm {
            background: #3b82f6;
            color: #ffffff;
            height: auto;
            padding: 6px 12px;
            font-size: 12px;
            margin-top: 8px;
            width: 100%;
        }
        .btn-save-perm:hover:not(:disabled) {
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

        /* Tabela */
        .table-wrap {
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
            font-size: 13px;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-inactive {
            background: #e2e8f0;
            color: #475569;
        }
        .actions {
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
        details summary {
            font-size: 12px;
            font-weight: bold;
            color: #3b82f6;
            cursor: pointer;
        }
        .perm-panel {
            margin-top: 6px;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            min-width: 190px;
        }
        .perm-panel label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: normal;
            font-size: 12px;
            color: #334155;
            margin-bottom: 4px;
            white-space: nowrap;
            cursor: pointer;
        }
        .perm-panel input[type="checkbox"] {
            margin: 0;
        }
        .perm-panel small {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
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
        <h1>Gerenciamento de Usuários do Sistema</h1>
        <p>Cadastre novos acessos, defina permissões por tela e gerencie contas ativas</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert-error"><?php echo escape_html($erro); ?></div>
    <?php endif; ?>

    <?php if (!empty($mensagem)): ?>
        <div class="alert-success"><?php echo escape_html($mensagem); ?></div>
    <?php endif; ?>

    <!-- Card de Cadastro -->
    <div class="section-card">
        <div class="card-header">
            <h3>Cadastrar Novo Acesso</h3>
            <span style="font-size: 12px; color: #64748b;">Acesso exclusivo SuperAdmin</span>
        </div>

        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
            <input type="hidden" name="acao" value="criar">

            <div class="form-group">
                <label for="nome_completo">Nome Completo</label>
                <input id="nome_completo" type="text" name="nome_completo" required>
            </div>

            <div class="form-group">
                <label for="usuario">Nome de Usuário (login)</label>
                <input id="usuario" type="text" name="usuario" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" required maxlength="254">
            </div>

            <div class="form-group">
                <label for="senha">Senha Inicial</label>
                <input id="senha" type="password" name="senha" required minlength="12" maxlength="200" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="perfil">Perfil</label>
                <select id="perfil" name="perfil">
                    <option value="Admin">Admin</option>
                    <option value="SuperAdmin">SuperAdmin</option>
                    <option value="Colaborador">Colaborador</option>
                </select>
            </div>

            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">Salvar Usuário</button>
            </div>

            <fieldset class="permissions-box">
                <legend>Permissões de Acesso (Páginas / Telas)</legend>
                <div class="permissions-list">
                    <?php foreach ($paginas as $pagina => $rotulo): ?>
                        <label>
                            <input type="checkbox" name="paginas[]" value="<?php echo escape_html($pagina); ?>" checked>
                            <?php echo escape_html($rotulo); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="helper-text">SuperAdmins mantêm acesso total independentemente destas marcações.</div>
            </fieldset>
        </form>
    </div>

    <!-- Card de Listagem -->
    <div class="section-card">
        <div class="card-header">
            <h3>Usuários Cadastrados</h3>
            <span style="font-size: 12px; color: #64748b;">Status e permissões</span>
        </div>

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
                            <td class="actions">
                                <details>
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
                                                <?php echo escape_html($rotulo); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if ($row['perfil'] === 'SuperAdmin'): ?>
                                            <small>Acesso total para SuperAdmin.</small>
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
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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