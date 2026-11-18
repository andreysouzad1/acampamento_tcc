<?php
// Tenta capturar as variáveis do Railway, senão usa o padrão local do XAMPP
$host = getenv('MYSQLHOST') ?: 'localhost';
$usuario = getenv('MYSQLUSER') ?: 'root';
$senha = getenv('MYSQLPASSWORD') ?: '';
$banco = getenv('MYSQLDATABASE') ?: 'tcc_acampamento';
$porta = (int) (getenv('MYSQLPORT') ?: 3306);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $usuario, $senha, $banco, $porta);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    exit('Erro interno ao conectar com a base de dados.');
}