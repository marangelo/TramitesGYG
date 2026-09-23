<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$numero_registro = isset($_GET['numero_registro']) ? trim($_GET['numero_registro']) : '';

if (strlen($numero_registro) < 2) {
    echo json_encode([]);
    exit;
}

// JOIN con Fabricante para obtener el nombre
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.numero_registro, p.nombre, p.marca, p.id_fabricante, 
           f.nombre AS fabricante_nombre,
           p.fecha_registro_inicial, p.fecha_vencimiento_registro, p.estado_producto
    FROM producto p
    LEFT JOIN fabricante f ON p.id_fabricante = f.id_fabricante
    WHERE p.numero_registro LIKE :busqueda AND p.deleted_at IS NULL
    ORDER BY p.nombre
    LIMIT 10
");
$stmt->execute([':busqueda' => "%$numero_registro%"]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($productos);
?>