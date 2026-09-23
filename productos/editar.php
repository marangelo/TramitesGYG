<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_prod']) ? (int)$_GET['id_prod'] : 0;

if ($id <= 0) {
    setMensaje('ID de producto no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$producto = obtenerProducto($id);
if (!$producto) {
    setMensaje('Producto no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

// Calcular estado real
$estado_real = $producto['estado_calculado'] ?? $producto['estado_producto'];
$es_vencido = ($estado_real == 'Vencido');

$titulo = 'Editar Producto';
include '../includes/header.php';

$fabricantes = obtenerFabricantes();
?>

<h2><i class="fas fa-edit"></i> Editar Producto</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="numero_registro" class="form-label">Número de Registro</label>
                    <input type="text" class="form-control" id="numero_registro" name="numero_registro" value="<?php echo h($producto['numero_registro']); ?>" placeholder="Ej: REG-2024-001">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo h($producto['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="marca" class="form-label">Marca *</label>
                    <input type="text" class="form-control" id="marca" name="marca" value="<?php echo h($producto['marca']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_fabricante" class="form-label">Fabricante *</label>
                    <select class="form-select" id="id_fabricante" name="id_fabricante" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($fabricantes as $f): ?>
                            <option value="<?php echo $f['id_fabricante']; ?>" <?php echo $f['id_fabricante'] == $producto['id_fabricante'] ? 'selected' : ''; ?>>
                                <?php echo h($f['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_registro_inicial" class="form-label">Fecha de Registro Inicial</label>
                    <input type="date" class="form-control" id="fecha_registro_inicial" name="fecha_registro_inicial" value="<?php echo $producto['fecha_registro_inicial']; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_vencimiento_registro" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="fecha_vencimiento_registro" name="fecha_vencimiento_registro" value="<?php echo $producto['fecha_vencimiento_registro']; ?>">
                </div>

                <!-- Estado con advertencia si está vencido -->
                <div class="col-md-6 mb-3">
                    <label for="estado_producto" class="form-label">Estado</label>
                    <select class="form-select" id="estado_producto" name="estado_producto" <?php echo $es_vencido ? 'disabled' : ''; ?>>
                        <option value="Activo" <?php echo $producto['estado_producto'] == 'Activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo $producto['estado_producto'] == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                    <?php if ($es_vencido): ?>
                        <input type="hidden" name="estado_producto" value="Vencido">
                        <small class="text-danger">
                            <i class="fas fa-exclamation-circle"></i> El producto está vencido. El estado se forzará a "Vencido" al guardar.
                        </small>
                    <?php else: ?>
                        <small class="text-muted">El estado "Vencido" se asigna automáticamente si la fecha de vencimiento es menor a hoy.</small>
                    <?php endif; ?>
                </div>

                <!-- Mostrar estado real calculado -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado Actual (Calculado)</label>
                    <div>
                        <?php if ($estado_real == 'Activo'): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php elseif ($estado_real == 'Vencido'): ?>
                            <span class="badge bg-danger">Vencido</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo h($estado_real); ?></span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Este estado se calcula automáticamente según la fecha de vencimiento.</small>
                </div>
            </div>

            <?php if ($es_vencido): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Producto Vencido</strong> – La fecha de vencimiento es anterior a la fecha actual. 
                    Al guardar, el estado se forzará a "Vencido" automáticamente.
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Al cambiar la fecha de vencimiento, se puede mostrar advertencia si es menor a hoy
document.getElementById('fecha_vencimiento_registro').addEventListener('change', function() {
    const fechaVenc = new Date(this.value);
    const hoy = new Date();
    hoy.setHours(0,0,0,0);
    if (fechaVenc < hoy) {
        alert('La fecha de vencimiento es menor a hoy. El producto se marcará como Vencido.');
    }
});
</script>

<?php include '../includes/footer.php'; ?>