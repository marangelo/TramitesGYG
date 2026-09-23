<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nuevo Titular';
include '../includes/header.php';

$paises = obtenerPaises();
?>

<h2><i class="fas fa-building"></i> Nuevo Titular</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_pais" class="form-label">País *</label>
                    <select class="form-select" id="id_pais" name="id_pais" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($paises as $pais): ?>
                            <option value="<?php echo $pais['id_pais']; ?>"><?php echo h($pais['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="direccion_texto" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion_texto" name="direccion_texto" rows="2"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="correo" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="correo" name="correo">
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