<?php
// Asegúrate de iniciar la sesión si 'auth.php' no lo hace
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/conexion.php";
require_once "includes/auth.php"; 

// Solo permite acceso a familiares
requireRole("familiar"); 

// Evitar volver atrás con el navegador una vez cerrada la sesión
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Asumimos que el nombre del usuario logueado se guarda en $_SESSION["nombre"]
$nombre = $_SESSION["nombre"] ?? 'Familiar';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel del Familiar</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
:root{
    --header-h: 160px;
}

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
    font-family: 'Poppins', sans-serif;
    background: #887d7dff;
}

/* Layout como en el primer archivo */
.layout{
    height: 100vh;
    display: flex;
    flex-direction: column;
}

/* HEADER igual que el del primer archivo */
.header{
    width: 100%;
    height: var(--header-h);
    background-image: url('imagenes/Banner.svg');
    background-size: cover;
    background-position: center;
    position: relative;
    flex: 0 0 auto;
}

/* Etiqueta inferior (igual que el primero) */
.user-role{
    position: absolute;
    bottom: 10px;
    left: 20px;
    color: white;
    font-weight: 700;
    font-size: 18px;
}

/* BOTÓN CERRAR SESIÓN: INTACTO (tu CSS) */
.logout-button {
    position: absolute;
    top: 15px;
    right: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 22px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 50px;
    background: #7a7676;
    color: #fff;
    border: none;
    cursor: pointer;
    text-decoration: none;
    overflow: hidden;
    z-index: 10;
    transition: all 0.3s ease;
}

.logout-button i {
    transition: transform 0.4s ease;
}

.logout-button::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 300%;
    height: 100%;
    background: linear-gradient(90deg, #7a7676, #968c8c, #c9beb6);
    transition: all 0.4s ease;
    z-index: -1;
}

.logout-button:hover::before {
    left: 0;
}

.logout-button:hover {
    transform: translateY(-3px);
}

.logout-button:hover i {
    transform: rotate(20deg);
}

/* SECCIÓN CENTRAL */
.main-section {
    flex: 1 1 auto;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 50px;
    flex-wrap: wrap;
    padding: 0;
}

.card {
    text-align: center;
    width: 260px;
    padding: 30px;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}

.card img {
    width: 200px;
    height: 200px;
    border-radius: 20px;
    object-fit: cover;
    margin-bottom: 20px;
}

.card h2 {
    font-size: 24px;
    font-weight: 600;
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .main-section {
        flex-direction: column;
        gap: 40px;
    }

    .card {
        width: 220px;
        padding: 20px;
    }

    .card img {
        width: 180px;
        height: 180px;
    }

    .card h2 {
        font-size: 22px;
    }

    .logout-button {
        padding: 8px 18px;
        font-size: 14px;
    }
}
</style>
</head>

<body>

<div class="layout">

    <div class="header">
        <div class="user-role">Panel del Familiar</div>

        <!-- Botón cerrar sesión intacto -->
        <a href="logout.php" class="logout-button">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>

    <div class="main-section">
        <div class="card" onclick="location.href='ver_progreso_familiar.php'">
            <img src="imagenes/progreso.png" alt="Seguimiento de progreso">
            <h2>Progreso</h2>
        </div>

        <div class="card" onclick="location.href='lista_profesionales.php'">
            <img src="imagenes/profesionales.png" alt="Profesionales">
            <h2>Profesionales</h2>
        </div>
    </div>

</div>

</body>
</html>
