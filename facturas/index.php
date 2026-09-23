<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para ver las facturas.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$estado_filtro = isset($_GET['estado_factura']) ? (int)$_GET['estado_factura'] : 0;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite = 15;

$resultado = buscarFacturas($busqueda, $pagina, $limite, $estado_filtro);
$facturas = $resultado['datos'];
$total = $resultado['total'];
$total_paginas = ceil($total / $limite);

$titulo = 'Facturas';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-invoice-dollar"></i> Facturas</h2>
    <a href="crear.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nueva Factura
    </a>
</div>

<div class="card card-shadow mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="text" name="busqueda" class="form-control" 
                       placeholder="Número de factura o nombre del cliente..." 
                       value="<?php echo h($busqueda); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="estado_factura" class="form-select">
                    <option value="0">Todos los estados</option>
                    <option value="1" <?php echo $estado_filtro == 1 ? 'selected' : ''; ?>>Emitidas</option>
                    <option value="2" <?php echo $estado_filtro == 2 ? 'selected' : ''; ?>>Pagadas</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
            <div class="col-md-2">
                <a href="index.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-undo"></i> Limpiar
                </a>
            </div>
            <div class="col-md-12 text-end mt-2">
                <span class="badge bg-secondary">
                    <i class="fas fa-list me-1"></i>
                    Total: <?php echo $total; ?> factura<?php echo $total != 1 ? 's' : ''; ?>
                </span>
            </div>
        </form>
    </div>
</div>

<?php mostrarMensaje(); ?>

<div class="card card-shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° Factura</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Moneda</th>
                        <th class="text-end">Monto Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($facturas) > 0): ?>
                        <?php foreach ($facturas as $f): 
                            $es_pagada = ($f['estado_nombre'] ?? '') === 'factura_pagada';
                        ?>
                            <tr>
                                <td>
                                    <strong class="text-primary" style="font-family:monospace;">
                                        <?php echo h($f['numero_factura']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($f['fecha_factura'])); ?>
                                    <br><small class="text-muted">
                                        <?php echo date('H:i', strtotime($f['fecha_factura'])); ?>
                                    </small>
                                </td>
                                <td><?php echo h($f['nombre']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $f['moneda'] === 'USD' ? 'success' : 'info'; ?>">
                                        <?php echo $f['moneda'] === 'USD' ? 'US$' : 'C$'; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo formatearMoneda($f['monto_total'], $f['moneda']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($es_pagada): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Pagada
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-clock me-1"></i> Emitida
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="detalle.php?id=<?php echo $f['id_factura']; ?>" 
                                       class="btn btn-sm btn-info" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="imprimir.php?id=<?php echo $f['id_factura']; ?>" 
                                       target="_blank"
                                       class="btn btn-sm btn-secondary" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-file-invoice fa-3x mb-3 d-block opacity-25"></i>
                                No hay facturas registradas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_paginas > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            </li>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>
        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>