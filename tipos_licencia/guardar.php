<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_POST['id_tipo_licencia']) ? (int)$_POST['id_tipo_licencia'] : null;
$nombre = trim($_POST['nombre'] ?? '');
$id_direccion_anrs = (int)($_POST['id_direccion_anrs'] ?? 0);

if (empty($nombre) || $id_direccion_anrs <= 0) {
    $error = 'El nombre y la dirección ANRS son obligatorios.';
    $redirect = $id ? "editar.php?id_tlic=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

try {
    if ($id) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE tipo_licencia 
            SET nombre = :nombre, id_direccion_anrs = :id_direccion_anrs
            WHERE id_tipo_licencia = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':id_direccion_anrs' => $id_direccion_anrs,
            ':id' => $id
        ]);
        $mensaje = 'Tipo de licencia actualizado correctamente.';
    } else {
        // Insertar
        $stmt = $pdo->prepare("
            INSERT INTO tipo_licencia (nombre, id_direccion_anrs)
            VALUES (:nombre, :id_direccion_anrs)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':id_direccion_anrs' => $id_direccion_anrs
        ]);
        $mensaje = 'Tipo de licencia creado correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    $redirect = isset($id) ? "editar.php?id_tlic=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}
?>