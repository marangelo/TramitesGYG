<?php
// ajax_distribuidores.php
session_start();
require_once 'includes/auth_check.php';
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$id_direccion_anrs = isset($_GET['id_direccion_anrs']) ? (int)$_GET['id_direccion_anrs'] : 0;
$id_tipo_licencia  = isset($_GET['id_tipo_licencia'])  ? (int)$_GET['id_tipo_licencia']  : 0;

if ($id_direccion_anrs <= 0 || $id_tipo_licencia <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT d.id_distribuidor,
               d.nombre,
               d.numero_licencia,
               d.fecha_inicio,
               d.fecha_vence,
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre
        FROM distribuidor d
        LEFT JOIN departamento dep ON d.id_departamento = dep.id_departamento
        LEFT JOIN municipio    mun ON d.id_municipio    = mun.id_municipio
        WHERE d.id_direccion_anrs = :id_direccion_anrs
          AND d.id_tipo_licencia  = :id_tipo_licencia
        ORDER BY d.nombre
    ");
    $stmt->execute([
        ':id_direccion_anrs' => $id_direccion_anrs,
        ':id_tipo_licencia'  => $id_tipo_licencia
    ]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}