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
        if ($usuario === '' || $nome === '' || strlen($senha) < 12 || strlen($senha) > 200
            || !in_array($perfil, ['Admin', 'SuperAdmin', 'Colaborador'], true)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    <title>Gerenciar Usuários - No Limite com Cristo</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; margin-bottom: 20px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 13px; }
        th { background: #f8fafc; color: #475569; }
        .btn { background: #3b82f6; color: #fff; border: 0; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn:hover { background: #2563eb; }
        .msg { padding: 10px; background: #d1fae5; color: #065f46; border-radius: 4px; margin-bottom: 15px; font-size: 13px; }
        .err { padding: 10px; background: #fee2e2; color: #b91c1c; border-radius: 4px; margin-bottom: 15px; font-size: 13px; }
        .permissions { grid-column: 1 / -1; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; }
        .permissions label { display: inline-flex; align-items: center; gap: 5px; margin: 8px 14px 0 0; font-size: 12px; }
        .permissions input { margin: 0; }
        .actions form { display: inline-block; margin: 2px; }
        .actions .btn { white-space: nowrap; }
        .secondary { background: #64748b; }
        .danger { background: #b91c1c; }
    </style>
</head>
<body>
<div class="container">
    <h1>Gerenciamento de Usuários do Sistema</h1>
    
    <?php if ($mensagem): ?><div class="msg"><?php echo escape_html($mensagem); ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="err"><?php echo escape_html($erro); ?></div><?php endif; ?>

    <h3>Cadastrar Novo Acesso</h3>
    <form method="post" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 30px;">
        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
        <input type="hidden" name="acao" value="criar">
        
        <div>
            <label style="font-size:12px; font-weight:bold;">Nome Completo</label>
            <input type="text" name="nome_completo" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Nome de Usuário (login)</label>
            <input type="text" name="usuario" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">E-mail</label>
            <input type="email" name="email" required maxlength="254" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Senha Inicial</label>
            <input type="password" name="senha" required minlength="12" maxlength="200" autocomplete="new-password" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Perfil</label>
            <select name="perfil" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                <option value="Admin">Admin</option>
                <option value="SuperAdmin">SuperAdmin</option>
                <option value="Colaborador">Colaborador</option>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn" style="width:100%; height: 35px;">Salvar Usuário</button>
        </div>
        <fieldset class="permissions">
            <legend style="font-size:12px; font-weight:bold;">Permissões de Acesso (Páginas / Telas)</legend>
            <?php foreach ($paginas as $pagina => $rotulo): ?>
                <label><input type="checkbox" name="paginas[]" value="<?php echo escape_html($pagina); ?>" checked> <?php echo escape_html($rotulo); ?></label>
            <?php endforeach; ?>
            <div style="font-size:11px; color:#64748b; margin-top:8px;">SuperAdmins mantêm acesso total independentemente destas marcações.</div>
        </fieldset>
    </form>

    <h3>Usuários Cadastrados</h3>
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
            <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo escape_html($row['usuario']); ?></td>
                <td><?php echo escape_html($row['nome_completo']); ?></td>
                <td><?php echo escape_html($row['perfil']); ?></td>
                <td><?php echo escape_html($row['status']); ?></td>
                <td><?php echo escape_html($row['ultimo_acesso'] ?? 'Nunca'); ?></td>
                <td class="actions">
                    <?php $userId = (int) $row['id']; ?>
                    <details>
                        <summary>Permissões</summary>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                            <input type="hidden" name="acao" value="permissoes">
                            <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                            <?php foreach ($paginas as $pagina => $rotulo): ?>
                                <label style="display:block; white-space:nowrap;">
                                    <input type="checkbox" name="paginas[]" value="<?php echo escape_html($pagina); ?>"
                                        <?php echo $row['perfil'] === 'SuperAdmin' || in_array($pagina, $permissoesPorUsuario[$userId] ?? [], true) ? 'checked' : ''; ?>
                                        <?php echo $row['perfil'] === 'SuperAdmin' ? 'disabled' : ''; ?>>
                                    <?php echo escape_html($rotulo); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($row['perfil'] === 'SuperAdmin'): ?><small>Acesso total para SuperAdmin.</small><?php endif; ?>
                            <button type="submit" class="btn" style="margin-top:6px;">Salvar permissões</button>
                        </form>
                    </details>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                        <input type="hidden" name="acao" value="status">
                        <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                        <button type="submit" class="btn secondary" <?php echo $userId === (int) $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                            <?php echo $row['status'] === 'Ativo' ? 'Inativar' : 'Ativar'; ?>
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('Confirma a exclusão permanente deste usuário? Esta ação não pode ser desfeita.');">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="usuario_id" value="<?php echo $userId; ?>">
                        <button type="submit" class="btn danger" <?php echo $userId === (int) $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>Excluir</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px;"><a href="home.php">&larr; Voltar ao Painel Principal</a></p>
</div>
</body>
</html>
<?php $conn->close(); ?>