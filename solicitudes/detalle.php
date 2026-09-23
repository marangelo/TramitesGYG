<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    setMensaje('ID no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$solicitud = obtenerSolicitudCompleta($id);
if (!$solicitud) {
    setMensaje('Solicitud no encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

if (!puedeAccederSolicitud($solicitud, 'view')) {
    setMensaje('No tienes permiso para ver esta solicitud.', 'danger');
    header('Location: index.php');
    exit;
}

$titulo = 'Detalle de Solicitud #' . $solicitud['id_solicitud'];
include '../includes/header.php';

// ============================================================
// CÁLCULO DE PERMISOS Y VARIABLES AUXILIARES
// ============================================================
$acciones_permitidas = obtenerAccionesPermitidas($solicitud);
$puede_subir_docs = puedeSubirDocumentos($solicitud['estado_nombre']) 
    && (esAdmin() || tieneRol(2) || (tieneRol(1) && $solicitud['estado_nombre'] == 'Nueva'));
$es_visualizador = tieneRol(4);
$es_evaluador    = esAdmin() || tieneRol(2);
$es_admin        = esAdmin();
$es_solicitante  = tieneRol(1) && !$es_evaluador && !$es_admin;

// Solo se pueden eliminar documentos cuando la solicitud está en estado "Nueva"
$puede_eliminar_docs = $es_admin && $solicitud['estado_nombre'] === 'Nueva';

$producto_propuesto = obtenerProductoPropuesto($id);
$producto_actual = null;
if ($solicitud['id_producto']) {
    $producto_actual = obtenerProducto($solicitud['id_producto']);
}

// ============================================================
// TIPO DE TRÁMITE Y VALORES PARA EL MODAL DE APROBAR
// ============================================================
$id_tipo_tramite  = (int)$solicitud['id_tipo_tramite'];
$es_tramite_nuevo = ($id_tipo_tramite === 1);
$es_modificacion  = ($id_tipo_tramite === 2);
$es_renovacion    = in_array($id_tipo_tramite, [3, 4]);

if ($es_tramite_nuevo) {
    // Nuevo: valores del JSON propuesto (todo editable)
    $modal_numero_registro = $producto_propuesto['numero_registro'] ?? '';
    $modal_fecha_registro  = $producto_propuesto['fecha_registro_inicial'] ?? '';
    $modal_fecha_venc      = $producto_propuesto['fecha_vencimiento_registro'] ?? '';
    $modal_numero_readonly = false;
    $modal_fechas_readonly = false;
} else {
    // Modificación / Renovación: valores del producto real
    $modal_numero_registro = $solicitud['producto_numero_registro'] ?? '';
    $modal_fecha_registro  = $solicitud['producto_fecha_registro_inicial'] ?? '';
    $modal_fecha_venc      = $solicitud['producto_fecha_vencimiento_registro'] ?? '';

    // N° registro SIEMPRE bloqueado en modificación y renovación
    $modal_numero_readonly = true;

    // Fechas bloqueadas SOLO en modificación; editables en renovación
    $modal_fechas_readonly = $es_modificacion;
}

// ============================================================
// DISTRIBUIDORES ASOCIADOS (N:N)
// ============================================================
$distribuidores_solicitud = obtenerDistribuidoresDeSolicitud($solicitud['id_solicitud']);

// ============================================================
// ESTADOS PERMITIDOS PARA AGREGAR REVISIÓN
// ============================================================
$estados_permitidos = obtenerEstadosPermitidosParaRevision($solicitud['id_estado_actual']);

$puede_agregar_revision = false;
if ($es_evaluador || $es_admin) {
    $puede_agregar_revision = !in_array($solicitud['estado_nombre'], ['Solicitud_aprobado', 'Solicitud_cancelado'])
        && !empty($estados_permitidos);
} elseif ($es_solicitante && $solicitud['estado_nombre'] === 'Nueva') {
    $puede_agregar_revision = true;
    $estados_permitidos = array_values(array_filter($estados_permitidos, function($e) {
        return in_array($e['nombre'], ['Solicitud_ingresado', 'Solicitud_cancelado']);
    }));
}

// ============================================================
// REQUISITOS DE DOCUMENTOS APLICABLES A ESTA SOLICITUD
// ============================================================
$requisitos_solicitud = obtenerRequisitosDeSolicitud($solicitud);
$ids_docs_subidos = array_column($solicitud['documentos'], 'id_tipo_documento');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-alt"></i> Solicitud #<?php echo $solicitud['id_solicitud']; ?></h2>
    <span class="badge bg-<?php echo ($solicitud['estado_nombre'] == 'Solicitud_aprobado') ? 'success' : (($solicitud['estado_nombre'] == 'Solicitud_rechazada') ? 'danger' : (($solicitud['estado_nombre'] == 'Solicitud_evaluacion') ? 'warning' : (($solicitud['estado_nombre'] == 'Solicitud_ingresado') ? 'info' : 'secondary'))); ?> fs-6">
        <?php echo h($solicitud['estado_nombre']); ?>
    </span>
</div>

<?php mostrarMensaje(); ?>

<div class="row">
    <div class="col-md-8">
        <!-- Datos generales -->
        <div class="card card-shadow mb-4">
            <div class="card-header bg-light"><strong>Información General</strong></div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Producto</dt>
                    <dd class="col-sm-8">
                        <?php
                        $nombre_mostrar = $solicitud['producto_nombre'] ?? null;
                        if (empty($nombre_mostrar) && $es_tramite_nuevo && $producto_propuesto && !empty($producto_propuesto['nombre'])) {
                            $nombre_mostrar = $producto_propuesto['nombre'];
                        }
                        echo h($nombre_mostrar ?: '(Producto no asignado)');
                        ?>
                    </dd>
                    <dt class="col-sm-4">Marca Actual</dt>
                    <dd class="col-sm-8">
                        <?php
                        $marca_mostrar = $solicitud['producto_marca'] ?? null;
                        if (empty($marca_mostrar) && $es_tramite_nuevo && $producto_propuesto && !empty($producto_propuesto['marca'])) {
                            $marca_mostrar = $producto_propuesto['marca'];
                        }
                        echo h($marca_mostrar ?: '-');
                        ?>
                    </dd>
                    <dt class="col-sm-4">N° Registro Sanitario</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['producto_numero_registro'] ?? 'No asignado'); ?></dd>
                    <dt class="col-sm-4">Fecha Registro</dt>
                    <dd class="col-sm-8"><?php echo formatearFecha($solicitud['producto_fecha_registro_inicial'] ?? null); ?></dd>
                    <dt class="col-sm-4">Fecha Vencimiento</dt>
                    <dd class="col-sm-8"><?php echo formatearFecha($solicitud['producto_fecha_vencimiento_registro'] ?? null); ?></dd>
                    <dt class="col-sm-4">Representante Legal</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['representante_nombre']); ?></dd>

                    <!-- FABRICANTE (antes del Titular) -->
                    <dt class="col-sm-4">Fabricante</dt>
                    <dd class="col-sm-8">
                        <?php
                        // Para trámite Nuevo: viene del JSON propuesto
                        // Para Modificación/Renovación: viene del producto real
                        $id_fabricante_mostrar = null;
                        if ($es_tramite_nuevo && $producto_propuesto && !empty($producto_propuesto['id_fabricante'])) {
                            $id_fabricante_mostrar = $producto_propuesto['id_fabricante'];
                        } elseif ($producto_actual && !empty($producto_actual['id_fabricante'])) {
                            $id_fabricante_mostrar = $producto_actual['id_fabricante'];
                        }

                        if ($id_fabricante_mostrar) {
                            $fab_mostrar = obtenerFabricante($id_fabricante_mostrar);
                            echo h($fab_mostrar['nombre'] ?? '-');
                        } else {
                            echo '<span class="text-muted">No asignado</span>';
                        }
                        ?>
                    </dd>

                    <dt class="col-sm-4">Titular</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['titular_nombre']); ?></dd>

                    <!-- DISTRIBUIDORES ASOCIADOS (N:N) -->
                    <dt class="col-sm-4">Distribuidores</dt>
                    <dd class="col-sm-8">
                        <?php if (empty($distribuidores_solicitud)): ?>
                            <span class="text-muted">No asignados</span>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($distribuidores_solicitud as $dist): ?>
                                    <li>
                                        <i class="fas fa-truck text-muted"></i>
                                        <strong><?php echo h($dist['nombre']); ?></strong>
                                        <?php if (!empty($dist['numero_licencia'])): ?>
                                            <small class="text-muted">(Lic. <?php echo h($dist['numero_licencia']); ?>)</small>
                                        <?php endif; ?>
                                        <?php 
                                        $ubicacion = [];
                                        if (!empty($dist['departamento_nombre'])) $ubicacion[] = $dist['departamento_nombre'];
                                        if (!empty($dist['municipio_nombre']))    $ubicacion[] = $dist['municipio_nombre'];
                                        if (!empty($ubicacion)): ?>
                                            <br><small class="text-muted ms-3"><?php echo h(implode(', ', $ubicacion)); ?></small>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Dirección ANRS</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['direccion_nombre']); ?></dd>
                    <dt class="col-sm-4">Tipo de Licencia</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['tipo_licencia_nombre']); ?></dd>
                    <dt class="col-sm-4">Tipo de Trámite</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['tipo_tramite_nombre']); ?></dd>
                    <dt class="col-sm-4">Tipo de Modificación</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['tipo_modificacion_nombre'] ?? 'Ninguno'); ?></dd>
                    <dt class="col-sm-4">Código Control Empresa</dt>
                    <dd class="col-sm-8"><?php echo h($solicitud['codigo_control_empresa']); ?></dd>
                    <dt class="col-sm-4">Fecha Solicitud</dt>
                    <dd class="col-sm-8"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicita'])); ?></dd>
                </dl>
            </div>
        </div>

        <!-- Cambios propuestos de producto -->
        <?php if ($producto_propuesto && ($es_admin || $es_evaluador)): ?>
        <div class="card card-shadow mb-4">
            <div class="card-header bg-light"><strong>Cambios Propuestos de Producto</strong></div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Nuevo Nombre</dt>
                    <dd class="col-sm-8"><?php echo h($producto_propuesto['nombre'] ?? '-'); ?></dd>
                    <dt class="col-sm-4">Nueva Marca</dt>
                    <dd class="col-sm-8"><?php echo h($producto_propuesto['marca'] ?? '-'); ?></dd>
                    <dt class="col-sm-4">Nuevo Fabricante</dt>
                    <dd class="col-sm-8">
                        <?php 
                        $fab = !empty($producto_propuesto['id_fabricante']) 
                            ? obtenerFabricante($producto_propuesto['id_fabricante']) 
                            : null;
                        echo h($fab['nombre'] ?? '-');
                        ?>
                    </dd>
                    <?php if (!empty($producto_propuesto['numero_registro'])): ?>
                        <dt class="col-sm-4">N° Registro Propuesto</dt>
                        <dd class="col-sm-8"><?php echo h($producto_propuesto['numero_registro']); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($producto_propuesto['fecha_registro_inicial'])): ?>
                        <dt class="col-sm-4">Fecha Registro Propuesta</dt>
                        <dd class="col-sm-8"><?php echo h($producto_propuesto['fecha_registro_inicial']); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($producto_propuesto['fecha_vencimiento_registro'])): ?>
                        <dt class="col-sm-4">Fecha Vencimiento Propuesta</dt>
                        <dd class="col-sm-8"><?php echo h($producto_propuesto['fecha_vencimiento_registro']); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documentos Adjuntos -->
        <div class="card card-shadow mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Documentos Adjuntos</strong>
                <?php if ($puede_subir_docs && !empty($requisitos_solicitud)): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubirDocumento">
                        <i class="fas fa-upload"></i> Subir Documento
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($solicitud['documentos']) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Archivo</th>
                                    <th>Subido por</th>
                                    <th>Fecha</th>
                                    <?php if ($puede_eliminar_docs): ?>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitud['documentos'] as $doc): ?>
                                    <tr>
                                        <td><?php echo h($doc['tipo_documento_nombre']); ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL . $doc['ruta_archivo']; ?>" target="_blank">
                                                <i class="fas fa-file"></i> Ver
                                            </a>
                                        </td>
                                        <td><?php echo h($doc['usuario_nombre']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($doc['fecha_subida'])); ?></td>
                                        <?php if ($puede_eliminar_docs): ?>
                                            <td>
                                                <a href="eliminar_documento.php?id_doc=<?php echo $doc['id_documento']; ?>&id_sol=<?php echo $solicitud['id_solicitud']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Eliminar este documento?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No hay documentos adjuntos.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de Revisiones -->
        <div class="card card-shadow mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Historial de Revisiones</strong>
                <?php if ($puede_agregar_revision): ?>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalAgregarRevision">
                        <i class="fas fa-plus-circle"></i> 
                        <?php echo $es_solicitante ? 'Enviar Solicitud' : 'Agregar Revisión'; ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($solicitud['revisiones']) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th># Revisión</th>
                                    <th>De</th>
                                    <th>A</th>
                                    <th>Usuario</th>
                                    <th>Comentario</th>
                                    <th>Documento</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitud['revisiones'] as $rev): ?>
                                    <tr>
                                        <td><?php echo $rev['numero_revision']; ?></td>
                                        <td><?php echo h($rev['estado_anterior_nombre']); ?></td>
                                        <td><?php echo h($rev['estado_nuevo_nombre']); ?></td>
                                        <td><?php echo h($rev['evaluador_nombre']); ?></td>
                                        <td><?php echo h($rev['comentario_evaluador']); ?></td>
                                        <td>
                                            <?php if (!empty($rev['documento'])): ?>
                                                <a href="<?php echo BASE_URL . $rev['documento']; ?>" target="_blank">
                                                    <i class="fas fa-file"></i> Ver
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($rev['fecha_revision'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No hay revisiones registradas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel de acciones -->
    <div class="col-md-4">
        <div class="card card-shadow">
           <div class="card-header bg-light"><strong>Acciones</strong></div>
                <div class="card-body">
                    <?php 
                    // Botón Facturar cuando el estado es Aprobado
                    $puede_facturar = $solicitud['estado_nombre'] === 'Solicitud_aprobado' && ($es_admin || $es_evaluador);
                    ?>

                    <?php if ($puede_facturar): ?>
                        <div class="d-grid gap-2 mb-3">
                            <a href="<?php echo BASE_URL; ?>facturas/crear.php" class="btn btn-success">
                                <i class="fas fa-file-invoice-dollar"></i> Facturar
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($acciones_permitidas) || $es_visualizador): ?>
                        <p class="text-muted">No hay acciones disponibles para este estado.</p>
                    <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($acciones_permitidas as $acc): ?>
                            <button type="button" class="btn <?php echo $acc['class']; ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalAccion_<?php echo $acc['accion']; ?>">
                                <i class="fas <?php echo $acc['icon']; ?>"></i> <?php echo $acc['label']; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL PARA APROBAR                                           -->
<!-- ============================================================ -->
<?php if (isset($acciones_permitidas[0]) && $acciones_permitidas[0]['accion'] === 'aprobar'): ?>
<div class="modal fade" id="modalAccion_aprobar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="aprobar_solicitud.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Aprobar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id_solicitud']; ?>">

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <?php if ($es_tramite_nuevo): ?>
                            Esta solicitud es de tipo <strong>Nuevo</strong>. Se creará un nuevo producto con los datos proporcionados.
                        <?php elseif ($es_modificacion): ?>
                            Esta solicitud es de tipo <strong>Modificación</strong>. El <strong>N° de Registro</strong> y las <strong>fechas</strong> se mantienen del producto original.
                        <?php elseif ($es_renovacion): ?>
                            Esta solicitud es de tipo <strong><?php echo h($solicitud['tipo_tramite_nombre']); ?></strong>. Se mantiene el <strong>N° de Registro</strong>; puedes actualizar las fechas.
                        <?php endif; ?>
                    </div>

                    <?php if ($producto_propuesto && !$es_tramite_nuevo): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light">Cambios propuestos al producto</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:30%;">Nombre</th>
                                            <td><?php echo h($producto_propuesto['nombre'] ?? 'Sin cambio'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Marca</th>
                                            <td><?php echo h($producto_propuesto['marca'] ?? 'Sin cambio'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Fabricante</th>
                                            <td>
                                                <?php 
                                                $fab = !empty($producto_propuesto['id_fabricante']) 
                                                    ? obtenerFabricante($producto_propuesto['id_fabricante']) 
                                                    : null;
                                                echo h($fab['nombre'] ?? 'Sin cambio');
                                                ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($es_tramite_nuevo && !$producto_propuesto): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No hay datos de producto propuestos. 
                            Asegúrate de que la solicitud tenga datos de producto antes de aprobar.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="numero_registro" class="form-label">
                                Número de Registro Sanitario *
                                <?php if ($modal_numero_readonly): ?>
                                    <i class="fas fa-lock text-muted ms-1" title="No se puede modificar"></i>
                                <?php endif; ?>
                            </label>
                            <input type="text" 
                                class="form-control <?php echo $modal_numero_readonly ? 'bg-light' : ''; ?>" 
                                id="numero_registro" name="numero_registro" 
                                value="<?php echo h($modal_numero_registro); ?>" 
                                <?php echo $modal_numero_readonly ? 'readonly' : ''; ?> required>
                            <?php if ($modal_numero_readonly): ?>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> El N° de registro no se puede modificar en este tipo de trámite.
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_registro_inicial" class="form-label">
                                Fecha de Registro Inicial *
                                <?php if ($modal_fechas_readonly): ?>
                                    <i class="fas fa-lock text-muted ms-1" title="No se puede modificar"></i>
                                <?php endif; ?>
                            </label>
                            <input type="date" 
                                class="form-control <?php echo $modal_fechas_readonly ? 'bg-light' : ''; ?>" 
                                id="fecha_registro_inicial" name="fecha_registro_inicial" 
                                value="<?php echo h($modal_fecha_registro); ?>" 
                                <?php echo $modal_fechas_readonly ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_vencimiento_registro" class="form-label">
                                Fecha de Vencimiento *
                                <?php if ($modal_fechas_readonly): ?>
                                    <i class="fas fa-lock text-muted ms-1" title="No se puede modificar"></i>
                                <?php endif; ?>
                            </label>
                            <input type="date" 
                                class="form-control <?php echo $modal_fechas_readonly ? 'bg-light' : ''; ?>" 
                                id="fecha_vencimiento_registro" name="fecha_vencimiento_registro" 
                                value="<?php echo h($modal_fecha_venc); ?>" 
                                <?php echo $modal_fechas_readonly ? 'readonly' : ''; ?> required>
                            <?php if ($modal_fechas_readonly): ?>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Las fechas se mantienen del producto original.
                                </small>
                            <?php elseif ($es_renovacion): ?>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Actualiza las fechas para la renovación.
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="comentario_aprobar" class="form-label">Comentario (opcional)</label>
                            <textarea class="form-control" id="comentario_aprobar" name="comentario" rows="2"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="documento_aprobar" class="form-label">Documento (opcional)</label>
                            <input type="file" class="form-control" id="documento_aprobar" name="documento" accept=".pdf,.doc,.docx,.jpg,.png">
                            <small class="text-muted">Puedes adjuntar un documento a la aprobación (ej: resolución).</small>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Al aprobar, la solicitud pasará a estado <strong>Aprobado</strong> y se aplicarán los cambios al producto.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar Aprobación</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- MODAL PARA AGREGAR REVISIÓN MANUAL / ENVIAR SOLICITUD       -->
<!-- ============================================================ -->
<?php if ($puede_agregar_revision): ?>
<div class="modal fade" id="modalAgregarRevision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="agregar_revision.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $es_solicitante ? 'Enviar Solicitud' : 'Agregar Revisión'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id_solicitud']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Estado Actual</label>
                        <p class="form-control-static"><strong><?php echo h($solicitud['estado_nombre']); ?></strong></p>
                    </div>

                    <div class="mb-3">
                        <label for="id_estado_nuevo" class="form-label">Nuevo Estado *</label>
                        <select class="form-select" id="id_estado_nuevo" name="id_estado_nuevo" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($estados_permitidos as $est): ?>
                                <option value="<?php echo $est['id_estado']; ?>">
                                    <?php echo h($est['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($es_solicitante): ?>
                            <small class="text-muted">
                                Puedes <strong>Enviar</strong> la solicitud (pasa a Ingresado) o <strong>Cancelarla</strong>.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="comentario_revision" class="form-label">Comentario *</label>
                        <textarea class="form-control" id="comentario_revision" name="comentario" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="documento_revision" class="form-label">Documento (opcional)</label>
                        <input type="file" class="form-control" id="documento_revision" name="documento_revision" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small class="text-muted">Puedes adjuntar un documento a esta revisión.</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> El número de revisión se asignará automáticamente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $es_solicitante ? 'Enviar Solicitud' : 'Guardar Revisión'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- MODAL PARA SUBIR DOCUMENTO                                   -->
<!-- ============================================================ -->
<?php if ($puede_subir_docs && !empty($requisitos_solicitud)): ?>
<div class="modal fade" id="modalSubirDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="subir_documento.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Subir Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id_solicitud']; ?>">
                    <div class="mb-3">
                        <label for="id_tipo_documento" class="form-label">Tipo de Documento *</label>
                        <select class="form-select" id="id_tipo_documento" name="id_tipo_documento" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($requisitos_solicitud as $req): 
                                $ya_subido = in_array($req['id_tipo_documento'], $ids_docs_subidos);
                                $es_obligatorio = !empty($req['obligatorio']);
                            ?>
                                <option value="<?php echo $req['id_tipo_documento']; ?>"
                                        <?php echo $ya_subido ? 'disabled' : ''; ?>>
                                    <?php echo h($req['tipo_documento_nombre']); ?>
                                    <?php echo $es_obligatorio ? ' *' : ''; ?>
                                    <?php echo $ya_subido ? ' (ya subido)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            Solo se muestran los documentos requeridos para este tipo de trámite.
                            Los marcados con <strong>*</strong> son obligatorios.
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="archivo" class="form-label">Archivo *</label>
                        <input type="file" class="form-control" id="archivo" name="archivo" required>
                    </div>
                    <?php if ($solicitud['estado_nombre'] == 'Solicitud_rechazada' && ($es_admin || $es_evaluador)): ?>
                        <div class="mb-3">
                            <label for="id_revision" class="form-label">Asociar a Revisión (opcional)</label>
                            <select class="form-select" id="id_revision" name="id_revision">
                                <option value="">Ninguna</option>
                                <?php foreach ($solicitud['revisiones'] as $rev): ?>
                                    <option value="<?php echo $rev['id_revision']; ?>">
                                        #<?php echo $rev['numero_revision']; ?> - <?php echo h($rev['estado_nuevo_nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>