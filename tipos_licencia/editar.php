<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_tlic']) ? (int)$_GET['id_tlic'] : 0;

if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$tipo = obtenerTipoLicencia($id);
if (!$tipo) {
    setMensaje('Tipo de licencia no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Editar Tipo de Licencia';
include '../includes/header.php';

$direcciones = obtenerDireccionesANRS();
?>

<h2><i class="fas fa-edit"></i> Editar Tipo de Licencia</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_tipo_licencia" value="<?php echo $tipo['id_tipo_licencia']; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo h($tipo['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direcciones as $d): ?>
                            <option value="<?php echo $d['id_direccion_anrs']; ?>" <?php echo $d['id_direccion_anrs'] == $tipo['id_direccion_anrs'] ? 'selected' : ''; ?>>
                                <?php echo h($d['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>