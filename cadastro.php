<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';
initialize_auth($conn);
require_superadmin($conn);
header('Location: admin_usuarios.php');
exit();
