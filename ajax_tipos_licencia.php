<?php
require_once 'config.php';
$id_direccion = isset($_GET['id_direccion']) ? (int)$_GET['id_direccion'] : 0;
if ($id_direccion > 0) {
    $stmt = $pdo->prepare("SELECT id_tipo_licencia, nombre FROM tipo_licencia WHERE id_direccion_anrs = :id ORDER BY nombre");
    $stmt->execute([':id' => $id_direccion]);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($tipos);
} else {
    echo json_encode([]);
}
?>