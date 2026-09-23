<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$busqueda      = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$id_direccion  = isset($_GET['id_direccion_anrs']) ? (int)$_GET['id_direccion_anrs'] : 0;
$pagina        = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;

$resultado     = buscarRequisitos($busqueda, $id_direccion, $pagina, $limite);
$datos         = $resultado['datos'];
$total         = $resultado['total'];
$total_paginas = ceil($total / $limite);

// Direcciones para el filtro
$direccionesAnrs = $pdo->query("SELECT id_direccion_anrs, nombre 
                                FROM direccion_anrs 
                                ORDER BY nombre")
                       ->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Catálogo de Requisitos de Documento';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-check-double"></i> <?php echo $titulo; ?></h2>
    <a href="<?php echo BASE_URL; ?>requisitos/crear.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nuevo Requisito
    </a>
</div>

<div class="row mb-3">
    <div class="col-md-8">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="busqueda" class="form-control"
                       placeholder="Buscar por licencia, trámite, modificación o documento..."
                       value="<?php echo h($busqueda); ?>">
            </div>
            <div class="col-md-4">
                <select name="id_direccion_anrs" class="form-select">
                    <option value="">Todas las Direcciones ANRS</option>
                    <?php foreach ($direccionesAnrs as $da): ?>
                        <option value="<?php echo $da['id_direccion_anrs']; ?>"
                            <?php echo $da['id_direccion_anrs'] == $id_direccion ? 'selected' : ''; ?>>
                            <?php echo h($da['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
    <div class="col-md-4 text-end">
        <span class="badge bg-secondary">Total: <?php echo $total; ?> registros</span>
    </div>
</div>

<?php mostrarMensaje(); ?>

<div class="card card-shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Dirección ANRS</th>
                        <th>Tipo Licencia</th>
                        <th>Tipo Trámite</th>
                        <th>Tipo Modificación</th>
                        <th>Tipo Documento</th>
                        <th>Obligatorio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($datos) > 0): ?>
                        <?php foreach ($datos as $item): ?>
                            <tr>
                                <td><?php echo $item['id_requisito']; ?></td>
                                <td><?php echo h($item['direccion_anrs_nombre'] ?? '-'); ?></td>
                                <td><?php echo h($item['tipo_licencia_nombre']); ?></td>
                                <td><?php echo h($item['tipo_tramite_nombre']); ?></td>
                                <td><?php echo h($item['tipo_modificacion_nombre'] ?? '-'); ?></td>
                                <td><?php echo h($item['tipo_documento_nombre']); ?></td>
                                <td>
                                    <?php if ($item['obligatorio']): ?>
                                        <span class="badge bg-danger">Obligatorio</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Opcional</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>requisitos/editar.php?id_req=<?php echo $item['id_requisito']; ?>"
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>requisitos/eliminar.php?id_req=<?php echo $item['id_requisito']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('¿Eliminar este requisito?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                No hay requisitos que coincidan con los filtros.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación">
            <ul class="pagination justify-content-center">
                <?php
                    $queryBase = 'busqueda=' . urlencode($busqueda) .
                                 '&id_direccion_anrs=' . (int)$id_direccion;
                ?>
                <?php if ($pagina > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo $queryBase; ?>&pagina=<?php echo $pagina-1; ?>">Anterior</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo $queryBase; ?>&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($pagina < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo $queryBase; ?>&pagina=<?php echo $pagina+1; ?>">Siguiente</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>