<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

// Solo usuarios pueden acceder
requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"];

$stmt = $conexion->prepare("
    SELECT dificultad 
    FROM dificultades_asignadas 
    WHERE usuario_id = ? AND tipo_juego = 'logica'
");
$stmt->execute([$usuario_id]);
$dificultad = $stmt->fetchColumn();

if (!$dificultad) {
    $dificultad = "media"; // dificultad por defecto si no hay asignada
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Juego de Razonamiento</title>

<style>
body {
    margin: 0;
    padding: 0;
    overflow: hidden;
    font-family: Arial, Helvetica, sans-serif;
}

/* ---------- HEADER ---------- */
.header {
    width: 100%;
    height: 20vh;
    min-height: 120px;
    background-image: url('../../imagenes/Banner.svg');
    background-size: cover;
    background-position: center;
    position: relative;
    color: white;
    text-align: center;
    padding-top: 30px;
}

.header h1 {
    font-size: 34px;
    font-weight: 300;
}

.header .user-role {
    position: absolute;
    bottom: 10px;
    left: 20px;
    font-weight: bold;
}

/* ---------- FLECHA DE VOLVER ---------- */
.back-arrow {
    position: absolute;
    top: 15px;
    left: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    cursor: pointer;
    text-decoration: none;
}

.back-arrow svg {
    transition: opacity 0.2s ease-in-out;
}

.back-arrow:hover svg {
    opacity: 0.7;
}

/* ---------- CONTENEDOR DEL JUEGO ---------- */
.game-container {
    width: 90%;
    height: 55vh;
    min-height: 300px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;

    box-shadow: 0px 4px 12px rgba(0,0,0,0.1);

    display: flex;
    justify-content: center;
    align-items: center;

    font-size: 22px;
    font-weight: bold;
}

/* ---------- IMAGEN INFERIOR ---------- */
.bottom-image {
    width: 100%;
    height: 25vh;
    min-height: 100px;
    overflow: hidden;
}

.bottom-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

</head>
<body>

<!-- HEADER -->
<div class="header">

    <!-- FLECHA GOOGLE MATERIAL ICON -->
    <a href="../../usuario.php" class="back-arrow">
        <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
            <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
        </svg>
    </a>
    <div class="user-role">Juego: Razonamiento</div>
</div>

<!-- CONTENEDOR DEL JUEGO -->
  <div class="game-container">
        <p>Dificultad asignada: <strong><?= ucfirst($dificultad) ?></strong></p>
    </div>

<!-- IMAGEN INFERIOR -->
<div class="bottom-image">
    <img src="../../imagenes/footerfoto.png" alt="">
</div>

</body>
</html>
