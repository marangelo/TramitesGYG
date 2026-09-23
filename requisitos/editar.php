<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id_req']) ? (int)$_GET['id_req'] : 0;

if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$item = obtenerRequisito($id);
if (!$item) {
    setMensaje('Requisito no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Editar Requisito de Documento';
include '../includes/header.php';

// Direcciones ANRS
$direccionesAnrs = $pdo->query("SELECT id_direccion_anrs, nombre 
                                FROM direccion_anrs 
                                ORDER BY nombre")
                       ->fetchAll(PDO::FETCH_ASSOC);

// Tipos de licencia CON su dirección (se filtran en el cliente)
$tiposLicencia = $pdo->query("SELECT id_tipo_licencia, id_direccion_anrs, nombre 
                              FROM tipo_licencia 
                              ORDER BY nombre")
                     ->fetchAll(PDO::FETCH_ASSOC);

$tiposTramite      = obtenerTiposTramite();
$tiposModificacion = obtenerTiposModificaciones();
$tiposDocumento    = obtenerTiposDocumentos();

// Deducir la dirección actual a partir del tipo de licencia guardado
$id_direccion_actual = 0;
foreach ($tiposLicencia as $tl) {
    if ((int)$tl['id_tipo_licencia'] === (int)$item['id_tipo_licencia']) {
        $id_direccion_actual = (int)$tl['id_direccion_anrs'];
        break;
    }
}
?>

<h2><i class="fas fa-edit"></i> <?php echo $titulo; ?></h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST">
            <input type="hidden" name="id_requisito" value="<?php echo $item['id_requisito']; ?>">
            <div class="row">

                <!-- 1. Dirección ANRS -->
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direccionesAnrs as $da): ?>
                            <option value="<?php echo $da['id_direccion_anrs']; ?>"
                                <?php echo $da['id_direccion_anrs'] == $id_direccion_actual ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $tt['id_tipo_tramite']; ?>"
                                <?php echo $tt['id_tipo_tramite'] == $item['id_tipo_tramite'] ? 'selected' : ''; ?>>
                                <?php echo h($tt['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Tipo de Modificación (condicional) -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_modificacion" class="form-label">Tipo de Modificación</label>
                    <select class="form-select" id="id_tipo_modificacion" name="id_tipo_modificacion" disabled>
                        <option value="">Ninguno</option>
                        <?php foreach ($tiposModificacion as $tm): ?>
                            <option value="<?php echo $tm['id_tipo_modificacion']; ?>"
                                <?php echo $tm['id_tipo_modificacion'] == $item['id_tipo_modificacion'] ? 'selected' : ''; ?>>
                                <?php echo h($tm['nombre']); ?>
                            </option>
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
                            <option value="<?php echo $td['id_tipo_documento']; ?>"
                                <?php echo $td['id_tipo_documento'] == $item['id_tipo_documento'] ? 'selected' : ''; ?>>
                                <?php echo h($td['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 6. Obligatorio -->
                <div class="col-md-6 mb-3">
                    <label for="obligatorio" class="form-label">Obligatorio</label>
                    <select class="form-select" id="obligatorio" name="obligatorio">
                        <option value="1" <?php echo $item['obligatorio'] ? 'selected' : ''; ?>>Sí</option>
                        <option value="0" <?php echo !$item['obligatorio'] ? 'selected' : ''; ?>>No</option>
                    </select>
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
document.addEventListener('DOMContentLoaded', function () {

    /* =========================================================
       1. Datos y elementos
       ========================================================= */
    const tiposLicencia = <?php echo json_encode($tiposLicencia, JSON_UNESCAPED_UNICODE); ?>;

    const idTipoLicenciaActual = <?php echo (int)$item['id_tipo_licencia']; ?>;

    const selectDireccion    = document.getElementById('id_direccion_anrs');
    const selectLicencia     = document.getElementById('id_tipo_licencia');
    const selectTramite      = document.getElementById('id_tipo_tramite');
    const selectModificacion = document.getElementById('id_tipo_modificacion');

    /* =========================================================
       2. Filtrar Tipo de Licencia por Dirección ANRS
       ========================================================= */
    function cargarTiposLicencia(preseleccionar) {
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
            const selected = (preseleccionar && String(tl.id_tipo_licencia) === String(idTipoLicenciaActual))
                ? 'selected' : '';
            html += `<option value="${tl.id_tipo_licencia}" ${selected}>${tl.nombre}</option>`;
        });

        selectLicencia.innerHTML = html;
        selectLicencia.disabled  = false;
    }

    selectDireccion.addEventListener('change', () => cargarTiposLicencia(false));

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

    /* =========================================================
       4. Estado inicial (cargar al abrir la página)
       ========================================================= */
    cargarTiposLicencia(true);   // true = preseleccionar el valor actual
    actualizarTipoModificacion();
});
</script>

<?php include '../includes/footer.php'; ?>