<?php
session_start();
include("conexao.php");

$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $senha = trim($_POST['senha']);

    if (!empty($usuario) && !empty($senha)) {
        // Busca o usuário no banco de dados
        $stmt = $conn->prepare("SELECT id, usuario, senha, nome_completo FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Valida a senha digitada contra o hash armazenado na tabela
            if (password_verify($senha, $user['senha'])) {
                $_SESSION['logado'] = true;
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome_completo'];
                
                header("Location: home.php");
                exit();
            } else {
                $erro = "Usuário ou senha inválidos!";
            }
        } else {
            $erro = "Usuário ou senha inválidos!";
        }
        $stmt->close();
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Acampamento "No Limite com Cristo"</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            box-sizing: border-box;
        }
        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            border: 1px solid #e2e8f0;
        }
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-header h2 {
            color: #1e293b;
            margin: 0 0 5px 0;
            font-size: 22px;
        }
        .login-header p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            background-color: #f8fafc;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #3b82f6;
            background-color: #fff;
        }
        .btn-primary {
            display: block;
            width: 100%;
            background: #3b82f6;
            color: #ffffff;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }
        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h2>No Limite com Cristo</h2>
        <p>Faça login para acessar o sistema</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert-error"><?php echo $erro; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" required placeholder="Digite seu usuário">
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required placeholder="Digite sua senha">
        </div>

        <button type="submit" class="btn-primary">Entrar no Sistema</button>
    </form>

    <div class="login-footer">
        Trabalho de Conclusão de Curso (TCC)
    </div>
</div>

</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>