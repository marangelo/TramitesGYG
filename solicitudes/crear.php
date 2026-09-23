<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

// Solo Solicitantes y Admin pueden crear
if (!tieneRol(1) && !esAdmin()) {
    setMensaje('No tienes permiso para crear solicitudes.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Nueva Solicitud';
include '../includes/header.php';

// Datos para selects
$personas = obtenerPersonas();
$titulares = esAdmin() ? obtenerTitulares() : obtenerTitularesAccesibles(true);
$direcciones = obtenerDireccionesANRS();
$tiposTramite = obtenerTiposTramite();
$tiposModificacion = obtenerTiposModificaciones();
$fabricantes = obtenerFabricantes();

// Si es Solicitante, verificar que tenga titulares asignados
if (!esAdmin()) {
    $titulares = obtenerTitularesAccesibles(true);
    if (count($titulares) == 0) {
        setMensaje('No tienes titulares asignados, no puedes crear solicitudes.', 'danger');
        header('Location: index.php');
        exit;
    }
}
?>

<style>
/* ============================================================
   ESTILOS PARA LA SECCIÓN DE DISTRIBUIDORES
   ============================================================ */
.seccion-distribuidores {
    background: #f8f9fc;
    border: 1px solid #e3e6f0;
    border-radius: 0.75rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.seccion-distribuidores .seccion-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e3e6f0;
}

.seccion-distribuidores .seccion-header .icono {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4e73df, #224abe);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 2px 6px rgba(78, 115, 223, 0.35);
}

.seccion-distribuidores .seccion-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
}

.seccion-distribuidores .seccion-header small {
    display: block;
    color: #858796;
    font-weight: 400;
    font-size: 0.78rem;
}

.selector-distribuidor {
    background: #fff;
    border-radius: 0.5rem;
    padding: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 1rem;
}

.tabla-distribuidores-wrapper {
    background: #fff;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.tabla-distribuidores {
    margin-bottom: 0;
}

.tabla-distribuidores thead {
    background: linear-gradient(135deg, #4e73df, #224abe);
    color: #fff;
}

.tabla-distribuidores thead th {
    font-weight: 600;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 0.7rem 0.9rem;
}

.tabla-distribuidores tbody tr {
    transition: background 0.2s ease;
}

.tabla-distribuidores tbody tr:hover {
    background: #f1f4fb;
}

.tabla-distribuidores tbody td {
    vertical-align: middle;
    font-size: 0.9rem;
    border-color: #eef1f7;
}

.tabla-distribuidores .badge-licencia {
    background: #e7f1ff;
    color: #224abe;
    font-weight: 600;
    padding: 0.35rem 0.6rem;
    border-radius: 0.35rem;
    font-size: 0.75rem;
    display: inline-block;
}

.tabla-distribuidores .texto-ubicacion {
    color: #6c757d;
    font-size: 0.85rem;
}

.tabla-distribuidores .btn-eliminar-dist {
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    transition: transform 0.15s ease, background 0.15s ease;
}

.tabla-distribuidores .btn-eliminar-dist:hover {
    transform: scale(1.1);
}

.contador-distribuidores {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border-radius: 2rem;
    font-size: 0.82rem;
    font-weight: 600;
}

.contador-distribuidores.ok {
    background: #d4edda;
    color: #155724;
}

.contador-distribuidores.vacio {
    background: #fff3cd;
    color: #856404;
}

.estado-vacio-tabla {
    padding: 2rem 1rem;
    text-align: center;
    color: #a0a4b8;
}

.estado-vacio-tabla i {
    font-size: 2rem;
    opacity: 0.4;
    margin-bottom: 0.5rem;
    display: block;
}
</style>

<h2><i class="fas fa-plus-circle"></i> <?php echo $titulo; ?></h2>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo h($_GET['error']); ?></div>
<?php endif; ?>

<div class="card card-shadow mt-3">
    <div class="card-body">
        <form action="guardar.php" method="POST" id="formSolicitud" enctype="multipart/form-data">
            <div class="row">
                <!-- 1. Dirección ANRS -->
                <div class="col-md-6 mb-3">
                    <label for="id_direccion_anrs" class="form-label">Dirección ANRS *</label>
                    <select class="form-select" id="id_direccion_anrs" name="id_direccion_anrs" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($direcciones as $d): ?>
                            <option value="<?php echo $d['id_direccion_anrs']; ?>"><?php echo h($d['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Tipo de Licencia (carga vía AJAX) -->
                <div class="col-md-6 mb-3">
                    <label for="id_tipo_licencia" class="form-label">Tipo de Licencia *</label>
                    <select class="form-select" id="id_tipo_licencia" name="id_tipo_licencia" required>
                        <option value="">Primero selecciona una dirección</option>
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

                <!-- 4. Tipo de Modificación -->
                <div class="col-md-6 mb-3" id="div_tipo_modificacion" style="display:none;">
                    <label for="id_tipo_modificacion" class="form-label">Tipo de Modificación *</label>
                    <select class="form-select" id="id_tipo_modificacion" name="id_tipo_modificacion">
                        <option value="">Seleccionar</option>
                        <?php foreach ($tiposModificacion as $tm): ?>
                            <option value="<?php echo $tm['id_tipo_modificacion']; ?>"><?php echo h($tm['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Sección: Producto Nuevo -->
                <div class="col-12 mb-3" id="seccion_producto_nuevo" style="display:none;">
                    <hr>
                    <h5>Datos del Producto Nuevo</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="producto_nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="producto_nombre" name="producto_nombre">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="producto_marca" class="form-label">Marca *</label>
                            <input type="text" class="form-control" id="producto_marca" name="producto_marca">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="producto_id_fabricante" class="form-label">Fabricante *</label>
                            <select class="form-select" id="producto_id_fabricante" name="producto_id_fabricante">
                                <option value="">Seleccionar</option>
                                <?php foreach ($fabricantes as $f): ?>
                                    <option value="<?php echo $f['id_fabricante']; ?>"><?php echo h($f['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="producto_numero_registro" class="form-label">Número de Registro</label>
                            <input type="text" class="form-control" id="producto_numero_registro" name="producto_numero_registro" placeholder="Opcional por ahora">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="producto_fecha_registro_inicial" class="form-label">Fecha Registro Inicial</label>
                            <input type="date" class="form-control" id="producto_fecha_registro_inicial" name="producto_fecha_registro_inicial">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="producto_fecha_vencimiento_registro" class="form-label">Fecha Vencimiento</label>
                            <input type="date" class="form-control" id="producto_fecha_vencimiento_registro" name="producto_fecha_vencimiento_registro">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="producto_estado_producto" class="form-label">Estado del Producto</label>
                            <select class="form-select" id="producto_estado_producto" name="producto_estado_producto">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="crear_producto_nuevo" value="1">
                </div>

                <!-- 6. Sección: Producto Existente -->
                <div class="col-12 mb-3" id="seccion_producto_existente" style="display:none;">
                    <hr>
                    <h5>Buscar Producto Existente</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="buscar_producto" class="form-label">Número de Registro</label>
                            <input type="text" class="form-control" id="buscar_producto" placeholder="Escribe al menos 2 caracteres...">
                            <div id="resultado_productos" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Producto Seleccionado</label>
                            <div id="producto_info_existente" class="border p-2 rounded bg-light">
                                <p class="text-muted">Selecciona un producto de la lista.</p>
                            </div>
                            <input type="hidden" name="id_producto" id="id_producto" value="">
                        </div>
                    </div>
                    <div class="row" id="campos_modificacion" style="display:none;">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nombre Propuesto</label>
                            <input type="text" class="form-control" id="propuesto_nombre" name="propuesto_nombre" placeholder="Dejar en blanco para no cambiar">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Marca Propuesta</label>
                            <input type="text" class="form-control" id="propuesto_marca" name="propuesto_marca" placeholder="Dejar en blanco para no cambiar">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fabricante Propuesto</label>
                            <select class="form-select" id="propuesto_id_fabricante" name="propuesto_id_fabricante">
                                <option value="">Sin cambio</option>
                                <?php foreach ($fabricantes as $f): ?>
                                    <option value="<?php echo $f['id_fabricante']; ?>"><?php echo h($f['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Representante Legal -->
                <div class="col-md-6 mb-3">
                    <label for="id_representante_legal" class="form-label">Representante Legal *</label>
                    <select class="form-select" id="id_representante_legal" name="id_representante_legal" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($personas as $per): ?>
                            <option value="<?php echo $per['id_persona']; ?>"><?php echo h($per['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Titular -->
                <div class="col-md-6 mb-3">
                    <label for="id_titular" class="form-label">Titular *</label>
                    <select class="form-select" id="id_titular" name="id_titular" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($titulares as $t): ?>
                            <option value="<?php echo $t['id_titular']; ?>"><?php echo h($t['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Código de Control -->
                <div class="col-md-6 mb-3">
                    <label for="codigo_control_empresa" class="form-label">Código de Control de Empresa</label>
                    <input type="text" class="form-control" id="codigo_control_empresa" name="codigo_control_empresa" placeholder="Opcional en borrador" required>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- SECCIÓN DISTRIBUIDORES (ESTILIZADA) - ANTES DE DOCUMENTOS    -->
            <!-- ============================================================ -->
            <div class="seccion-distribuidores">
                <div class="seccion-header">
                    <div class="icono"><i class="fas fa-truck"></i></div>
                    <div class="flex-grow-1">
                        <h5>Distribuidores Asociados <span class="text-danger">*</span></h5>
                        <small>Selecciona los distribuidores vinculados a la combinación de dirección y tipo de licencia.</small>
                    </div>
                    <div>
                        <span id="contador_distribuidores" class="contador-distribuidores vacio">
                            <i class="fas fa-list"></i> 0 agregados
                        </span>
                    </div>
                </div>

                <!-- Selector -->
                <div class="selector-distribuidor">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-9">
                            <label for="select_distribuidor_disponible" class="form-label small mb-1 text-muted">
                                Distribuidor disponible
                            </label>
                            <select class="form-select" id="select_distribuidor_disponible">
                                <option value="">Primero selecciona dirección y tipo de licencia</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary w-100" id="btn_agregar_distribuidor">
                                <i class="fas fa-plus-circle"></i> Agregar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="tabla-distribuidores-wrapper">
                    <table class="table tabla-distribuidores mb-0">
                        <thead>
                            <tr>
                                <th style="width:35%;"><i class="fas fa-building me-1"></i> Nombre</th>
                                <th style="width:22%;"><i class="fas fa-id-card me-1"></i> N° Licencia</th>
                                <th style="width:33%;"><i class="fas fa-map-marker-alt me-1"></i> Ubicación</th>
                                <th style="width:10%;" class="text-center"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tbody_distribuidores">
                            <tr id="fila_vacia_distribuidores">
                                <td colspan="4" class="estado-vacio-tabla">
                                    <i class="fas fa-truck-moving"></i>
                                    Aún no has agregado ningún distribuidor.
                                    <br>
                                    <small>Usa el selector de arriba para agregarlos.</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Inputs hidden generados dinámicamente -->
                <div id="hidden_distribuidores"></div>
            </div>

            <!-- Documentos Requeridos -->
            <hr>
            <h5><i class="fas fa-file-alt"></i> Documentos Requeridos</h5>
            <div id="documentos_requeridos" class="row">
                <div class="col-12 text-muted">Selecciona una combinación válida para ver los documentos requeridos.</div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="guardar_borrador" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Borrador
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// 1. Cargar tipos de licencia según dirección ANRS
// ============================================================
document.getElementById('id_direccion_anrs').addEventListener('change', function() {
    const dirId = this.value;
    const tipoLicenciaSelect = document.getElementById('id_tipo_licencia');
    tipoLicenciaSelect.innerHTML = '<option value="">Cargando...</option>';
    if (dirId) {
        fetch('<?php echo BASE_URL; ?>ajax_tipos_licencia.php?id_direccion=' + dirId)
            .then(response => response.json())
            .then(data => {
                tipoLicenciaSelect.innerHTML = '<option value="">Seleccionar</option>';
                data.forEach(tl => {
                    const option = document.createElement('option');
                    option.value = tl.id_tipo_licencia;
                    option.textContent = tl.nombre;
                    tipoLicenciaSelect.appendChild(option);
                });
                tipoLicenciaSelect.dispatchEvent(new Event('change'));
            })
            .catch(() => {
                tipoLicenciaSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    } else {
        tipoLicenciaSelect.innerHTML = '<option value="">Primero selecciona una dirección</option>';
    }
});

// ============================================================
// 2. Mostrar/ocultar según tipo de trámite
// ============================================================
document.getElementById('id_tipo_tramite').addEventListener('change', function() {
    const idTramite = parseInt(this.value);
    const divModificacion = document.getElementById('div_tipo_modificacion');
    const selectModificacion = document.getElementById('id_tipo_modificacion');
    const seccionNuevo = document.getElementById('seccion_producto_nuevo');
    const seccionExistente = document.getElementById('seccion_producto_existente');
    const camposModificacion = document.getElementById('campos_modificacion');

    selectModificacion.value = '';
    selectModificacion.required = false;
    divModificacion.style.display = 'none';
    seccionNuevo.style.display = 'none';
    seccionExistente.style.display = 'none';
    camposModificacion.style.display = 'none';

    document.getElementById('producto_nombre').required = false;
    document.getElementById('producto_marca').required = false;
    document.getElementById('producto_id_fabricante').required = false;

    if (idTramite === 1) {
        seccionNuevo.style.display = 'block';
        document.getElementById('producto_nombre').required = true;
        document.getElementById('producto_marca').required = true;
        document.getElementById('producto_id_fabricante').required = true;
    } else if (idTramite === 2 || idTramite === 4) {
        divModificacion.style.display = 'block';
        selectModificacion.required = true;
        seccionExistente.style.display = 'block';
        camposModificacion.style.display = 'block';
    } else if (idTramite === 3) {
        seccionExistente.style.display = 'block';
        camposModificacion.style.display = 'none';
    }

    cargarDocumentosRequeridos();
});
// ============================================================
// 3. Buscar productos por número de registro
// ============================================================
let timeoutBusqueda = null;
let productosEncontrados = {}; // Mapa: id_producto -> datos

document.getElementById('buscar_producto').addEventListener('input', function() {
    clearTimeout(timeoutBusqueda);
    const busqueda = this.value.trim();
    const container = document.getElementById('resultado_productos');
    if (busqueda.length < 2) {
        container.innerHTML = '';
        return;
    }
    timeoutBusqueda = setTimeout(() => {
        fetch('<?php echo BASE_URL; ?>ajax_buscar_producto.php?numero_registro=' + encodeURIComponent(busqueda))
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    container.innerHTML = '<div class="text-muted small">No se encontraron productos.</div>';
                    return;
                }

                // Guardar productos en el mapa para acceso posterior
                productosEncontrados = {};
                data.forEach(p => {
                    productosEncontrados[p.id_producto] = p;
                });

                // Generar lista con data-attributes (sin onclick inline)
                let html = '<ul class="list-group">';
                data.forEach(p => {
                    html += `
                        <li class="list-group-item list-group-item-action producto-resultado" 
                            style="cursor:pointer;" 
                            data-id-producto="${p.id_producto}">
                            <strong>${escapeHtml(p.nombre)}</strong> - ${escapeHtml(p.marca)} 
                            <small class="text-muted">(${escapeHtml(p.numero_registro)})</small>
                        </li>
                    `;
                });
                html += '</ul>';
                container.innerHTML = html;
            })
            .catch(() => {
                container.innerHTML = '<div class="text-danger small">Error al buscar.</div>';
            });
    }, 300);
});

// Delegación de eventos: detecta click en cualquier producto de la lista
document.getElementById('resultado_productos').addEventListener('click', function(e) {
    const li = e.target.closest('.producto-resultado');
    if (!li) return;

    const idProducto = parseInt(li.getAttribute('data-id-producto'));
    const prod = productosEncontrados[idProducto];
    if (!prod) return;

    seleccionarProducto(prod);
});

function seleccionarProducto(prod) {
    document.getElementById('id_producto').value = prod.id_producto;
    document.getElementById('resultado_productos').innerHTML = '';

    const infoDiv = document.getElementById('producto_info_existente');
    infoDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6"><strong>Nombre:</strong> ${escapeHtml(prod.nombre)}</div>
            <div class="col-md-6"><strong>Marca:</strong> ${escapeHtml(prod.marca)}</div>
            <div class="col-md-6"><strong>Fabricante:</strong> ${escapeHtml(prod.fabricante_nombre || '-')}</div>
            <div class="col-md-6"><strong>N° Registro:</strong> ${escapeHtml(prod.numero_registro || '-')}</div>
            <div class="col-md-6"><strong>Fecha Registro:</strong> ${escapeHtml(prod.fecha_registro_inicial || '-')}</div>
            <div class="col-md-6"><strong>Vencimiento:</strong> ${escapeHtml(prod.fecha_vencimiento_registro || '-')}</div>
            <div class="col-md-6"><strong>Estado:</strong> ${escapeHtml(prod.estado_producto || '-')}</div>
        </div>
    `;

    // Precargar campos propuestos con los datos actuales
    document.getElementById('propuesto_nombre').value = prod.nombre || '';
    document.getElementById('propuesto_marca').value = prod.marca || '';
    document.getElementById('propuesto_id_fabricante').value = prod.id_fabricante || '';

    // Mostrar campos de modificación si corresponde
    const idTramite = parseInt(document.getElementById('id_tipo_tramite').value);
    if (idTramite === 2 || idTramite === 4) {
        document.getElementById('campos_modificacion').style.display = 'block';
    }
}
// ============================================================
// 4. Cargar documentos requeridos según combinación
// ============================================================
function cargarDocumentosRequeridos() {
    const id_tipo_licencia = document.getElementById('id_tipo_licencia').value;
    const id_tipo_tramite = document.getElementById('id_tipo_tramite').value;
    const id_tipo_modificacion = document.getElementById('id_tipo_modificacion').value;

    const container = document.getElementById('documentos_requeridos');
    container.innerHTML = '<div class="col-12 text-muted">Cargando...</div>';

    if (!id_tipo_licencia || !id_tipo_tramite) {
        container.innerHTML = '<div class="col-12 text-muted">Selecciona tipo de licencia y trámite.</div>';
        return;
    }

    let url = '<?php echo BASE_URL; ?>ajax_requisitos.php?id_tipo_licencia=' + id_tipo_licencia + '&id_tipo_tramite=' + id_tipo_tramite;
    if (id_tipo_modificacion) {
        url += '&id_tipo_modificacion=' + id_tipo_modificacion;
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            if (data.length === 0) {
                container.innerHTML = '<div class="col-12 text-muted">No hay documentos requeridos para esta combinación.</div>';
                return;
            }
            data.forEach(req => {
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                col.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">${req.tipo_documento_nombre}</h6>
                            <p class="card-text small">${req.obligatorio ? 'Obligatorio' : 'Opcional'}</p>
                            <input type="file" class="form-control form-control-sm" name="documentos[${req.id_tipo_documento}]" accept=".pdf,.doc,.docx,.jpg,.png">
                            <input type="hidden" name="tipo_documento_ids[]" value="${req.id_tipo_documento}">
                            <input type="hidden" name="obligatorios[${req.id_tipo_documento}]" value="${req.obligatorio}">
                        </div>
                    </div>
                `;
                container.appendChild(col);
            });
        })
        .catch(() => {
            container.innerHTML = '<div class="col-12 text-danger">Error al cargar documentos.</div>';
        });
}

// ============================================================
// 5. Cargar documentos al cambiar selecciones
// ============================================================
document.getElementById('id_tipo_licencia').addEventListener('change', cargarDocumentosRequeridos);
document.getElementById('id_tipo_tramite').addEventListener('change', cargarDocumentosRequeridos);
document.getElementById('id_tipo_modificacion').addEventListener('change', cargarDocumentosRequeridos);

// ============================================================
// 5.1 Distribuidores: select + tabla dinámica
// ============================================================
let distribuidoresDisponibles = [];

function cargarDistribuidoresDisponibles() {
    const idDireccion = document.getElementById('id_direccion_anrs').value;
    const idTipoLicencia = document.getElementById('id_tipo_licencia').value;
    const select = document.getElementById('select_distribuidor_disponible');

    select.innerHTML = '<option value="">Cargando...</option>';
    distribuidoresDisponibles = [];

    if (!idDireccion || !idTipoLicencia) {
        select.innerHTML = '<option value="">Primero selecciona dirección y tipo de licencia</option>';
        return;
    }

    fetch('<?php echo BASE_URL; ?>ajax_distribuidores.php?id_direccion_anrs=' + idDireccion + '&id_tipo_licencia=' + idTipoLicencia)
        .then(response => response.json())
        .then(data => {
            distribuidoresDisponibles = data;
            if (data.length === 0) {
                select.innerHTML = '<option value="">No hay distribuidores para esta combinación</option>';
                return;
            }
            select.innerHTML = '<option value="">Seleccionar distribuidor...</option>';
            data.forEach(d => {
                const infoExtra = [];
                if (d.numero_licencia) infoExtra.push('Lic. ' + d.numero_licencia);

                const option = document.createElement('option');
                option.value = d.id_distribuidor;
                option.textContent = d.nombre + (infoExtra.length ? ' — ' + infoExtra.join(' · ') : '');
                select.appendChild(option);
            });
        })
        .catch(() => {
            select.innerHTML = '<option value="">Error al cargar distribuidores</option>';
        });
}

// Agregar distribuidor
document.getElementById('btn_agregar_distribuidor').addEventListener('click', function() {
    const select = document.getElementById('select_distribuidor_disponible');
    const idDist = parseInt(select.value);

    if (!idDist) {
        alert('Selecciona un distribuidor del listado.');
        return;
    }

    if (document.querySelector(`#tbody_distribuidores tr[data-id="${idDist}"]`)) {
        alert('Este distribuidor ya está en la lista.');
        return;
    }

    const dist = distribuidoresDisponibles.find(d => parseInt(d.id_distribuidor) === idDist);
    if (!dist) return;

    agregarFilaDistribuidor(dist);
    select.value = '';
    actualizarTablaDistribuidores();
});

function agregarFilaDistribuidor(dist) {
    const tbody = document.getElementById('tbody_distribuidores');
    const filaVacia = document.getElementById('fila_vacia_distribuidores');
    if (filaVacia) filaVacia.remove();

    const ubicacion = [];
    if (dist.departamento_nombre) ubicacion.push(dist.departamento_nombre);
    if (dist.municipio_nombre) ubicacion.push(dist.municipio_nombre);

    const licencia = dist.numero_licencia 
        ? `<span class="badge-licencia">${escapeHtml(dist.numero_licencia)}</span>` 
        : '<span class="text-muted">—</span>';

    const tr = document.createElement('tr');
    tr.setAttribute('data-id', dist.id_distribuidor);
    tr.innerHTML = `
        <td><strong>${escapeHtml(dist.nombre)}</strong></td>
        <td>${licencia}</td>
        <td><span class="texto-ubicacion"><i class="fas fa-map-marker-alt text-muted me-1"></i>${escapeHtml(ubicacion.join(', ') || '—')}</span></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger btn-eliminar-dist" title="Eliminar">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

document.getElementById('tbody_distribuidores').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-eliminar-dist');
    if (!btn) return;
    const tr = btn.closest('tr');
    tr.remove();
    actualizarTablaDistribuidores();
});

function actualizarTablaDistribuidores() {
    const tbody = document.getElementById('tbody_distribuidores');
    const filas = tbody.querySelectorAll('tr[data-id]');
    const containerHidden = document.getElementById('hidden_distribuidores');
    const contador = document.getElementById('contador_distribuidores');

    // Inputs hidden
    containerHidden.innerHTML = '';
    filas.forEach(tr => {
        const id = tr.getAttribute('data-id');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'distribuidores[]';
        input.value = id;
        containerHidden.appendChild(input);
    });

    // Fila vacía si aplica
    if (filas.length === 0 && !document.getElementById('fila_vacia_distribuidores')) {
        const trVacio = document.createElement('tr');
        trVacio.id = 'fila_vacia_distribuidores';
        trVacio.innerHTML = `
            <td colspan="4" class="estado-vacio-tabla">
                <i class="fas fa-truck-moving"></i>
                Aún no has agregado ningún distribuidor.
                <br>
                <small>Usa el selector de arriba para agregarlos.</small>
            </td>
        `;
        tbody.appendChild(trVacio);
    }

    // Contador
    if (filas.length === 0) {
        contador.className = 'contador-distribuidores vacio';
        contador.innerHTML = '<i class="fas fa-list"></i> 0 agregados';
    } else {
        contador.className = 'contador-distribuidores ok';
        contador.innerHTML = `<i class="fas fa-check-circle"></i> ${filas.length} agregado(s)`;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// Disparadores
document.getElementById('id_tipo_licencia').addEventListener('change', function() {
    cargarDistribuidoresDisponibles();
});

document.getElementById('id_direccion_anrs').addEventListener('change', function() {
    document.getElementById('select_distribuidor_disponible').innerHTML = '<option value="">Primero selecciona dirección y tipo de licencia</option>';
    document.getElementById('tbody_distribuidores').innerHTML = '';
    document.getElementById('hidden_distribuidores').innerHTML = '';
    actualizarTablaDistribuidores();
});

// ============================================================
// 5.2 Validar en submit
// ============================================================
document.getElementById('formSolicitud').addEventListener('submit', function(e) {
    const filas = document.querySelectorAll('#tbody_distribuidores tr[data-id]');
    if (filas.length === 0) {
        e.preventDefault();
        alert('Debes agregar al menos un distribuidor.');
        return false;
    }
});

// ============================================================
// 6. Inicialización
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('seccion_producto_nuevo').style.display = 'none';
    document.getElementById('seccion_producto_existente').style.display = 'none';
    document.getElementById('div_tipo_modificacion').style.display = 'none';
    document.getElementById('campos_modificacion').style.display = 'none';
    document.getElementById('documentos_requeridos').innerHTML = '<div class="col-12 text-muted">Selecciona una combinación válida.</div>';
    actualizarTablaDistribuidores();
});
</script>

<?php include '../includes/footer.php'; ?>