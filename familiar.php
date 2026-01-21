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

$nombre = $_SESSION["nombre"] ?? 'Familiar';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Familiar - Centro Pere Bas</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root{
            --nav-height: 80px;
            --primary: #3b82f6;
            --text-dark: #1f2937;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;
        }

        /* --- FONDO MESH ANIMADO --- */
        .canvas-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
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

        .layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: var(--nav-height);
        }

        /* --- NAVBAR ESTILO ACTUALIZADO --- */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--nav-height);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            box-sizing: border-box;
            z-index: 1000;
        }

        .brand-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            gap: 15px; /* Ajustado para que quepa bien el botón de Inicio */
            align-items: center;
        }

        .nav-item {
            text-decoration: none;
            color: #4b5563;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            border-radius: 12px;
        }

        .nav-item:hover {
            color: var(--primary);
            background: rgba(59, 130, 246, 0.05);
        }

        /* Estilo para el botón activo (como en la imagen) */
        .nav-item.active {
            color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .role-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-logout {
            text-decoration: none;
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border: 1px solid #fecaca;
            border-radius: 12px;
            transition: all 0.2s;
            background: white;
        }

        .btn-logout:hover {
            background: #fef2f2;
            border-color: #dc2626;
        }

        /* --- SECCIÓN CENTRAL Y TARJETAS --- */
        .main-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            flex-wrap: wrap;
            padding: 40px;
        }

        .card {
            text-align: center;
            width: 260px;
            padding: 30px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.4); 
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            color: #1f2937;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.6); 
        }

        .card img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .card h2 { font-size: 22px; font-weight: 700; margin: 0; color: #111; }
        .card-subtitle { margin-top: 4px; font-size: 14px; color: #555; font-weight: 500; }

        @media (max-width: 1000px) {
            .navbar { padding: 0 20px; height: auto; flex-direction: column; padding-bottom: 20px; gap: 10px;}
            .brand-text { padding: 15px 0; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .layout { padding-top: 200px; }
        }
    </style>
</head>

<body>

    <div class="canvas-bg"></div>

    <nav class="navbar">
        <div class="brand">
            <div class="brand-text">Centro de día Pere Bas</div>
        </div>

        <div class="nav-links">
            <a href="ver_progreso_familiar.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> Mi Progreso
            </a>
            <a href="lista_profesionales.php" class="nav-item">
                <i class="fas fa-comments"></i> Chat Profesional
            </a>
        </div>

        <div class="user-actions">
            <span class="role-badge">Familiar</span>
            <a href="logout.php" class="btn-logout">
                Cerrar sesión
            </a>
        </div>
    </nav>

    <div class="layout">
        <div class="main-section">
            <div class="card" onclick="location.href='ver_progreso_familiar.php'">
                <img src="imagenes/progreso.svg" alt="Progreso">
                <h2>Progreso</h2>
                <p class="card-subtitle">Ver evolución del usuario</p>
            </div>

            <div class="card" onclick="location.href='lista_profesionales.php'">
                <img src="imagenes/chat.svg" alt="Profesionales">
                <h2>Profesionales</h2>
                <p class="card-subtitle">Contactar con el centro</p>
            </div>
        </div>
    </div>

</body>
</html>