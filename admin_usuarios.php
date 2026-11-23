<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);

if (empty($_SESSION['logado']) || $_SESSION['usuario_perfil'] !== 'SuperAdmin') {
    header('Location: home.php');
    exit();
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_is_valid()) {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'criar') {
        $usuario = trim($_POST['usuario'] ?? '');
        $nome = trim($_POST['nome_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'Admin';
        
        if ($usuario !== '' && $senha !== '') {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (usuario, nome_completo, email, senha, perfil, status, exige_troca_senha) VALUES (?, ?, ?, ?, ?, 'Ativo', 1)");
            $stmt->bind_param('sssss', $usuario, $nome, $email, $hash, $perfil);
            if ($stmt->execute()) {
                $mensagem = 'Usuário cadastrado com sucesso!';
                audit_event($conn, 'usuario', $_SESSION['usuario_id'], $usuario, 'Criação de usuário', 'Novo usuário adicionado.', 'sucesso');
            } else {
                $erro = 'Erro ao cadastrar usuário (verifique se o login ou e-mail já existem).';
            }
            $stmt->close();
        } else {
            $erro = 'Preencha os campos obrigatórios.';
        }
    }
}

$usuarios = $conn->query("SELECT id, usuario, nome_completo, email, perfil, status, ultimo_acesso FROM usuarios ORDER BY id DESC");
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
            <input type="email" name="email" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:bold;">Senha Inicial</label>
            <input type="password" name="senha" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
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
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px;"><a href="home.php">&larr; Voltar ao Painel Principal</a></p>
</div>
</body>
</html>
<?php $conn->close(); ?>