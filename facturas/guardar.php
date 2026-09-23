<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para crear facturas.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ============================================================
// DATOS DEL CLIENTE
// ============================================================
$nombre     = trim($_POST['nombre'] ?? '');
$direccion  = trim($_POST['direccion'] ?? '');
$telefono   = trim($_POST['telefono'] ?? '');
$moneda     = in_array($_POST['moneda'] ?? '', ['NIO','USD']) ? $_POST['moneda'] : 'NIO';
$id_titular = !empty($_POST['id_titular']) ? (int)$_POST['id_titular'] : null;

// ============================================================
// SOLICITUDES SELECCIONADAS
// ============================================================
$ids_solicitudes = $_POST['solicitudes'] ?? [];
$descripciones   = $_POST['descripcion'] ?? [];
$montos          = $_POST['monto_unitario'] ?? [];

if (empty($nombre)) {
    setMensaje('El nombre del cliente es obligatorio.', 'danger');
    header('Location: crear.php');
    exit;
}
if (empty($ids_solicitudes) || !is_array($ids_solicitudes)) {
    setMensaje('Debes seleccionar al menos una solicitud.', 'danger');
    header('Location: crear.php');
    exit;
}

// ============================================================
// CONSTRUIR ITEMS CON FALLBACK DE DESCRIPCIÓN
// Si la descripción viene vacía, reconstruirla desde la BD
// con formato: Producto — Tipo Trámite [— Tipo Modificación]
// ============================================================
$items = [];
foreach ($ids_solicitudes as $id_sol) {
    $id_sol = (int)$id_sol;
    if ($id_sol <= 0) continue;

    $descripcion_final = trim($descripciones[$id_sol] ?? '');

    // Si viene vacía, construir automáticamente
    if ($descripcion_final === '') {
        $stmtDesc = $pdo->prepare("
            SELECT 
                s.codigo_control_empresa,
                s.producto_datos_propuestos,
                p.nombre AS producto_nombre,
                tt.nombre AS tipo_tramite_nombre,
                tm.nombre AS tipo_modificacion_nombre
            FROM solicitud s
            LEFT JOIN producto p ON s.id_producto = p.id_producto
            LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
            LEFT JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
            WHERE s.id_solicitud = :id
        ");
        $stmtDesc->execute([':id' => $id_sol]);
        $info = $stmtDesc->fetch(PDO::FETCH_ASSOC);

        if ($info) {
            $nombre_producto = $info['producto_nombre'] ?: null;

            // Si es trámite nuevo, tomar del JSON propuesto
            if (empty($nombre_producto) && !empty($info['producto_datos_propuestos'])) {
                $prop = json_decode($info['producto_datos_propuestos'], true);
                if (is_array($prop) && !empty($prop['nombre'])) {
                    $nombre_producto = $prop['nombre'];
                }
            }
            if (empty($nombre_producto)) {
                $nombre_producto = '(Producto no asignado)';
            }

            $partes = [$nombre_producto];
            if (!empty($info['tipo_tramite_nombre'])) {
                $partes[] = $info['tipo_tramite_nombre'];
            }
            if (!empty($info['tipo_modificacion_nombre'])) {
                $partes[] = $info['tipo_modificacion_nombre'];
            }
            $descripcion_final = implode(' — ', $partes);
        } else {
            $descripcion_final = 'Servicio';
        }
    }

    $items[] = [
        'id_solicitud'   => $id_sol,
        'descripcion'    => $descripcion_final,
        'monto_unitario' => (float)($montos[$id_sol] ?? 0),
        'cantidad'       => 1,
    ];
}

if (empty($items)) {
    setMensaje('Debes seleccionar al menos una solicitud.', 'danger');
    header('Location: crear.php');
    exit;
}

// ============================================================
// CREAR LA FACTURA
// ============================================================
$resultado = crearFactura([
    'nombre'     => $nombre,
    'direccion'  => $direccion,
    'telefono'   => $telefono,
    'moneda'     => $moneda,
    'id_titular' => $id_titular,
], $items);

if ($resultado['success']) {
    setMensaje($resultado['message'], 'success');
    header('Location: detalle.php?id=' . $resultado['id_factura']);
} else {
    setMensaje($resultado['message'], 'danger');
    header('Location: crear.php');
}
exit;