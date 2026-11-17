<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);

if (!empty($_SESSION['logado'])) {
    header('Location: home.php');
    exit();
}

$erro = '';
$identificador = '';
$mensagemErro = 'Usuário ou senha inválidos. Verifique seus dados ou contate o administrador.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfInvalid = !csrf_is_valid();
    $identificador = strtolower((string) ($_POST['usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS tentativas FROM logs_auditoria
         WHERE tipo = 'login' AND status = 'falha' AND ip = ? AND criado_em >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)"
    );
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $rateLimited = (int) $stmt->get_result()->fetch_assoc()['tentativas'] >= 20;
    $stmt->close();

    $identificadorValido = strlen($identificador) >= 4
        && strlen($identificador) <= 254
        && !preg_match('/\s/', $identificador)
        && (filter_var($identificador, FILTER_VALIDATE_EMAIL)
            || preg_match('/^[a-z0-9._]+$/', $identificador));

    $user = null;
    if (!$csrfInvalid && !$rateLimited && $identificadorValido && $senha !== '') {
        $stmt = $conn->prepare(
            "SELECT id, usuario, senha, nome_completo, perfil, status, exige_troca_senha
             FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $identificador, $identificador);
        $stmt->execute();
        $matches = $stmt->get_result();
        if ($matches->num_rows === 1) {
            $user = $matches->fetch_assoc();
        }
        $stmt->close();
    }

    $dummyHash = '$2y$10$Z7CatssNJ/qzoM5DxZu6judPatQiZW27d.kJu1X.8DhtqmPv4raJC';
    $passwordValid = password_verify($senha, $user['senha'] ?? $dummyHash);
    
    if (!$csrfInvalid && !$rateLimited && $user && $user['status'] === 'Ativo' && $passwordValid) {
        session_regenerate_id(true);
        $_SESSION['logado'] = true;
        $_SESSION['usuario_id'] = (int) $user['id'];
        $_SESSION['usuario_nome'] = $user['nome_completo'];
        $_SESSION['usuario_perfil'] = $user['perfil'];
        $_SESSION['ultima_atividade'] = time();

        $stmt = $conn->prepare('UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $stmt->close();
        
        audit_event($conn, 'login', (int) $user['id'], $user['usuario'], 'Início de sessão', 'Autenticação realizada.', 'sucesso');
        header('Location: ' . ((int) $user['exige_troca_senha'] === 1 ? 'trocar_senha.php' : 'home.php'));
        exit();
    }

    audit_event(
        $conn,
        'login',
        $user ? (int) $user['id'] : null,
        $identificador !== '' ? substr($identificador, 0, 254) : '(vazio)',
        $csrfInvalid ? 'Solicitação de login inválida' : 'Tentativa de início de sessão',
        $csrfInvalid ? 'Token CSRF ausente ou expirado.' : 'Tentativa de autenticação.',
        'falha'
    );
    $erro = $mensagemErro;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Acesso corporativo - Acampamento "No Limite com Cristo"</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, sans-serif;
            color: #333;
            background: #f4f4f9;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 18px;
            background-color: #f4f4f9;
        }
        .shell {
            width: min(100%, 420px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 22px 4px;
            color: #1e293b;
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            color: #fff;
            background: #3b82f6;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: .04em;
        }
        .brand-name {
            font-size: 15px;
            font-weight: bold;
            line-height: 1.35;
        }
        .brand-name span {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: normal;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 20px 0;
            color: #1e293b;
            font-size: 22px;
            font-weight: bold;
        }
        .field {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
            font-weight: bold;
        }
        input {
            width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #334155;
            font: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, background .2s;
        }
        input:focus {
            border-color: #3b82f6;
            background: #fff;
        }
        .password-wrap {
            position: relative;
        }
        .password-wrap input {
            padding-right: 65px;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            border: 0;
            padding: 4px 8px;
            background: transparent;
            color: #64748b;
            font: inherit;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }
        .toggle-password:hover {
            color: #1e293b;
        }
        .error {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 6px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
        }
        .submit {
            width: 100%;
            height: 38px;
            margin-top: 5px;
            border: 0;
            border-radius: 4px;
            background: #3b82f6;
            color: white;
            font: inherit;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: background .2s;
        }
        .submit:hover {
            background: #2563eb;
        }
        .notice {
            display: flex;
            gap: 8px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            color: #64748b;
            font-size: 11px;
            line-height: 1.4;
        }
        .notice strong {
            color: #475569;
        }
        @media (max-width: 480px) {
            body { padding: 20px 14px; }
            .card { padding: 20px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="brand" aria-label="Acampamento "No Limite com Cristo", acesso interno">
        <div class="brand-mark" aria-hidden="true">NL</div>
        <div class="brand-name">Acampamento "No Limite com Cristo"<span>Plataforma interna</span></div>
    </div>
    <section class="card" aria-labelledby="login-title">
        <h1 id="login-title">Entre na sua conta</h1>
        <?php if ($erro !== ''): ?>
            <div class="error" role="alert"><?php echo escape_html($erro); ?></div>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
            <div class="field">
                <label for="usuario">Usuário ou e-mail corporativo</label>
                <input type="text" id="usuario" name="usuario" required minlength="4" maxlength="254"
                       value="<?php echo escape_html($identificador); ?>" placeholder="seu.usuario ou email@exemplo.com"
                       autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email">
            </div>
            <div class="field">
                <label for="senha">Senha</label>
                <div class="password-wrap">
                    <input type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="Digite sua senha">
                    <button class="toggle-password" type="button" aria-controls="senha" aria-pressed="false">Mostrar</button>
                </div>
            </div>
            <button class="submit" type="submit">Entrar</button>
        </form>
        <div class="notice"><span aria-hidden="true">●</span><span><strong>Privacidade e segurança.</strong> Acesso restrito e monitorado conforme as diretrizes do sistema.</span></div>
    </section>
</main>
<script>
    const userField = document.getElementById('usuario');
    userField.addEventListener('input', () => {
        const start = userField.selectionStart;
        const end = userField.selectionEnd;
        userField.value = userField.value.toLowerCase().replace(/\s/g, '');
        userField.setSelectionRange(start, end);
    });
    document.querySelector('.toggle-password').addEventListener('click', event => {
        const button = event.currentTarget;
        const password = document.getElementById('senha');
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    });
</script>
</body>
</html>
<?php $conn->close(); ?>