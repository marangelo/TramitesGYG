<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

// Recibir datos del formulario
$id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : null;
$id_persona = (int)($_POST['id_persona'] ?? 0);
$nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
$password = $_POST['contraseña'] ?? '';  // Campo del formulario: "contraseña"
$activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

// Validaciones
if ($id_persona <= 0 || empty($nombre_usuario)) {
    $error = 'La persona y el nombre de usuario son obligatorios.';
    $redirect = $id_user ? "editar.php?id_user=$id_user" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}

// Verificar que la persona no esté ya asociada a otro usuario (solo en creación)
if (!$id_user) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = :id_persona");
    $stmt->execute([':id_persona' => $id_persona]);
    if ($stmt->fetchColumn() > 0) {
        $error = 'Esta persona ya tiene un usuario asignado.';
        header("Location: crear.php?error=" . urlencode($error));
        exit;
    }
}

try {
    if ($id_user) {
        // ========== ACTUALIZAR USUARIO ==========
        $sql = "UPDATE usuario 
                SET id_persona = :id_persona, 
                    nombre_usuario = :nombre_usuario, 
                    activo = :activo";
        $params = [
            ':id_persona' => $id_persona,
            ':nombre_usuario' => $nombre_usuario,
            ':activo' => $activo,
            ':id_user' => $id_user
        ];
        
        // Si se envió una nueva contraseña, actualizarla
        if (!empty($password)) {
            $sql .= ", contrasena_hash = :password";  // Nombre correcto de la columna
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id_usuario = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $mensaje = 'Usuario actualizado correctamente.';
    } else {
        // ========== CREAR NUEVO USUARIO ==========
        if (empty($password)) {
            $error = 'La contraseña es obligatoria para un nuevo usuario.';
            header("Location: crear.php?error=" . urlencode($error));
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO usuario (id_persona, nombre_usuario, contrasena_hash, activo)
            VALUES (:id_persona, :nombre_usuario, :password, :activo)
        ");
        
        $stmt->execute([
            ':id_persona' => $id_persona,
            ':nombre_usuario' => $nombre_usuario,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':activo' => $activo
        ]);
        
        $mensaje = 'Usuario creado correctamente.';
    }

    setMensaje($mensaje, 'success');
    header('Location: index.php');
    exit;
    
} catch (PDOException $e) {
    $error = 'Error al guardar: ' . $e->getMessage();
    $redirect = isset($id_user) ? "editar.php?id_user=$id_user" : "crear.php";
    header("Location: $redirect?error=" . urlencode($error));
    exit;
}
?>