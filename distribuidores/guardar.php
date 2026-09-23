<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_distribuidor'] ?? null;
$numero_licencia = trim($_POST['numero_licencia'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$id_departamento = (int)($_POST['id_departamento'] ?? 0);
$id_municipio = (int)($_POST['id_municipio'] ?? 0);
$direccion_texto = trim($_POST['direccion_texto'] ?? '');
$id_direccion_anrs = (int)($_POST['id_direccion_anrs'] ?? 0);
$id_tipo_licencia = (int)($_POST['id_tipo_licencia'] ?? 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$fecha_vence = $_POST['fecha_vence'] ?? null;

if (empty($numero_licencia) || empty($nombre) || !$id_departamento || !$id_municipio || !$id_direccion_anrs || !$id_tipo_licencia || !$fecha_inicio || !$fecha_vence) {
    $error = 'Todos los campos marcados con * son obligatorios.';
    header("Location: " . ($id ? "editar.php?id_dist=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE distribuidor 
            SET numero_licencia = :numero_licencia,
                nombre = :nombre,
                id_departamento = :id_departamento,
                id_municipio = :id_municipio,
                direccion_texto = :direccion_texto,
                id_direccion_anrs = :id_direccion_anrs,
                id_tipo_licencia = :id_tipo_licencia,
                fecha_inicio = :fecha_inicio,
                fecha_vence = :fecha_vence
            WHERE id_distribuidor = :id
        ");
        $stmt->execute([
            ':numero_licencia' => $numero_licencia,
            ':nombre' => $nombre,
            ':id_departamento' => $id_departamento,
            ':id_municipio' => $id_municipio,
            ':direccion_texto' => $direccion_texto,
            ':id_direccion_anrs' => $id_direccion_anrs,
            ':id_tipo_licencia' => $id_tipo_licencia,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_vence' => $fecha_vence,
            ':id' => $id
        ]);
        $mensaje = 'Distribuidor actualizado correctamente.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO distribuidor (numero_licencia, nombre, id_departamento, id_municipio, direccion_texto, id_direccion_anrs, id_tipo_licencia, fecha_inicio, fecha_vence)
            VALUES (:numero_licencia, :nombre, :id_departamento, :id_municipio, :direccion_texto, :id_direccion_anrs, :id_tipo_licencia, :fecha_inicio, :fecha_vence)
        ");
        $stmt->execute([
            ':numero_licencia' => $numero_licencia,
            ':nombre' => $nombre,
            ':id_departamento' => $id_departamento,
            ':id_municipio' => $id_municipio,
            ':direccion_texto' => $direccion_texto,
            ':id_direccion_anrs' => $id_direccion_anrs,
            ':id_tipo_licencia' => $id_tipo_licencia,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_vence' => $fecha_vence
        ]);
        $mensaje = 'Distribuidor creado correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    header("Location: " . ($id ? "editar.php?id_dist=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}
?>