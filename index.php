<?php
session_start();
// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login ANRS - Sistema de Trámites</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts (opcional) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 600px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.12), 0 10px 30px rgba(0,0,0,0.06);
            overflow: hidden;
            margin: 20px;
        }
        /* Panel izquierdo (sidebar) */
        .login-sidebar {
            width: 40%;
            background: linear-gradient(145deg, #0b2a4a 0%, #1a4a7a 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }
        .login-sidebar .brand-icon {
            font-size: 4rem;
            background: rgba(255,255,255,0.12);
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            backdrop-filter: blur(4px);
            border: 2px solid rgba(255,255,255,0.25);
        }
        .login-sidebar h1 {
            font-weight: 300;
            font-size: 2.6rem;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .login-sidebar h1 span {
            font-weight: 700;
        }
        .login-sidebar p {
            opacity: 0.8;
            font-size: 1.1rem;
            max-width: 280px;
            line-height: 1.6;
        }
        .login-sidebar .decorative-line {
            width: 60px;
            height: 3px;
            background: rgba(255,255,255,0.4);
            margin: 25px auto;
            border-radius: 10px;
        }
        .login-sidebar .footer-small {
            position: absolute;
            bottom: 30px;
            font-size: 0.8rem;
            opacity: 0.5;
        }
        /* Panel derecho (formulario) */
        .login-form {
            width: 60%;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }
        .login-form h2 {
            font-weight: 600;
            font-size: 2rem;
            color: #1a3a5c;
            margin-bottom: 8px;
        }
        .login-form .subtitle {
            color: #6c7a8a;
            margin-bottom: 35px;
            font-size: 0.95rem;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .form-floating input {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #fafcff;
            transition: all 0.2s;
        }
        .form-floating input:focus {
            border-color: #1a4a7a;
            box-shadow: 0 0 0 4px rgba(26, 74, 122, 0.15);
            background: white;
        }
        .form-floating label {
            color: #6c7a8a;
            font-weight: 500;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            background: #1a4a7a;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.25s;
            margin-top: 10px;
            box-shadow: 0 6px 20px rgba(26, 74, 122, 0.25);
        }
        .btn-login:hover {
            background: #0b2a4a;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(26, 74, 122, 0.35);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .btn-login i {
            margin-right: 8px;
        }
        .alert-custom {
            border-radius: 12px;
            border-left: 4px solid #b91c1c;
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 18px;
            margin-bottom: 25px;
            display: <?php echo isset($_SESSION['login_error']) ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 12px;
        }
        .alert-custom i {
            font-size: 1.2rem;
        }
        /* Responsive */
        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 500px;
                min-height: auto;
                border-radius: 20px;
            }
            .login-sidebar {
                width: 100%;
                padding: 40px 30px;
                border-radius: 20px 20px 0 0;
                min-height: 220px;
            }
            .login-sidebar .brand-icon {
                width: 80px;
                height: 80px;
                font-size: 2.8rem;
                margin-bottom: 20px;
            }
            .login-sidebar h1 {
                font-size: 2rem;
            }
            .login-sidebar .footer-small {
                display: none;
            }
            .login-form {
                width: 100%;
                padding: 40px 30px;
            }
        }
        @media (max-width: 480px) {
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Sidebar izquierdo -->
        <div class="login-sidebar">
           <img src="image/logo.jpg" alt="Logo ANRS" style="height: 250px; width: auto; border-radius: 50%;">
            <div class="decorative-line"></div>
            <p>Gestión de solicitudes de registro sanitario y licencias</p>
            <div class="footer-small">&copy; <?php echo date('Y'); ?> ANRS</div>
        </div>

        <!-- Formulario derecho -->
        <div class="login-form">
            <h2>Iniciar Sesión</h2>
            <p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>

            <!-- Mensaje de error -->
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert-custom" style="display:flex;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['login_error']); ?></span>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <div class="form-floating">
                    <input type="text" class="form-control" id="usuario" name="nombre_usuario" placeholder="Usuario" required>
                    <label for="usuario"><i class="fas fa-user me-2"></i>Usuario</label>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="contraseña" placeholder="Contraseña" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                </div>
                <button type="submit" class="btn btn-login text-white">
                    <i class="fas fa-sign-in-alt"></i> Ingresar
                </button>
            </form>

            <div class="mt-4 text-center text-muted small">
                Sistema de trámites ANRS v1.0
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (opcional para toggles, etc.) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>