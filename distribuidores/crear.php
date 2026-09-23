<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nuevo Distribuidor';
include '../includes/header.php';

$departamentos = obtenerDepartamentos();
$direccionesANRS = obtenerDireccionesANRS();
// Los tipos de licencia se cargarán vía AJAX
?>

<h2><i class="fas fa-plus-circle"></i> Nuevo Distribuidor</h2>

<?php
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger">' . h($_GET['error']) . '</div>';
}
?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="numero_licencia" class="form-label">Número de Licencia *</label>
                    <input type="text" class="form-control" id="numero_licencia" name="numero_licencia" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_departamento" class="form-label">Departamento *</label>
                    <select class="form-select" id="id_departamento" name="id_departamento" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?php echo $dep['id_departamento']; ?>"><?php echo h($dep['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_municipio" class="form-label">Municipio *</label>
                    <select class="form-select" id="id_municipio" name="id_municipio" required>
                        <option value="">Primero selecciona un departamento</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="direccion_texto" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion_texto" name="direccion_texto" rows="2"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direccionesANRS as $dir): ?>
                            <option value="<?php echo $dir['id_direccion_anrs']; ?>"><?php echo h($dir['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_licencia" class="form-label">Tipo de Licencia *</label>
                    <select class="form-select" id="id_tipo_licencia" name="id_tipo_licencia" required>
                        <option value="">Primero selecciona una dirección ANRS</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fecha_vence" class="form-label">Fecha de Vencimiento *</label>
                    <input type="date" class="form-control" id="fecha_vence" name="fecha_vence" required>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Cargar municipios dinámicamente según departamento
document.getElementById('id_departamento').addEventListener('change', function() {
    const depId = this.value;
    const municipioSelect = document.getElementById('id_municipio');
    municipioSelect.innerHTML = '<option value="">Cargando...</option>';
    if (depId) {
        fetch('../ajax_municipios.php?departamento=' + depId)
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

// Cargar tipos de licencia al seleccionar dirección ANRS
document.getElementById('id_direccion_anrs').addEventListener('change', function() {
    const dirId = this.value;
    const tipoSelect = document.getElementById('id_tipo_licencia');
    tipoSelect.innerHTML = '<option value="">Cargando...</option>';
    if (dirId) {
        fetch('../ajax_tipos_licencia.php?id_direccion=' + dirId)
            .then(response => response.json())
            .then(data => {
                tipoSelect.innerHTML = '<option value="">Seleccionar</option>';
                if (data.length === 0) {
                    tipoSelect.innerHTML = '<option value="">No hay tipos disponibles</option>';
                } else {
                    data.forEach(tipo => {
                        const option = document.createElement('option');
                        option.value = tipo.id_tipo_licencia;
                        option.textContent = tipo.nombre;
                        tipoSelect.appendChild(option);
                    });
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