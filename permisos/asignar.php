<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;

if ($id_user <= 0) {
    setMensaje('ID de usuario no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuario($id_user);
if (!$usuario) {
    setMensaje('Usuario no encontrado.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Asignar Permisos a ' . $usuario['nombre_usuario'];
include '../includes/header.php';

$roles = obtenerRoles();
$titulares = obtenerTitularesParaPermisos();

// ============================================================
// RECUPERAR PERMISOS ACTUALES
// ============================================================
$roles_actuales = obtenerRolesDeUsuario($id_user); // Array de IDs de roles
$titulares_actuales_ids = obtenerTitularesDeUsuario($id_user); // Array de IDs de titulares

// Obtener los nombres de los titulares actuales para la tabla
$titulares_con_nombre = [];
if (!empty($titulares_actuales_ids)) {
    $placeholders = implode(',', array_fill(0, count($titulares_actuales_ids), '?'));
    $stmt = $pdo->prepare("SELECT id_titular, nombre FROM titular WHERE id_titular IN ($placeholders)");
    $stmt->execute($titulares_actuales_ids);
    $titulares_con_nombre = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2><i class="fas fa-key"></i> Asignar Permisos</h2>
<p class="text-muted">Usuario: <strong><?php echo h($usuario['nombre_usuario']); ?></strong> (ID: <?php echo $id_user; ?>)</p>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST" id="formPermisos">
            <input type="hidden" name="id_user" value="<?php echo $id_user; ?>">

            <!-- Roles -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <h5><i class="fas fa-tag"></i> Roles</h5>
                    <div class="border p-2 rounded">
                        <?php foreach ($roles as $r): ?>
                            <div class="form-check">
                                <input class="form-check-input rol-checkbox" type="checkbox" 
                                       name="roles[]" value="<?php echo $r['id_rol']; ?>" 
                                       id="rol_<?php echo $r['id_rol']; ?>"
                                       <?php echo in_array($r['id_rol'], $roles_actuales) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="rol_<?php echo $r['id_rol']; ?>">
                                    <?php echo h($r['nombre_rol']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Selecciona al menos un rol.</small>
                </div>
            </div>

            <!-- Titulares (con tabla dinámica) -->
            <hr>
            <h5><i class="fas fa-building"></i> Titulares Asignados</h5>
            <div class="alert alert-info" id="alertaAdmin" style="display:none;">
                <i class="fas fa-info-circle"></i> 
                El rol <strong>Administrador</strong> tiene acceso a todos los titulares. No es necesario asignar titulares específicos.
            </div>

            <div class="row mb-3" id="titularesSection">
                <div class="col-md-6">
                    <div class="input-group">
                        <select class="form-select" id="selectTitular" <?php echo in_array(3, $roles_actuales) ? 'disabled' : ''; ?>>
                            <option value="">Seleccionar titular...</option>
                            <?php foreach ($titulares as $t): ?>
                                <option value="<?php echo $t['id_titular']; ?>"><?php echo h($t['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" id="btnAgregarTitular" <?php echo in_array(3, $roles_actuales) ? 'disabled' : ''; ?>>
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="tablaTitulares">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTitulares">
                                <!-- Se llena dinámicamente con los titulares actuales -->
                            </tbody>
                        </table>
                    </div>
                    <input type="hidden" name="titulares_ids" id="titulares_ids" value="">
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Permisos</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// 1. Inicializar titulares desde PHP (precargados)
// ============================================================
let titularesSeleccionados = <?php echo json_encode($titulares_con_nombre); ?>;

// ============================================================
// 2. Renderizar tabla de titulares
// ============================================================
function renderizarTabla() {
    const tbody = document.getElementById('tbodyTitulares');
    tbody.innerHTML = '';
    if (titularesSeleccionados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No hay titulares asignados</td></tr>';
        return;
    }
    titularesSeleccionados.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.id_titular}</td>
            <td>${item.nombre}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarTitular(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    // Actualizar campo oculto
    const ids = titularesSeleccionados.map(item => item.id_titular).join(',');
    document.getElementById('titulares_ids').value = ids;
}

// ============================================================
// 3. Agregar titular
// ============================================================
document.getElementById('btnAgregarTitular').addEventListener('click', function() {
    const select = document.getElementById('selectTitular');
    const id = parseInt(select.value);
    const nombre = select.options[select.selectedIndex]?.text;
    if (!id) {
        alert('Seleccione un titular válido.');
        return;
    }
    if (titularesSeleccionados.some(item => item.id_titular === id)) {
        alert('Este titular ya está en la lista.');
        return;
    }
    titularesSeleccionados.push({ id_titular: id, nombre });
    renderizarTabla();
    select.value = '';
});

// ============================================================
// 4. Eliminar titular
// ============================================================
function eliminarTitular(index) {
    titularesSeleccionados.splice(index, 1);
    renderizarTabla();
}

// ============================================================
// 5. Validación de Admin (deshabilitar titulares)
// ============================================================
document.querySelectorAll('.rol-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const adminCheckbox = document.querySelector('.rol-checkbox[value="3"]');
        const isAdmin = adminCheckbox.checked;
        const selectTitular = document.getElementById('selectTitular');
        const btnAgregar = document.getElementById('btnAgregarTitular');
        const alertaAdmin = document.getElementById('alertaAdmin');
        const titularesSection = document.getElementById('titularesSection');

        if (isAdmin) {
            // Deshabilitar todo lo relacionado con titulares
            selectTitular.disabled = true;
            btnAgregar.disabled = true;
            alertaAdmin.style.display = 'block';
            titularesSection.style.opacity = '0.5';
            // Limpiar titulares seleccionados
            titularesSeleccionados = [];
            renderizarTabla();
        } else {
            // Habilitar
            selectTitular.disabled = false;
            btnAgregar.disabled = false;
            alertaAdmin.style.display = 'none';
            titularesSection.style.opacity = '1';
        }
    });
});

// ============================================================
// 6. Validar antes de enviar
// ============================================================
document.getElementById('formPermisos').addEventListener('submit', function(e) {
    const roles = document.querySelectorAll('.rol-checkbox:checked');
    if (roles.length === 0) {
        e.preventDefault();
        alert('Debe seleccionar al menos un rol.');
        return;
    }
    const isAdmin = document.querySelector('.rol-checkbox[value="3"]').checked;
    if (!isAdmin && titularesSeleccionados.length === 0) {
        e.preventDefault();
        alert('Debe asignar al menos un titular (a menos que sea Administrador).');
        return;
    }
    // Actualizar campo oculto antes de enviar
    document.getElementById('titulares_ids').value = titularesSeleccionados.map(item => item.id_titular).join(',');
});

// ============================================================
// 7. Ejecutar al cargar la página
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    renderizarTabla();
    // Si Admin está seleccionado al cargar, aplicar estado
    const adminCheckbox = document.querySelector('.rol-checkbox[value="3"]');
    if (adminCheckbox && adminCheckbox.checked) {
        adminCheckbox.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include '../includes/footer.php'; ?>