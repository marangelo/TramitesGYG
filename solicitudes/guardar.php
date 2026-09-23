<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!tieneRol(1) && !esAdmin()) {
    setMensaje('No tienes permiso para crear solicitudes.', 'danger');
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id_solicitud']) ? (int)$_POST['id_solicitud'] : null;
$es_edicion = $id !== null;

// Datos básicos de la solicitud
$id_direccion_anrs      = (int)($_POST['id_direccion_anrs'] ?? 0);
$id_tipo_licencia       = (int)($_POST['id_tipo_licencia'] ?? 0);
$id_tipo_tramite        = (int)($_POST['id_tipo_tramite'] ?? 0);
$id_tipo_modificacion   = !empty($_POST['id_tipo_modificacion']) ? (int)$_POST['id_tipo_modificacion'] : null;
$id_representante_legal = (int)($_POST['id_representante_legal'] ?? 0);
$id_titular             = (int)($_POST['id_titular'] ?? 0);
$codigo_control_empresa = trim($_POST['codigo_control_empresa'] ?? '');

// ============================================================
// DISTRIBUIDORES (N:N)
// ============================================================
$distribuidores = $_POST['distribuidores'] ?? [];
if (!is_array($distribuidores)) {
    $distribuidores = [];
}
// Limpiar: solo enteros > 0, sin duplicados
$distribuidores = array_values(array_unique(array_filter(array_map('intval', $distribuidores), function($v) {
    return $v > 0;
})));

// Validar campos obligatorios
if (!$id_direccion_anrs || !$id_tipo_licencia || !$id_tipo_tramite || !$id_representante_legal || !$id_titular) {
    $error = 'Todos los campos con * son obligatorios.';
    $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
    header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
    exit;
}

// Validar que se haya seleccionado al menos un distribuidor
if (empty($distribuidores)) {
    $error = 'Debes seleccionar al menos un distribuidor.';
    $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
    header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
    exit;
}

// Si es Modificación o Renovación con Modificación, el tipo_modificacion es obligatorio
if (($id_tipo_tramite == 2 || $id_tipo_tramite == 4) && empty($id_tipo_modificacion)) {
    $error = 'El tipo de modificación es obligatorio para este trámite.';
    $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
    header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
    exit;
}

// ============================================================
// LÓGICA DE PRODUCTO SEGÚN TIPO DE TRÁMITE
// ============================================================
$producto_propuesto = [];
$id_producto = null;

if ($id_tipo_tramite == 1) {
    // CASO: TRÁMITE NUEVO → PRODUCTO NUEVO (id_producto se deja NULL)
    $producto_propuesto['nombre'] = trim($_POST['producto_nombre'] ?? '');
    $producto_propuesto['marca'] = trim($_POST['producto_marca'] ?? '');
    $producto_propuesto['id_fabricante'] = (int)($_POST['producto_id_fabricante'] ?? 0);
    $producto_propuesto['numero_registro'] = trim($_POST['producto_numero_registro'] ?? '');
    $producto_propuesto['fecha_registro_inicial'] = $_POST['producto_fecha_registro_inicial'] ?? null;
    $producto_propuesto['fecha_vencimiento_registro'] = $_POST['producto_fecha_vencimiento_registro'] ?? null;
    $producto_propuesto['estado_producto'] = $_POST['producto_estado_producto'] ?? 'Activo';

    if (empty($producto_propuesto['nombre']) || empty($producto_propuesto['marca']) || empty($producto_propuesto['id_fabricante'])) {
        $error = 'Nombre, marca y fabricante son obligatorios para un producto nuevo.';
        $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
        header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
        exit;
    }
    $id_producto = null;
} else {
    // CASO: PRODUCTO EXISTENTE
    $id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
    if ($id_producto <= 0) {
        $error = 'Debe seleccionar un producto existente.';
        $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
        header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
        exit;
    }

    // Solo para Modificación (2) o Renovación con Modificación (4) se permiten cambios propuestos
    if ($id_tipo_tramite == 2 || $id_tipo_tramite == 4) {
        $propuesto_nombre = trim($_POST['propuesto_nombre'] ?? '');
        $propuesto_marca = trim($_POST['propuesto_marca'] ?? '');
        $propuesto_fabricante = (int)($_POST['propuesto_id_fabricante'] ?? 0);

        if (!empty($propuesto_nombre)) $producto_propuesto['nombre'] = $propuesto_nombre;
        if (!empty($propuesto_marca)) $producto_propuesto['marca'] = $propuesto_marca;
        if ($propuesto_fabricante > 0) $producto_propuesto['id_fabricante'] = $propuesto_fabricante;
    }
}

if (empty($producto_propuesto)) {
    $producto_propuesto = null;
}

// ============================================================
// GUARDAR SOLICITUD (CREAR O ACTUALIZAR)
// ============================================================
try {
    $pdo->beginTransaction();

    if ($es_edicion) {
        // Actualizar solicitud existente
        $solicitud = obtenerSolicitudPorId($id);
        if (!$solicitud || $solicitud['estado_nombre'] != 'Nueva' || !puedeAccederSolicitud($solicitud, 'edit')) {
            throw new Exception('No se puede editar esta solicitud.');
        }

        $stmt = $pdo->prepare("
            UPDATE solicitud 
            SET id_direccion_anrs = :id_direccion_anrs,
                id_tipo_licencia = :id_tipo_licencia,
                id_tipo_tramite = :id_tipo_tramite,
                id_tipo_modificacion = :id_tipo_modificacion,
                id_producto = :id_producto,
                id_representante_legal = :id_representante_legal,
                id_titular = :id_titular,
                codigo_control_empresa = :codigo_control_empresa,
                producto_datos_propuestos = :producto_datos
            WHERE id_solicitud = :id
        ");
        $stmt->execute([
            ':id_direccion_anrs'      => $id_direccion_anrs,
            ':id_tipo_licencia'       => $id_tipo_licencia,
            ':id_tipo_tramite'        => $id_tipo_tramite,
            ':id_tipo_modificacion'   => $id_tipo_modificacion,
            ':id_producto'            => $id_producto,
            ':id_representante_legal' => $id_representante_legal,
            ':id_titular'             => $id_titular,
            ':codigo_control_empresa' => $codigo_control_empresa,
            ':producto_datos'         => $producto_propuesto ? json_encode($producto_propuesto) : null,
            ':id'                     => $id
        ]);
        $mensaje = 'Solicitud actualizada correctamente.';
        $redirect = "detalle.php?id=$id";
    } else {
        // Crear nueva solicitud
        $id_estado_nueva = obtenerIdEstado('Nueva');
        $stmt = $pdo->prepare("
            INSERT INTO solicitud 
            (id_direccion_anrs, id_tipo_licencia, id_tipo_tramite, id_tipo_modificacion,
             id_producto, id_representante_legal, id_titular, codigo_control_empresa,
             producto_datos_propuestos, id_estado_actual, fecha_solicita)
            VALUES 
            (:id_direccion_anrs, :id_tipo_licencia, :id_tipo_tramite, :id_tipo_modificacion,
             :id_producto, :id_representante_legal, :id_titular, :codigo_control_empresa,
             :producto_datos, :id_estado, NOW())
        ");
        $stmt->execute([
            ':id_direccion_anrs'      => $id_direccion_anrs,
            ':id_tipo_licencia'       => $id_tipo_licencia,
            ':id_tipo_tramite'        => $id_tipo_tramite,
            ':id_tipo_modificacion'   => $id_tipo_modificacion,
            ':id_producto'            => $id_producto,
            ':id_representante_legal' => $id_representante_legal,
            ':id_titular'             => $id_titular,
            ':codigo_control_empresa' => $codigo_control_empresa,
            ':producto_datos'         => $producto_propuesto ? json_encode($producto_propuesto) : null,
            ':id_estado'              => $id_estado_nueva
        ]);
        $id = $pdo->lastInsertId();
        $mensaje = 'Solicitud creada correctamente como borrador.';
        $redirect = "detalle.php?id=$id";
    }

    // ============================================================
    // GUARDAR DISTRIBUIDORES ASOCIADOS (N:N)
    // ============================================================
    $distribuidores_guardados = guardarDistribuidoresDeSolicitud($id, $distribuidores);
    if (!$distribuidores_guardados) {
        throw new Exception('Error al guardar los distribuidores asociados.');
    }

    // ============================================================
    // PROCESAR DOCUMENTOS (si se subieron archivos)
    // ============================================================
    if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name']) && is_array($_FILES['documentos']['name'])) {
        $upload_dir = '../uploads/documentos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['documentos']['name'] as $id_tipo_doc => $nombre_original) {
            if (empty($nombre_original)) continue;

            $tmp_name = $_FILES['documentos']['tmp_name'][$id_tipo_doc] ?? null;
            if (!$tmp_name || !is_uploaded_file($tmp_name)) continue;

            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            $extensiones_permitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $extensiones_permitidas)) continue;

            $nombre_archivo = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $ruta_destino = $upload_dir . $nombre_archivo;

            if (move_uploaded_file($tmp_name, $ruta_destino)) {
                $ruta_relativa = 'uploads/documentos/' . $nombre_archivo;
                subirDocumentoSolicitud($id, (int)$id_tipo_doc, $ruta_relativa, $_SESSION['usuario_id']);
            }
        }
    }

    $pdo->commit();
    setMensaje($mensaje, 'success');
    header("Location: $redirect");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = 'Error al guardar: ' . $e->getMessage();
    $redirect = $es_edicion ? "editar.php?id=$id" : "crear.php";
    header("Location: $redirect" . ($es_edicion ? '&' : '?') . "error=" . urlencode($error));
    exit;
}
?>