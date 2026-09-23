<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Verificar autenticación (opcional)
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Recibir parámetros con los nombres usados en el frontend
$id_tipo_licencia = isset($_GET['id_tipo_licencia']) ? (int)$_GET['id_tipo_licencia'] : 0;
$id_tipo_tramite = isset($_GET['id_tipo_tramite']) ? (int)$_GET['id_tipo_tramite'] : 0;
$id_tipo_modificacion = isset($_GET['id_tipo_modificacion']) && $_GET['id_tipo_modificacion'] !== '' ? (int)$_GET['id_tipo_modificacion'] : null;

if ($id_tipo_licencia > 0 && $id_tipo_tramite > 0) {
    $requisitos = obtenerRequisitosPorCombinacion($id_tipo_licencia, $id_tipo_tramite, $id_tipo_modificacion);
    header('Content-Type: application/json');
    echo json_encode($requisitos);
} else {
    echo json_encode([]);
}
?>