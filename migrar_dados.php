<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/crypto.php';

// Proteção simples via parâmetro na URL: migrar_dados.php?chave=tcc2026
if (($_GET['chave'] ?? '') !== 'tcc2026') {
    http_response_code(403);
    exit('Acesso negado.');
}

$sql = "SELECT id, cpf, telefone, email, rua, numero, complemento,
               contato_emergencia, nome_contato_emergencia,
               descricao_alimentar, descricao_alergia
        FROM inscricoes";

$res = $conn->query($sql);
if ($res === false) {
    http_response_code(500);
    exit('Erro ao consultar inscricoes: ' . $conn->error);
}

$stmt = $conn->prepare("
    UPDATE inscricoes SET
        cpf = ?,
        cpf_hash = ?,
        telefone = ?,
        email = ?,
        rua = ?,
        numero = ?,
        complemento = ?,
        contato_emergencia = ?,
        nome_contato_emergencia = ?,
        descricao_alimentar = ?,
        descricao_alergia = ?
    WHERE id = ?
");

if ($stmt === false) {
    http_response_code(500);
    exit('Erro ao preparar UPDATE: ' . $conn->error);
}

$totalAtualizados = 0;
$totalIgnorados = 0;

while ($row = $res->fetch_assoc()) {
    $cpfAtual = (string) ($row['cpf'] ?? '');

    // Se já estiver encriptado, não encripta de novo
    if (str_starts_with($cpfAtual, 'enc::')) {
        $totalIgnorados++;
        continue;
    }

    $cpfHash  = blind_index($cpfAtual);
    $cpfEnc   = encrypt_data($row['cpf'] ?? null);
    $telEnc   = encrypt_data($row['telefone'] ?? null);
    $emailEnc = encrypt_data($row['email'] ?? null);
    $ruaEnc   = encrypt_data($row['rua'] ?? null);
    $numEnc   = isset($row['numero']) && $row['numero'] !== null ? encrypt_data((string) $row['numero']) : null;
    $compEnc  = encrypt_data($row['complemento'] ?? null);
    $cEmerEnc = encrypt_data($row['contato_emergencia'] ?? null);
    $nEmerEnc = encrypt_data($row['nome_contato_emergencia'] ?? null);
    $dAlimEnc = encrypt_data($row['descricao_alimentar'] ?? null);
    $dAlerEnc = encrypt_data($row['descricao_alergia'] ?? null);
    $id       = (int) $row['id'];

    $stmt->bind_param(
        'sssssssssssi',
        $cpfEnc,
        $cpfHash,
        $telEnc,
        $emailEnc,
        $ruaEnc,
        $numEnc,
        $compEnc,
        $cEmerEnc,
        $nEmerEnc,
        $dAlimEnc,
        $dAlerEnc,
        $id
    );

    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        http_response_code(500);
        exit("Erro ao atualizar ID {$id}: {$erro}");
    }

    $totalAtualizados++;
}

$stmt->close();

echo "Migração concluída! Atualizados: {$totalAtualizados} | Já encriptados/ignorados: {$totalIgnorados}. Apague este ficheiro após confirmar no banco.";