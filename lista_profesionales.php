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

try {
    $stmt = $conexion->prepare("
        SELECT id, nombre, email, foto, rol
        FROM usuarios
        WHERE rol = 'profesional'
        ORDER BY nombre ASC
    ");
    $stmt->execute();
    $profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar la lista de profesionales: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profesionales - Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root{
            --header-h: 160px;
        }

        html, body{
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;
        }

        /* --- FONDO MESH (Optimizado para no dar lag) --- */
        .canvas-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1; 
            background: #e5e5e5;
            background-image:
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 150% 150%;
            animation: meshMove 12s infinite alternate ease-in-out;
            transform: translateZ(0); /* Aceleración por hardware */
        }

        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .back-arrow{
            position: absolute;
            top: 15px;
            left: 15px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .main-section {
            max-width: 1200px;
            margin: 40px auto;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            padding: 0 20px 40px;
        }

        /* --- ESTÉTICA ORIGINAL RECUPERADA --- */
        .profesional-card {
            text-align: center;
            width: 260px;
            padding: 30px;
            border-radius: 20px;
            background: #ffffff; /* Blanco original */
            border: 1px solid #eee;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            /* Optimización técnica interna */
            will-change: transform; 
            contain: layout;
        }

        .profesional-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .profesional-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid #7a7676;
        }

        /* --- SOLUCIÓN TEXTO LARGO --- */
        .profesional-card h2 {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: #2c3e50;
            /* Truncado */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            display: block;
        }

        .profesional-card p {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-indicator {
            color: #4CAF50;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="canvas-bg"></div>

    <div class="header">
        <a href="familiar.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>
        <div class="user-role">Profesionales</div>
    </div>

    <div class="main-section">
        <?php if (count($profesionales) > 0): ?>
            <?php foreach ($profesionales as $profesional):
                $ruta_foto = ($profesional['foto'] === 'default.png') ? 'imagenes/admin.jpg' : 'uploads/' . htmlspecialchars($profesional['foto']);
                ?>
                <div class="profesional-card" onclick="location.href='chat.php?destinatario_id=<?= $profesional['id'] ?>'">
                    <img src="<?= $ruta_foto ?>" alt="Perfil">
                    <h2><?= htmlspecialchars($profesional['nombre']) ?></h2>
                    <p><?= htmlspecialchars($profesional['email']) ?></p>
                    <div class="chat-indicator">
                        <i class="fas fa-comment-dots"></i> Iniciar Conversación
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>