<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para ver facturas.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$factura = obtenerFactura($id);
if (!$factura) {
    setMensaje('Factura no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

$es_pagada = ($factura['estado_nombre'] ?? '') === 'factura_pagada';

$titulo = 'Factura ' . $factura['numero_factura'];
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0">
        <i class="fas fa-file-invoice-dollar"></i> Factura <?php echo h($factura['numero_factura']); ?>
        <?php if ($es_pagada): ?>
            <span class="badge bg-success fs-6 ms-2">
                <i class="fas fa-check-circle me-1"></i> PAGADA
            </span>
        <?php else: ?>
            <span class="badge bg-warning text-dark fs-6 ms-2">
                <i class="fas fa-clock me-1"></i> EMITIDA
            </span>
        <?php endif; ?>
    </h2>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="imprimir.php?id=<?php echo $factura['id_factura']; ?>" 
           target="_blank" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimir
        </a>
        <?php if (!$es_pagada): ?>
            <form action="marcar_pagada.php" method="POST" class="d-inline"
                  onsubmit="return confirm('¿Confirmas que esta factura ha sido PAGADA?\n\nLas solicitudes asociadas pasarán a estado "Pagada".');">
                <input type="hidden" name="id_factura" value="<?php echo $factura['id_factura']; ?>">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-money-check-alt"></i> Marcar como Pagada
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php mostrarMensaje(); ?>

<div class="card card-shadow">
    <div class="card-body p-4">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5 class="text-primary mb-1">GYG Professional Service</h5>
                <small class="text-muted">Sistema de gestión de licencias sanitarias</small>
            </div>
            <div class="col-md-6 text-end">
                <div class="badge bg-primary fs-6 mb-2" style="font-family:monospace;">
                    <?php echo h($factura['numero_factura']); ?>
                </div>
                <br>
                <small class="text-muted">
                    Emitida: <?php echo date('d/m/Y H:i', strtotime($factura['fecha_factura'])); ?>
                </small>
                <?php if ($es_pagada): ?>
                    <br>
                    <span class="badge bg-success mt-2">
                        <i class="fas fa-check-circle me-1"></i> PAGADA
                    </span>
                <?php else: ?>
                    <br>
                    <span class="badge bg-warning text-dark mt-2">
                        <i class="fas fa-clock me-1"></i> EMITIDA
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Datos del cliente -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">Cliente</h6>
                <p class="mb-1"><strong><?php echo h($factura['nombre']); ?></strong></p>
                <?php if (!empty($factura['direccion'])): ?>
                    <p class="mb-1 small"><?php echo h($factura['direccion']); ?></p>
                <?php endif; ?>
                <?php if (!empty($factura['telefono'])): ?>
                    <p class="mb-0 small">Tel: <?php echo h($factura['telefono']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <h6 class="text-muted text-uppercase small">Detalles</h6>
                <p class="mb-1 small">
                    <strong>Moneda:</strong>
                    <?php if ($factura['moneda'] === 'USD'): ?>
                        <span class="badge bg-success">US$ Dólares</span>
                    <?php else: ?>
                        <span class="badge bg-info">C$ Córdobas</span>
                    <?php endif; ?>
                </p>
                <p class="mb-0 small">
                    <strong>Creada por:</strong>
                    <?php echo h($factura['usuario_creador'] ?? '-'); ?>
                </p>
            </div>
        </div>

        <!-- Detalle -->
        <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:50px;">No</th>
                        <th style="width:130px;">Código</th>
                        <th>Descripción</th>
                        <th class="text-end" style="width:140px;">Monto Unitario</th>
                        <th class="text-center" style="width:80px;">Cant.</th>
                        <th class="text-end" style="width:140px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 0; foreach ($factura['detalles'] as $d): $n++; ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?php echo $n; ?></td>
                            <td>
                                <?php if (!empty($d['codigo_control_empresa'])): ?>
                                    <span class="badge bg-light text-primary border" 
                                          style="font-family:monospace; font-size:.8rem;">
                                        <?php echo h($d['codigo_control_empresa']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($d['descripcion']); ?></td>
                            <td class="text-end"><?php echo formatearMoneda($d['monto_unitario'], $factura['moneda']); ?></td>
                            <td class="text-center"><?php echo $d['cantidad']; ?></td>
                            <td class="text-end fw-bold"><?php echo formatearMoneda($d['subtotal'], $factura['moneda']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="5" class="text-end fw-bold">TOTAL</td>
                        <td class="text-end fw-bold fs-5">
                            <?php echo formatearMoneda($factura['monto_total'], $factura['moneda']); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!$es_pagada): ?>
            <div class="alert alert-warning mb-0">
                <i class="fas fa-info-circle me-1"></i>
                Esta factura está pendiente de pago. Al marcar como pagada, todas las solicitudes asociadas 
                pasarán al estado <strong>Pagada</strong>.
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-0">
                <i class="fas fa-check-circle me-1"></i>
                Esta factura ha sido pagada. Las solicitudes asociadas están en estado <strong>Pagada</strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>