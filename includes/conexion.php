<?php


$host   = '127.0.0.1';
$port   = 3306;   // <<--- CAMBIA AQUÍ TU PUERTO REAL
$dbname = 'neuroplay';  // nombre de tu base de datos
$user   = 'root';       // usuario MySQL
$pass   = '';           // contraseña (vacía en XAMPP por defecto)

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $conexion = new PDO($dsn, $user, $pass);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("❌ Error de conexión a la base de datos: " . $e->getMessage());
}
?>
