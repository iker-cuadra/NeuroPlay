<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Profesional</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
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

        .layout {
            height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* --- HEADER MEJORADO --- */
        .header {
            width: 100%;
            height: 160px;
            background-image: url('../frontend/imagenes/fondo.svg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            position: relative;
            opacity: 0;
            transform: translateY(-30px);
            animation: headerSlideDown 0.8s ease forwards 0.2s;
            display: block; /* Asegura que no se comporte como flex y mueva el título */
        }

        @keyframes headerSlideDown {
            to { opacity: 1; transform: translateY(0); }
        }

        /* TÍTULO CENTRAL CORREGIDO */
        .center-title {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); /* Centrado absoluto real */
            color: white !important;
            font-weight: 700;
            font-size: 48px; /* Tamaño grande solicitado */
            text-transform: uppercase;
            letter-spacing: 3px;
            white-space: nowrap;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.6s;
            margin: 0;
            z-index: 10;
        }

        .user-role {
            position: absolute;
            bottom: 15px;
            left: 25px;
            color: white;
            font-weight: 700;
            font-size: 18px;
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.8s;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        /* --- BOTÓN CERRAR SESIÓN --- */
        .logout-button {
            position: absolute;
            top: 30px;
            right: 45px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 14px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1.5px solid rgba(255,255,255,0.7);
            cursor: pointer;
            text-decoration: none;
            z-index: 100;
            backdrop-filter: blur(5px);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 1s;
            transition: all 0.3s ease;
        }

        .logout-button:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        /* --- SECCIÓN DE TARJETAS --- */
        .main-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            flex-wrap: wrap;
            padding: 20px;
        }

        .card {
            text-align: center;
            width: 280px;
            padding: 35px;
            border-radius: 25px;
            background: rgba(49, 49, 49, 0.4);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            color: #fff;
        }

        .card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
            background: rgba(60, 60, 60, 0.5);
        }

        .card img {
            width: 180px;
            height: 180px;
            border-radius: 20px;
            object-fit: contain; /* Cambiado a contain para asegurar que se vea el SVG entero */
            margin-bottom: 25px;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));
        }

        .card h2 { font-size: 28px; font-weight: 600; margin: 0; }
        .card-subtitle { margin-top: 6px; font-size: 15px; color: rgba(255,255,255,0.7); font-weight: 500; }

        .card-label::after {
            content: '';
            display: block;
            width: 30px;
            height: 3px;
            background: #3b82f6;
            margin: 12px auto 0;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .card:hover.card-label::after { width: 80px; }

        @media (max-width: 900px) {
            .center-title { font-size: 26px; }
            .main-section { flex-direction: column; gap: 30px; padding-top: 50px; }
            .card { width: 240px; }
            .logout-button { right: 20px; top: 15px; padding: 8px 15px; font-size: 14px; }
        }
    </style>
</head>

<body>

    <div class="canvas-bg"></div>

    <div class="layout">
        <div class="header">
            <h1 class="center-title">Centro Pere Bas</h1>
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
            </a>
            <div class="user-role">Panel del Profesional</div>
        </div>

        <div class="main-section">
            <div class="card card-label" onclick="location.href='gestionar_users.php'">
                <img src="../frontend/imagenes/users.svg" alt="Usuarios">
                <h2>Usuarios</h2>
                <p class="card-subtitle">Gestionar</p>
            </div>

            <div class="card card-label" onclick="location.href='lista_familiares.php'">
                <img src="../frontend/imagenes/family.svg" alt="Familiares">
                <h2>Familiares</h2>
                <p class="card-subtitle">Chatear</p>
            </div>
        </div>
    </div>

</body>
</html>