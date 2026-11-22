<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function h(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function renderErroBox(string $titulo, string $mensagem, string $detalhe = ''): void
{
    echo "<div style='font-family: Arial, sans-serif; max-width: 560px; margin: 50px auto; padding: 25px; border: 1px solid #f5c6cb; background: #f8d7da; color: #721c24; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>";
    echo "<h3 style='margin-top: 0; color: #721c24;'>" . h($titulo) . "</h3>";
    echo "<p>" . h($mensagem) . "</p>";
    if ($detalhe !== '') {
        echo "<pre style='background:#fff; color:#333; padding:10px; border-radius:4px; overflow:auto; font-size:12px;'>" . h($detalhe) . "</pre>";
    }
    echo "<br><a href='formulario.php' style='background-color: #721c24; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;'>Voltar e Corrigir</a>";
    echo "</div>";
}

$apiKey = getenv('ASAAS_API_KEY') ?: ($_ENV['ASAAS_API_KEY'] ?? '');
if (!$apiKey) {
    exit('Erro: A chave de API do Asaas não está configurada no servidor.');
}
$headers = [
    'Content-Type: application/json',
    'access_token: ' . $apiKey,
    'User-Agent: TCC-Acampamento-Andrey',
];

function validaCPF($cpf): bool
{
    $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);
    if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int) $cpf[$c] !== $d) {
            return false;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $cpf = trim((string) ($_POST['cpf'] ?? ''));

    if (!validaCPF($cpf)) {
        renderErroBox(
            'Erro de Validação!',
            'O CPF informado é inválido ou incompleto: ' . $cpf,
            'Certifique-se de preencher todos os 11 dígitos corretamente.'
        );
        exit();
    }

    $data_nascimento = trim((string) ($_POST['data_nascimento'] ?? ''));
    $genero = trim((string) ($_POST['genero'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $cep = trim((string) ($_POST['cep'] ?? ''));
    $rua = trim((string) ($_POST['rua'] ?? ''));
    $numero = trim((string) ($_POST['numero'] ?? ''));
    $complemento = trim((string) ($_POST['complemento'] ?? ''));
    $bairro = trim((string) ($_POST['bairro'] ?? ''));
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $estado = trim((string) ($_POST['estado'] ?? ''));

    $medicamento_controlado = trim((string) ($_POST['medicamento_controlado'] ?? ''));
    $restricao_alimentar = trim((string) ($_POST['restricao_alimentar'] ?? ''));
    $descAlimRaw = trim((string) ($_POST['descricao_alimentar'] ?? ''));
    $descricao_alimentar = $descAlimRaw !== '' ? $descAlimRaw : null;

    $alergia = trim((string) ($_POST['alergia'] ?? ''));
    $descAlerRaw = trim((string) ($_POST['descricao_alergia'] ?? ''));
    $descricao_alergia = $descAlerRaw !== '' ? $descAlerRaw : null;

    $tamanho_camiseta = trim((string) ($_POST['tamanho_camiseta'] ?? ''));

    $contato_emergencia = trim((string) ($_POST['contato_emergencia'] ?? ''));
    $nome_contato_emergencia = trim((string) ($_POST['nome_contato_emergencia'] ?? ''));

    $codigo_mala = 'MALA-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    $status_pagamento = 'Pendente';

    $sql = 'INSERT INTO inscricoes (
                nome, cpf, data_nascimento, genero, telefone, email,
                cep, rua, numero, complemento, bairro, cidade, estado,
                medicamento_controlado, restricao_alimentar, descricao_alimentar,
                alergia, descricao_alergia, tamanho_camiseta,
                contato_emergencia, nome_contato_emergencia, status_pagamento, codigo_mala
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?
            )';

    try {
        $stmtInsert = $conn->prepare($sql);
        if ($stmtInsert === false) {
            renderErroBox(
                'Erro ao preparar inserção no banco',
                'Verifique se todas as colunas da query existem na tabela inscricoes.',
                $conn->error
            );
            exit();
        }

        $stmtInsert->bind_param(
            'sssssssssssssssssssssss',
            $nome,
            $cpf,
            $data_nascimento,
            $genero,
            $telefone,
            $email,
            $cep,
            $rua,
            $numero,
            $complemento,
            $bairro,
            $cidade,
            $estado,
            $medicamento_controlado,
            $restricao_alimentar,
            $descricao_alimentar,
            $alergia,
            $descricao_alergia,
            $tamanho_camiseta,
            $contato_emergencia,
            $nome_contato_emergencia,
            $status_pagamento,
            $codigo_mala
        );

        if ($stmtInsert->execute()) {
            $inscricao_id = $conn->insert_id;
            $stmtInsert->close();

            audit_event(
                $conn,
                'administrativo',
                (int) ($_SESSION['usuario_id'] ?? 0),
                (string) ($_SESSION['usuario_nome'] ?? ''),
                'Criação de inscrição',
                'Nova inscrição criada. Registro ' . $inscricao_id . '.',
                'sucesso'
            );
            echo '<!-- Passo 1: Inserido no Banco com Sucesso -->';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://sandbox.asaas.com/api/v3/customers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'name' => $nome,
                'email' => $email,
                'phone' => $telefone,
                'cpfCnpj' => preg_replace('/[^0-9]/', '', $cpf),
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $responseCliente = curl_exec($ch);
            $clienteData = json_decode((string) $responseCliente, true);

            if (isset($clienteData['id'])) {
                $clienteId = $clienteData['id'];
                echo '<!-- Passo 2: Cliente Criado no Asaas -->';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://sandbox.asaas.com/api/v3/payments');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'customer' => $clienteId,
                    'billingType' => 'PIX',
                    'value' => 100.00,
                    'dueDate' => date('Y-m-d', strtotime('+3 days')),
                    'description' => 'Inscrição Acampamento - TCC',
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $responseCobranca = curl_exec($ch);
                $cobrancaData = json_decode((string) $responseCobranca, true);

                if (isset($cobrancaData['id'])) {
                    $paymentId = (string) $cobrancaData['id'];
                    $invoiceUrl = isset($cobrancaData['invoiceUrl']) ? (string) $cobrancaData['invoiceUrl'] : '';

                    $stmtUpdate = $conn->prepare('UPDATE inscricoes SET asaas_id = ? WHERE id = ?');
                    if ($stmtUpdate !== false) {
                        $stmtUpdate->bind_param('si', $paymentId, $inscricao_id);
                        $stmtUpdate->execute();
                        $stmtUpdate->close();
                    }
                    echo '<!-- Passo 3: Cobrança Pix Gerada -->';

                    $smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? 'testemail.acampamento@gmail.com');
                    $smtpPass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? 'xcis qwav spui bxxi');

                    $mail = new PHPMailer(true);
                    try {
                        $mail->SMTPDebug = 0;
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtpUser;
                        $mail->Password = $smtpPass;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->CharSet = 'UTF-8';

                        $mail->setFrom($smtpUser, 'Organização do Acampamento');
                        $mail->addAddress($email, $nome);

                        $nomeSeguro = h($nome);
                        $codigoSeguro = h($codigo_mala);
                        $urlSegura = h($invoiceUrl);

                        $mail->isHTML(true);
                        $mail->Subject = 'Confirmação de Inscrição - Acampamento';
                        $mail->Body = "
                            <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                                <h2 style='color: #2563eb;'>Olá, {$nomeSeguro}!</h2>
                                <p>A tua inscrição no <strong>Acampamento</strong> foi registada com sucesso!</p>
                                
                                <div style='background: #f8fafc; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;'>
                                    <p style='margin: 0;'><strong>Código da tua Bagagem:</strong> <span style='font-family: monospace; font-size: 18px; color: #1e293b;'>{$codigoSeguro}</span></p>
                                    <small style='color: #64748b;'>Guarda este código para o dia do embarque.</small>
                                </div>

                                <h3>Orientações Importantes:</h3>
                                <ul>
                                    <li><strong>Check-in:</strong> Apresenta o teu CPF no momento do embarque.</li>
                                    <li><strong>Bagagem:</strong> Cada participante tem direito a identificar a sua mala no local.</li>
                                    <li><strong>Pagamento:</strong> Podes aceder à tua fatura Pix através do botão abaixo.</li>
                                </ul>

                                <br>
                                <div style='text-align: center;'>
                                    <a href='{$urlSegura}' target='_blank' rel='noopener noreferrer' style='background-color: #16a34a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Pagar Inscrição via Pix</a>
                                </div>

                                <br><hr style='border: none; border-top: 1px solid #eee;'><br>
                                <p style='font-size: 12px; color: #94a3b8; text-align: center;'>Esta é uma mensagem automática. Por favor não repondas a este e-mail.</p>
                            </div>
                        ";

                        $mail->send();
                    } catch (Exception $e) {
                        error_log('Aviso PHPMailer (processa.php): ' . $mail->ErrorInfo);
                    }

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://sandbox.asaas.com/api/v3/payments/' . urlencode($paymentId) . '/pixQrCode');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $responsePix = curl_exec($ch);
                    $pixData = json_decode((string) $responsePix, true);

                    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;'>";
                    echo "<h2 style='color: #2e7d32;'>Inscrição realizada com sucesso!</h2>";

                    echo "<div style='background: #e8f5e9; border: 1px dashed #2e7d32; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center;'>";
                    echo '<strong>Código de Rastreio da Mala:</strong><br>';
                    echo "<span style='font-size: 22px; font-weight: bold; color: #1b5e20; font-family: monospace;'>" . h($codigo_mala) . '</span>';
                    echo '</div>';

                    if (isset($pixData['encodedImage']) && !empty($pixData['encodedImage'])) {
                        echo "<div style='text-align:center; margin-top:20px; background: #f9f9f9; padding: 15px; border-radius: 6px;'>";
                        echo '<strong>QR Code Pix:</strong><br><br>';
                        echo "<img src='data:image/png;base64," . h((string) $pixData['encodedImage']) . "' alt='QR Code Pix' style='max-width:200px;' /><br>";
                        echo '</div>';
                    }

                    echo "<div style='margin-top: 25px; text-align: center;'>";
                    echo "<a href='formulario.php' style='color: #555; text-decoration: underline;'>Fazer nova inscrição</a>";
                    echo '</div>';
                    echo '</div>';

                    $conn->close();
                    exit();
                } else {
                    echo '<h3>Erro ao criar cobrança no Asaas:</h3>';
                    echo '<pre>' . h((string) $responseCobranca) . '</pre>';
                }
            } else {
                echo '<h3>Erro ao criar cliente no Asaas:</h3>';
                echo '<pre>' . h((string) $responseCliente) . '</pre>';
            }
        } else {
            $dbError = $stmtInsert->error;
            $stmtInsert->close();
            renderErroBox('Erro no Banco de Dados', 'Não foi possível inserir a inscrição (CPF duplicado ou restrição de coluna).', $dbError);
        }
    } catch (Throwable $e) {
        error_log('Erro fatal em processa.php: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        renderErroBox(
            'Erro interno ao processar inscrição',
            'Ocorreu uma exceção durante o cadastro.',
            $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        );
    }
}