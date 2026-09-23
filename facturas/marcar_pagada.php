<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para modificar facturas.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_factura = (int)($_POST['id_factura'] ?? 0);
if ($id_factura <= 0) {
    setMensaje('ID de factura no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$resultado = marcarFacturaComoPagada($id_factura, $_SESSION['usuario_id']);

if ($resultado['success']) {
    setMensaje($resultado['message'], 'success');
} else {
    setMensaje($resultado['message'], 'danger');
}

header('Location: detalle.php?id=' . $id_factura);
exit;