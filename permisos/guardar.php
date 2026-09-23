<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
$roles = isset($_POST['roles']) ? $_POST['roles'] : [];
$titulares_ids = isset($_POST['titulares_ids']) && !empty($_POST['titulares_ids']) 
    ? array_map('intval', explode(',', $_POST['titulares_ids'])) 
    : [];

if ($id_user <= 0) {
    setMensaje('ID de usuario no válido.', 'danger');
    header('Location: index.php');
    exit;
}

if (empty($roles)) {
    setMensaje('Debe seleccionar al menos un rol.', 'danger');
    header("Location: asignar.php?id_user=$id_user");
    exit;
}

$es_admin = in_array(3, $roles);
if (!$es_admin && empty($titulares_ids)) {
    setMensaje('Debe asignar al menos un titular (a menos que sea Administrador).', 'danger');
    header("Location: asignar.php?id_user=$id_user");
    exit;
}

if ($es_admin) {
    $titulares_ids = [];
}

try {
    $resultado = guardarPermisosUsuario($id_user, $roles, $titulares_ids);
    if ($resultado) {
        setMensaje('Permisos asignados correctamente.', 'success');
    } else {
        setMensaje('Error al guardar los permisos.', 'danger');
    }
} catch (Exception $e) {
    setMensaje('Error al guardar: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit;
?>