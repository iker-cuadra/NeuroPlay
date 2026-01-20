<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";

requireRole("usuario");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$nombre = $_SESSION["nombre"];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Usuario</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            background: transparent;
        }

        /* --- FONDO MESH ANIMADO (CLARO ORIGINAL) --- */
        .canvas-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            background: #e5e5e5;
            background-image:
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 200% 200%;
            animation: meshMove 8s infinite alternate ease-in-out;
        }

        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        .header {
            width: 100%;
            height: 160px;
            background-image: url('imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* --- BASE BOTONES PREMIUM --- */
        .btn-premium {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            position: relative;
            overflow: hidden;
            transition: all .25s cubic-bezier(.2,.8,.2,1);
            box-sizing: border-box; /* Evita que se salga */
        }

        .btn-premium::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 20%, rgba(255,255,255,0.2), transparent 80%);
            opacity: 0;
            transform: translateX(-100%);
            transition: opacity .35s ease, transform .45s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.3);
        }

        .btn-premium:hover::after {
            opacity: 1;
            transform: translateX(100%);
        }

        /* Botón Cerrar Sesión (Transparente/Claro sobre Banner) */
        .logout-button {
            position: absolute;
            top: 30px;
            right: 45px;
            padding: 10px 20px;
            font-size: 16px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(255, 255, 255, 0.7);
            z-index: 100;
        }

        /* BOTÓN JUGAR (ESTILO OSCURO) */
        .btn-play {
            width: 100%; /* Ocupa el ancho de la tarjeta */
            padding: 12px 0;
            font-size: 16px;
            color: #ffffff;
            background: #1a1a1a; /* FONDO OSCURO */
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 15px;
        }

        .btn-play:hover {
            background: #000000;
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* SECCIÓN JUEGOS */
        .games-section {
            height: calc(100vh - 160px);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 35px;
            flex-wrap: wrap;
            padding: 20px;
            overflow-y: auto;
        }

        .game-card {
            width: 320px;
            text-align: center;
            padding: 22px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.4);
            box-sizing: border-box; /* Importante para que el botón no se salga */
        }

        .game-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .image-wrapper {
            width: 210px;
            height: 210px;
            margin: 0 auto 18px auto;
            overflow: hidden;
            border-radius: 22px;
            background: #fff;
        }

        .game-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .game-card:hover img {
            transform: scale(1.08);
        }

        .game-card h2 {
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 24px;
            color: #222;
        }

        @media (max-width: 900px) {
            .games-section { align-items: flex-start; padding-top: 40px; }
            .game-card { width: 100%; max-width: 280px; }
            .logout-button { right: 20px; top: 15px; }
        }
    </style>
</head>

<body>
    <div class="canvas-bg"></div>

    <div class="header">
        <a href="logout.php" class="logout-button btn-premium">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
        <div class="user-role">Panel del Usuario</div>
    </div>

    <div class="games-section">
        <div class="game-card" onclick="location.href='juegos/logicajuego/logica.php'">
            <h2>Lógica</h2>
            <div class="image-wrapper">
                <img src="imagenes/logica.png" alt="Lógica">
            </div>
            <div class="btn-play btn-premium">Jugar</div>
        </div>

        <div class="game-card" onclick="location.href='juegos/memoriajuego/memoria.php'">
            <h2>Memoria</h2>
            <div class="image-wrapper">
                <img src="imagenes/memoria.png" alt="Memoria">
            </div>
            <div class="btn-play btn-premium">Jugar</div>
        </div>

        <div class="game-card" onclick="location.href='juegos/razonamientojuego/razonamiento.php'">
            <h2>Razonamiento</h2>
            <div class="image-wrapper">
                <img src="imagenes/razonamiento.png" alt="Razonamiento">
            </div>
            <div class="btn-play btn-premium">Jugar</div>
        </div>

        <div class="game-card" onclick="location.href='juegos/atencionjuego/atencion.php'">
            <h2>Atención</h2>
            <div class="image-wrapper">
                <img src="imagenes/atencion.jpg" alt="Atención">
            </div>
            <div class="btn-play btn-premium">Jugar</div>
        </div>
    </div>
</body>
</html>