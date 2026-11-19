<?php
$host    = getenv('MYSQLHOST')     ?: ($_ENV['MYSQLHOST']     ?? 'mysql.railway.internal');
$porta   = (int) (getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT']   ?? 3306));
$usuario = getenv('MYSQLUSER')     ?: ($_ENV['MYSQLUSER']     ?? 'root');
$senha   = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? 'FOPgwjMKOtdNidpUGmoHxfXikwstxDwG');
$banco   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? 'railway');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $usuario, $senha, $banco, $porta);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    exit('Erro interno ao conectar com a base de dados.');
}