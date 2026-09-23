<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_solicitud = (int)($_POST['id_solicitud'] ?? 0);
$producto_nombre_nuevo = trim($_POST['producto_nombre_nuevo'] ?? '');
$producto_marca_nueva = trim($_POST['producto_marca_nueva'] ?? '');

if ($id_solicitud <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$solicitud = obtenerSolicitudPorId($id_solicitud);
if (!$solicitud) {
    setMensaje('Solicitud no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para modificar los campos de producto.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

if (in_array($solicitud['estado_nombre'], ['Solicitud_aprobado', 'Solicitud_cancelado'])) {
    setMensaje('No se pueden modificar los campos de producto en estados terminales.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE solicitud 
        SET producto_nombre_nuevo = :nombre_nuevo,
            producto_marca_nueva = :marca_nueva
        WHERE id_solicitud = :id
    ");
    $stmt->execute([
        ':nombre_nuevo' => $producto_nombre_nuevo ?: null,
        ':marca_nueva' => $producto_marca_nueva ?: null,
        ':id' => $id_solicitud
    ]);
    setMensaje('Campos propuestos actualizados correctamente.', 'success');
} catch (Exception $e) {
    setMensaje('Error al actualizar: ' . $e->getMessage(), 'danger');
}

header('Location: detalle.php?id=' . $id_solicitud);
exit;
?>