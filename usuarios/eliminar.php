<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;

if ($id_user <= 0) {
    setMensaje('ID de usuario no válido.', 'danger');
    header('Location: index.php');
    exit;
}

if ($id_user == $_SESSION['usuario_id']) {
    setMensaje('No puedes eliminar tu propio usuario.', 'danger');
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuario($id_user);
if (!$usuario) {
    setMensaje('Usuario no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id_user");
    $stmt->execute([':id_user' => $id_user]);
    setMensaje('Usuario eliminado correctamente.', 'success');
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el usuario tiene registros asociados en otras tablas.', 'danger');
}

header('Location: index.php');
exit;
?>