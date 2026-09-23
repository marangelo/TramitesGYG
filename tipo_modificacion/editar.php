<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_tmod']) ? (int)$_GET['id_tmod'] : 0;

if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$item = obtenerTipoModificacion($id);
if (!$item) {
    setMensaje('Tipo de modificación no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Editar Tipo de Modificación';
include '../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> <?php echo $titulo; ?></h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_tipo_modificacion" value="<?php echo $item['id_tipo_modificacion']; ?>">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo h($item['nombre']); ?>" required>
            </div>
            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>