<?php
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Strict',
        'path' => '/'
    ]);
    session_start();
}

function initialize_auth(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(254) NOT NULL,
            senha VARCHAR(255) NOT NULL,
            nome_completo VARCHAR(150) NOT NULL,
            email VARCHAR(254) NULL,
            perfil VARCHAR(30) NOT NULL DEFAULT 'AdminComum',
            status VARCHAR(20) NOT NULL DEFAULT 'Ativo',
            ultimo_acesso DATETIME NULL,
            exige_troca_senha TINYINT(1) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usuarios_usuario (usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $columns = [
        'email' => "VARCHAR(254) NULL",
        'perfil' => "VARCHAR(30) NOT NULL DEFAULT 'AdminComum'",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'Ativo'",
        'ultimo_acesso' => 'DATETIME NULL',
        'exige_troca_senha' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'criado_em' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'atualizado_em' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];

    foreach ($columns as $column => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM usuarios LIKE '" . $conn->real_escape_string($column) . "'");
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE usuarios ADD COLUMN `$column` $definition");
        }
    }

    $usernameIndex = $conn->query("SHOW INDEX FROM usuarios WHERE Key_name = 'uq_usuarios_usuario'");
    if ($usernameIndex->num_rows === 0) {
        $duplicates = $conn->query('SELECT usuario FROM usuarios GROUP BY usuario HAVING COUNT(*) > 1 LIMIT 1');
        if ($duplicates->num_rows === 0) {
            $conn->query('ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_usuario (usuario)');
        }
    }
    $emailIndex = $conn->query("SHOW INDEX FROM usuarios WHERE Key_name = 'uq_usuarios_email'");
    if ($emailIndex->num_rows === 0) {
        $duplicateEmails = $conn->query("SELECT email FROM usuarios WHERE email IS NOT NULL AND email <> '' GROUP BY email HAVING COUNT(*) > 1 LIMIT 1");
        if ($duplicateEmails->num_rows === 0) {
            $conn->query('ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_email (email)');
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS logs_auditoria (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(30) NOT NULL,
            usuario_id INT NULL,
            usuario VARCHAR(254) NOT NULL,
            acao VARCHAR(100) NOT NULL,
            detalhes TEXT NOT NULL,
            ip VARCHAR(45) NOT NULL,
            dispositivo VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_auditoria_data (criado_em),
            KEY idx_auditoria_usuario (usuario_id),
            KEY idx_auditoria_ip_status_data (ip, status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $triggers = [
        'logs_auditoria_immutable_update' => 'UPDATE',
        'logs_auditoria_immutable_delete' => 'DELETE'
    ];
    foreach ($triggers as $trigger => $operation) {
        $stmt = $conn->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?');
        $stmt->bind_param('s', $trigger);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            $conn->query(
                "CREATE TRIGGER `$trigger` BEFORE $operation ON logs_auditoria
                 FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Logs de auditoria sao imutaveis'"
            );
        }
    }

    $superAdminPassword = getenv('SUPERADMIN_SEED_PASSWORD');
    if ($superAdminPassword === false || $superAdminPassword === '') {
        $superAdminPassword = 'admin123'; // Senha padrão para ambiente local/XAMPP se a variável não estiver definida
    }

    $legacySuperAdmin = $conn->query("SELECT id, senha FROM usuarios WHERE usuario = 'superadmin_' LIMIT 1")->fetch_assoc();
    $currentSuperAdmin = $conn->query("SELECT id FROM usuarios WHERE usuario = 'admin' LIMIT 1")->fetch_assoc();
    
    if ($legacySuperAdmin) {
        if ($currentSuperAdmin && (int) $currentSuperAdmin['id'] !== (int) $legacySuperAdmin['id']) {
            throw new RuntimeException('Não foi possível migrar o SuperAdmin: o usuário admin já existe.');
        }
        $newPasswordHash = password_hash($superAdminPassword, PASSWORD_DEFAULT);
        $mustChangePassword = 1;
        $stmt = $conn->prepare(
            "UPDATE usuarios SET usuario = 'admin', senha = ?, perfil = 'SuperAdmin',
             status = 'Ativo', exige_troca_senha = ? WHERE id = ?"
        );
        $stmt->bind_param('sii', $newPasswordHash, $mustChangePassword, $legacySuperAdmin['id']);
        $stmt->execute();
        $stmt->close();
    }

    $seedUsers = [
        ['administrador', 'adminadmin', 'Administrador', 'AdminComum']
    ];
    foreach ($seedUsers as $seed) {
        [$username, $password, $fullName, $role] = $seed;
        $stmt = $conn->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $mustChangePassword = 1;
            $stmt = $conn->prepare(
                'INSERT INTO usuarios (usuario, senha, nome_completo, perfil, status, exige_troca_senha) VALUES (?, ?, ?, ?, "Ativo", ?)'
            );
            $stmt->bind_param('ssssi', $username, $hash, $fullName, $role, $mustChangePassword);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = 'admin' AND perfil = 'SuperAdmin' LIMIT 1");
    $stmt->execute();
    $superAdminExists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if (!$superAdminExists) {
        $hash = password_hash($superAdminPassword, PASSWORD_DEFAULT);
        $username = 'admin';
        $fullName = 'Super Administrador';
        $role = 'SuperAdmin';
        $status = 'Ativo';
        $mustChangePassword = 1;
        $stmt = $conn->prepare(
            'INSERT INTO usuarios (usuario, senha, nome_completo, perfil, status, exige_troca_senha) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssi', $username, $hash, $fullName, $role, $status, $mustChangePassword);
        $stmt->execute();
        $stmt->close();
    }
}

function audit_event(mysqli $conn, string $type, ?int $userId, string $username, string $action, string $details, string $status = 'sucesso'): void
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);
    $device = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'), 0, 255);
    $stmt = $conn->prepare(
        'INSERT INTO logs_auditoria (tipo, usuario_id, usuario, acao, detalhes, ip, dispositivo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sissssss', $type, $userId, $username, $action, $details, $ip, $device, $status);
    $stmt->execute();
    $stmt->close();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!csrf_is_valid()) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

function csrf_is_valid(): bool
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    return $submitted !== '' && hash_equals(csrf_token(), $submitted);
}

function require_login(mysqli $conn): void
{
    if (empty($_SESSION['logado']) || empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }

    if (!empty($_SESSION['ultima_atividade']) && time() - (int) $_SESSION['ultima_atividade'] > 1800) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?expirada=1');
        exit();
    }

    $userId = (int) $_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT perfil, status, exige_troca_senha FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || $user['status'] !== 'Ativo') {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $_SESSION['usuario_perfil'] = $user['perfil'];
    $_SESSION['ultima_atividade'] = time();
    $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((int) $user['exige_troca_senha'] === 1 && $currentPage !== 'trocar_senha.php' && $currentPage !== 'logout.php') {
        header('Location: trocar_senha.php');
        exit();
    }
}

function require_superadmin(mysqli $conn): void
{
    require_login($conn);
    if (($_SESSION['usuario_perfil'] ?? '') !== 'SuperAdmin') {
        http_response_code(403);
        header('Location: home.php?acesso=negado');
        exit();
    }
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}