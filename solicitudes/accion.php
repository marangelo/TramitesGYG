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
$accion = $_POST['accion'] ?? '';
$comentario = trim($_POST['comentario'] ?? '');
$numero_revision = isset($_POST['numero_revision']) ? (int)$_POST['numero_revision'] : null;

if ($id_solicitud <= 0 || empty($accion)) {
    setMensaje('Datos incompletos.', 'danger');
    header('Location: index.php');
    exit;
}

$solicitud = obtenerSolicitudPorId($id_solicitud);
if (!$solicitud) {
    setMensaje('Solicitud no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

if (!puedeAccederSolicitud($solicitud, 'action')) {
    setMensaje('No tienes permiso para realizar esta acción.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

$acciones_permitidas = obtenerAccionesPermitidas($solicitud);
$accion_valida = false;
foreach ($acciones_permitidas as $a) {
    if ($a['accion'] == $accion) {
        $accion_valida = true;
        break;
    }
}
if (!$accion_valida) {
    setMensaje('Acción no permitida.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

// Para aprobar con trámite Nuevo, recibir campos de registro
if ($accion === 'aprobar' && $solicitud['id_tipo_tramite'] == 1) {
    $numero_registro = trim($_POST['numero_registro'] ?? '');
    $fecha_registro_inicial = $_POST['fecha_registro_inicial'] ?? null;
    $fecha_vencimiento_registro = $_POST['fecha_vencimiento_registro'] ?? null;

    if (empty($numero_registro) || empty($fecha_registro_inicial) || empty($fecha_vencimiento_registro)) {
        setMensaje('Número de registro y fechas de vigencia son obligatorios.', 'danger');
        header('Location: detalle.php?id=' . $id_solicitud);
        exit;
    }

    // Agregar datos al producto propuesto
    $datos_propuestos = obtenerProductoPropuesto($id_solicitud);
    if (!$datos_propuestos) {
        $datos_propuestos = [];
    }
    $datos_propuestos['numero_registro'] = $numero_registro;
    $datos_propuestos['fecha_registro_inicial'] = $fecha_registro_inicial;
    $datos_propuestos['fecha_vencimiento_registro'] = $fecha_vencimiento_registro;
    guardarProductoPropuesto($id_solicitud, $datos_propuestos);
}

// Datos para el cambio de estado
$datos = [
    'comentario_evaluador' => $comentario,
    'numero_revision' => $numero_revision
];

// Ejecutar cambio de estado
$resultado = cambiarEstadoSolicitud($id_solicitud, $accion, $_SESSION['usuario_id'], $datos);

if ($resultado['success']) {
    // Si la acción es Rechazar o Reabrir, y se subió un documento, asociarlo a la revisión
    if (in_array($accion, ['rechazar', 'reabrir']) && isset($_FILES['documento']) && $_FILES['documento']['error'] == UPLOAD_ERR_OK) {
        // Obtener el ID de la última revisión creada para esta solicitud
        $stmtRev = $pdo->prepare("SELECT MAX(id_revision) as id_revision FROM solicitud_revision WHERE id_solicitud = :id");
        $stmtRev->execute([':id' => $id_solicitud]);
        $id_revision = $stmtRev->fetchColumn();
        
        if ($id_revision) {
            $upload_dir = '../uploads/documentos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $nombre_original = $_FILES['documento']['name'];
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nombre_archivo = 'doc_rev_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $ruta_destino = $upload_dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $ruta_destino)) {
                $ruta_relativa = 'uploads/documentos/' . $nombre_archivo;
                // Guardar en Documento_Solicitud asociado a la revisión
                $stmtDoc = $pdo->prepare("
                    INSERT INTO documento_solicitud (id_solicitud, id_revision, id_tipo_documento, ruta_archivo, fecha_subida, id_usuario_subio)
                    VALUES (:id_solicitud, :id_revision, 0, :ruta, NOW(), :id_usuario)
                ");
                $stmtDoc->execute([
                    ':id_solicitud' => $id_solicitud,
                    ':id_revision' => $id_revision,
                    ':ruta' => $ruta_relativa,
                    ':id_usuario' => $_SESSION['usuario_id']
                ]);
            }
        }
    }
    setMensaje($resultado['message'], 'success');
} else {
    setMensaje($resultado['message'], 'danger');
}

header('Location: detalle.php?id=' . $id_solicitud);
exit;
?>