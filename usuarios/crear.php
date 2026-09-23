<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nuevo Usuario';
include '../includes/header.php';

$personas = obtenerPersonas();
?>

<h2><i class="fas fa-user-plus"></i> Nuevo Usuario</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_persona" class="form-label">Persona *</label>
                    <select class="form-select" id="id_persona" name="id_persona" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($personas as $p): ?>
                            <option value="<?php echo $p['id_persona']; ?>">
                                <?php echo h($p['nombre'] . ' (' . $p['numero_identificacion'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario *</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="contraseña" class="form-label">Contraseña *</label>
                    <input type="password" class="form-control" id="contraseña" name="contraseña" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="activo" class="form-label">Estado</label>
                    <select class="form-select" id="activo" name="activo">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>