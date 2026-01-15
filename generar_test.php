<?php
require_once "includes/conexion.php";

// 1. Configuramos los datos base
$password_plana = "1234";
$password_hash = password_hash($password_plana, PASSWORD_DEFAULT);
$roles = ['usuario', 'familiar', 'profesional'];

try {
    $conexion->beginTransaction();

    for ($i = 1; $i <= 40; $i++) {
        $nombre = "Usuario Test " . $i;
        $email = "test" . $i . "@ejemplo.com";
        $rol = $roles[array_rand($roles)]; // Asigna un rol aleatorio
        $foto = "default.png";

        $sql = "INSERT INTO usuarios (nombre, email, rol, password_hash, foto) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$nombre, $email, $rol, $password_hash, $foto]);
    }

    $conexion->commit();
    echo "¡Éxito! Se han creado 40 usuarios con la contraseña '1234'.";
} catch (Exception $e) {
    $conexion->rollBack();
    echo "Error: " . $e->getMessage();
}
?>