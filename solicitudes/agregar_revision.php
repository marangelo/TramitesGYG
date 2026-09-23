<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Recibir datos del formulario
$id_solicitud    = (int)($_POST['id_solicitud'] ?? 0);
$id_estado_nuevo = (int)($_POST['id_estado_nuevo'] ?? 0);
$comentario      = trim($_POST['comentario'] ?? '');
$id_usuario      = $_SESSION['usuario_id'] ?? 0;

// Validar mínimos
if ($id_solicitud <= 0 || $id_estado_nuevo <= 0 || empty($comentario)) {
    setMensaje('Todos los campos son obligatorios.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

// Procesar documento si se subió
$ruta_documento = null;
if (isset($_FILES['documento_revision']) && $_FILES['documento_revision']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/documentos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $nombre_original = $_FILES['documento_revision']['name'];
    $extension       = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    if (in_array($extension, $extensiones_permitidas)) {
        $nombre_archivo = 'doc_rev_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $ruta_destino   = $upload_dir . $nombre_archivo;
        if (move_uploaded_file($_FILES['documento_revision']['tmp_name'], $ruta_destino)) {
            $ruta_documento = 'uploads/documentos/' . $nombre_archivo;
        }
    }
}

// Delegar TODA la validación de permisos y transición a la función
$resultado = agregarRevisionSolicitud($id_solicitud, $id_estado_nuevo, $id_usuario, $comentario);

// Si se subió un documento y la revisión fue exitosa, guardarlo asociado
if ($resultado['success'] && $ruta_documento) {
    // Actualizar la última revisión insertada con la ruta del documento
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE solicitud_revision 
        SET documento = :documento 
        WHERE id_solicitud = :id_solicitud 
        ORDER BY id_revision DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':documento'     => $ruta_documento,
        ':id_solicitud'  => $id_solicitud
    ]);
}

// Redirigir con el mensaje correspondiente
if ($resultado['success']) {
    setMensaje($resultado['message'], 'success');
} else {
    setMensaje($resultado['message'], 'danger');
}

header('Location: detalle.php?id=' . $id_solicitud);
exit;