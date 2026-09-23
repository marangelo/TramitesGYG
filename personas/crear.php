<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nueva Persona';
include '../includes/header.php';

$tiposIdentificacion = obtenerTiposIdentificacion();
$paises = obtenerPaises();
$departamentos = obtenerDepartamentos();
$idNicaragua = obtenerIdNicaragua();
?>

<h2><i class="fas fa-user-plus"></i> Nueva Persona</h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_identificacion" class="form-label">Tipo de Identificación *</label>
                    <select class="form-select" id="id_tipo_identificacion" name="id_tipo_identificacion" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($tiposIdentificacion as $ti): ?>
                            <option value="<?php echo $ti['id_tipo_identificacion']; ?>"><?php echo h($ti['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="numero_identificacion" class="form-label">Número de Identificación *</label>
                    <input type="text" class="form-control" id="numero_identificacion" name="numero_identificacion" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre Completo *</label>
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
                <div class="col-md-6 mb-3" id="departamento_group">
                    <label for="id_departamento" class="form-label">Departamento</label>
                    <select class="form-select" id="id_departamento" name="id_departamento">
                        <option value="">Seleccionar</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?php echo $dep['id_departamento']; ?>"><?php echo h($dep['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Obligatorio si el país es Nicaragua</small>
                </div>
                <div class="col-md-6 mb-3" id="municipio_group">
                    <label for="id_municipio" class="form-label">Municipio</label>
                    <select class="form-select" id="id_municipio" name="id_municipio">
                        <option value="">Primero selecciona un departamento</option>
                    </select>
                    <small class="text-muted">Obligatorio si el país es Nicaragua</small>
                </div>
                <!-- NUEVO CAMPO DIRECCIÓN -->
                <div class="col-md-12 mb-3">
                    <label for="direccion" class="form-label">Dirección</label>
                    <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Dirección exacta (opcional)">
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

<script>
const idNicaragua = <?php echo json_encode($idNicaragua); ?>;
const paisSelect = document.getElementById('id_pais');
const departamentoSelect = document.getElementById('id_departamento');
const municipioSelect = document.getElementById('id_municipio');

function toggleDepartamentoMunicipio() {
    const selectedPais = parseInt(paisSelect.value);
    const esNicaragua = (selectedPais === idNicaragua);
    
    departamentoSelect.disabled = !esNicaragua;
    municipioSelect.disabled = !esNicaragua;
    
    if (!esNicaragua) {
        departamentoSelect.value = '';
        municipioSelect.innerHTML = '<option value="">Seleccione un departamento primero</option>';
        departamentoSelect.removeAttribute('required');
        municipioSelect.removeAttribute('required');
    } else {
        departamentoSelect.setAttribute('required', 'required');
        municipioSelect.setAttribute('required', 'required');
        if (departamentoSelect.value) {
            cargarMunicipios(departamentoSelect.value);
        }
    }
}

function cargarMunicipios(idDepartamento) {
    municipioSelect.innerHTML = '<option value="">Cargando...</option>';
    if (idDepartamento) {
        fetch('<?php echo BASE_URL; ?>ajax_municipios.php?departamento=' + idDepartamento)
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
}

paisSelect.addEventListener('change', toggleDepartamentoMunicipio);

departamentoSelect.addEventListener('change', function() {
    if (this.disabled) {
        municipioSelect.innerHTML = '<option value="">Seleccione un departamento primero</option>';
        return;
    }
    cargarMunicipios(this.value);
});

document.addEventListener('DOMContentLoaded', function() {
    departamentoSelect.disabled = true;
    municipioSelect.disabled = true;
    departamentoSelect.removeAttribute('required');
    municipioSelect.removeAttribute('required');
});
</script>

<?php include '../includes/footer.php'; ?>