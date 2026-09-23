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
$numero_registro = trim($_POST['numero_registro'] ?? '');
$fecha_registro_inicial = $_POST['fecha_registro_inicial'] ?? null;
$fecha_vencimiento_registro = $_POST['fecha_vencimiento_registro'] ?? null;
$comentario = trim($_POST['comentario'] ?? '');
$comentario = !empty($comentario) ? $comentario : 'Solicitud aprobada.';

if ($id_solicitud <= 0 || empty($numero_registro) || empty($fecha_registro_inicial) || empty($fecha_vencimiento_registro)) {
    setMensaje('Todos los campos son obligatorios.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

$solicitud = obtenerSolicitudPorId($id_solicitud);
if (!$solicitud) {
    setMensaje('Solicitud no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para aprobar solicitudes.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

if ($solicitud['estado_nombre'] !== 'Solicitud_evaluacion') {
    setMensaje('Solo se pueden aprobar solicitudes en estado Evaluación.', 'danger');
    header('Location: detalle.php?id=' . $id_solicitud);
    exit;
}

// Procesar documento si se subió
$ruta_documento = null;
if (isset($_FILES['documento']) && $_FILES['documento']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/documentos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $nombre_original = $_FILES['documento']['name'];
    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    $nombre_archivo = 'doc_aprob_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $ruta_destino = $upload_dir . $nombre_archivo;
    if (move_uploaded_file($_FILES['documento']['tmp_name'], $ruta_destino)) {
        $ruta_documento = 'uploads/documentos/' . $nombre_archivo;
    }
}

// Iniciar transacción
$pdo->beginTransaction();

try {
    // 1. Obtener datos propuestos del producto
    $producto_propuesto = obtenerProductoPropuesto($id_solicitud);
    if (!$producto_propuesto) {
        throw new Exception('No hay datos de producto para aprobar.');
    }

    // Agregar los campos de registro y fechas (si no existen)
    $producto_propuesto['numero_registro'] = $numero_registro;
    $producto_propuesto['fecha_registro_inicial'] = $fecha_registro_inicial;
    $producto_propuesto['fecha_vencimiento_registro'] = $fecha_vencimiento_registro;
    $producto_propuesto['estado_producto'] = 'Activo';

    // Guardar en el campo JSON actualizado
    guardarProductoPropuesto($id_solicitud, $producto_propuesto);

    // 2. Aplicar los cambios al producto (crear o actualizar)
    $resultado = aplicarProductoPropuesto($id_solicitud, $solicitud['id_producto']);
    if (!$resultado['success']) {
        throw new Exception($resultado['message']);
    }

    // Si se creó un nuevo producto, actualizar id_producto en la solicitud
    if ($resultado['id_producto'] && !$solicitud['id_producto']) {
        $stmtUpdate = $pdo->prepare("UPDATE solicitud SET id_producto = :id_prod WHERE id_solicitud = :id");
        $stmtUpdate->execute([':id_prod' => $resultado['id_producto'], ':id' => $id_solicitud]);
        $solicitud['id_producto'] = $resultado['id_producto'];
    }

    // 3. Cambiar estado a Aprobado
    $id_estado_aprobado = obtenerIdEstado('Solicitud_aprobado');
    $id_estado_actual = $solicitud['id_estado_actual'];

    $stmt = $pdo->prepare("UPDATE solicitud SET id_estado_actual = :id_estado WHERE id_solicitud = :id");
    $stmt->execute([':id_estado' => $id_estado_aprobado, ':id' => $id_solicitud]);

    // 4. Registrar revisión de aprobación
    $stmtNum = $pdo->prepare("SELECT MAX(numero_revision) FROM solicitud_revision WHERE id_solicitud = :id");
    $stmtNum->execute([':id' => $id_solicitud]);
    $ultimo_numero = (int)$stmtNum->fetchColumn();
    $numero_revision = $ultimo_numero + 1;

    $stmtRev = $pdo->prepare("
        INSERT INTO solicitud_revision 
        (id_solicitud, numero_revision, id_usuario_evaluador, fecha_revision, 
         id_estado_anterior, id_estado_nuevo, comentario_evaluador, documento)
        VALUES 
        (:id_solicitud, :numero_revision, :id_usuario, NOW(), 
         :id_estado_anterior, :id_estado_nuevo, :comentario, :documento)
    ");
    $stmtRev->execute([
        ':id_solicitud' => $id_solicitud,
        ':numero_revision' => $numero_revision,
        ':id_usuario' => $_SESSION['usuario_id'],
        ':id_estado_anterior' => $id_estado_actual,
        ':id_estado_nuevo' => $id_estado_aprobado,
        ':comentario' => $comentario,
        ':documento' => $ruta_documento
    ]);

    // 5. Limpiar campos propuestos (ya aplicados)
    $stmtClean = $pdo->prepare("UPDATE solicitud SET producto_datos_propuestos = NULL WHERE id_solicitud = :id");
    $stmtClean->execute([':id' => $id_solicitud]);

    // Confirmar transacción
    $pdo->commit();
    setMensaje('Solicitud aprobada correctamente. Producto actualizado.', 'success');

} catch (Exception $e) {
    // Revertir transacción solo si está activa
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setMensaje('Error al aprobar: ' . $e->getMessage(), 'danger');
}

header('Location: detalle.php?id=' . $id_solicitud);
exit;
?>