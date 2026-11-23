<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['senha'] ?? '');
    $confirmation = (string) ($_POST['confirmacao'] ?? '');
    if (strlen($password) < 12 || strlen($password) > 200 || $password !== $confirmation) {
        $erro = 'Use uma senha com pelo menos 12 caracteres e confirme-a corretamente.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = (int) $_SESSION['usuario_id'];
        $stmt = $conn->prepare('UPDATE usuarios SET senha = ?, exige_troca_senha = 0 WHERE id = ?');
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();
        audit_event(
            $conn,
            'administrativo',
            $userId,
            (string) $_SESSION['usuario_nome'],
            'Alteração de senha',
            'Senha alterada pelo próprio usuário.',
            'sucesso'
        );
        $destino = default_user_page($conn, $userId, (string) $_SESSION['usuario_perfil']);
        header('Location: ' . $destino . '?senha=alterada');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Troca de senha obrigatória</title>
    <style>
        * { box-sizing: border-box; } body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 18px; background: #f3f5f8; color: #172033; font: 14px "Segoe UI", Arial, sans-serif; }
        main { width: min(100%, 440px); padding: 32px; border: 1px solid #e0e6ed; border-radius: 12px; background: #fff; box-shadow: 0 12px 38px rgba(20,42,72,.07); }
        h1 { margin: 0 0 10px; color: #142a48; font-size: 23px; } p { color: #66758a; line-height: 1.5; } label { display: block; margin: 16px 0 7px; color: #43536a; font-weight: 650; }
        input { width: 100%; height: 44px; padding: 0 11px; border: 1px solid #ccd5e0; border-radius: 7px; font: inherit; } button { width: 100%; height: 44px; margin-top: 20px; border: 0; border-radius: 7px; background: #142a48; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { padding: 10px; border-radius: 7px; background: #fff2f1; color: #8d2929; }
    </style>
</head>
<body>
<main>
    <h1>Defina sua senha pessoal</h1>
    <p>Por segurança, a senha inicial precisa ser alterada antes de continuar. Use no mínimo 12 caracteres.</p>
    <?php if ($erro !== ''): ?><p class="error" role="alert"><?php echo escape_html($erro); ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
        <label for="senha">Nova senha</label>
        <input id="senha" name="senha" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
        <label for="confirmacao">Confirme a nova senha</label>
        <input id="confirmacao" name="confirmacao" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
        <button type="submit">Salvar nova senha</button>
    </form>
</main>
</body>
</html>
<?php $conn->close(); ?>
