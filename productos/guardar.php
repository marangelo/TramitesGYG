<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_producto'] ?? null;
$numero_registro = trim($_POST['numero_registro'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$marca = trim($_POST['marca'] ?? '');
$id_fabricante = (int)($_POST['id_fabricante'] ?? 0);
$fecha_registro_inicial = $_POST['fecha_registro_inicial'] ?? null;
$fecha_vencimiento_registro = $_POST['fecha_vencimiento_registro'] ?? null;
$estado_producto = $_POST['estado_producto'] ?? 'Activo';

// Validar campos obligatorios
if (empty($nombre) || empty($marca) || $id_fabricante <= 0) {
    $error = 'El nombre, marca y fabricante son obligatorios.';
    header("Location: " . ($id ? "editar.php?id_prod=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}

// --- FORZAR ESTADO SEGÚN FECHA DE VENCIMIENTO ---
if (!empty($fecha_vencimiento_registro)) {
    $fecha_vencimiento = strtotime($fecha_vencimiento_registro);
    $fecha_actual = time();
    if ($fecha_vencimiento < $fecha_actual) {
        $estado_producto = 'Vencido';
    } else {
        // Si la fecha es mayor o igual a hoy, y el estado no es "Inactivo", lo dejamos como "Activo"
        // Pero respetamos si el usuario seleccionó "Inactivo" manualmente
        // Nota: si el usuario seleccionó Inactivo, se mantiene Inactivo, pero si es Vencido, se fuerza.
        // Si el estado es Vencido no debería llegar aquí porque lo forzamos arriba.
        if ($estado_producto == 'Vencido') {
            $estado_producto = 'Activo'; // Si se intentó poner Vencido manualmente, pero no ha vencido, lo ponemos Activo
        }
    }
} else {
    // Si no hay fecha de vencimiento, no se fuerza Vencido
    // Mantener el estado seleccionado, pero si es Vencido, cambiarlo a Activo
    if ($estado_producto == 'Vencido') {
        $estado_producto = 'Activo';
    }
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE producto 
            SET numero_registro = :numero_registro,
                nombre = :nombre,
                marca = :marca,
                id_fabricante = :id_fabricante,
                fecha_registro_inicial = :fecha_registro_inicial,
                fecha_vencimiento_registro = :fecha_vencimiento_registro,
                estado_producto = :estado_producto
            WHERE id_producto = :id
        ");
        $stmt->execute([
            ':numero_registro' => $numero_registro,
            ':nombre' => $nombre,
            ':marca' => $marca,
            ':id_fabricante' => $id_fabricante,
            ':fecha_registro_inicial' => $fecha_registro_inicial,
            ':fecha_vencimiento_registro' => $fecha_vencimiento_registro,
            ':estado_producto' => $estado_producto,
            ':id' => $id
        ]);
        $mensaje = 'Producto actualizado correctamente.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO producto (numero_registro, nombre, marca, id_fabricante, fecha_registro_inicial, fecha_vencimiento_registro, estado_producto)
            VALUES (:numero_registro, :nombre, :marca, :id_fabricante, :fecha_registro_inicial, :fecha_vencimiento_registro, :estado_producto)
        ");
        $stmt->execute([
            ':numero_registro' => $numero_registro,
            ':nombre' => $nombre,
            ':marca' => $marca,
            ':id_fabricante' => $id_fabricante,
            ':fecha_registro_inicial' => $fecha_registro_inicial,
            ':fecha_vencimiento_registro' => $fecha_vencimiento_registro,
            ':estado_producto' => $estado_producto
        ]);
        $mensaje = 'Producto creado correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    header("Location: " . ($id ? "editar.php?id_prod=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}
?>