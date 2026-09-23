<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? (int)$_GET['estado'] : 0;
$titular = isset($_GET['titular']) ? (int)$_GET['titular'] : 0;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;

$filtros = [
    'busqueda' => $busqueda,
    'estado' => $estado,
    'titular' => $titular,
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta
];
$resultado = buscarSolicitudes($filtros, $pagina, $limite);
$solicitudes = $resultado['datos'];
$total = $resultado['total'];
$total_paginas = ceil($total / $limite);

$estados = obtenerEstados();
$titulares_disponibles = esAdmin() ? obtenerTitulares() : obtenerTitularesAccesibles(true);

$titulo = 'Gestión de Solicitudes';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-alt"></i> <?php echo $titulo; ?></h2>
    <?php if (tieneRol(1) || esAdmin()): ?>
        <a href="<?php echo BASE_URL; ?>solicitudes/crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Solicitud</a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card card-shadow mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="busqueda" class="form-control" placeholder="Buscar por producto o código..." value="<?php echo h($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select">
                    <option value="0">Todos los estados</option>
                    <?php foreach ($estados as $e): ?>
                        <option value="<?php echo $e['id_estado']; ?>" <?php echo $estado == $e['id_estado'] ? 'selected' : ''; ?>><?php echo h($e['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="titular" class="form-select">
                    <option value="0">Todos los titulares</option>
                    <?php foreach ($titulares_disponibles as $t): ?>
                        <option value="<?php echo $t['id_titular']; ?>" <?php echo $titular == $t['id_titular'] ? 'selected' : ''; ?>><?php echo h($t['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="fecha_desde" class="form-control" placeholder="Desde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="fecha_hasta" class="form-control" placeholder="Hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
            <div class="col-md-1">
                <a href="index.php" class="btn btn-secondary w-100"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>
</div>

<?php mostrarMensaje(); ?>

<div class="card card-shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código Control</th>
                        <th>Producto</th>
                        <th>Titular</th>
                        <th>Trámite</th>
                        <th>Estado</th>
                        <th>Fecha Solicitud</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($solicitudes) > 0): ?>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td><?php echo $s['id_solicitud']; ?></td>
                                <td><?php echo h($s['codigo_control_empresa']); ?></td>
                                <td>
                                    <strong><?php echo h($s['producto_nombre']); ?></strong>
                                </td>
                                <td><?php echo h($s['titular_nombre']); ?></td>
                                <td>
                                    <?php if (!empty($s['tipo_tramite_nombre'])): ?>
                                        <span class="badge bg-primary"><?php echo h($s['tipo_tramite_nombre']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <?php if (!empty($s['tipo_modificacion_nombre'])): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-pen"></i> <?php echo h($s['tipo_modificacion_nombre']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($s['estado_nombre'] == 'Solicitud_aprobado') $badge = 'success';
                                    elseif ($s['estado_nombre'] == 'Solicitud_rechazada') $badge = 'danger';
                                    elseif ($s['estado_nombre'] == 'Solicitud_evaluacion') $badge = 'warning';
                                    elseif ($s['estado_nombre'] == 'Solicitud_ingresado') $badge = 'info';
                                    elseif ($s['estado_nombre'] == 'Solicitud_cancelado') $badge = 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo h($s['estado_nombre']); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicita'])); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>solicitudes/detalle.php?id=<?php echo $s['id_solicitud']; ?>" class="btn btn-sm btn-info" title="Ver detalle"><i class="fas fa-eye"></i></a>
                                    <?php if (puedeAccederSolicitud($s, 'edit')): ?>
                                        <a href="<?php echo BASE_URL; ?>solicitudes/editar.php?id=<?php echo $s['id_solicitud']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No hay solicitudes que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación">
            <ul class="pagination justify-content-center">
                <?php if ($pagina > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina-1])); ?>">Anterior</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($pagina < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina+1])); ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>