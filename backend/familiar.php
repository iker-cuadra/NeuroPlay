<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/conexion.php";
require_once "includes/auth.php";

requireRole("familiar");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$nombre = $_SESSION["nombre"] ?? "Familiar";
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
            width: 100%;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
        }

        /* --- FONDO MESH ANIMADO --- */
        .canvas-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
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

        .layout{
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER CON BANNER --- */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('../frontend/imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
            flex: 0 0 auto;
        }

        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* --- BOTÓN LOGOUT --- */
        .logout-button {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 16px;
            background: rgba(255,255,255,0.05);
            color: #fff;
            border: 1.5px solid rgba(255,255,255,0.7);
            cursor: pointer;
            text-decoration: none;
            z-index: 10;
            overflow: hidden;
            transition: 
                transform 0.25s cubic-bezier(.2,.8,.2,1),
                box-shadow 0.25s cubic-bezier(.2,.8,.2,1),
                background 0.3s ease,
                border-color 0.3s ease;
        }

        .logout-button::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                120deg,
                transparent 20%,
                rgba(255,255,255,0.25),
                transparent 80%
            );
            opacity: 0;
            transform: translateX(-60%);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        .logout-button:hover {
            background: rgba(255,255,255,0.12);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.35);
            border-color: #fff;
        }

        .logout-button:hover::after {
            opacity: 1;
            transform: translateX(60%);
        }

        /* --- MAIN --- */
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
            background: rgba(49, 49, 49, 0.35);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border: 1px solid rgba(12, 12, 12, 0.2);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            margin: 0;
            color: #fff;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        .card img {
            width: 200px;
            height: 200px;
            border-radius: 16px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .card-subtitle {
            margin-top: 4px;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }

        /* --- SUBRAYADO IGUAL AL PROFESIONAL --- */
        .card-label::after {
            content: '';
            display: block;
            width: 20px;
            height: 2px;
            background: #3b82f6;
            margin: 8px auto 0;
            transition: width 0.3s ease;
        }

        .card-label:hover::after {
            width: 60px;
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
            }
        }
    </style>
</head>

<body>

<div class="canvas-bg"></div>

<div class="layout">
    <div class="header">
        <div class="user-role">Panel del Familiar</div>
        <a href="logout.php" class="logout-button">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>

    <div class="main-section">
        <div class="card card-label" onclick="location.href='ver_progreso_familiar.php'">
            <img src="../frontend/imagenes/progreso2.png" alt="Progreso">
            <h2>Progreso</h2>
            <p class="card-subtitle">Ver evolución del usuario</p>
        </div>

        <div class="card card-label" onclick="location.href='lista_profesionales.php'">
            <img src="../frontend/imagenes/chat.svg" alt="Chat">
            <h2>Profesionales</h2>
            <p class="card-subtitle">Contactar con el centro</p>
        </div>
    </div>
</div>

</body>
</html>
