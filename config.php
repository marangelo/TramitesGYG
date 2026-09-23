<?php
// config.php
$host = '192.168.1.7';
$dbname = 'anrs_tramite';
$user = 'root';
$pass = 'a7m1425.';
$port = 3306;

//define('BASE_URL', 'http://localhost/Tramites/');
define('BASE_URL', 'https://gygservice.endscom.com/');
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
?>
