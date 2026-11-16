<?php
include("conexao.php");

// Defina aqui as credenciais que você quer usar:
$usuario = "admin"; // Nome de usuário desejado
$senha_pura = "admin404"; // Senha em texto puro
$nome_completo = "Administrador do Sistema"; // Nome completo do usuário

// Criptografa a senha com segurança (Bcrypt)
$senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);

// Insere na tabela 'usuarios'
$stmt = $conn->prepare("INSERT INTO usuarios (usuario, senha, nome_completo) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $usuario, $senha_hash, $nome_completo);

if ($stmt->execute()) {
    echo "<h2>Usuário cadastrado com sucesso!</h2>";
    echo "<p>Usuário: <b>{$usuario}</b></p>";
    echo "<p>Senha pura usada: <b>{$senha_pura}</b> (Salva criptografada no banco)</p>";
    echo "<br><a href='login.php' style='padding: 10px 15px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px;'>Ir para a Tela de Login</a>";
} else {
    echo "Erro ao cadastrar (talvez o usuário 'admin' já exista): " . $conn->error;
}

$stmt->close();
$conn->close();
?>