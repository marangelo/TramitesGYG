<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

function require_role($role_id) {
    if (!in_array($role_id, $_SESSION['roles'] ?? [])) {
        header('Location: dashboard.php?error=no_autorizado');
        exit;
    }
}
?>