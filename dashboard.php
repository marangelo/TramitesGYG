<?php
// dashboard.php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
date_default_timezone_set('America/Managua');
require_once 'functions.php';

// ============================================================
// NOMBRE REAL DE LA PERSONA
// ============================================================
global $pdo;
$nombre_persona = $_SESSION['nombre_usuario'] ?? 'Usuario';
try {
    $stmt = $pdo->prepare("
        SELECT p.nombre 
        FROM usuario u
        LEFT JOIN personas p ON u.id_persona = p.id_persona
        WHERE u.id_usuario = :id
    ");
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $nombre_bd = $stmt->fetchColumn();
    if (!empty($nombre_bd)) {
        $nombre_persona = $nombre_bd;
    }
} catch (Exception $e) { /* fallback */ }

// ============================================================
// BLOQUE 1: FILTRO DE SOLICITUDES (independiente)
// Usa: sol_fecha_desde / sol_fecha_hasta / sol_preset
// Afecta: conteo estados, tendencias, últimos, KPIs operativos
// ============================================================
$sol_fecha_desde = isset($_GET['sol_fecha_desde']) && $_GET['sol_fecha_desde'] !== '' ? $_GET['sol_fecha_desde'] : null;
$sol_fecha_hasta = isset($_GET['sol_fecha_hasta']) && $_GET['sol_fecha_hasta'] !== '' ? $_GET['sol_fecha_hasta'] : null;

$sol_preset = $_GET['sol_preset'] ?? '';
if ($sol_preset === 'hoy') {
    $sol_fecha_desde = $sol_fecha_hasta = date('Y-m-d');
} elseif ($sol_preset === 'semana') {
    $sol_fecha_desde = date('Y-m-d', strtotime('monday this week'));
    $sol_fecha_hasta = date('Y-m-d');
} elseif ($sol_preset === 'mes') {
    $sol_fecha_desde = date('Y-m-01');
    $sol_fecha_hasta = date('Y-m-d');
} elseif ($sol_preset === 'mes_anterior') {
    $sol_fecha_desde = date('Y-m-01', strtotime('first day of last month'));
    $sol_fecha_hasta = date('Y-m-t', strtotime('last day of last month'));
} elseif ($sol_preset === 'año') {
    $sol_fecha_desde = date('Y-01-01');
    $sol_fecha_hasta = date('Y-m-d');
}

// ============================================================
// BLOQUE 2: FILTRO FINANCIERO (independiente)
// Usa: fac_fecha_desde / fac_fecha_hasta / fac_preset
// Afecta: facturas por estado, montos, tipos de facturación
// ============================================================
$fac_fecha_desde = isset($_GET['fac_fecha_desde']) && $_GET['fac_fecha_desde'] !== '' ? $_GET['fac_fecha_desde'] : null;
$fac_fecha_hasta = isset($_GET['fac_fecha_hasta']) && $_GET['fac_fecha_hasta'] !== '' ? $_GET['fac_fecha_hasta'] : null;

$fac_preset = $_GET['fac_preset'] ?? '';
if ($fac_preset === 'hoy') {
    $fac_fecha_desde = $fac_fecha_hasta = date('Y-m-d');
} elseif ($fac_preset === 'semana') {
    $fac_fecha_desde = date('Y-m-d', strtotime('monday this week'));
    $fac_fecha_hasta = date('Y-m-d');
} elseif ($fac_preset === 'mes') {
    $fac_fecha_desde = date('Y-m-01');
    $fac_fecha_hasta = date('Y-m-d');
} elseif ($fac_preset === 'mes_anterior') {
    $fac_fecha_desde = date('Y-m-01', strtotime('first day of last month'));
    $fac_fecha_hasta = date('Y-m-t', strtotime('last day of last month'));
} elseif ($fac_preset === 'año') {
    $fac_fecha_desde = date('Y-01-01');
    $fac_fecha_hasta = date('Y-m-d');
}

// ============================================================
// PERMISOS
// ============================================================
$ver_financiero = esAdmin() || tieneRol(2);

// ============================================================
// DATOS OPERATIVOS (usan $sol_*)
// ============================================================
$conteo_estados       = obtenerConteoSolicitudesPorEstado($sol_fecha_desde, $sol_fecha_hasta);
$total_solicitudes    = contarTotalSolicitudesRango($sol_fecha_desde, $sol_fecha_hasta);
$ultimas_solicitudes  = obtenerUltimasSolicitudes(5, $sol_fecha_desde, $sol_fecha_hasta);

$datos_mes_tramite      = obtenerSolicitudesPorMesYTipoTramite($sol_fecha_desde, $sol_fecha_hasta);
$datos_mes_modificacion = obtenerSolicitudesPorMesYTipoModificacion($sol_fecha_desde, $sol_fecha_hasta);

$serie_tramites       = construirSeriesPorMesYTipo($datos_mes_tramite, 'id_tipo_tramite', 'tipo_tramite_nombre');
$serie_modificaciones = construirSeriesPorMesYTipo($datos_mes_modificacion, 'id_tipo_modificacion', 'tipo_modificacion_nombre');

// ============================================================
// DATOS FINANCIEROS (usan $fac_*)
// ============================================================
$resumen_por_estado = [
    'factura_emitida' => ['cantidad' => 0, 'NIO' => 0, 'USD' => 0],
    'factura_pagada'  => ['cantidad' => 0, 'NIO' => 0, 'USD' => 0],
];
$ingresos_por_tramite      = [];
$ingresos_por_modificacion = [];

if ($ver_financiero) {
    $resumen_por_estado        = obtenerResumenFacturasPorEstado($fac_fecha_desde, $fac_fecha_hasta);
    $ingresos_por_tramite      = obtenerIngresosPorTipoTramite($fac_fecha_desde, $fac_fecha_hasta);
    $ingresos_por_modificacion = obtenerIngresosPorTipoModificacion($fac_fecha_desde, $fac_fecha_hasta);
}

$emitidas = $resumen_por_estado['factura_emitida'];
$pagadas  = $resumen_por_estado['factura_pagada'];

// ============================================================
// PREPARAR DATOS DEL DONUT OPERATIVO
// ============================================================
$chart_labels = [];
$chart_data   = [];
$chart_colors = [];
$colores_hex  = [
    'secondary' => '#6c757d', 'info' => '#0dcaf0', 'warning' => '#ffc107',
    'success'   => '#198754', 'danger' => '#dc3545', 'dark' => '#212529',
    'primary'   => '#0d6efd',
];

$total_aprobadas  = 0;
$total_pendientes = 0;

foreach ($conteo_estados as $ce) {
    $info = infoEstadoSolicitud($ce['estado_nombre']);
    $chart_labels[] = $info['label'];
    $chart_data[]   = (int)$ce['total'];
    $chart_colors[] = $colores_hex[$info['color']] ?? '#6c757d';

    if ($ce['estado_nombre'] === 'Solicitud_aprobado') {
        $total_aprobadas = (int)$ce['total'];
    }
    if (in_array($ce['estado_nombre'], ['Nueva', 'Solicitud_ingresado', 'Solicitud_evaluacion'])) {
        $total_pendientes += (int)$ce['total'];
    }
}

$porcentaje_aprobadas = $total_solicitudes > 0 
    ? round(($total_aprobadas / $total_solicitudes) * 100, 1) 
    : 0;

// ============================================================
// PREPARAR DATOS DE GRÁFICOS FINANCIEROS
// ============================================================
$fin_tramite_labels = [];
$fin_tramite_nio    = [];
$fin_tramite_usd    = [];
$tmp_tramite = [];
foreach ($ingresos_por_tramite as $row) {
    $nombre = $row['tipo_tramite_nombre'];
    if (!isset($tmp_tramite[$nombre])) {
        $tmp_tramite[$nombre] = ['NIO' => 0, 'USD' => 0];
    }
    $tmp_tramite[$nombre][$row['moneda']] = (float)$row['total'];
}
foreach ($tmp_tramite as $nombre => $valores) {
    $fin_tramite_labels[] = $nombre;
    $fin_tramite_nio[]    = $valores['NIO'];
    $fin_tramite_usd[]    = $valores['USD'];
}

$fin_mod_labels = [];
$fin_mod_nio    = [];
$fin_mod_usd    = [];
$tmp_mod = [];
foreach ($ingresos_por_modificacion as $row) {
    $nombre = $row['tipo_modificacion_nombre'];
    if (!isset($tmp_mod[$nombre])) {
        $tmp_mod[$nombre] = ['NIO' => 0, 'USD' => 0];
    }
    $tmp_mod[$nombre][$row['moneda']] = (float)$row['total'];
}
foreach ($tmp_mod as $nombre => $valores) {
    $fin_mod_labels[] = $nombre;
    $fin_mod_nio[]    = $valores['NIO'];
    $fin_mod_usd[]    = $valores['USD'];
}

$titulo = 'Dashboard';
include 'includes/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .welcome-header {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border-radius: 1rem;
        padding: 1.5rem 2rem;
        color: #fff;
        box-shadow: 0 6px 20px rgba(78, 115, 223, 0.25);
        margin-bottom: 1.5rem;
    }
    .welcome-header h1 { margin: 0; font-size: 1.6rem; font-weight: 600; }
    .welcome-header .badge { font-size: .85rem; padding: .5rem 1rem; border-radius: 2rem; }

    /* BLOQUES */
    .bloque-dashboard {
        background: #fff;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.06);
        border: 1px solid #eef1f7;
        margin-bottom: 1.5rem;
    }
    .bloque-dashboard.bloque-solicitudes {
        border-top: 5px solid #4e73df;
    }
    .bloque-dashboard.bloque-financiero {
        border-top: 5px solid #198754;
    }
    .bloque-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        padding-bottom: .75rem;
        border-bottom: 2px solid #eef1f7;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .bloque-header .bloque-titulo {
        display: flex;
        align-items: center;
        gap: .75rem;
        font-size: 1.15rem;
        font-weight: 700;
        color: #2c3e50;
    }
    .bloque-header .bloque-titulo .icono {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .bloque-solicitudes .bloque-titulo .icono {
        background: linear-gradient(135deg, #4e73df, #224abe);
        box-shadow: 0 4px 12px rgba(78,115,223,.35);
    }
    .bloque-financiero .bloque-titulo .icono {
        background: linear-gradient(135deg, #198754, #0f5132);
        box-shadow: 0 4px 12px rgba(25,135,84,.35);
    }
    .bloque-header .bloque-titulo small {
        display: block;
        font-weight: 400;
        font-size: .78rem;
        color: #8898aa;
        margin-top: .15rem;
    }

    /* FILTRO */
    .filtro-interno {
        background: #f8f9fc;
        border-radius: .65rem;
        padding: .85rem 1rem;
        border: 1px solid #eef1f7;
        margin-bottom: 1.25rem;
    }
    .filtro-interno .preset-btn {
        border-radius: 2rem;
        font-size: .75rem;
        padding: .25rem .75rem;
        border: 1px solid #d1d3e2;
        background: #fff;
        color: #5a5c69;
        transition: all .15s ease;
        text-decoration: none;
    }
    .filtro-interno .preset-btn:hover {
        background: #fff;
        border-color: #4e73df;
        color: #4e73df;
    }
    .bloque-solicitudes .filtro-interno .preset-btn.active {
        background: #4e73df; border-color: #4e73df; color: #fff; font-weight: 600;
    }
    .bloque-financiero .filtro-interno .preset-btn:hover {
        border-color: #198754; color: #198754;
    }
    .bloque-financiero .filtro-interno .preset-btn.active {
        background: #198754; border-color: #198754; color: #fff; font-weight: 600;
    }

    /* STAT CARDS */
    .stat-card {
        border: none;
        border-radius: .85rem;
        transition: all .2s ease;
        overflow: hidden;
        position: relative;
        height: 100%;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.1); }
    .stat-card .card-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem;
    }
    .stat-card .stat-label {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .8px;
        font-weight: 600;
        color: #8898aa;
        margin: 0;
    }
    .stat-card .stat-number {
        font-size: 1.7rem;
        font-weight: 700;
        line-height: 1;
        margin: .35rem 0 0;
    }
    .stat-card .stat-icon-circle {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .stat-primary .stat-icon-circle { background: rgba(13,110,253,.12); color: #0d6efd; }
    .stat-primary .stat-number { color: #0d6efd; }
    .stat-secondary .stat-icon-circle { background: rgba(108,117,125,.12); color: #6c757d; }
    .stat-secondary .stat-number { color: #6c757d; }
    .stat-info .stat-icon-circle { background: rgba(13,202,240,.12); color: #0dcaf0; }
    .stat-info .stat-number { color: #0dcaf0; }
    .stat-warning .stat-icon-circle { background: rgba(255,193,7,.15); color: #d39e00; }
    .stat-warning .stat-number { color: #d39e00; }
    .stat-success .stat-icon-circle { background: rgba(25,135,84,.12); color: #198754; }
    .stat-success .stat-number { color: #198754; }
    .stat-danger .stat-icon-circle { background: rgba(220,53,69,.12); color: #dc3545; }
    .stat-danger .stat-number { color: #dc3545; }
    .stat-dark .stat-icon-circle { background: rgba(33,37,41,.12); color: #212529; }
    .stat-dark .stat-number { color: #212529; }

    /* CHART CARDS */
    .chart-card {
        border: none;
        border-radius: .85rem;
        box-shadow: 0 4px 12px rgba(0,0,0,.05);
        height: 100%;
        background: #fff;
        border: 1px solid #eef1f7;
    }
    .chart-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef1f7;
        border-radius: .85rem .85rem 0 0 !important;
        padding: 1rem 1.25rem;
        font-weight: 600;
        color: #2c3e50;
    }
    .chart-container { position: relative; height: 280px; padding: 1rem; }

    /* PROGRESO */
    .progress-info-card {
        background: linear-gradient(135deg, #f8f9fc 0%, #eef1fb 100%);
        border-radius: .85rem;
        padding: 1.25rem;
        border: 1px solid #e3e6f0;
    }
    .progress-info-card .progress { height: 12px; border-radius: 1rem; background-color: #e9ecef; }
    .progress-info-card .progress-bar { border-radius: 1rem; }

    /* ANIMACIONES */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-in { animation: fadeInUp .4s ease forwards; }

    /* TABLA */
    .table-solicitudes thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #8898aa;
        font-weight: 700;
        border-bottom: 1px solid #eef1f7;
    }
    .table-solicitudes tbody td { vertical-align: middle; font-size: .88rem; border-color: #f1f4fb; }
    .table-solicitudes tbody tr:hover { background: #f8f9fc; }

    /* KPIs */
    .kpi-big { display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100%; padding: 1.25rem; }
    .kpi-big .kpi-number {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, #4e73df, #224abe);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .kpi-big .kpi-sub { font-size: .78rem; color: #8898aa; text-transform: uppercase; letter-spacing: .8px; margin-top: .5rem; }

    /* FIN BLOQUES POR COBRAR / COBRADO */
    .fin-bloque {
        border-radius: 1rem;
        padding: 1.25rem;
        height: 100%;
        transition: all .2s ease;
        border: 1px solid transparent;
    }
    .fin-bloque:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(0,0,0,.08);
    }
    .fin-bloque-pendiente {
        background: linear-gradient(135deg, #fffbee 0%, #fff5d6 100%);
        border-color: #fde79a;
    }
    .fin-bloque-pagada {
        background: linear-gradient(135deg, #eefbf3 0%, #d5f2df 100%);
        border-color: #a8e4bd;
    }
    .fin-bloque-header {
        display: flex;
        align-items: center;
        gap: .9rem;
        margin-bottom: .75rem;
        padding-bottom: .75rem;
        border-bottom: 1px dashed rgba(0,0,0,.08);
    }
    .fin-bloque-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #fff;
        flex-shrink: 0;
    }
    .fin-bloque-icon.pendiente {
        background: linear-gradient(135deg, #ffc107, #d39e00);
        box-shadow: 0 4px 12px rgba(255,193,7,.4);
    }
    .fin-bloque-icon.pagada {
        background: linear-gradient(135deg, #198754, #0f5132);
        box-shadow: 0 4px 12px rgba(25,135,84,.4);
    }
    .fin-bloque-titulo { flex-grow: 1; }
    .fin-bloque-titulo h5 { margin: 0; font-weight: 700; color: #2c3e50; font-size: 1.05rem; }
    .fin-bloque-titulo small { color: #6c757d; font-size: .78rem; }
    .fin-bloque-count .badge { font-size: .75rem; padding: .4rem .75rem; font-weight: 600; }

    .fin-mini-card {
        background: #fff;
        border-radius: .65rem;
        padding: .85rem 1rem;
        text-align: center;
        border: 1px solid rgba(0,0,0,.05);
        box-shadow: 0 2px 6px rgba(0,0,0,.03);
        position: relative;
        overflow: hidden;
    }
    .fin-mini-card::before {
        content: "";
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
    }
    .fin-mini-nio::before { background: #0dcaf0; }
    .fin-mini-usd::before { background: #198754; }
    .fin-mini-nio-solid::before { background: #0a8ba3; }
    .fin-mini-usd-solid::before { background: #0f5132; }
    .fin-mini-label {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #8898aa;
        font-weight: 600;
        margin: 0 0 .35rem;
    }
    .fin-mini-valor { font-size: 1.15rem; font-weight: 800; margin: 0; color: #2c3e50; }
    .fin-mini-nio-solid .fin-mini-valor { color: #0a8ba3; }
    .fin-mini-usd-solid .fin-mini-valor { color: #0f5132; }
</style>

<!-- HEADER DE BIENVENIDA -->
<div class="welcome-header d-flex justify-content-between align-items-center animate-in">
    <div>
        <h1><i class="fas fa-hand-sparkles me-2"></i> Hola, <?php echo htmlspecialchars($nombre_persona); ?></h1>
        <small class="opacity-75">
            <i class="far fa-calendar-alt me-1"></i>
            <?php echo date('l, d \d\e F \d\e Y'); ?>
        </small>
    </div>
    <?php if (esAdmin()): ?>
        <span class="badge bg-light text-danger fw-bold"><i class="fas fa-shield-alt me-1"></i> Administrador</span>
    <?php elseif (tieneRol(2)): ?>
        <span class="badge bg-light text-warning fw-bold"><i class="fas fa-user-check me-1"></i> Evaluador</span>
    <?php else: ?>
        <span class="badge bg-light text-secondary fw-bold"><i class="fas fa-user me-1"></i> Usuario</span>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- BLOQUE 2: FINANCIERO (solo admin/evaluador) - PRIMERO        -->
<!-- ============================================================ -->
<?php if ($ver_financiero): ?>
<div class="bloque-dashboard bloque-financiero animate-in">

    <div class="bloque-header">
        <div class="bloque-titulo">
            <div class="icono"><i class="fas fa-coins"></i></div>
            <div>
                Resumen Financiero
                <small>Facturas filtradas por <code>fecha_factura</code></small>
            </div>
        </div>
    </div>

    <!-- Filtro financiero -->
    <div class="filtro-interno">
        <form method="GET" class="row g-2 align-items-end">
            <!-- Preservar filtros de solicitudes -->
            <input type="hidden" name="sol_fecha_desde" value="<?php echo h($sol_fecha_desde ?? ''); ?>">
            <input type="hidden" name="sol_fecha_hasta" value="<?php echo h($sol_fecha_hasta ?? ''); ?>">
            <input type="hidden" name="sol_preset" value="<?php echo h($sol_preset); ?>">

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Desde
                </label>
                <input type="date" name="fac_fecha_desde" class="form-control form-control-sm"
                       value="<?php echo h($fac_fecha_desde ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">
                    <i class="fas fa-calendar-day me-1 text-success"></i> Hasta
                </label>
                <input type="date" name="fac_fecha_hasta" class="form-control form-control-sm"
                       value="<?php echo h($fac_fecha_hasta ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Aplicar
                </button>
            </div>
            <div class="col-md-2">
                <a href="?<?php echo http_build_query(array_filter([
                    'sol_fecha_desde' => $sol_fecha_desde,
                    'sol_fecha_hasta' => $sol_fecha_hasta,
                    'sol_preset'      => $sol_preset,
                ])); ?>" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-times me-1"></i> Limpiar
                </a>
            </div>

            <div class="col-md-12 mt-2 d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted me-1">Rápido:</small>
                <?php 
                $base_fac = array_filter([
                    'sol_fecha_desde' => $sol_fecha_desde,
                    'sol_fecha_hasta' => $sol_fecha_hasta,
                    'sol_preset'      => $sol_preset,
                ]);
                ?>
                <a href="?<?php echo http_build_query(array_merge($base_fac, ['fac_preset' => 'hoy'])); ?>" 
                   class="preset-btn <?php echo $fac_preset==='hoy'?'active':''; ?>">Hoy</a>
                <a href="?<?php echo http_build_query(array_merge($base_fac, ['fac_preset' => 'semana'])); ?>" 
                   class="preset-btn <?php echo $fac_preset==='semana'?'active':''; ?>">Esta semana</a>
                <a href="?<?php echo http_build_query(array_merge($base_fac, ['fac_preset' => 'mes'])); ?>" 
                   class="preset-btn <?php echo $fac_preset==='mes'?'active':''; ?>">Este mes</a>
                <a href="?<?php echo http_build_query(array_merge($base_fac, ['fac_preset' => 'mes_anterior'])); ?>" 
                   class="preset-btn <?php echo $fac_preset==='mes_anterior'?'active':''; ?>">Mes anterior</a>
                <a href="?<?php echo http_build_query(array_merge($base_fac, ['fac_preset' => 'año'])); ?>" 
                   class="preset-btn <?php echo $fac_preset==='año'?'active':''; ?>">Este año</a>
                <a href="?<?php echo http_build_query($base_fac); ?>" 
                   class="preset-btn <?php echo empty($fac_preset) && !$fac_fecha_desde && !$fac_fecha_hasta ? 'active' : ''; ?>">Todo</a>
            </div>
        </form>

        <?php if ($fac_fecha_desde || $fac_fecha_hasta): ?>
            <div class="mt-2 pt-2 border-top">
                <small class="text-muted">
                    <i class="fas fa-info-circle text-success me-1"></i>
                    Facturas
                    <?php if ($fac_fecha_desde): ?> desde <strong><?php echo date('d/m/Y', strtotime($fac_fecha_desde)); ?></strong><?php endif; ?>
                    <?php if ($fac_fecha_hasta): ?> hasta <strong><?php echo date('d/m/Y', strtotime($fac_fecha_hasta)); ?></strong><?php endif; ?>
                    — <strong><?php echo ($emitidas['cantidad'] + $pagadas['cantidad']); ?></strong> factura(s)
                </small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bloques Por Cobrar / Cobrado -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="fin-bloque fin-bloque-pendiente">
                <div class="fin-bloque-header">
                    <div class="fin-bloque-icon pendiente"><i class="fas fa-hourglass-half"></i></div>
                    <div class="fin-bloque-titulo">
                        <h5>Por Cobrar</h5>
                        <small>Facturas emitidas pendientes de pago</small>
                    </div>
                    <div class="fin-bloque-count">
                        <span class="badge bg-warning text-dark">
                            <?php echo (int)$emitidas['cantidad']; ?> factura<?php echo $emitidas['cantidad'] != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="fin-mini-card fin-mini-nio">
                            <p class="fin-mini-label">C$ Córdobas</p>
                            <p class="fin-mini-valor"><?php echo number_format($emitidas['NIO'], 2); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="fin-mini-card fin-mini-usd">
                            <p class="fin-mini-label">US$ Dólares</p>
                            <p class="fin-mini-valor"><?php echo number_format($emitidas['USD'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="fin-bloque fin-bloque-pagada">
                <div class="fin-bloque-header">
                    <div class="fin-bloque-icon pagada"><i class="fas fa-check-circle"></i></div>
                    <div class="fin-bloque-titulo">
                        <h5>Cobrado</h5>
                        <small>Facturas pagadas — ingresos reales</small>
                    </div>
                    <div class="fin-bloque-count">
                        <span class="badge bg-success">
                            <?php echo (int)$pagadas['cantidad']; ?> factura<?php echo $pagadas['cantidad'] != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="fin-mini-card fin-mini-nio-solid">
                            <p class="fin-mini-label">C$ Córdobas</p>
                            <p class="fin-mini-valor"><?php echo number_format($pagadas['NIO'], 2); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="fin-mini-card fin-mini-usd-solid">
                            <p class="fin-mini-label">US$ Dólares</p>
                            <p class="fin-mini-valor"><?php echo number_format($pagadas['USD'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativa + por tipo -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="chart-card">
                <div class="card-header">
                    <i class="fas fa-chart-column text-success me-2"></i>
                    Comparativa <strong>Por Cobrar</strong> vs <strong>Cobrado</strong>
                </div>
                <div class="chart-container" style="height: 240px;">
                    <canvas id="chartComparativaEstado"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-bar text-success me-2"></i>Por Tipo de Trámite</span>
                    <span class="badge bg-light text-dark border"><?php echo count($fin_tramite_labels); ?> tipo(s)</span>
                </div>
                <div class="chart-container">
                    <?php if (empty($fin_tramite_labels)): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                            <div class="text-center">
                                <i class="fas fa-inbox fa-3x mb-2 opacity-25"></i>
                                <p>Sin facturación registrada</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <canvas id="chartFinTramite"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-bar text-warning me-2"></i>Por Tipo de Modificación</span>
                    <span class="badge bg-light text-dark border"><?php echo count($fin_mod_labels); ?> tipo(s)</span>
                </div>
                <div class="chart-container">
                    <?php if (empty($fin_mod_labels)): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                            <div class="text-center">
                                <i class="fas fa-inbox fa-3x mb-2 opacity-25"></i>
                                <p>Sin facturación por modificaciones</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <canvas id="chartFinModificacion"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- BLOQUE 1: SOLICITUDES - SEGUNDO                              -->
<!-- ============================================================ -->
<div class="bloque-dashboard bloque-solicitudes animate-in">

    <div class="bloque-header">
        <div class="bloque-titulo">
            <div class="icono"><i class="fas fa-file-alt"></i></div>
            <div>
                Resumen de Solicitudes
                <small>Filtrado por <code>fecha_solicita</code></small>
            </div>
        </div>
    </div>

    <!-- Filtro de solicitudes -->
    <div class="filtro-interno">
        <form method="GET" class="row g-2 align-items-end">
            <!-- Preservar filtros financieros -->
            <input type="hidden" name="fac_fecha_desde" value="<?php echo h($fac_fecha_desde ?? ''); ?>">
            <input type="hidden" name="fac_fecha_hasta" value="<?php echo h($fac_fecha_hasta ?? ''); ?>">
            <input type="hidden" name="fac_preset" value="<?php echo h($fac_preset); ?>">

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">
                    <i class="fas fa-calendar-day me-1 text-primary"></i> Desde
                </label>
                <input type="date" name="sol_fecha_desde" class="form-control form-control-sm"
                       value="<?php echo h($sol_fecha_desde ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">
                    <i class="fas fa-calendar-day me-1 text-primary"></i> Hasta
                </label>
                <input type="date" name="sol_fecha_hasta" class="form-control form-control-sm"
                       value="<?php echo h($sol_fecha_hasta ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Aplicar
                </button>
            </div>
            <div class="col-md-2">
                <a href="?<?php echo http_build_query(array_filter([
                    'fac_fecha_desde' => $fac_fecha_desde,
                    'fac_fecha_hasta' => $fac_fecha_hasta,
                    'fac_preset'      => $fac_preset,
                ])); ?>" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-times me-1"></i> Limpiar
                </a>
            </div>

            <div class="col-md-12 mt-2 d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted me-1">Rápido:</small>
                <?php 
                $base_sol = array_filter([
                    'fac_fecha_desde' => $fac_fecha_desde,
                    'fac_fecha_hasta' => $fac_fecha_hasta,
                    'fac_preset'      => $fac_preset,
                ]);
                ?>
                <a href="?<?php echo http_build_query(array_merge($base_sol, ['sol_preset' => 'hoy'])); ?>" 
                   class="preset-btn <?php echo $sol_preset==='hoy'?'active':''; ?>">Hoy</a>
                <a href="?<?php echo http_build_query(array_merge($base_sol, ['sol_preset' => 'semana'])); ?>" 
                   class="preset-btn <?php echo $sol_preset==='semana'?'active':''; ?>">Esta semana</a>
                <a href="?<?php echo http_build_query(array_merge($base_sol, ['sol_preset' => 'mes'])); ?>" 
                   class="preset-btn <?php echo $sol_preset==='mes'?'active':''; ?>">Este mes</a>
                <a href="?<?php echo http_build_query(array_merge($base_sol, ['sol_preset' => 'mes_anterior'])); ?>" 
                   class="preset-btn <?php echo $sol_preset==='mes_anterior'?'active':''; ?>">Mes anterior</a>
                <a href="?<?php echo http_build_query(array_merge($base_sol, ['sol_preset' => 'año'])); ?>" 
                   class="preset-btn <?php echo $sol_preset==='año'?'active':''; ?>">Este año</a>
                <a href="?<?php echo http_build_query($base_sol); ?>" 
                   class="preset-btn <?php echo empty($sol_preset) && !$sol_fecha_desde && !$sol_fecha_hasta ? 'active' : ''; ?>">Todo</a>
            </div>
        </form>

        <?php if ($sol_fecha_desde || $sol_fecha_hasta): ?>
            <div class="mt-2 pt-2 border-top">
                <small class="text-muted">
                    <i class="fas fa-info-circle text-primary me-1"></i>
                    Solicitudes
                    <?php if ($sol_fecha_desde): ?> desde <strong><?php echo date('d/m/Y', strtotime($sol_fecha_desde)); ?></strong><?php endif; ?>
                    <?php if ($sol_fecha_hasta): ?> hasta <strong><?php echo date('d/m/Y', strtotime($sol_fecha_hasta)); ?></strong><?php endif; ?>
                    — <strong><?php echo $total_solicitudes; ?></strong> resultado<?php echo $total_solicitudes != 1 ? 's' : ''; ?>
                </small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cards por estado -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card stat-card stat-primary">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Total</p>
                        <p class="stat-number"><?php echo $total_solicitudes; ?></p>
                    </div>
                    <div class="stat-icon-circle"><i class="fas fa-file-alt"></i></div>
                </div>
            </div>
        </div>

        <?php foreach ($conteo_estados as $ce): 
            $info = infoEstadoSolicitud($ce['estado_nombre']);
            $clase_color = 'stat-' . $info['color'];
            $query = http_build_query(array_filter([
                'estado' => $ce['id_estado'],
                'fecha_desde' => $sol_fecha_desde,
                'fecha_hasta' => $sol_fecha_hasta,
            ]));
            $url_filtro = BASE_URL . 'solicitudes/index.php?' . $query;
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?php echo $url_filtro; ?>" class="text-decoration-none">
                    <div class="card stat-card <?php echo $clase_color; ?>">
                        <div class="card-body">
                            <div>
                                <p class="stat-label"><?php echo h($info['label']); ?></p>
                                <p class="stat-number"><?php echo (int)$ce['total']; ?></p>
                            </div>
                            <div class="stat-icon-circle"><i class="fas <?php echo $info['icon']; ?>"></i></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Donut + KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="chart-card">
                <div class="card-header">
                    <i class="fas fa-chart-pie text-primary me-2"></i> Distribución por Estado
                </div>
                <div class="chart-container">
                    <canvas id="chartEstados"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="chart-card h-100">
                <div class="card-header">
                    <i class="fas fa-trophy text-warning me-2"></i> Métricas Clave
                </div>
                <div class="card-body p-0" style="display:flex; flex-direction:column;">
                    <div class="kpi-big border-bottom" style="flex:1;">
                        <div class="kpi-number"><?php echo $porcentaje_aprobadas; ?>%</div>
                        <div class="kpi-sub">Tasa de Aprobación</div>
                        <small class="text-muted mt-1">
                            <?php echo $total_aprobadas; ?> de <?php echo $total_solicitudes; ?> solicitudes
                        </small>
                    </div>
                    <div class="kpi-big" style="flex:1; border-top: 1px solid #eef1f7;">
                        <div class="kpi-number" style="background: linear-gradient(135deg, #ffc107, #d39e00); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
                            <?php echo $total_pendientes; ?>
                        </div>
                        <div class="kpi-sub">En Proceso</div>
                        <small class="text-muted mt-1">Nuevas, Ingresadas o en Evaluación</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tendencia por tipo de trámite -->
    <?php if (!empty($serie_tramites['meses'])): ?>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-line text-primary me-2"></i>Tendencia por <strong>Tipo de Trámite</strong></span>
                    <span class="badge bg-light text-dark border"><?php echo count($serie_tramites['datasets']); ?> tipo(s)</span>
                </div>
                <div class="chart-container" style="height: 280px;">
                    <canvas id="chartTramites"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tendencia por tipo de modificación -->
    <?php if (!empty($serie_modificaciones['meses'])): ?>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-line text-warning me-2"></i>Tendencia por <strong>Tipo de Modificación</strong></span>
                    <span class="badge bg-light text-dark border"><?php echo count($serie_modificaciones['datasets']); ?> tipo(s)</span>
                </div>
                <div class="chart-container" style="height: 280px;">
                    <canvas id="chartModificaciones"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra de progreso -->
    <?php if ($total_solicitudes > 0): ?>
    <div class="progress-info-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="text-muted small text-uppercase">Progreso general de aprobación</strong>
            <strong class="text-primary"><?php echo $porcentaje_aprobadas; ?>%</strong>
        </div>
        <div class="progress">
            <div class="progress-bar bg-success" 
                 role="progressbar" 
                 style="width: <?php echo $porcentaje_aprobadas; ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <small class="text-muted"><i class="fas fa-check-circle text-success me-1"></i><?php echo $total_aprobadas; ?> aprobadas</small>
            <small class="text-muted"><i class="fas fa-clock text-warning me-1"></i><?php echo $total_pendientes; ?> pendientes</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimas solicitudes -->
    <?php if (!empty($ultimas_solicitudes)): ?>
    <div class="card card-shadow animate-in">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
            <strong class="text-dark"><i class="fas fa-clock text-primary me-2"></i>Últimas Solicitudes</strong>
            <a href="<?php echo BASE_URL; ?>solicitudes/index.php" class="btn btn-sm btn-outline-primary">
                Ver todas <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-solicitudes mb-0">
                    <thead>
                        <tr>
                            <th>ID</th><th>Producto</th><th>Trámite</th><th>Estado</th><th>Fecha</th>
                            <th class="text-center">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_solicitudes as $s): 
                            $info = infoEstadoSolicitud($s['estado_nombre']);
                        ?>
                            <tr>
                                <td class="text-muted fw-bold">#<?php echo $s['id_solicitud']; ?></td>
                                <td>
                                    <strong class="text-dark"><?php echo h($s['producto_nombre'] ?? '(No asignado)'); ?></strong>
                                    <?php if (!empty($s['titular_nombre'])): ?>
                                        <br><small class="text-muted"><?php echo h($s['titular_nombre']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo h($s['tipo_tramite_nombre'] ?? '-'); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $info['color']; ?>">
                                        <i class="fas <?php echo $info['icon']; ?> me-1"></i><?php echo h($info['label']); ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?php echo date('d/m/Y', strtotime($s['fecha_solicita'])); ?></td>
                                <td class="text-center">
                                    <a href="<?php echo BASE_URL; ?>solicitudes/detalle.php?id=<?php echo $s['id_solicitud']; ?>" 
                                       class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- EMPRESAS ASIGNADAS -->
<?php if (!esAdmin() && !empty($_SESSION['titulares'])): ?>
    <div class="card card-shadow mt-4 animate-in">
        <div class="card-header bg-light">
            <i class="fas fa-building me-2"></i>Empresas a las que tienes acceso
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <?php foreach ($_SESSION['titulares'] as $id): ?>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <?php echo htmlspecialchars(getTitularNombre($id)); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- SCRIPTS DE CHART.JS                                          -->
<!-- ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ============================================================
    // DONUT — Distribución por estado
    // ============================================================
    const ctxDonut = document.getElementById('chartEstados');
    const labels = <?php echo json_encode($chart_labels); ?>;
    const data   = <?php echo json_encode($chart_data); ?>;
    const colors = <?php echo json_encode($chart_colors); ?>;

    if (ctxDonut) {
        if (data.every(v => v === 0)) {
            ctxDonut.parentNode.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">' +
                '<div class="text-center"><i class="fas fa-inbox fa-3x mb-2 opacity-25"></i><p>Sin solicitudes en el rango</p></div></div>';
        } else {
            new Chart(ctxDonut, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '62%',
                    plugins: {
                        legend: { position: 'right', labels: { padding: 14, font: { size: 12, weight: '500' }, usePointStyle: true, pointStyle: 'circle', boxWidth: 10, color: '#495057' } },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8,
                            callbacks: { label: function(c) {
                                const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((c.parsed / total) * 100).toFixed(1) : 0;
                                return '  ' + c.parsed + ' (' + pct + '%)';
                            } }
                        }
                    },
                    animation: { animateScale: true, animateRotate: true, duration: 900 }
                }
            });
        }
    }

    // ============================================================
    // TENDENCIA POR TIPO DE TRÁMITE
    // ============================================================
    const ctxTramites = document.getElementById('chartTramites');
    const tramitesLabels   = <?php echo json_encode($serie_tramites['meses']); ?>;
    const tramitesDatasets = <?php echo json_encode($serie_tramites['datasets']); ?>;

    if (ctxTramites && tramitesLabels.length > 0 && tramitesDatasets.length > 0) {
        new Chart(ctxTramites, {
            type: 'line',
            data: { labels: tramitesLabels, datasets: tramitesDatasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 12, font: { size: 12, weight: '500' }, color: '#495057' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8, callbacks: { label: c => '  ' + c.dataset.label + ': ' + c.parsed.y } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#8898aa', font: { size: 11 } }, grid: { color: '#f1f4fb', drawBorder: false } },
                    x: { ticks: { color: '#8898aa', font: { size: 11 } }, grid: { display: false } }
                },
                animation: { duration: 800 }
            }
        });
    }

    // ============================================================
    // TENDENCIA POR TIPO DE MODIFICACIÓN
    // ============================================================
    const ctxMod = document.getElementById('chartModificaciones');
    const modLabels   = <?php echo json_encode($serie_modificaciones['meses']); ?>;
    const modDatasets = <?php echo json_encode($serie_modificaciones['datasets']); ?>;

    if (ctxMod && modLabels.length > 0 && modDatasets.length > 0) {
        new Chart(ctxMod, {
            type: 'line',
            data: { labels: modLabels, datasets: modDatasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 12, font: { size: 12, weight: '500' }, color: '#495057' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8, callbacks: { label: c => '  ' + c.dataset.label + ': ' + c.parsed.y } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#8898aa', font: { size: 11 } }, grid: { color: '#f1f4fb', drawBorder: false } },
                    x: { ticks: { color: '#8898aa', font: { size: 11 } }, grid: { display: false } }
                },
                animation: { duration: 800 }
            }
        });
    }

    // ============================================================
    // COMPARATIVA Por Cobrar vs Cobrado
    // ============================================================
    const ctxComparativa = document.getElementById('chartComparativaEstado');
    const cmpNioEmitidas = <?php echo json_encode($emitidas['NIO'] ?? 0); ?>;
    const cmpNioPagadas  = <?php echo json_encode($pagadas['NIO'] ?? 0); ?>;
    const cmpUsdEmitidas = <?php echo json_encode($emitidas['USD'] ?? 0); ?>;
    const cmpUsdPagadas  = <?php echo json_encode($pagadas['USD'] ?? 0); ?>;

    if (ctxComparativa) {
        new Chart(ctxComparativa, {
            type: 'bar',
            data: {
                labels: ['C$ Córdobas', 'US$ Dólares'],
                datasets: [
                    { label: 'Por Cobrar', data: [cmpNioEmitidas, cmpUsdEmitidas], backgroundColor: 'rgba(255,193,7,0.8)', borderColor: '#d39e00', borderWidth: 1, borderRadius: 8, maxBarThickness: 80 },
                    { label: 'Cobrado', data: [cmpNioPagadas, cmpUsdPagadas], backgroundColor: 'rgba(25,135,84,0.8)', borderColor: '#0f5132', borderWidth: 1, borderRadius: 8, maxBarThickness: 80 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 12, font: { size: 12, weight: '500' }, color: '#495057' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8,
                        callbacks: { label: function(c) {
                            const sim = c.label.includes('US$') ? 'US$ ' : 'C$ ';
                            return '  ' + c.dataset.label + ': ' + sim + c.parsed.y.toLocaleString('es-NI', { minimumFractionDigits: 2 });
                        } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#8898aa', font: { size: 11 }, callback: v => v.toLocaleString('es-NI') }, grid: { color: '#f1f4fb', drawBorder: false } },
                    x: { ticks: { color: '#8898aa', font: { size: 12, weight: '600' } }, grid: { display: false } }
                },
                animation: { duration: 800 }
            }
        });
    }

    // ============================================================
    // FACTURACIÓN POR TIPO DE TRÁMITE
    // ============================================================
    const ctxFinTramite = document.getElementById('chartFinTramite');
    const finTramLabels = <?php echo json_encode($fin_tramite_labels); ?>;
    const finTramNio    = <?php echo json_encode($fin_tramite_nio); ?>;
    const finTramUsd    = <?php echo json_encode($fin_tramite_usd); ?>;

    if (ctxFinTramite && finTramLabels.length > 0) {
        new Chart(ctxFinTramite, {
            type: 'bar',
            data: {
                labels: finTramLabels,
                datasets: [
                    { label: 'C$ Córdobas', data: finTramNio, backgroundColor: 'rgba(13,202,240,0.75)', borderColor: '#0a8ba3', borderWidth: 1, borderRadius: 6, maxBarThickness: 45 },
                    { label: 'US$ Dólares', data: finTramUsd, backgroundColor: 'rgba(25,135,84,0.75)', borderColor: '#0f5132', borderWidth: 1, borderRadius: 6, maxBarThickness: 45 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 12, font: { size: 12, weight: '500' }, color: '#495057' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8,
                        callbacks: { label: function(c) {
                            const sim = c.dataset.label.includes('US$') ? 'US$ ' : 'C$ ';
                            return '  ' + c.dataset.label + ': ' + sim + c.parsed.y.toLocaleString('es-NI', { minimumFractionDigits: 2 });
                        } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#8898aa', font: { size: 11 }, callback: v => v.toLocaleString('es-NI') }, grid: { color: '#f1f4fb', drawBorder: false } },
                    x: { ticks: { color: '#8898aa', font: { size: 11 } }, grid: { display: false } }
                },
                animation: { duration: 800 }
            }
        });
    }

    // ============================================================
    // FACTURACIÓN POR TIPO DE MODIFICACIÓN
    // ============================================================
    const ctxFinMod = document.getElementById('chartFinModificacion');
    const finModLabels = <?php echo json_encode($fin_mod_labels); ?>;
    const finModNio    = <?php echo json_encode($fin_mod_nio); ?>;
    const finModUsd    = <?php echo json_encode($fin_mod_usd); ?>;

    if (ctxFinMod && finModLabels.length > 0) {
        new Chart(ctxFinMod, {
            type: 'bar',
            data: {
                labels: finModLabels,
                datasets: [
                    { label: 'C$ Córdobas', data: finModNio, backgroundColor: 'rgba(13,202,240,0.75)', borderColor: '#0a8ba3', borderWidth: 1, borderRadius: 6, maxBarThickness: 45 },
                    { label: 'US$ Dólares', data: finModUsd, backgroundColor: 'rgba(25,135,84,0.75)', borderColor: '#0f5132', borderWidth: 1, borderRadius: 6, maxBarThickness: 45 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 12, font: { size: 12, weight: '500' }, color: '#495057' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,.85)', padding: 12, cornerRadius: 8,
                        callbacks: { label: function(c) {
                            const sim = c.dataset.label.includes('US$') ? 'US$ ' : 'C$ ';
                            return '  ' + c.dataset.label + ': ' + sim + c.parsed.y.toLocaleString('es-NI', { minimumFractionDigits: 2 });
                        } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#8898aa', font: { size: 11 }, callback: v => v.toLocaleString('es-NI') }, grid: { color: '#f1f4fb', drawBorder: false } },
                    x: { ticks: { color: '#8898aa', font: { size: 11 } }, grid: { display: false } }
                },
                animation: { duration: 800 }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>