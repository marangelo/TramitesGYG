<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_tlic']) ? (int)$_GET['id_tlic'] : 0;

if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$tipo = obtenerTipoLicencia($id);
if (!$tipo) {
    setMensaje('Tipo de licencia no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

// Verificar dependencias
$dependencias = verificarDependenciasTipoLicencia($id);
if (count($dependencias) > 0) {
    $tablas = implode(', ', $dependencias);
    setMensaje("No se puede eliminar porque está asociado a: $tablas.", 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM tipo_licencia WHERE id_tipo_licencia = :id");
    $stmt->execute([':id' => $id]);
    setMensaje('Tipo de licencia eliminado correctamente.', 'success');
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque tiene registros asociados.', 'danger');
}

header('Location: index.php');
exit;
?>