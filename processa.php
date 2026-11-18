<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_login($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$apiKey = getenv('ASAAS_API_KEY');

$headers = [
    "Content-Type: application/json",
    "access_token: " . $apiKey,
    "User-Agent: TCC-Acampamento-Andrey"
];

// Função para validar se o CPF é matematicamente correto e possui 11 dígitos reais
function validaCPF($cpf) {
    // Remove tudo o que não for número
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos ou se são sequências repetidas (ex: 11111111111)
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Validação dos dígitos verificadores do CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Captura dos dados básicos
    $nome = $_POST['nome'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    
    // VALIDAÇÃO DE SEGURANÇA: Impede o prosseguimento se o CPF for inválido
    if (!validaCPF($cpf)) {
        echo "<div style='font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 25px; border: 1px solid #f5c6cb; background: #f8d7da; color: #721c24; border-radius: 8px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>";
        echo "<h3 style='margin-top: 0; color: #721c24;'>Erro de Validação!</h3>";
        echo "<p>O CPF informado é inválido ou incompleto: <strong style='font-family: monospace; font-size: 16px;'>" . htmlspecialchars($cpf) . "</strong></p>";
        echo "<p style='font-size: 13px; color: #555;'>Por favor, certifique-se de preencher todos os 11 dígitos corretamente.</p>";
        echo "<br><a href='formulario.php' style='background-color: #721c24; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;'>Voltar e Corrigir</a>";
        echo "</div>";
        exit(); // Interrompe o script imediatamente
    }

    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Captura dos novos campos de endereço
    $cep = $_POST['cep'] ?? '';
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';

    // Captura dos campos de saúde e tamanho
    $medicamento_controlado = $_POST['medicamento_controlado'] ?? '';
    $restricao_alimentar = $_POST['restricao_alimentar'] ?? '';
    $descricao_alimentar = $_POST['descricao_alimentar'] ?? null;
    $alergia = $_POST['alergia'] ?? '';
    $descricao_alergia = $_POST['descricao_alergia'] ?? null;
    $tamanho_camiseta = $_POST['tamanho_camiseta'] ?? '';

    // Captura dos contatos de emergência
    $contato_emergencia = $_POST['contato_emergencia'] ?? '';
    $nome_contato_emergencia = $_POST['nome_contato_emergencia'] ?? '';

    $codigo_mala = 'MALA-' . strtoupper(substr(md5(uniqid()), 0, 6));

    // Instrução SQL atualizada com todas as novas colunas
    $sql = "INSERT INTO inscricoes (
                nome, cpf, data_nascimento, genero, telefone, email, 
                cep, rua, numero, complemento, bairro, cidade, estado, 
                medicamento_controlado, restricao_alimentar, descricao_alimentar, 
                alergia, descricao_alergia, tamanho_camiseta, 
                contato_emergencia, nome_contato_emergencia, status_pagamento, codigo_mala
            ) VALUES (
                '$nome', '$cpf', '$data_nascimento', '$genero', '$telefone', '$email', 
                '$cep', '$rua', '$numero', '$complemento', '$bairro', '$cidade', '$estado', 
                '$medicamento_controlado', '$restricao_alimentar', " . ($descricao_alimentar ? "'$descricao_alimentar'" : "NULL") . ", 
                '$alergia', " . ($descricao_alergia ? "'$descricao_alergia'" : "NULL") . ", '$tamanho_camiseta', 
                '$contato_emergencia', '$nome_contato_emergencia', 'Pendente', '$codigo_mala'
            )";

    if ($conn->query($sql) === TRUE) {
        $inscricao_id = $conn->insert_id;
        audit_event(
            $conn,
            'administrativo',
            (int) $_SESSION['usuario_id'],
            (string) $_SESSION['usuario_nome'],
            'Criação de inscrição',
            'Nova inscrição criada. Registro ' . $inscricao_id . '.',
            'sucesso'
        );
        echo "<!-- Passo 1: Inserido no Banco com Sucesso -->";

        // Criação do Cliente no Asaas
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://sandbox.asaas.com/api/v3/customers");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "name" => $nome,
            "email" => $email,
            "phone" => $telefone,
            "cpfCnpj" => preg_replace('/[^0-9]/', '', $cpf)
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseCliente = curl_exec($ch);
        curl_close($ch);
        $clienteData = json_decode($responseCliente, true);

        if (isset($clienteData['id'])) {
            $clienteId = $clienteData['id'];
            echo "<!-- Passo 2: Cliente Criado no Asaas -->";

            // Criação da Cobrança Pix no Asaas
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://sandbox.asaas.com/api/v3/payments");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "customer" => $clienteId,
                "billingType" => "PIX",
                "value" => 100.00,
                "dueDate" => date('Y-m-d', strtotime('+3 days')),
                "description" => "Inscrição Acampamento - TCC"
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $responseCobranca = curl_exec($ch);
            curl_close($ch);
            $cobrancaData = json_decode($responseCobranca, true);

            if (isset($cobrancaData['id'])) {
                $paymentId = $cobrancaData['id'];
                $invoiceUrl = isset($cobrancaData['invoiceUrl']) ? $cobrancaData['invoiceUrl'] : '';

                $conn->query("UPDATE inscricoes SET asaas_id = '$paymentId' WHERE id = $inscricao_id");
                echo "<!-- Passo 3: Cobrança Pix Gerada -->";

                // Envio de E-mail com PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'testemail.acampamento@gmail.com';
                    $mail->Password   = 'xcis qwav spui bxxi';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom('testemail.acampamento@gmail.com', 'Organização do Acampamento');
                    $mail->addAddress($email, $nome);

                    $mail->isHTML(true);
                    $mail->Subject = 'Confirmação de Inscrição - Acampamento';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                            <h2 style='color: #2563eb;'>Olá, {$nome}!</h2>
                            <p>A tua inscrição no <strong>Acampamento</strong> foi registada com sucesso!</p>
                            
                            <div style='background: #f8fafc; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;'>
                                <p style='margin: 0;'><strong>Código da tua Bagagem:</strong> <span style='font-family: monospace; font-size: 18px; color: #1e293b;'>{$codigo_mala}</span></p>
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
                                <a href='{$invoiceUrl}' target='_blank' style='background-color: #16a34a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Pagar Inscrição via Pix</a>
                            </div>

                            <br><hr style='border: none; border-top: 1px solid #eee;'><br>
                            <p style='font-size: 12px; color: #94a3b8; text-align: center;'>Esta é uma mensagem automática. Por favor não repondas a este e-mail.</p>
                        </div>
                    ";

                    $mail->send();
                    $email_status = "E-mail de confirmação enviado com sucesso!";
                } catch (Exception $e) {
                    $email_status = "Erro no E-mail: {$mail->ErrorInfo}";
                }

                // Obtenção do QR Code Pix
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://sandbox.asaas.com/api/v3/payments/{$paymentId}/pixQrCode");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $responsePix = curl_exec($ch);
                curl_close($ch);
                $pixData = json_decode($responsePix, true);

                echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;'>";
                echo "<h2 style='color: #2e7d32;'>Inscrição realizada com sucesso!</h2>";
                echo "<p style='color: #2563eb; font-weight: bold;'>{$email_status}</p>";
                
                echo "<div style='background: #e8f5e9; border: 1px dashed #2e7d32; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center;'>";
                echo "<strong>Código de Rastreio da Mala:</strong><br>";
                echo "<span style='font-size: 22px; font-weight: bold; color: #1b5e20; font-family: monospace;'>{$codigo_mala}</span>";
                echo "</div>";

                if (isset($pixData['encodedImage']) && !empty($pixData['encodedImage'])) {
                    echo "<div style='text-align:center; margin-top:20px; background: #f9f9f9; padding: 15px; border-radius: 6px;'>";
                    echo "<strong>QR Code Pix:</strong><br><br>";
                    echo "<img src='data:image/png;base64," . $pixData['encodedImage'] . "' alt='QR Code Pix' style='max-width:200px;' /><br>";
                    echo "</div>";
                }

                echo "<div style='margin-top: 25px; text-align: center;'>";
                echo "<a href='formulario.php' style='color: #555; text-decoration: underline;'>Fazer nova inscrição</a>";
                echo "</div>";
                echo "</div>";
                
                $conn->close();
                exit();
            } else {
                echo "<h3>Erro ao criar cobrança no Asaas:</h3>";
                echo "<pre>" . htmlspecialchars($responseCobranca) . "</pre>";
            }
        } else {
            echo "<h3>Erro ao criar cliente no Asaas:</h3>";
            echo "<pre>" . htmlspecialchars($responseCliente) . "</pre>";
        }
    } else {
        echo "<h3>Erro no Banco de Dados (CPF duplicado?):</h3>";
        echo "<p>" . $conn->error . "</p>";
    }
}
?>