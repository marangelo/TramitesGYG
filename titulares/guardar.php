<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_titular'] ?? null;
$nombre = trim($_POST['nombre'] ?? '');
$id_pais = (int)($_POST['id_pais'] ?? 0);
$direccion_texto = trim($_POST['direccion_texto'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim($_POST['correo'] ?? '');

if (empty($nombre) || !$id_pais) {
    $error = 'El nombre y el país son obligatorios.';
    header("Location: " . ($id ? "editar.php?id_titu=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE titular 
            SET nombre = :nombre,
                id_pais = :id_pais,
                direccion_texto = :direccion_texto,
                telefono = :telefono,
                correo = :correo
            WHERE id_titular = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':id_pais' => $id_pais,
            ':direccion_texto' => $direccion_texto,
            ':telefono' => $telefono,
            ':correo' => $correo,
            ':id' => $id
        ]);
        $mensaje = 'Titular actualizado correctamente.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO titular (nombre, id_pais, direccion_texto, telefono, correo)
            VALUES (:nombre, :id_pais, :direccion_texto, :telefono, :correo)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':id_pais' => $id_pais,
            ':direccion_texto' => $direccion_texto,
            ':telefono' => $telefono,
            ':correo' => $correo
        ]);
        $mensaje = 'Titular creado correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    header("Location: " . ($id ? "editar.php?id_titu=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}
?>