<?php
session_start();
require_once '../includes/auth_check.php';
require_role(3);
require_once '../config.php';
require_once '../functions.php';

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;

$resultado = buscarFabricantes($busqueda, $pagina, $limite);
$fabricantes = $resultado['datos'];
$total = $resultado['total'];
$total_paginas = ceil($total / $limite);

$titulo = 'Lista de Fabricantes';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-industry"></i> Fabricantes</h2>
    <a href="<?php echo BASE_URL; ?>fabricantes/crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Fabricante</a>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <form method="GET" class="d-flex">
            <input type="text" name="busqueda" class="form-control me-2" placeholder="Buscar..." value="<?php echo h($busqueda); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
            <a href="index.php" class="btn btn-secondary ms-2"><i class="fas fa-undo"></i> Limpiar</a>
        </form>
    </div>
    <div class="col-md-6 text-end">
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
                        <th>ID</th><th>Nombre</th><th>País</th><th>Dirección</th><th>Teléfono</th><th>Correo</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($fabricantes) > 0): ?>
                        <?php foreach ($fabricantes as $f): ?>
                            <tr>
                                <td><?php echo $f['id_fabricante']; ?></td>
                                <td><strong><?php echo h($f['nombre']); ?></strong></td>
                                <td><?php echo h($f['pais_nombre']); ?></td>
                                <td><?php echo h($f['direccion_texto']); ?></td>
                                <td><?php echo h($f['telefono']); ?></td>
                                <td><?php echo h($f['correo']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>fabricantes/editar.php?id_fab=<?php echo $f['id_fabricante']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo BASE_URL; ?>fabricantes/eliminar.php?id_fab=<?php echo $f['id_fabricante']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No hay fabricantes que coincidan con la búsqueda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_paginas > 1): ?>
        <nav><ul class="pagination justify-content-center">
            <?php if ($pagina > 1): ?><li class="page-item"><a class="page-link" href="?busqueda=<?php echo urlencode($busqueda); ?>&pagina=<?php echo $pagina-1; ?>">Anterior</a></li><?php endif; ?>
            <?php for ($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?php echo ($i==$pagina)?'active':''; ?>"><a class="page-link" href="?busqueda=<?php echo urlencode($busqueda); ?>&pagina=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
            <?php if ($pagina < $total_paginas): ?><li class="page-item"><a class="page-link" href="?busqueda=<?php echo urlencode($busqueda); ?>&pagina=<?php echo $pagina+1; ?>">Siguiente</a></li><?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>