<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_req']) ? (int)$_GET['id_req'] : 0;

if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM requisito_documento WHERE id_requisito = :id");
    $stmt->execute([':id' => $id]);
    setMensaje('Requisito eliminado correctamente.', 'success');
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el registro está asociado a otras operaciones.', 'danger');
}

header('Location: index.php');
exit;
?>