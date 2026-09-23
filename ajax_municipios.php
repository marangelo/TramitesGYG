<?php
// ajax_municipios.php
require_once 'config.php';
$departamento = $_GET['departamento'] ?? 0;
if ($departamento) {
    $stmt = $pdo->prepare("SELECT id_municipio, nombre FROM municipio WHERE id_departamento = :id ORDER BY nombre");
    $stmt->execute([':id' => $departamento]);
    $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($municipios);
} else {
    echo json_encode([]);
}
?>