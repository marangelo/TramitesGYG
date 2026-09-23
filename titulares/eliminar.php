<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_titu']) ? (int)$_GET['id_titu'] : 0;

if ($id <= 0) {
    setMensaje('ID de titular no válido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $stmtCheck = $pdo->prepare("SELECT id_titular FROM titular WHERE id_titular = :id");
    $stmtCheck->execute([':id' => $id]);
    if (!$stmtCheck->fetch()) {
        setMensaje('No se encontró el titular con ID ' . $id . '.', 'warning');
        header('Location: index.php');
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM titular WHERE id_titular = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        setMensaje('Titular eliminado correctamente.', 'success');
    } else {
        setMensaje('No se pudo eliminar el titular.', 'warning');
    }
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el registro está asociado a otras operaciones.', 'danger');
}

header('Location: index.php');
exit;
?>