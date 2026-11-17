<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);

if (!empty($_SESSION['usuario_id'])) {
    audit_event(
        $conn,
        'login',
        (int) $_SESSION['usuario_id'],
        (string) ($_SESSION['usuario_nome'] ?? 'desconhecido'),
        'Encerramento de sessão',
        'Sessão encerrada.',
        'sucesso'
    );
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
$conn->close();
header('Location: login.php');
exit();
