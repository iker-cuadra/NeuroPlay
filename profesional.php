<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo permite acceso a profesionales
requireRole("profesional");

// Evitar volver atrás con el navegador una vez cerrada la sesión
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Obtener el nombre del profesional logueado (AÚN SE GUARDA, PERO NO SE USA EN EL HTML)
$nombre_profesional = $_SESSION['nombre'] ?? 'Profesional';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Profesional</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root{
            --header-h: 160px;
        }

        html,
        body {
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

        /* etiqueta inferior */
        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* BOTÓN CERRAR SESIÓN: INTACTO (mismo CSS) */
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
            z-index: 10;
            overflow: hidden;
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

        /* MAIN */
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
            background: #ffffffff;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            margin: 0;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
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
            margin: 0;
        }

        /* Subtítulo debajo de Usuarios/Familiares */
        .card-subtitle {
            margin-top: 4px;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

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
                height: auto;
            }

            .card h2 {
                font-size: 22px;
            }

            .logout-button {
                padding: 8px 18px;
                font-size: 14px;
            }
        }

        /* Línea decorativa bajo la etiqueta */
        .card-label::after {
            content: '';
            display: block;
            width: 20px;
            height: 2px;
            background: #3b82f6;
            margin: 8px auto 0;
            transition: width 0.3s ease;
        }

        /* AUMENTAR LÍNEA AL HACER HOVER EN LA TARJETA */
        .card-label:hover::after {
            width: 60px;
        }
    </style>
</head>

<body>

<div class="layout">

    <div class="header">
        <div class="user-role">Panel del Profesional</div>

        <!-- BOTÓN CERRAR SESIÓN: INTACTO -->
        <a href="logout.php" class="logout-button">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>

    <div class="main-section">

        <div class="card card-label" onclick="location.href='gestionar_users.php'">
            <img src="imagenes/usuarios.png" alt="Usuarios">
            <h2>Usuarios</h2>
            <p class="card-subtitle">Gestionar</p>
        </div>

        <div class="card card-label" onclick="location.href='lista_familiares.php'">
            <img src="imagenes/familiares.png" alt="Familiares">
            <h2>Familiares</h2>
            <p class="card-subtitle">Chatear</p>
        </div>

    </div>

</div>

</body>
</html>
