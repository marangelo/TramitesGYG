<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

// Recibir el ID específico de persona
$id = isset($_GET['id_pers']) ? (int)$_GET['id_pers'] : 0;

if ($id <= 0) {
    setMensaje('ID de persona no válido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    // Verificar si existe
    $stmtCheck = $pdo->prepare("SELECT id_persona FROM personas WHERE id_persona = :id");
    $stmtCheck->execute([':id' => $id]);
    if (!$stmtCheck->fetch()) {
        setMensaje('No se encontró la persona con ID ' . $id . '.', 'warning');
        header('Location: index.php');
        exit;
    }

    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM personas WHERE id_persona = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        setMensaje('Persona eliminada correctamente.', 'success');
    } else {
        setMensaje('No se pudo eliminar la persona.', 'warning');
    }
} catch (PDOException $e) {
    setMensaje('No se puede eliminar porque el registro está asociado a otras operaciones.', 'danger');
}

header('Location: index.php');
exit;
?>