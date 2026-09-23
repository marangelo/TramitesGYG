<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_persona'] ?? null;
$id_tipo_identificacion = (int)($_POST['id_tipo_identificacion'] ?? 0);
$numero_identificacion = trim($_POST['numero_identificacion'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$id_pais = (int)($_POST['id_pais'] ?? 0);
$id_departamento = !empty($_POST['id_departamento']) ? (int)$_POST['id_departamento'] : null;
$id_municipio = !empty($_POST['id_municipio']) ? (int)$_POST['id_municipio'] : null;
$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim($_POST['correo'] ?? '');

if (!$id_tipo_identificacion || empty($numero_identificacion) || empty($nombre) || !$id_pais) {
    $error = 'Todos los campos marcados con * son obligatorios.';
    header("Location: " . ($id ? "editar.php?id_pers=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}

$idNicaragua = obtenerIdNicaragua();
if ($id_pais == $idNicaragua) {
    if (empty($id_departamento) || empty($id_municipio)) {
        $error = 'Si el país es Nicaragua, el departamento y municipio son obligatorios.';
        header("Location: " . ($id ? "editar.php?id_pers=$id" : "crear.php") . "?error=" . urlencode($error));
        exit;
    }
} else {
    $id_departamento = null;
    $id_municipio = null;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE personas 
            SET id_tipo_identificacion = :id_tipo_identificacion,
                numero_identificacion = :numero_identificacion,
                nombre = :nombre,
                id_pais = :id_pais,
                id_departamento = :id_departamento,
                id_municipio = :id_municipio,
                direccion = :direccion,
                telefono = :telefono,
                correo = :correo
            WHERE id_persona = :id
        ");
        $stmt->execute([
            ':id_tipo_identificacion' => $id_tipo_identificacion,
            ':numero_identificacion' => $numero_identificacion,
            ':nombre' => $nombre,
            ':id_pais' => $id_pais,
            ':id_departamento' => $id_departamento,
            ':id_municipio' => $id_municipio,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':correo' => $correo,
            ':id' => $id
        ]);
        $mensaje = 'Persona actualizada correctamente.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO personas (id_tipo_identificacion, numero_identificacion, nombre, id_pais, id_departamento, id_municipio, direccion, telefono, correo)
            VALUES (:id_tipo_identificacion, :numero_identificacion, :nombre, :id_pais, :id_departamento, :id_municipio, :direccion, :telefono, :correo)
        ");
        $stmt->execute([
            ':id_tipo_identificacion' => $id_tipo_identificacion,
            ':numero_identificacion' => $numero_identificacion,
            ':nombre' => $nombre,
            ':id_pais' => $id_pais,
            ':id_departamento' => $id_departamento,
            ':id_municipio' => $id_municipio,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':correo' => $correo
        ]);
        $mensaje = 'Persona creada correctamente.';
    }
    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    header("Location: " . ($id ? "editar.php?id_pers=$id" : "crear.php") . "?error=" . urlencode($error));
    exit;
}
?>