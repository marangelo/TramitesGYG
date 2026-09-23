<?php
session_start();
require_once 'config.php';

unset($_SESSION['login_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $contrasena = $_POST['contraseña'] ?? '';

    if (empty($nombre_usuario) || empty($contrasena)) {
        $_SESSION['login_error'] = 'Por favor, completa todos los campos.';
        header('Location: index.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id_usuario, id_persona, nombre_usuario, contrasena_hash, activo FROM usuario WHERE nombre_usuario = :usuario AND activo = 1");
        $stmt->execute([':usuario' => $nombre_usuario]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $_SESSION['login_error'] = 'Usuario no encontrado o inactivo.';
            header('Location: index.php');
            exit;
        }

        if (password_verify($contrasena, $usuario['contrasena_hash'])) {
            // Datos básicos
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
            $_SESSION['id_persona'] = $usuario['id_persona'];

            // Obtener roles y titulares asociados
            $stmtPermisos = $pdo->prepare("SELECT id_rol, id_titular FROM permiso WHERE id_usuario = :id_usuario");
            $stmtPermisos->execute([':id_usuario' => $usuario['id_usuario']]);
            $permisos = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);

            $roles = array_column($permisos, 'id_rol');
            $titulares = array_column($permisos, 'id_titular');

            $_SESSION['roles'] = array_unique($roles);
            $_SESSION['titulares'] = array_unique($titulares);

            // Si es admin (rol 3) no necesita titulares, pero los dejamos por si acaso
            // Ahora redirigir al dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_error'] = 'Contraseña incorrecta.';
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        $error = 'Error al iniciar sesión: ' . $e->getMessage();
        $_SESSION['login_error'] = 'Error en el servidor. Intenta más tarde.';
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>