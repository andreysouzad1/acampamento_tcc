<?php

declare(strict_types=1);

function get_crypto_key(): string
{
    $rawKey = getenv('FORM_DATA_ENCRYPTION_KEY')
        ?: ($_ENV['FORM_DATA_ENCRYPTION_KEY'] ?? '')
        ?: (getenv('DATA_ENCRYPTION_KEY') ?: ($_ENV['DATA_ENCRYPTION_KEY'] ?? ''));

    if ($rawKey === '') {
        throw new RuntimeException('Chave de criptografia (FORM_DATA_ENCRYPTION_KEY) não configurada no ambiente.');
    }

    // Deriva uma chave binária de exatamente 32 bytes (256 bits) para o AES-256-GCM
    return hash('sha256', $rawKey, true);
}

function encrypt_data(?string $plaintext): ?string
{
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }

    // Evita criptografar novamente caso o valor já esteja protegido
    if (str_starts_with($plaintext, 'enc::')) {
        return $plaintext;
    }

    $key = get_crypto_key();
    $iv  = random_bytes(12); // 96 bits recomendados para GCM
    $tag = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($ciphertext === false) {
        throw new RuntimeException('Falha ao criptografar os dados.');
    }

    return 'enc::' . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_data(?string $stored): ?string
{
    // Se for nulo, vazio ou dado antigo em texto puro, retorna como está
    if ($stored === null || $stored === '' || !str_starts_with($stored, 'enc::')) {
        return $stored;
    }

    $raw = base64_decode(substr($stored, 5), true);
    if ($raw === false || strlen($raw) < 28) {
        return '';
    }

    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $key        = get_crypto_key();

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $plaintext === false ? '' : $plaintext;
}

function blind_index(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    // Mantém apenas os dígitos (útil para CPF) antes de gerar o HMAC-SHA256
    $limpo = preg_replace('/[^0-9]/', '', $value);
    if ($limpo === '') {
        return null;
    }

    return hash_hmac('sha256', $limpo, get_crypto_key());
}