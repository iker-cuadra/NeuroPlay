<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo permite acceso a usuarios
requireRole("usuario");
// Evitar volver atrás con el navegador una vez cerrada la sesión
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


// Información del usuario logueado
$nombre = $_SESSION["nombre"];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Panel del Usuario</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            /* Evita scroll */
            font-family: Arial, Helvetica, sans-serif;
            background: #887d7dff;
        }

        /* ENCABEZADO SUPERIOR */
        .header {
            width: 100%;
            height: 160px;
            background-image: url('imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
            text-align: center;
            padding-top: 40px;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 300;
        }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            font-size: 18px;
            font-weight: bold;
        }

        /* SECCIÓN CENTRAL DE JUEGOS */
        .games-section {
            height: calc(100vh - 160px - 160px);
            /* espacio entre header y footer */
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 60px;
            flex-wrap: wrap;
            padding: 20px;
        }

        .game-card {
            width: 260px;
            text-align: center;
            padding: 20px;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .game-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
        }

        .image-wrapper {
            width: 200px;
            height: 200px;
            margin: 0 auto 15px auto;
            overflow: hidden;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .game-card img {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            transition: transform 0.6s ease, box-shadow 0.6s ease;
        }

        .game-card img:hover {
            transform: scale(1.18);
            box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.35);
        }

        .game-card h2 {
            margin-bottom: 15px;
            font-weight: 400;
            font-size: 24px;
        }

        .btn-play {
            background: #4a4a4a;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .btn-play:hover {
            background: #333;
        }

        /* IMAGEN INFERIOR */
        .bottom-image {
            width: 100%;
            height: 160px;
            overflow: hidden;
        }

        .bottom-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .games-section {
                flex-direction: column;
                gap: 40px;
            }

            .game-card {
                width: 220px;
                padding: 15px;
            }

            .image-wrapper {
                width: 180px;
                height: 180px;
            }

            .game-card h2 {
                font-size: 22px;
            }

            .btn-play {
                padding: 12px 25px;
                font-size: 15px;
            }
        }

        .logout-button {
            position: absolute;
            top: 15px;
            right: 20px;

            background: rgba(0, 0, 0, 0.45);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;

            transition: background 0.25s ease, transform 0.25s ease;
        }

        .logout-button:hover {
            background: rgba(0, 0, 0, 0.65);
            transform: scale(1.05);
        }
        
    </style>
</head>

<body>

    <!-- ENCABEZADO -->
    <div class="header">
        <!-- BOTÓN DE CERRAR SESIÓN -->
        <a href="logout.php" class="logout-button">Cerrar sesión</a>

        <div class="user-role">Usuario</div>
    </div>

    <!-- TARJETAS DE JUEGOS -->
    <div class="games-section">

        <div class="game-card">
            <h2>Lógica</h2>
            <div class="image-wrapper">
                <img src="imagenes/logica.png" alt="Juego de lógica" onclick="location.href='juegos/logicajuego/logica.php'">>
            </div>
            <button class="btn-play" onclick="location.href='juegos/logicajuego/logica.php'">Jugar</button>
        </div>

        <div class="game-card">
            <h2>Memoria</h2>
            <div class="image-wrapper">
                <img src="imagenes/memoria.png" alt="Juego de memoria" onclick="location.href='juegos/memoriajuego/memoria.php'">
            </div>
            <button class="btn-play" onclick="location.href='juegos/memoriajuego/memoria.php'">Jugar</button>
        </div>

        <div class="game-card">
            <h2>Razonamiento</h2>
            <div class="image-wrapper">
                <img src="imagenes/razonamiento.png" alt="Juego de razonamiento" onclick="location.href='juegos/razonamientojuego/razonamiento.php'">>
            </div>
            <button class="btn-play" onclick="location.href='juegos/razonamientojuego/razonamiento.php'">Jugar</button>
        </div>

    </div>


</body>

</html>