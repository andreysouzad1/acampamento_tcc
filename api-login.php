<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/conexao.php';

$rawInput = (string) file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$identificador = trim((string) (
    $input['usuario']
    ?? $input['email']
    ?? $input['login']
    ?? ''
));
$senha = (string) ($input['senha'] ?? '');

if ($identificador === '' || $senha === '') {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Informe usuário/e-mail e senha.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id, nome, email, senha, setor FROM usuarios WHERE email = ? OR usuario = ? LIMIT 1'
);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno ao consultar usuário.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('ss', $identificador, $identificador);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result ? $result->fetch_assoc() : null;
$stmt->close();

$senhaValida = false;
if ($usuario) {
    $hashArmazenado = (string) ($usuario['senha'] ?? '');
    if ($hashArmazenado !== '' && password_verify($senha, $hashArmazenado)) {
        $senhaValida = true;
    } elseif ($hashArmazenado !== '' && hash_equals($hashArmazenado, $senha)) {
        // Fallback caso algum usuário de teste ainda tenha senha simples
        $senhaValida = true;
    }
}

if (!$usuario || !$senhaValida) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário/e-mail ou senha inválidos.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$setor = strtolower(trim((string) ($usuario['setor'] ?? 'admin')));

$secret = getenv('FORM_DATA_ENCRYPTION_KEY')
    ?: ($_ENV['FORM_DATA_ENCRYPTION_KEY'] ?? '')
    ?: 'chave_secreta_tcc_2026';

$payload = base64_encode((string) json_encode([
    'id'    => (int) $usuario['id'],
    'setor' => $setor,
    'exp'   => time() + (86400 * 7), // Válido por 7 dias
]));
$assinatura = hash_hmac('sha256', $payload, $secret);
$token = $payload . '.' . $assinatura;

echo json_encode([
    'sucesso' => true,
    'token'   => $token,
    'usuario' => [
        'id'    => (int) $usuario['id'],
        'nome'  => (string) $usuario['nome'],
        'email' => (string) $usuario['email'],
        'setor' => $setor,
    ],
], JSON_UNESCAPED_UNICODE);