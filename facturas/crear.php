<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    setMensaje('No tienes permiso para crear facturas.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

$solicitudes = obtenerSolicitudesAprobadasParaFacturar();

$titulo = 'Nueva Factura';
include '../includes/header.php';
?>

<style>
    .solicitud-item {
        transition: background .15s ease;
        cursor: pointer;
    }
    .solicitud-item:hover {
        background: #f8f9fc;
    }
    .solicitud-item.selected {
        background: #e7f1ff;
    }
    .monto-input {
        max-width: 140px;
        text-align: right;
    }
    .total-box {
        background: linear-gradient(135deg, #4e73df, #224abe);
        color: #fff;
        border-radius: .85rem;
        padding: 1.25rem 1.5rem;
        text-align: right;
    }
    .total-box .total-monto {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
    }
    .total-box .total-label {
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .8px;
        opacity: .8;
    }
    .descripcion-input {
        background: #fff;
    }
    .solicitud-item.selected .descripcion-input {
        background: #fff;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-invoice-dollar"></i> Nueva Factura</h2>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<?php if (empty($solicitudes)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        No hay solicitudes en estado <strong>Aprobado</strong> disponibles para facturar.
    </div>
    <a href="<?php echo BASE_URL; ?>solicitudes/index.php" class="btn btn-primary">
        <i class="fas fa-file-alt"></i> Ver Solicitudes
    </a>
<?php else: ?>

<form action="guardar.php" method="POST" id="formFactura">
    <div class="row g-3">
        <!-- ============================================================ -->
        <!-- DATOS DEL CLIENTE                                            -->
        <!-- ============================================================ -->
        <div class="col-lg-8">
            <div class="card card-shadow mb-3">
                <div class="card-header bg-light"><strong>Datos del Cliente</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Titular (opcional)</label>
                            <select class="form-select" id="id_titular_select">
                                <option value="">— Seleccionar para autocompletar —</option>
                                <?php 
                                $titulares_vistos = [];
                                foreach ($solicitudes as $s): 
                                    if (!empty($s['id_titular']) && !in_array($s['id_titular'], $titulares_vistos)):
                                        $titulares_vistos[] = $s['id_titular'];
                                ?>
                                    <option value="<?php echo $s['id_titular']; ?>"
                                            data-nombre="<?php echo h($s['titular_nombre']); ?>"
                                            data-direccion="<?php echo h($s['titular_direccion']); ?>"
                                            data-telefono="<?php echo h($s['titular_telefono']); ?>">
                                        <?php echo h($s['titular_nombre']); ?>
                                    </option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required>
                            <input type="hidden" name="id_titular" id="id_titular">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion" id="direccion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select" name="moneda" id="moneda" required>
                                <option value="NIO">C$ — Córdobas</option>
                                <option value="USD">US$ — Dólares</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- SOLICITUDES A FACTURAR                                       -->
            <!-- ============================================================ -->
            <div class="card card-shadow mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Selecciona las Solicitudes</strong>
                    <span class="badge bg-primary" id="contador_seleccionadas">0 seleccionadas</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th style="width:180px;">Solicitud</th>
                                    <th>Descripción</th>
                                    <th style="width:160px;" class="text-end">Monto Unitario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitudes as $s): 
                                    // Construir descripción: Producto — Tipo de Trámite [— Tipo de Modificación]
                                    $partes_desc = [];
                                    $partes_desc[] = $s['producto_nombre'] ?: '(Producto no asignado)';
                                    if (!empty($s['tipo_tramite_nombre'])) {
                                        $partes_desc[] = $s['tipo_tramite_nombre'];
                                    }
                                    if (!empty($s['tipo_modificacion_nombre'])) {
                                        $partes_desc[] = $s['tipo_modificacion_nombre'];
                                    }
                                    $descripcion_default = implode(' — ', $partes_desc);
                                ?>
                                    <tr class="solicitud-item" data-id="<?php echo $s['id_solicitud']; ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input check-solicitud"
                                                   name="solicitudes[]" 
                                                   value="<?php echo $s['id_solicitud']; ?>">
                                        </td>
                                        <td>
                                            <strong>#<?php echo $s['id_solicitud']; ?></strong>
                                            <?php if (!empty($s['codigo_control_empresa'])): ?>
                                                <br><small class="text-primary" style="font-family:monospace;">
                                                    <?php echo h($s['codigo_control_empresa']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <br><small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($s['fecha_solicita'])); ?>
                                                · <?php echo h($s['titular_nombre']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control form-control-sm descripcion-input"
                                                   name="descripcion[<?php echo $s['id_solicitud']; ?>]"
                                                   value="<?php echo h($descripcion_default); ?>"
                                                   disabled>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" 
                                                   class="form-control form-control-sm monto-input monto-unitario"
                                                   name="monto_unitario[<?php echo $s['id_solicitud']; ?>]"
                                                   step="0.01" min="0" value="0.00"
                                                   disabled>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- RESUMEN LATERAL                                              -->
        <!-- ============================================================ -->
        <div class="col-lg-4">
            <div class="card card-shadow mb-3" style="position: sticky; top: 1rem;">
                <div class="card-header bg-light"><strong>Resumen</strong></div>
                <div class="card-body">
                    <div class="total-box mb-3">
                        <p class="total-label">Monto Total</p>
                        <p class="total-monto" id="monto_total_display">C$ 0.00</p>
                    </div>

                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Solicitudes:</span>
                            <strong id="cantidad_seleccionadas">0</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Moneda:</span>
                            <strong id="moneda_display">C$ Córdobas</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">N° Factura:</span>
                            <strong class="text-primary">Se asignará al guardar</strong>
                        </li>
                    </ul>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Generar Factura
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.check-solicitud');
    const monedaSelect = document.getElementById('moneda');
    const totalDisplay = document.getElementById('monto_total_display');
    const contador = document.getElementById('contador_seleccionadas');
    const cantidad = document.getElementById('cantidad_seleccionadas');
    const monedaDisplay = document.getElementById('moneda_display');

    // Autocompletar desde titular
    document.getElementById('id_titular_select').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (!opt.value) return;
        document.getElementById('nombre').value     = opt.dataset.nombre || '';
        document.getElementById('direccion').value  = opt.dataset.direccion || '';
        document.getElementById('telefono').value   = opt.dataset.telefono || '';
        document.getElementById('id_titular').value = opt.value;
    });

    // Símbolo de moneda
    function getSimbolo() {
        return monedaSelect.value === 'USD' ? 'US$ ' : 'C$ ';
    }
    function getNombreMoneda() {
        return monedaSelect.value === 'USD' ? 'US$ Dólares' : 'C$ Córdobas';
    }

    // Recalcular totales — habilita descripción y monto al seleccionar
    function recalcular() {
        let total = 0;
        let cont = 0;
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            const montoInput = row.querySelector('.monto-unitario');
            const descInput  = row.querySelector('.descripcion-input');
            if (cb.checked) {
                cont++;
                row.classList.add('selected');
                montoInput.disabled = false;
                descInput.disabled  = false;
                total += parseFloat(montoInput.value) || 0;
            } else {
                row.classList.remove('selected');
                montoInput.disabled = true;
                descInput.disabled  = true;
            }
        });

        contador.textContent = cont + ' seleccionada' + (cont === 1 ? '' : 's');
        cantidad.textContent = cont;
        totalDisplay.textContent = getSimbolo() + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Toggle al hacer click en la fila
    document.querySelectorAll('.solicitud-item').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'checkbox') return;
            if (e.target.type === 'checkbox') {
                setTimeout(recalcular, 10);
                return;
            }
            const cb = this.querySelector('.check-solicitud');
            cb.checked = !cb.checked;
            recalcular();
        });
    });

    checkboxes.forEach(cb => cb.addEventListener('change', recalcular));
    document.querySelectorAll('.monto-unitario').forEach(inp => {
        inp.addEventListener('input', recalcular);
    });
    monedaSelect.addEventListener('change', function() {
        monedaDisplay.textContent = getNombreMoneda();
        recalcular();
    });

    // Validación antes de enviar
    document.getElementById('formFactura').addEventListener('submit', function(e) {
        const seleccionadas = document.querySelectorAll('.check-solicitud:checked');
        if (seleccionadas.length === 0) {
            e.preventDefault();
            alert('Debes seleccionar al menos una solicitud para facturar.');
            return false;
        }
        // Validar que todas tengan monto > 0
        let error = false;
        seleccionadas.forEach(cb => {
            const row = cb.closest('tr');
            const monto = parseFloat(row.querySelector('.monto-unitario').value) || 0;
            if (monto <= 0) error = true;
        });
        if (error) {
            e.preventDefault();
            alert('Todas las solicitudes seleccionadas deben tener un monto mayor a 0.');
            return false;
        }
        // Asegurar que las seleccionadas estén habilitadas (para que se envíen)
        // y las no seleccionadas deshabilitadas (para no enviarlas)
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            const desc  = row.querySelector('.descripcion-input');
            const monto = row.querySelector('.monto-unitario');
            if (cb.checked) {
                desc.disabled  = false;
                monto.disabled = false;
            } else {
                desc.disabled  = true;
                monto.disabled = true;
            }
        });
    });

    recalcular();
});
</script>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>