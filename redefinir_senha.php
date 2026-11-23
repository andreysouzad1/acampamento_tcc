<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

header('Referrer-Policy: no-referrer');

$mensagem = '';
$erro = '';
$tokenInput = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($tokenInput) ? $tokenInput : '';
$tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$definindoSenha = $tokenValido;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'solicitar') {
    $mensagem = 'Se o e-mail informado estiver vinculado a uma conta ativa, enviaremos as instruções para redefinir a senha.';
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS tentativas FROM logs_auditoria
         WHERE tipo = 'password_reset' AND ip = ? AND criado_em >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)"
    );
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $rateLimited = (int) $stmt->get_result()->fetch_assoc()['tentativas'] >= 5;
    $stmt->close();

    $csrfValido = csrf_is_valid();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $usuario = null;
    if ($csrfValido && !$rateLimited && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare(
            "SELECT id, usuario, email FROM usuarios
             WHERE email = ? AND status = 'Ativo' LIMIT 2"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $matches = $stmt->get_result();
        if ($matches->num_rows === 1) {
            $usuario = $matches->fetch_assoc();
        }
        $stmt->close();
    }

    $auditoriaId = $usuario ? (int) $usuario['id'] : null;
    $auditoriaUsuario = $usuario ? (string) $usuario['usuario'] : '(solicitação de redefinição)';
    if ($csrfValido && !$rateLimited && $usuario) {
        $appUrl = trim((string) (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '')));
        $smtpHost = trim((string) (getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? '')));
        $smtpPort = (int) (getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587));
        $smtpUser = trim((string) (getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? ''))));
        $smtpPass = (string) (getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? '')));
        $smtpFrom = trim((string) (getenv('SMTP_FROM') ?: ($_ENV['SMTP_FROM'] ?? $smtpUser)));
        $appParts = parse_url($appUrl);
        $appHost = is_array($appParts) ? strtolower((string) ($appParts['host'] ?? '')) : '';
        $appScheme = is_array($appParts) ? strtolower((string) ($appParts['scheme'] ?? '')) : '';
        $urlValida = filter_var($appUrl, FILTER_VALIDATE_URL)
            && in_array($appScheme, ['https', 'http'], true)
            && ($appScheme === 'https' || in_array($appHost, ['localhost', '127.0.0.1'], true))
            && empty($appParts['query'])
            && empty($appParts['fragment']);

        if ($urlValida && $smtpHost !== '' && $smtpPort > 0 && $smtpUser !== '' && $smtpPass !== ''
            && filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('DELETE FROM tokens_redefinicao_senha WHERE usuario_id = ?');
                $stmt->bind_param('i', $usuario['id']);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare(
                    'INSERT INTO tokens_redefinicao_senha (usuario_id, token_hash, expira_em)
                     VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
                );
                $stmt->bind_param('is', $usuario['id'], $tokenHash);
                $stmt->execute();
                $stmt->close();
                $conn->commit();

                $resetUrl = rtrim($appUrl, '/') . '/redefinir_senha.php?token=' . rawurlencode($token);
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
                $mail->SMTPSecure = $smtpPort === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $smtpPort;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($smtpFrom, 'Acampamento - Acesso corporativo');
                $mail->addAddress((string) $usuario['email']);
                $mail->isHTML(false);
                $mail->Subject = 'Redefinição de senha - Acesso corporativo';
                $mail->Body = "Recebemos uma solicitação para redefinir a senha da sua conta.\n\n"
                    . "Acesse o link abaixo em até 30 minutos para criar uma nova senha:\n{$resetUrl}\n\n"
                    . "Se você não solicitou a redefinição, ignore esta mensagem.";
                $mail->send();
                audit_event($conn, 'password_reset', $auditoriaId, $auditoriaUsuario, 'Solicitação de redefinição de senha', 'Link de redefinição enviado.', 'sucesso');
            } catch (MailException $e) {
                $conn->rollback();
                $stmt = $conn->prepare('DELETE FROM tokens_redefinicao_senha WHERE token_hash = ?');
                $stmt->bind_param('s', $tokenHash);
                $stmt->execute();
                $stmt->close();
                error_log('Falha ao enviar e-mail de redefinição de senha: ' . $e->getMessage());
                audit_event($conn, 'password_reset', $auditoriaId, $auditoriaUsuario, 'Falha ao enviar redefinição de senha', 'O serviço de e-mail não concluiu o envio.', 'falha');
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                error_log('Falha na operação de redefinição de senha: ' . $e->getMessage());
                audit_event($conn, 'password_reset', $auditoriaId, $auditoriaUsuario, 'Falha ao preparar redefinição de senha', 'Não foi possível registrar o token.', 'falha');
            }
        } else {
            error_log('Redefinição de senha indisponível: configure APP_URL e SMTP.');
            audit_event($conn, 'password_reset', $auditoriaId, $auditoriaUsuario, 'Redefinição de senha indisponível', 'Configuração de e-mail ausente ou inválida.', 'falha');
        }
    } else {
        audit_event(
            $conn,
            'password_reset',
            $auditoriaId,
            $auditoriaUsuario,
            $csrfValido ? 'Solicitação de redefinição de senha' : 'Solicitação de redefinição inválida',
            $rateLimited ? 'Limite de solicitações excedido.' : ($csrfValido ? 'Solicitação não associada a uma conta ativa.' : 'Token CSRF inválido.'),
            'falha'
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'definir') {
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    $tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    $definindoSenha = true;
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $confirmacao = is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '';
    if (!csrf_is_valid()) {
        $erro = 'Solicitação inválida. Atualize a página e tente novamente.';
    } elseif (strlen($senha) < 12 || strlen($senha) > 200 || $senha !== $confirmacao) {
        $erro = 'Use uma senha com pelo menos 12 caracteres e confirme-a corretamente.';
    } elseif (!$tokenValido) {
        $erro = 'Este link é inválido ou expirou. Solicite uma nova redefinição.';
    } else {
        $tokenHash = hash('sha256', $token);
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                'SELECT usuario_id FROM tokens_redefinicao_senha
                 WHERE token_hash = ? AND expira_em > CURRENT_TIMESTAMP FOR UPDATE'
            );
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $tokenRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$tokenRow) {
                $conn->rollback();
                $erro = 'Este link é inválido ou expirou. Solicite uma nova redefinição.';
                $definindoSenha = false;
            } else {
                $userId = (int) $tokenRow['usuario_id'];
                $passwordHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE usuarios SET senha = ?, exige_troca_senha = 0 WHERE id = ?');
                $stmt->bind_param('si', $passwordHash, $userId);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare('DELETE FROM tokens_redefinicao_senha WHERE usuario_id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                audit_event($conn, 'password_reset', $userId, '(conta)', 'Senha redefinida por e-mail', 'Senha alterada por token de uso único.', 'sucesso');
                $mensagem = 'Senha redefinida com sucesso. Você já pode entrar com a nova senha.';
                $definindoSenha = false;
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('Falha ao concluir redefinição de senha: ' . $e->getMessage());
            $erro = 'Não foi possível redefinir a senha. Solicite um novo link e tente novamente.';
        }
    }
}

if ($definindoSenha && $tokenValido && $erro === '' && $mensagem === '') {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT id FROM tokens_redefinicao_senha
         WHERE token_hash = ? AND expira_em > CURRENT_TIMESTAMP LIMIT 1'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $definindoSenha = $stmt->get_result()->num_rows === 1;
    $stmt->close();
}

if (!$definindoSenha && $token !== '' && $mensagem === '' && $erro === '') {
    $erro = 'Este link é inválido ou expirou. Solicite uma nova redefinição.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light">
    <title>Redefinir senha - Acesso corporativo</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #333; background: #f4f4f9; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 32px 18px; background-color: #f4f4f9; }
        .shell { width: min(100%, 420px); }
        .brand { display: flex; align-items: center; gap: 12px; margin: 0 0 22px 4px; color: #1e293b; }
        .brand-mark { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 6px; color: #fff; background: #3b82f6; font-size: 15px; font-weight: bold; letter-spacing: .04em; }
        .brand-name { font-size: 15px; font-weight: bold; line-height: 1.35; }
        .brand-name span { display: block; color: #64748b; font-size: 11px; font-weight: normal; letter-spacing: .08em; text-transform: uppercase; }
        .card { background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { margin: 0 0 20px; color: #1e293b; font-size: 22px; font-weight: bold; }
        p { color: #64748b; font-size: 13px; line-height: 1.5; }
        .field { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; color: #475569; font-size: 13px; font-weight: bold; }
        input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 4px; background: #f8fafc; color: #334155; font: inherit; font-size: 13px; }
        .submit { width: 100%; height: 38px; margin-top: 5px; border: 0; border-radius: 4px; background: #3b82f6; color: #fff; font: inherit; font-weight: bold; font-size: 13px; cursor: pointer; }
        .submit:hover { background: #2563eb; }
        .error, .message { margin-bottom: 20px; padding: 12px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; text-align: center; }
        .error { background: #fee2e2; color: #b91c1c; }
        .message { background: #d1fae5; color: #065f46; }
        .back { display: block; margin-top: 18px; color: #2563eb; font-size: 12px; text-align: center; }
        @media (max-width: 480px) { body { padding: 20px 14px; } .card { padding: 20px; } h1 { font-size: 20px; } }
    </style>
</head>
<body>
<main class="shell">
    <div class="brand" aria-label="Acampamento No Limite com Cristo, acesso interno">
        <div class="brand-mark" aria-hidden="true">NL</div>
        <div class="brand-name">Acampamento "No Limite com Cristo"<span>Plataforma interna</span></div>
    </div>
    <section class="card" aria-labelledby="reset-title">
        <h1 id="reset-title"><?php echo $definindoSenha ? 'Definir nova senha' : 'Redefinir senha'; ?></h1>
        <?php if ($erro !== ''): ?><div class="error" role="alert"><?php echo escape_html($erro); ?></div><?php endif; ?>
        <?php if ($mensagem !== ''): ?><div class="message" role="status"><?php echo escape_html($mensagem); ?></div><?php endif; ?>
        <?php if ($definindoSenha): ?>
            <p>Escolha uma senha com pelo menos 12 caracteres.</p>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                <input type="hidden" name="acao" value="definir">
                <input type="hidden" name="token" value="<?php echo escape_html($token); ?>">
                <div class="field"><label for="senha">Nova senha</label><input id="senha" type="password" name="senha" minlength="12" maxlength="200" required autocomplete="new-password"></div>
                <div class="field"><label for="confirmacao">Confirme a nova senha</label><input id="confirmacao" type="password" name="confirmacao" minlength="12" maxlength="200" required autocomplete="new-password"></div>
                <button class="submit" type="submit">Salvar nova senha</button>
            </form>
        <?php elseif ($mensagem === '' || $erro !== ''): ?>
            <p>Informe o e-mail cadastrado na sua conta. Se houver uma conta ativa associada, enviaremos um link seguro.</p>
            <form method="post" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                <input type="hidden" name="acao" value="solicitar">
                <div class="field"><label for="email">E-mail cadastrado</label><input id="email" type="email" name="email" maxlength="254" required autocomplete="email"></div>
                <button class="submit" type="submit">Enviar instruções</button>
            </form>
        <?php endif; ?>
        <a class="back" href="login.php">Voltar ao acesso corporativo</a>
    </section>
</main>
</body>
</html>
<?php $conn->close(); ?>
