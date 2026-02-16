<?php
// Asegúrate de incluir tu conexión y autenticación
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo permite acceso a profesionales
requireRole("profesional");

// Evitar cache (opcional pero recomendable en paneles)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// --- Lógica para obtener todos los usuarios con rol 'familiar' ---
if (!isset($conexion) || !($conexion instanceof PDO)) {
    die("Error de configuración: La variable \$conexion no es un objeto PDO válido.");
}

try {
    $stmt = $conexion->prepare("
        SELECT
            u1.id, u1.nombre, u1.email, u1.foto, u1.rol,
            u2.nombre AS usuario_asociado
        FROM usuarios u1
        LEFT JOIN usuarios u2 ON u2.familiar_id = u1.id AND u2.rol = 'usuario'
        WHERE u1.rol = 'familiar'
        ORDER BY u1.nombre ASC
    ");
    $stmt->execute();
    $familiares = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error al cargar la lista de familiares: ' . $e->getMessage());
}

// ----------------------------------------------------
// FOTO DE PERFIL (predeterminada por rol o subida por usuario)
// ----------------------------------------------------
function resolverRutaFotoPerfil(array $u): string {
    $rol  = strtolower(trim((string)($u['rol'] ?? 'usuario')));
    $foto = trim((string)($u['foto'] ?? ''));

    $defaultPorRol = [
        'usuario'     => 'default_usuario.png',
        'familiar'    => 'default_familiar.png',
        'profesional' => 'default_profesional.png',
    ];

    $defaults = [
        '',
        'default.png',
        'default_usuario.png',
        'default_familiar.png',
        'default_profesional.png',
    ];

    $fotoSeguro = $foto !== '' ? basename($foto) : '';
    $uploadsDirFisico = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

    if ($fotoSeguro !== '' && !in_array($fotoSeguro, $defaults, true)) {
        if (file_exists($uploadsDirFisico . $fotoSeguro)) {
            return 'uploads/' . $fotoSeguro;
        }
    }

    $defaultElegido = $defaultPorRol[$rol] ?? 'default.png';
    if (!file_exists($uploadsDirFisico . $defaultElegido)) {
        $defaultElegido = 'default.png';
    }

    return 'uploads/' . $defaultElegido;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de Familiares - Chat</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root { --header-h: 160px; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;
        }

        /* --- FONDO MESH ANIMADO --- */
        .canvas-bg{
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
            0%   { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        .layout{
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER --- */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('../frontend/imagenes/fondo.svg'); /* ajusta si hace falta */
            background-size: cover;
            background-position: center;
            position: relative;
            flex: 0 0 auto;

            opacity: 0;
            transform: translateY(-30px);
            animation: headerSlideDown 0.8s ease forwards 0.2s;
        }
        @keyframes headerSlideDown { to { opacity: 1; transform: translateY(0); } }

        .center-title{
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: 700;
            font-size: 48px;
            text-transform: uppercase;
            letter-spacing: 3px;
            white-space: nowrap;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.6s;
            margin: 0;
            z-index: 10;
        }

        .user-role{
            position: absolute;
            bottom: 15px;
            left: 25px;
            color: white;
            font-weight: 700;
            font-size: 18px;
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.8s;
        }

        @keyframes fadeIn { to { opacity: 1; } }

        /* --- BOTÓN LOGOUT --- */
        .logout-button{
            position: absolute;
            top: 30px;
            right: 45px;
            display: inline-flex;
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
        .logout-button:hover{
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        /* --- FLECHA VOLVER (igual idea que tu back) --- */
        .back-arrow{
            position: absolute;
            top: 22px;
            left: 20px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            z-index: 120;
        }
        .back-arrow svg{
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        .back-arrow:hover svg{
            opacity: 0.75;
            transform: translateX(-2px);
        }

        /* --- MAIN --- */
        .main-section{
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 30px;
            flex-wrap: wrap;
            padding: 30px 20px 50px;
            opacity: 0;
            animation: fadeIn 0.8s ease forwards 0.8s;
        }

        /* --- TARJETA (estilo panel ejemplo) --- */
.familiar-card{
    text-align: center;
    width: 280px;
    padding: 28px;
    border-radius: 20px;

    /* NUEVO: fondo sólido blanco */
    background: #ffffff;

    /* borde suave */
    border: 1px solid rgba(0,0,0,0.08);

    /* sombra moderna */
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);

    transition: all 0.3s ease;
    cursor: pointer;

    color: #1f2937;
}

.familiar-card:hover{
    transform: translateY(-8px);
    box-shadow: 0 18px 40px rgba(0,0,0,0.18);
}

/* imagen */
.familiar-card img{
    width: 130px;
    height: 130px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 16px;
    border: 3px solid #e5e7eb;
}

/* nombre */
.familiar-card h2{
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

/* email */
.familiar-card .email{
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

/* usuario asociado */
.asociado{
    margin-top: 10px;
    font-size: 14px;
    color: #374151;
}

.asociado span{
    color: #2563eb;
    font-weight: 600;
}

/* botón chat */
.chat-indicator{
    margin-top: 16px;
    font-weight: 600;
    font-size: 14px;
    color: #16a34a;
}

/* línea azul */
.familiar-card::after{
    content:'';
    display:block;
    width:30px;
    height:3px;
    background:#3b82f6;
    margin:12px auto 0;
    border-radius:10px;
    transition:width .3s ease;
}

.familiar-card:hover::after{
    width:80px;
}


        .no-data{
            width: 100%;
            text-align: center;
            color: rgba(255,255,255,0.85);
            font-size: 18px;
            padding: 40px 10px;
        }
        .no-data i{
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            opacity: 0.9;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 900px){
            .center-title { font-size: 34px; }
            .logout-button { right: 18px; top: 15px; padding: 8px 15px; font-size: 14px; }
            .back-arrow { top: 14px; left: 14px; }
            .main-section { padding-top: 25px; }
            .familiar-card { width: 240px; }
            .familiar-card img { width: 120px; height: 120px; }
        }
        @media (max-width: 600px){
            .center-title { font-size: 26px; letter-spacing: 2px; }
            .familiar-card { width: 100%; max-width: 420px; }
        }
    </style>
</head>

<body>
<div class="canvas-bg"></div>

<div class="layout">
    <div class="header">
        <a href="profesional.php" class="back-arrow" aria-label="Volver al panel del profesional">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>

        <h1 class="center-title">Centro Pere Bas</h1>
        <div class="user-role">Lista de Familiares</div>

        <a href="logout.php" class="logout-button">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>

    <div class="main-section">
        <?php if (!empty($familiares)): ?>
            <?php foreach ($familiares as $familiar):
                $ruta_foto = resolverRutaFotoPerfil($familiar);
                $nombre_asociado = trim((string)($familiar['usuario_asociado'] ?? ''));
            ?>
                <div class="familiar-card"
                     onclick="window.location='chat.php?destinatario_id=<?= htmlspecialchars($familiar['id']) ?>'">
                    <img src="<?= htmlspecialchars($ruta_foto) ?>" alt="Perfil de <?= htmlspecialchars($familiar['nombre']) ?>">

                    <h2><?= htmlspecialchars($familiar['nombre']) ?></h2>
                    <div class="email"><?= htmlspecialchars($familiar['email']) ?></div>

                    <div class="asociado">
                        Usuario asociado:
                        <span><?= $nombre_asociado !== '' ? htmlspecialchars($nombre_asociado) : 'Sin asignar' ?></span>
                    </div>

                    <div class="chat-indicator">
                        <i class="fas fa-comment-dots"></i> Iniciar conversación
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-user-slash"></i>
                No se encontraron familiares registrados.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
