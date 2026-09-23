<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = $_POST['id_requisito'] ?? null;
$id_direccion_anrs    = (int)($_POST['id_direccion_anrs'] ?? 0);
$id_tipo_licencia     = (int)($_POST['id_tipo_licencia'] ?? 0);
$id_tipo_tramite      = (int)($_POST['id_tipo_tramite'] ?? 0);
$id_tipo_modificacion = !empty($_POST['id_tipo_modificacion']) ? (int)$_POST['id_tipo_modificacion'] : null;
$id_tipo_documento    = (int)($_POST['id_tipo_documento'] ?? 0);
$obligatorio          = isset($_POST['obligatorio']) ? (int)$_POST['obligatorio'] : 1;

// Validar campos obligatorios
if (!$id_direccion_anrs || !$id_tipo_licencia || !$id_tipo_tramite || !$id_tipo_documento) {
    $error = 'Los campos con * son obligatorios.';
    $redirect = $id ? "editar.php?id_req=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

// Validar que el tipo de licencia pertenezca a la dirección seleccionada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tipo_licencia 
                       WHERE id_tipo_licencia = :id_tl 
                         AND id_direccion_anrs = :id_da");
$stmt->execute([
    ':id_tl' => $id_tipo_licencia,
    ':id_da' => $id_direccion_anrs
]);

if ((int)$stmt->fetchColumn() === 0) {
    $error = 'El tipo de licencia seleccionado no corresponde a la Dirección ANRS indicada.';
    $redirect = $id ? "editar.php?id_req=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

// Verificar duplicado
if (requisitoExiste($id_tipo_licencia, $id_tipo_tramite, $id_tipo_modificacion, $id_tipo_documento, $id)) {
    $error = 'Ya existe un requisito con esta combinación de licencia, trámite, modificación y documento.';
    $redirect = $id ? "editar.php?id_req=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE requisito_documento 
            SET id_tipo_licencia     = :id_tipo_licencia,
                id_tipo_tramite      = :id_tipo_tramite,
                id_tipo_modificacion = :id_tipo_modificacion,
                id_tipo_documento    = :id_tipo_documento,
                obligatorio          = :obligatorio
            WHERE id_requisito = :id
        ");
        $stmt->execute([
            ':id_tipo_licencia'     => $id_tipo_licencia,
            ':id_tipo_tramite'      => $id_tipo_tramite,
            ':id_tipo_modificacion' => $id_tipo_modificacion,
            ':id_tipo_documento'    => $id_tipo_documento,
            ':obligatorio'          => $obligatorio,
            ':id'                   => $id
        ]);
        $mensaje = 'Requisito actualizado correctamente.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO requisito_documento 
                (id_tipo_licencia, id_tipo_tramite, id_tipo_modificacion, id_tipo_documento, obligatorio)
            VALUES 
                (:id_tipo_licencia, :id_tipo_tramite, :id_tipo_modificacion, :id_tipo_documento, :obligatorio)
        ");
        $stmt->execute([
            ':id_tipo_licencia'     => $id_tipo_licencia,
            ':id_tipo_tramite'      => $id_tipo_tramite,
            ':id_tipo_modificacion' => $id_tipo_modificacion,
            ':id_tipo_documento'    => $id_tipo_documento,
            ':obligatorio'          => $obligatorio
        ]);
        $mensaje = 'Requisito creado correctamente.';
    }

    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    $redirect = $id ? "editar.php?id_req=$id" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}
?>