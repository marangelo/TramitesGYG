<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$titulo = 'Nuevo Requisito de Documento';
include '../includes/header.php';

// Direcciones ANRS
$direccionesAnrs = $pdo->query("SELECT id_direccion_anrs, nombre FROM direccion_anrs WHERE 1 ORDER BY nombre")
                       ->fetchAll(PDO::FETCH_ASSOC);

// Tipos de licencia CON su dirección (se filtran en el cliente)
$tiposLicencia = $pdo->query("SELECT id_tipo_licencia, id_direccion_anrs, nombre 
                              FROM tipo_licencia 
                              ORDER BY nombre")
                     ->fetchAll(PDO::FETCH_ASSOC);

$tiposTramite = obtenerTiposTramite();
$tiposModificacion = obtenerTiposModificaciones();
$tiposDocumento = obtenerTiposDocumentos();
?>

<h2><i class="fas fa-plus-circle"></i> <?php echo $titulo; ?></h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <div class="row">

                <!-- 1. Dirección ANRS -->
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direccionesAnrs as $da): ?>
                            <option value="<?php echo $da['id_direccion_anrs']; ?>">
                                <?php echo h($da['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Tipo de Licencia (dependiente) -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_licencia" class="form-label">Tipo de Licencia *</label>
                    <select class="form-select" id="id_tipo_licencia" name="id_tipo_licencia" required disabled>
                        <option value="">Seleccione primero una Dirección ANRS</option>
                    </select>
                </div>

                <!-- 3. Tipo de Trámite -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_tramite" class="form-label">Tipo de Trámite *</label>
                    <select class="form-select" id="id_tipo_tramite" name="id_tipo_tramite" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($tiposTramite as $tt): ?>
                            <option value="<?php echo $tt['id_tipo_tramite']; ?>"><?php echo h($tt['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Tipo de Modificación (condicional) -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_modificacion" class="form-label">Tipo de Modificación</label>
                    <select class="form-select" id="id_tipo_modificacion" name="id_tipo_modificacion" disabled>
                        <option value="">Ninguno</option>
                        <?php foreach ($tiposModificacion as $tm): ?>
                            <option value="<?php echo $tm['id_tipo_modificacion']; ?>"><?php echo h($tm['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Solo necesario si el trámite es "Modificación" o "Renovación con Modificación".</small>
                </div>

                <!-- 5. Tipo de Documento -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_documento" class="form-label">Tipo de Documento *</label>
                    <select class="form-select" id="id_tipo_documento" name="id_tipo_documento" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($tiposDocumento as $td): ?>
                            <option value="<?php echo $td['id_tipo_documento']; ?>"><?php echo h($td['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 6. Obligatorio -->
                <div class="col-md-6 mb-3">
                    <label for="obligatorio" class="form-label">Obligatorio</label>
                    <select class="form-select" id="obligatorio" name="obligatorio">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
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

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =========================================================
       1. Datos de tipos de licencia (con su id_direccion_anrs)
       ========================================================= */
    const tiposLicencia = <?php echo json_encode($tiposLicencia, JSON_UNESCAPED_UNICODE); ?>;

    const selectDireccion     = document.getElementById('id_direccion_anrs');
    const selectLicencia      = document.getElementById('id_tipo_licencia');
    const selectTramite       = document.getElementById('id_tipo_tramite');
    const selectModificacion  = document.getElementById('id_tipo_modificacion');

    /* =========================================================
       2. Filtrar Tipo de Licencia por Dirección ANRS
       ========================================================= */
    function cargarTiposLicencia() {
        const idDireccion = selectDireccion.value;

        selectLicencia.innerHTML = '';
        selectLicencia.disabled  = true;

        if (!idDireccion) {
            selectLicencia.innerHTML = '<option value="">Seleccione primero una Dirección ANRS</option>';
            return;
        }

        const filtrados = tiposLicencia.filter(
            tl => String(tl.id_direccion_anrs) === String(idDireccion)
        );

        if (filtrados.length === 0) {
            selectLicencia.innerHTML = '<option value="">Sin tipos de licencia para esta dirección</option>';
            return;
        }

        let html = '<option value="">Seleccionar</option>';
        filtrados.forEach(tl => {
            html += `<option value="${tl.id_tipo_licencia}">${tl.nombre}</option>`;
        });

        selectLicencia.innerHTML = html;
        selectLicencia.disabled  = false;
    }

    selectDireccion.addEventListener('change', cargarTiposLicencia);

    /* =========================================================
       3. Tipo de Modificación: solo para Modificación / Renov. c/ Modif.
       ========================================================= */
    const normalizar = (texto) => texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    function actualizarTipoModificacion() {
        const opcion = selectTramite.options[selectTramite.selectedIndex];
        const nombre = opcion ? normalizar(opcion.textContent) : '';

        const permite = ['modificacion', 'renovacion con modificacion'].includes(nombre);

        selectModificacion.disabled = !permite;
        if (!permite) selectModificacion.value = '';
    }

    selectTramite.addEventListener('change', actualizarTipoModificacion);
    actualizarTipoModificacion();

    /* =========================================================
       4. Estado inicial
       ========================================================= */
    cargarTiposLicencia();
});
</script>

<?php include '../includes/footer.php'; ?>