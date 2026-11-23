<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/crypto.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if ($authHeader === '' && function_exists('getallheaders')) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $authHeader = (string) ($headers['authorization'] ?? '');
}

if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Token de autenticação ausente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$secret = getenv('FORM_DATA_ENCRYPTION_KEY')
    ?: ($_ENV['FORM_DATA_ENCRYPTION_KEY'] ?? '')
    ?: 'chave_secreta_tcc_2026';

$partes = explode('.', $matches[1]);
if (count($partes) !== 2 || !hash_equals(hash_hmac('sha256', $partes[0], $secret), $partes[1])) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Token inválido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$dadosToken = json_decode((string) base64_decode($partes[0], true), true);
if (!is_array($dadosToken) || (($dadosToken['exp'] ?? 0) < time())) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Token expirado. Faça login novamente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$setor = strtolower(trim((string) ($dadosToken['setor'] ?? '')));

switch ($setor) {
    case 'enfermaria':
        $sql = "SELECT id, nome, data_nascimento, medicamento_controlado,
                       restricao_alimentar, descricao_alimentar,
                       alergia, descricao_alergia,
                       contato_emergencia, nome_contato_emergencia
                FROM inscricoes
                ORDER BY nome ASC";
        break;

    case 'cozinha':
        $sql = "SELECT id, nome, restricao_alimentar, descricao_alimentar
                FROM inscricoes
                ORDER BY restricao_alimentar DESC, nome ASC";
        break;

    case 'bagagens':
    case 'equipedelogistica_':
    case 'logistica':
        $sql = "SELECT id, nome, codigo_mala, status_envio, tamanho_camiseta
                FROM inscricoes
                ORDER BY nome ASC";
        break;

    case 'portaria':
        $sql = "SELECT id, nome, cpf, status_pagamento, codigo_mala
                FROM inscricoes
                ORDER BY nome ASC";
        break;

    case 'admin':
        $sql = "SELECT id, nome, cpf, data_nascimento, genero, telefone, email,
                       cep, rua, numero, complemento, bairro, cidade, estado,
                       medicamento_controlado, restricao_alimentar, descricao_alimentar,
                       alergia, descricao_alergia, tamanho_camiseta,
                       contato_emergencia, nome_contato_emergencia,
                       status_pagamento, codigo_mala, status_envio
                FROM inscricoes
                ORDER BY id DESC";
        break;

    default:
        http_response_code(403);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Setor não autorizado.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
}

$result = $conn->query($sql);
if ($result === false) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao consultar participantes.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$camposSensiveis = [
    'cpf',
    'telefone',
    'email',
    'rua',
    'numero',
    'complemento',
    'descricao_alimentar',
    'descricao_alergia',
    'contato_emergencia',
    'nome_contato_emergencia',
];

$registros = [];
while ($row = $result->fetch_assoc()) {
    foreach ($camposSensiveis as $campo) {
        if (array_key_exists($campo, $row)) {
            $row[$campo] = decrypt_data($row[$campo]);
        }
    }
    $registros[] = $row;
}

echo json_encode([
    'sucesso' => true,
    'setor'   => $setor,
    'total'   => count($registros),
    'dados'   => $registros,
], JSON_UNESCAPED_UNICODE);