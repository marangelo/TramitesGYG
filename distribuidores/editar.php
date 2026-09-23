<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_dist']) ? (int)$_GET['id_dist'] : 0;

if ($id <= 0) {
    setMensaje('ID de distribuidor no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$distribuidor = obtenerDistribuidor($id);
if (!$distribuidor) {
    setMensaje('Distribuidor no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Editar Distribuidor';
include '../includes/header.php';

$departamentos = obtenerDepartamentos();
$direccionesANRS = obtenerDireccionesANRS();
$municipios = $distribuidor['id_departamento'] ? obtenerMunicipiosPorDepartamento($distribuidor['id_departamento']) : [];
?>

<h2><i class="fas fa-edit"></i> Editar Distribuidor</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_distribuidor" value="<?php echo $distribuidor['id_distribuidor']; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="numero_licencia" class="form-label">Número de Licencia *</label>
                    <input type="text" class="form-control" id="numero_licencia" name="numero_licencia" value="<?php echo h($distribuidor['numero_licencia']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo h($distribuidor['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_departamento" class="form-label">Departamento *</label>
                    <select class="form-select" id="id_departamento" name="id_departamento" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?php echo $dep['id_departamento']; ?>" <?php echo $dep['id_departamento'] == $distribuidor['id_departamento'] ? 'selected' : ''; ?>>
                                <?php echo h($dep['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_municipio" class="form-label">Municipio *</label>
                    <select class="form-select" id="id_municipio" name="id_municipio" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($municipios as $mun): ?>
                            <option value="<?php echo $mun['id_municipio']; ?>" <?php echo $mun['id_municipio'] == $distribuidor['id_municipio'] ? 'selected' : ''; ?>>
                                <?php echo h($mun['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="direccion_texto" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion_texto" name="direccion_texto" rows="2"><?php echo h($distribuidor['direccion_texto']); ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direccionesANRS as $dir): ?>
                            <option value="<?php echo $dir['id_direccion_anrs']; ?>" <?php echo $dir['id_direccion_anrs'] == $distribuidor['id_direccion_anrs'] ? 'selected' : ''; ?>>
                                <?php echo h($dir['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_licencia" class="form-label">Tipo de Licencia *</label>
                    <select class="form-select" id="id_tipo_licencia" name="id_tipo_licencia" required>
                        <option value="">Seleccionar</option>
                        <?php foreach (obtenerTiposLicenciaPorDireccion($distribuidor['id_direccion_anrs']) as $tl): ?>
                            <option value="<?php echo $tl['id_tipo_licencia']; ?>" <?php echo $tl['id_tipo_licencia'] == $distribuidor['id_tipo_licencia'] ? 'selected' : ''; ?>>
                                <?php echo h($tl['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo $distribuidor['fecha_inicio']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_vence" class="form-label">Fecha de Vencimiento *</label>
                    <input type="date" class="form-control" id="fecha_vence" name="fecha_vence" value="<?php echo $distribuidor['fecha_vence']; ?>" required>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('id_departamento').addEventListener('change', function() {
    const depId = this.value;
    const municipioSelect = document.getElementById('id_municipio');
    municipioSelect.innerHTML = '<option value="">Cargando...</option>';
    if (depId) {
        fetch('<?php echo BASE_URL; ?>ajax_municipios.php?departamento=' + depId)
            .then(response => response.json())
            .then(data => {
                municipioSelect.innerHTML = '<option value="">Seleccionar</option>';
                data.forEach(mun => {
                    const option = document.createElement('option');
                    option.value = mun.id_municipio;
                    option.textContent = mun.nombre;
                    municipioSelect.appendChild(option);
                });
            })
            .catch(() => {
                municipioSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    } else {
        municipioSelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
    }
});

document.getElementById('id_direccion_anrs').addEventListener('change', function() {
    const dirId = this.value;
    const tipoSelect = document.getElementById('id_tipo_licencia');
    tipoSelect.innerHTML = '<option value="">Cargando...</option>';
    if (dirId) {
        fetch('<?php echo BASE_URL; ?>ajax_tipos_licencia.php?id_direccion=' + dirId)
            .then(response => response.json())
            .then(data => {
                tipoSelect.innerHTML = '<option value="">Seleccionar</option>';
                data.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo.id_tipo_licencia;
                    option.textContent = tipo.nombre;
                    tipoSelect.appendChild(option);
                });
                const currentTipo = <?php echo json_encode($distribuidor['id_tipo_licencia']); ?>;
                if (currentTipo) {
                    tipoSelect.value = currentTipo;
                }
            })
            .catch(() => {
                tipoSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    } else {
        tipoSelect.innerHTML = '<option value="">Primero selecciona una dirección ANRS</option>';
    }
});
</script>

<?php include '../includes/footer.php'; ?>