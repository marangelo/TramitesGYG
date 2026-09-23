<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_tipo_documento'] ?? null;
$nombre = trim($_POST['nombre'] ?? '');

if (empty($nombre)) {
    $error = 'El nombre es obligatorio.';
    $redirect = $id ? "editar.php?id_tdoc=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE tipo_documento SET nombre = :nombre WHERE id_tipo_documento = :id");
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);
        $mensaje = 'Tipo de documento actualizado correctamente.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tipo_documento (nombre) VALUES (:nombre)");
        $stmt->execute([':nombre' => $nombre]);
        $mensaje = 'Tipo de documento creado correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    $redirect = $id ? "editar.php?id_tdoc=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}
?>