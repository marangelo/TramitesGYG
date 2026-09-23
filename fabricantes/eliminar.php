<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_fab']) ? (int)$_GET['id_fab'] : 0;

if ($id <= 0) {
    setMensaje('ID de fabricante no válido.', 'danger');
    header('Location: index.php');
    exit;
}

// Verificar si el fabricante existe
$fabricante = obtenerFabricante($id);
if (!$fabricante) {
    setMensaje('Fabricante no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

// Verificar si tiene productos asociados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM producto WHERE id_fabricante = :id");
$stmt->execute([':id' => $id]);
if ($stmt->fetchColumn() > 0) {
    setMensaje('No se puede eliminar porque el fabricante tiene productos asociados.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM fabricante WHERE id_fabricante = :id");
    $stmt->execute([':id' => $id]);
    setMensaje('Fabricante eliminado correctamente.', 'success');
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el registro está asociado a otras operaciones.', 'danger');
}

header('Location: index.php');
exit;
?>