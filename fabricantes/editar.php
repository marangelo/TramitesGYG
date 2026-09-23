<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_fab']) ? (int)$_GET['id_fab'] : 0;

if ($id <= 0) {
    setMensaje('ID de fabricante no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$fabricante = obtenerFabricante($id);
if (!$fabricante) {
    setMensaje('Fabricante no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Editar Fabricante';
include '../includes/header.php';

$paises = obtenerPaises();
?>

<h2><i class="fas fa-edit"></i> Editar Fabricante</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_fabricante" value="<?php echo $fabricante['id_fabricante']; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo h($fabricante['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_pais" class="form-label">País *</label>
                    <select class="form-select" id="id_pais" name="id_pais" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($paises as $pais): ?>
                            <option value="<?php echo $pais['id_pais']; ?>" <?php echo $pais['id_pais'] == $fabricante['id_pais'] ? 'selected' : ''; ?>>
                                <?php echo h($pais['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="direccion_texto" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion_texto" name="direccion_texto" rows="2"><?php echo h($fabricante['direccion_texto']); ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo h($fabricante['telefono']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="correo" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="correo" name="correo" value="<?php echo h($fabricante['correo']); ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>