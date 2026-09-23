<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nuevo Producto';
include '../includes/header.php';

$fabricantes = obtenerFabricantes();
?>

<h2><i class="fas fa-plus-circle"></i> Nuevo Producto</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="numero_registro" class="form-label">Número de Registro</label>
                    <input type="text" class="form-control" id="numero_registro" name="numero_registro" placeholder="Ej: REG-2024-001">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="marca" class="form-label">Marca *</label>
                    <input type="text" class="form-control" id="marca" name="marca" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_fabricante" class="form-label">Fabricante *</label>
                    <select class="form-select" id="id_fabricante" name="id_fabricante" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($fabricantes as $f): ?>
                            <option value="<?php echo $f['id_fabricante']; ?>"><?php echo h($f['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_registro_inicial" class="form-label">Fecha de Registro Inicial</label>
                    <input type="date" class="form-control" id="fecha_registro_inicial" name="fecha_registro_inicial">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_vencimiento_registro" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="fecha_vencimiento_registro" name="fecha_vencimiento_registro">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="estado_producto" class="form-label">Estado</label>
                    <select class="form-select" id="estado_producto" name="estado_producto">
                        <option value="Activo" selected>Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>