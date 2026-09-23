<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_prod']) ? (int)$_GET['id_prod'] : 0;

if ($id <= 0) {
    setMensaje('ID de producto no válido.', 'danger');
    header('Location: index.php');
    exit;
}

// Verificar si el producto existe
$producto = obtenerProducto($id);
if (!$producto) {
    setMensaje('Producto no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

// Verificar si tiene dependencias (solicitudes)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitud WHERE id_producto = :id");
$stmt->execute([':id' => $id]);
if ($stmt->fetchColumn() > 0) {
    setMensaje('No se puede eliminar porque el producto tiene solicitudes asociadas.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM producto WHERE id_producto = :id");
    $stmt->execute([':id' => $id]);
    setMensaje('Producto eliminado correctamente.', 'success');
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el registro está asociado a otras operaciones.', 'danger');
}

header('Location: index.php');
exit;
?>