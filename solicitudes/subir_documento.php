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
$id_tipo_documento = (int)($_POST['id_tipo_documento'] ?? 0);
$id_revision = !empty($_POST['id_revision']) ? (int)$_POST['id_revision'] : null;

if ($id_solicitud <= 0 || $id_tipo_documento <= 0 || !isset($_FILES['archivo']) || $_FILES['archivo']['error'] != UPLOAD_ERR_OK) {
    setMensaje('Error al subir el archivo.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

$solicitud = obtenerSolicitudPorId($id_solicitud);
if (!$solicitud) {
    setMensaje('Solicitud no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

if (!puedeSubirDocumentos($solicitud['estado_nombre'])) {
    setMensaje('No se permiten subir documentos en el estado actual.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

if (!puedeAccederSolicitud($solicitud, 'action')) {
    setMensaje('No tienes permiso para subir documentos a esta solicitud.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

$extension = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
$nombre_archivo = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
$ruta_destino = '../uploads/documentos/' . $nombre_archivo;

if (!is_dir('../uploads/documentos/')) {
    mkdir('../uploads/documentos/', 0777, true);
}

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino)) {
    setMensaje('Error al mover el archivo.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

$ruta_relativa = 'uploads/documentos/' . $nombre_archivo;
$resultado = subirDocumentoSolicitud($id_solicitud, $id_tipo_documento, $ruta_relativa, $_SESSION['usuario_id'], $id_revision);

if ($resultado) {
    setMensaje('Documento subido correctamente.', 'success');
} else {
    setMensaje('Error al guardar el documento.', 'danger');
}

header('Location: detalle.php?id=' . $id_solicitud);
exit;
?>