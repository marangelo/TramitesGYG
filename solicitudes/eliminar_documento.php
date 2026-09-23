<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin()) {
    setMensaje('No tienes permiso para eliminar documentos.', 'danger');
    header('Location: index.php');
    exit;
}

$id_doc = isset($_GET['id_doc']) ? (int)$_GET['id_doc'] : 0;
$id_sol = isset($_GET['id_sol']) ? (int)$_GET['id_sol'] : 0;

if ($id_doc <= 0 || $id_sol <= 0) {
    setMensaje('Datos no válidos.', 'danger');
    header('Location: index.php');
    exit;
}

$resultado = eliminarDocumentoSolicitud($id_doc);
if ($resultado) {
    setMensaje('Documento eliminado correctamente.', 'success');
} else {
    setMensaje('Error al eliminar el documento.', 'danger');
}

header('Location: detalle.php?id=' . $id_sol);
exit;
?>