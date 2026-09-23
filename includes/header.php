<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$in_catalogos   = in_array($current_dir, ['distribuidores', 'titulares', 'personas', 'productos', 'fabricantes', 'tipos_licencia', 'tipo_modificacion', 'tipo_documento', 'requisitos']);
$in_solicitudes = ($current_page == 'mis_tramites.php' || $current_page == 'evaluaciones.php');
$in_admin       = ($current_page == 'usuarios.php' || $current_page == 'roles.php' || $current_page == 'configuracion.php');
$in_reportes    = ($current_page == 'reporte_tramites.php' || $current_page == 'estadisticas.php');
$in_facturas    = ($current_dir == 'facturas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo ?? 'ANRS Trámites'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f6f9; min-height: 100vh; }
        .sidebar {
            min-height: 100vh;
            background: #0b2a4a;
            color: white;
            padding: 20px 0;
        }
        .sidebar .brand {
            font-size: 1.8rem;
            font-weight: 300;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .brand span { font-weight: 700; }
        .sidebar .user-badge {
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.9rem;
            margin: 10px 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 8px;
            margin: 4px 12px;
            padding: 12px 16px;
            transition: 0.2s;
            cursor: pointer;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }
        /* Flechas para los encabezados colapsables */
        .sidebar .nav-link[data-bs-toggle="collapse"]::after {
            content: "\f078";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            float: right;
            margin-left: 10px;
            transition: transform 0.2s;
        }
        .sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"]::after {
            content: "\f077";
        }
        .sidebar .collapse .nav-link {
            padding-left: 40px;
        }
        .sidebar .logout-btn { margin: 20px; }
        .main-content { padding: 30px; }
        .card-shadow {
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            border-radius: 16px;
        }
        @media (max-width: 768px) {
            .sidebar { min-height: auto; padding-bottom: 20px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="brand">G&G <span>Trámites</span></div>
            <div class="user-badge">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?>
                <br><small><?php echo esAdmin() ? 'Administrador' : 'Usuario'; ?></small>
            </div>
            <ul class="nav flex-column">
                <!-- INICIO -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php' && !$in_catalogos && !$in_solicitudes && !$in_admin && !$in_reportes) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard.php">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </li>

                <!-- SOLICITUDES -->
                <?php if (esAdmin() || !empty($_SESSION['titulares']) || in_array(2, $_SESSION['roles'] ?? [])): ?>
                <li class="nav-item">
                      <a class="nav-link <?php echo ($current_dir == 'solicitudes') ? 'active' : ''; ?>" 
                    href="<?php echo BASE_URL; ?>solicitudes/">
                        <i class="fas fa-file-alt"></i> Solicitudes
                    </a>
                </li>
                <?php endif; ?>

                <!-- FACTURACIÓN (admin y aprobadores) -->
                <?php if (esAdmin() || in_array(2, $_SESSION['roles'] ?? [])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $in_facturas ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>facturas/">
                        <i class="fas fa-file-invoice-dollar"></i> Facturación
                    </a>
                </li>
                <?php endif; ?>

                <!-- CATÁLOGOS (solo admin) -->
                <?php if (esAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $in_catalogos ? 'active' : ''; ?>" 
                       data-bs-toggle="collapse" href="#menuCatalogos" role="button" 
                       aria-expanded="<?php echo $in_catalogos ? 'true' : 'false'; ?>" 
                       aria-controls="menuCatalogos">
                        <i class="fas fa-boxes"></i> Catálogos
                    </a>
                    <div class="collapse <?php echo $in_catalogos ? 'show' : ''; ?>" id="menuCatalogos">
                        <a class="nav-link <?php echo ($current_dir == 'distribuidores') ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>distribuidores/">
                            <i class="fas fa-truck"></i> Distribuidores
                        </a>
                         <a class="nav-link <?php echo ($current_dir == 'fabricantes') ? 'active' : ''; ?>" 
                        href="<?php echo BASE_URL; ?>fabricantes/">
                            <i class="fas fa-industry"></i> Fabricantes
                        </a>
                        <a class="nav-link <?php echo ($current_dir == 'titulares') ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>titulares/">
                            <i class="fas fa-building"></i> Titulares
                        </a>
                        <a class="nav-link <?php echo ($current_dir == 'personas') ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>personas/">
                            <i class="fas fa-users"></i> Personas
                        </a>
                        <a class="nav-link <?php echo ($current_dir == 'productos') ? 'active' : ''; ?>" 
                        href="<?php echo BASE_URL; ?>productos/">
                            <i class="fas fa-cube"></i> Productos
                        </a>
                     <!-- Dentro de Catálogos -->
                        <div class="collapse <?php echo $in_catalogos ? 'show' : ''; ?>" id="menuCatalogos">
                            <!-- ... enlaces existentes ... -->
                            <a class="nav-link <?php echo ($current_dir == 'tipos_licencia') ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>tipos_licencia/">
                                <i class="fas fa-tags"></i> Tipos de Licencia
                            </a>
                            <a class="nav-link <?php echo ($current_dir == 'tipo_modificacion') ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>tipo_modificacion/">
                                <i class="fas fa-edit"></i> Tipos de Modificación
                            </a>
                              <a class="nav-link <?php echo ($current_dir == 'tipo_documento') ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>tipo_documento/">
                                <i class="fas fa-file-alt"></i> Tipos de Documento
                            </a>
                            <a class="nav-link <?php echo ($current_dir == 'requisitos') ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>requisitos/">
                                <i class="fas fa-check-double"></i> Requisitos
                            </a>
                        </div>
                    </div>
                </li>
                <?php endif; ?>

                <!-- ADMINISTRACIÓN (solo admin) -->
                <?php if (esAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $in_admin ? 'active' : ''; ?>" 
                       data-bs-toggle="collapse" href="#menuAdministracion" role="button" 
                       aria-expanded="<?php echo $in_admin ? 'true' : 'false'; ?>" 
                       aria-controls="menuAdministracion">
                        <i class="fas fa-users-cog"></i> Administración
                    </a>
                    <div class="collapse show" id="menuAdministracion">
                        <a class="nav-link <?php echo ($current_dir == 'usuarios') ? 'active' : ''; ?>" 
                        href="<?php echo BASE_URL; ?>usuarios/">
                            <i class="fas fa-user-shield"></i> Usuarios
                        </a>
                       <a class="nav-link <?php echo ($current_dir == 'permisos') ? 'active' : ''; ?>" 
                        href="<?php echo BASE_URL; ?>permisos/">
                            <i class="fas fa-user-shield"></i> Permisos
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                    </div>
                </li>
                <?php endif; ?>

            </ul>
            <div class="logout-btn">
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-danger w-100">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </nav>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="col-md-9 col-lg-10 main-content">