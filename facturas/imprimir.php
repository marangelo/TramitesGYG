<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config.php';
require_once '../functions.php';

if (!esAdmin() && !tieneRol(2)) {
    header('Location: ../dashboard.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$factura = obtenerFactura($id);
if (!$factura) {
    die('Factura no encontrada.');
}

$es_pagada = ($factura['estado_nombre'] ?? '') === 'factura_pagada';

// Ruta del logo
$logo_src = file_exists(__DIR__ . '/../image/logo.jpg') 
    ? '../image/logo.jpg' 
    : '';

// Logos de bancos (con fallback automático si no existen)
$logos_bancos = [
    'lafise' => file_exists(__DIR__ . '/../image/lafise.jpg') ? '../image/lafise.jpg' : '',
    'bac'    => file_exists(__DIR__ . '/../image/bac.jpg')    ? '../image/bac.jpg'    : '',
];

// ============================================================
// DATOS PARA EL CÓDIGO QR (incluye detalle completo)
// ============================================================
$qr_data  = "=== FACTURA " . $factura['numero_factura'] . " ===\n";
$qr_data .= "Fecha: "   . date('d/m/Y H:i', strtotime($factura['fecha_factura'])) . "\n";
$qr_data .= "Cliente: " . $factura['nombre'] . "\n";
if (!empty($factura['direccion'])) {
    $qr_data .= "Direccion: " . $factura['direccion'] . "\n";
}
if (!empty($factura['telefono'])) {
    $qr_data .= "Telefono: " . $factura['telefono'] . "\n";
}
$qr_data .= "Moneda: "  . $factura['moneda'] . "\n";
$qr_data .= "Estado: "  . ($es_pagada ? 'PAGADA' : 'EMITIDA') . "\n";
$qr_data .= "------------------------\n";
$qr_data .= "DETALLE:\n";

$i = 0;
foreach ($factura['detalles'] as $d) {
    $i++;
    $codigo = $d['codigo_control_empresa'] ?? '—';
    $desc   = mb_substr($d['descripcion'], 0, 40); // recorta descripciones muy largas
    $qr_data .= $i . ". [" . $codigo . "] " . $desc
              . " | Cant: " . $d['cantidad']
              . " | Unit: " . formatearMoneda($d['monto_unitario'], $factura['moneda'])
              . " | Subt: " . formatearMoneda($d['subtotal'], $factura['moneda'])
              . "\n";
}

$qr_data .= "------------------------\n";
$qr_data .= "TOTAL: " . formatearMoneda($factura['monto_total'], $factura['moneda']) . "\n";
$qr_data .= "Emitida por: " . ($factura['usuario_creador'] ?? 'N/A');

// URL del QR usando API gratuita de qrserver
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data='
        . urlencode($qr_data);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?php echo h($factura['numero_factura']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            color: #2c3e50;
            background: #f4f6f9;
            padding: 2rem 1rem;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .factura {
            max-width: 850px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,.08);
        }

        /* HEADER */
        .factura-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .factura-header::after {
            content: "";
            position: absolute;
            bottom: -20px;
            right: -20px;
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
        }

        .empresa {
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 2;
        }
        .empresa .logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            flex-shrink: 0;
        }
        .empresa .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .empresa .info h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: .5px;
            color: #fff;
        }
        .empresa .info p {
            margin: .15rem 0 0;
            font-size: .78rem;
            opacity: .85;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .factura-numero {
            text-align: right;
            z-index: 2;
        }
        .factura-numero .label {
            font-size: .72rem;
            letter-spacing: 2px;
            opacity: .75;
            text-transform: uppercase;
            margin: 0;
        }
        .factura-numero .numero {
            font-size: 1.6rem;
            font-weight: 800;
            margin: .15rem 0 .35rem;
            letter-spacing: 1px;
        }
        .factura-numero .fecha {
            font-size: .82rem;
            opacity: .9;
            margin: 0;
        }

        /* BADGE ESTADO */
        .badge-estado {
            display: inline-block;
            margin-top: .6rem;
            padding: .35rem .9rem;
            border-radius: 1rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1.2px;
        }
        .badge-estado.pagada { background: #198754; color: #fff; }
        .badge-estado.emitida { background: #ffc107; color: #212529; }

        /* BODY */
        .factura-body {
            padding: 2rem 2.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-box {
            background: #f8f9fc;
            border-left: 4px solid #2a5298;
            padding: 1rem 1.25rem;
            border-radius: 6px;
        }
        .info-box h3 {
            margin: 0 0 .5rem;
            font-size: .72rem;
            color: #2a5298;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
        }
        .info-box p {
            margin: .25rem 0;
            font-size: .88rem;
            color: #333;
        }
        .info-box p strong {
            color: #1e3c72;
        }

        .badge-moneda {
            display: inline-block;
            padding: .25rem .75rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .5px;
            margin-top: .35rem;
        }
        .badge-moneda.nio { background: #d1ecf1; color: #0c5460; }
        .badge-moneda.usd { background: #d4edda; color: #155724; }

        /* TABLA */
        table.detalle {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        table.detalle thead th {
            background: #1e3c72;
            color: #fff;
            text-transform: uppercase;
            font-size: .72rem;
            letter-spacing: 1px;
            padding: .85rem 1rem;
            text-align: left;
            font-weight: 600;
        }
        table.detalle thead th:first-child { border-radius: 6px 0 0 0; }
        table.detalle thead th:last-child  { border-radius: 0 6px 0 0; }
        table.detalle thead th.text-right  { text-align: right; }
        table.detalle thead th.text-center { text-align: center; }

        table.detalle tbody td {
            padding: .85rem 1rem;
            border-bottom: 1px solid #eef1f7;
            font-size: .88rem;
            vertical-align: middle;
        }
        table.detalle tbody td.text-right  { text-align: right; }
        table.detalle tbody td.text-center { text-align: center; }
        table.detalle tbody tr:nth-child(even) { background: #fafbfd; }
        table.detalle tbody tr:last-child td { border-bottom: none; }

        .sol-id {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #2a5298;
            font-size: .82rem;
            letter-spacing: .3px;
        }
        .descripcion {
            font-weight: 500;
            color: #2c3e50;
        }

        /* TOTAL + QR */
        .total-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .total-box {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 1.1rem 1.5rem;
            border-radius: 8px;
            min-width: 280px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 20px rgba(30,60,114,.25);
            gap: 1rem;
        }
        .total-box .label {
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            opacity: .85;
            margin: 0;
        }
        .total-box .monto {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: .5px;
        }

        /* QR */
        .qr-container {
            display: flex;
            align-items: center;
            gap: .85rem;
            background: #f8f9fc;
            border: 1px solid #e6e9f2;
            border-radius: 8px;
            padding: .75rem 1rem;
        }
        .qr-container img {
            width: 130px;
            height: 130px;
            display: block;
            background: #fff;
            border-radius: 6px;
            padding: 4px;
            border: 1px solid #eef1f7;
        }
        .qr-container .qr-info {
            font-size: .72rem;
            line-height: 1.4;
            color: #6b7c93;
            max-width: 170px;
        }
        .qr-container .qr-info strong {
            display: block;
            color: #1e3c72;
            font-size: .78rem;
            letter-spacing: .5px;
            margin-bottom: .2rem;
            text-transform: uppercase;
        }

        /* CUENTAS BANCARIAS */
        .cuentas {
            margin-top: 1.5rem;
            background: #f8f9fc;
            border-left: 4px solid #2a5298;
            border-radius: 6px;
            padding: 1rem 1.25rem;
        }
        .cuentas .titulo {
            font-size: .72rem;
            color: #2a5298;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            margin-bottom: .85rem;
        }
        .cuentas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 1.5rem;
        }
        .cuentas-col {
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .cuenta-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #fff;
            border: 1px solid #e6e9f2;
            border-radius: 8px;
            padding: .6rem .75rem;
            transition: all .2s;
        }
        .cuenta-item .logo-banco {
            width: 46px;
            height: 46px;
            border-radius: 6px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid #eef1f7;
        }
        .cuenta-item .logo-banco img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .cuenta-item .logo-banco .fallback {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: .5px;
        }
        .cuenta-item .logo-banco .fallback.lafise { color: #c8102e; }
        .cuenta-item .logo-banco .fallback.bac    { color: #003da5; }

        .cuenta-item .datos {
            font-size: .78rem;
            line-height: 1.35;
            color: #2c3e50;
        }
        .cuenta-item .datos .banco-nombre {
            font-weight: 700;
            color: #1e3c72;
            font-size: .8rem;
            display: block;
            margin-bottom: .1rem;
        }
        .cuenta-item .datos .titular {
            color: #6b7c93;
            font-size: .72rem;
            display: block;
        }
        .cuenta-item .datos .numero-cuenta {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #2a5298;
            font-size: .85rem;
            letter-spacing: .5px;
            display: block;
            margin-top: .15rem;
        }

        .cuenta-item.activa {
            border-color: #2a5298;
            box-shadow: 0 2px 8px rgba(42,82,152,.12);
        }

        /* FOOTER */
        .factura-footer {
            background: #f8f9fc;
            border-top: 1px solid #eef1f7;
            padding: 1.25rem 2.5rem;
            text-align: center;
            font-size: .78rem;
            color: #8898aa;
        }
        .factura-footer strong { color: #2a5298; }
        .factura-footer .lineas {
            margin-top: .5rem;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .factura-footer .lineas span {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        /* Botones flotantes */
        .acciones {
            max-width: 850px;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: .6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: all .2s;
        }
        .btn-primary { background: #2a5298; color: #fff; }
        .btn-primary:hover { background: #1e3c72; color: #fff; }
        .btn-outline { background: #fff; color: #2c3e50; border: 1px solid #d1d3e2; }
        .btn-outline:hover { background: #f8f9fc; }

        /* PRINT */
        @media print {
            body { background: #fff; padding: 0; }
            .factura { box-shadow: none; border-radius: 0; max-width: 100%; }
            .acciones, .no-print { display: none !important; }
            .factura-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table.detalle thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-moneda { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-estado { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cuentas { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cuenta-item { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cuenta-item { break-inside: avoid; page-break-inside: avoid; }
            .qr-container { -webkit-print-color-adjust: exact; print-color-adjust: exact; break-inside: avoid; page-break-inside: avoid; }
            .qr-container img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>

    <!-- Botones (no se imprimen) -->
    <div class="acciones no-print">
        <a href="detalle.php?id=<?php echo $factura['id_factura']; ?>" class="btn btn-outline">
            ← Volver al detalle
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Imprimir / Guardar PDF
        </button>
    </div>

    <div class="factura">

        <!-- HEADER -->
        <div class="factura-header">
            <div class="empresa">
                <div class="logo">
                    <?php if ($logo_src): ?>
                        <img src="<?php echo $logo_src; ?>" alt="Logo">
                    <?php else: ?>
                        <i style="font-size:1.8rem; color:#2a5298; font-weight:800;">G</i>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <h1>Lic. Dayana García C.</h1>
                    <div class="sub">Código sanitario: 24415 | Química Farmacéutica</div>
                    <div class="contacto">garciadayana73@yahoo.com | gyg2726@hotmail.com | (505) 8510 - 8137</div>
                </div>
            </div>
            <div class="factura-numero">
                <p class="label">Factura N°</p>
                <p class="numero"><?php echo h($factura['numero_factura']); ?></p>
                <p class="fecha">
                    <?php echo date('d/m/Y', strtotime($factura['fecha_factura'])); ?> · 
                    <?php echo date('H:i', strtotime($factura['fecha_factura'])); ?>
                </p>
                <?php if ($es_pagada): ?>
                    <span class="badge-estado pagada">✓ PAGADA</span>
                <?php else: ?>
                    <span class="badge-estado emitida">⏳ EMITIDA</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- BODY -->
        <div class="factura-body">

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-box">
                    <h3>Datos del Cliente</h3>
                    <p><strong><?php echo h($factura['nombre']); ?></strong></p>
                    <?php if (!empty($factura['direccion'])): ?>
                        <p><?php echo h($factura['direccion']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($factura['telefono'])): ?>
                        <p>📞 <?php echo h($factura['telefono']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="info-box">
                    <h3>Detalles de Pago</h3>
                    <p><strong>Moneda:</strong></p>
                    <span class="badge-moneda <?php echo $factura['moneda'] === 'USD' ? 'usd' : 'nio'; ?>">
                        <?php echo $factura['moneda'] === 'USD' ? 'US$ Dólares' : 'C$ Córdobas'; ?>
                    </span>
                    <?php if (!empty($factura['usuario_creador'])): ?>
                        <p style="margin-top:.6rem;">
                            <strong>Emitida por:</strong> 
                            <?php echo h($factura['usuario_creador']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabla de detalles -->
            <table class="detalle">
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px;">No</th>
                        <th style="width:120px;">Código</th>
                        <th>Descripción</th>
                        <th class="text-right" style="width:130px;">Monto Unit.</th>
                        <th class="text-center" style="width:60px;">Cant.</th>
                        <th class="text-right" style="width:130px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 0; foreach ($factura['detalles'] as $d): $n++; ?>
                        <tr>
                            <td class="text-center" style="font-weight:600; color:#8898aa;">
                                <?php echo $n; ?>
                            </td>
                            <td class="sol-id">
                                <?php echo h($d['codigo_control_empresa'] ?? '—'); ?>
                            </td>
                            <td class="descripcion"><?php echo h($d['descripcion']); ?></td>
                            <td class="text-right"><?php echo formatearMoneda($d['monto_unitario'], $factura['moneda']); ?></td>
                            <td class="text-center"><?php echo $d['cantidad']; ?></td>
                            <td class="text-right">
                                <strong><?php echo formatearMoneda($d['subtotal'], $factura['moneda']); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Total + QR -->
            <div class="total-container">

                <!-- Código QR -->
                <div class="qr-container">
                    <img src="<?php echo $qr_url; ?>" alt="Código QR de la factura">
                    <div class="qr-info">
                        <strong>Detalle completo</strong>
                        Escanee este código para verificar los ítems, montos y datos de la factura.
                    </div>
                </div>

                <!-- Total -->
                <div class="total-box">
                    <p class="label">Total a Pagar</p>
                    <p class="monto"><?php echo formatearMoneda($factura['monto_total'], $factura['moneda']); ?></p>
                </div>
            </div>

            <!-- CUENTAS BANCARIAS -->
            <div class="cuentas">
                <div class="titulo">Cuentas para depósito o transferencia</div>
                <div class="cuentas-grid">

                    <!-- Columna Córdobas -->
                    <div class="cuentas-col">

                        <div class="cuenta-item <?php echo $factura['moneda'] !== 'USD' ? 'activa' : ''; ?>">
                            <div class="logo-banco">
                                <?php if ($logos_bancos['lafise']): ?>
                                    <img src="<?php echo $logos_bancos['lafise']; ?>" alt="LAFISE">
                                <?php else: ?>
                                    <span class="fallback lafise">LAFISE</span>
                                <?php endif; ?>
                            </div>
                            <div class="datos">
                                <span class="banco-nombre">LAFISE · Córdobas</span>
                                <span class="titular">Dayana Sorayda García Cruz</span>
                                <span class="numero-cuenta">134075969</span>
                            </div>
                        </div>

                        <div class="cuenta-item <?php echo $factura['moneda'] !== 'USD' ? 'activa' : ''; ?>">
                            <div class="logo-banco">
                                <?php if ($logos_bancos['bac']): ?>
                                    <img src="<?php echo $logos_bancos['bac']; ?>" alt="BAC">
                                <?php else: ?>
                                    <span class="fallback bac">BAC</span>
                                <?php endif; ?>
                            </div>
                            <div class="datos">
                                <span class="banco-nombre">BAC · Córdobas</span>
                                <span class="titular">Dayana Sorayda García Cruz</span>
                                <span class="numero-cuenta">365182088</span>
                            </div>
                        </div>

                    </div>

                    <!-- Columna Dólares -->
                    <div class="cuentas-col">

                        <div class="cuenta-item <?php echo $factura['moneda'] === 'USD' ? 'activa' : ''; ?>">
                            <div class="logo-banco">
                                <?php if ($logos_bancos['lafise']): ?>
                                    <img src="<?php echo $logos_bancos['lafise']; ?>" alt="LAFISE">
                                <?php else: ?>
                                    <span class="fallback lafise">LAFISE</span>
                                <?php endif; ?>
                            </div>
                            <div class="datos">
                                <span class="banco-nombre">LAFISE · Dólares</span>
                                <span class="titular">Dayana Sorayda García Cruz</span>
                                <span class="numero-cuenta">109289700</span>
                            </div>
                        </div>

                        <div class="cuenta-item <?php echo $factura['moneda'] === 'USD' ? 'activa' : ''; ?>">
                            <div class="logo-banco">
                                <?php if ($logos_bancos['bac']): ?>
                                    <img src="<?php echo $logos_bancos['bac']; ?>" alt="BAC">
                                <?php else: ?>
                                    <span class="fallback bac">BAC</span>
                                <?php endif; ?>
                            </div>
                            <div class="datos">
                                <span class="banco-nombre">BAC · Dólares</span>
                                <span class="titular">Dayana Sorayda García Cruz</span>
                                <span class="numero-cuenta">372528679</span>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="factura-footer">
            <p style="margin: 0 0 .35rem;">
                <strong>GYG Professional Service</strong> — Gracias por su preferencia
            </p>
            <p style="margin: .75rem 0 0; font-size: .7rem; opacity: .7;">
                Documento generado electrónicamente · <?php echo date('Y'); ?>
            </p>
        </div>

    </div>

</body>
</html>