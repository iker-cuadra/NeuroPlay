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

$nombre_usuario = $_SESSION["nombre"] ?? 'Familiar';

// Obtener lista de profesionales
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
    <title>Profesionales - Centro Pere Bas</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root{
            --nav-height: 80px;
            --primary: #3b82f6;
            --text-dark: #1f2937;
        }

        html, body{
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
        }

        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        /* --- NAVBAR ESTILO ACTUALIZADO --- */
        .navbar {
            position: fixed;
            top: 0; left: 0; width: 100%;
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
        }

        .nav-links {
            display: flex;
            gap: 15px;
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

        /* Clase activa para Chat Profesional */
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
            background: white;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #fef2f2;
            border-color: #dc2626;
        }

        /* --- CONTENIDO PRINCIPAL --- */
        .main-container {
            padding-top: calc(var(--nav-height) + 40px);
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 60px;
        }

        .section-title {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 24px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .profesionales-grid {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            padding: 0 20px;
        }

        /* --- TARJETAS PROFESIONALES --- */
        .profesional-card {
            text-align: center;
            width: 260px;
            padding: 35px 25px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .profesional-card:hover {
            transform: translateY(-12px);
            background: #ffffff;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .profesional-card img {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .profesional-card h2 {
            font-size: 1.15rem;
            margin: 0 0 8px;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profesional-card p {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-indicator {
            color: #10b981;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: rgba(16, 185, 129, 0.08);
            border-radius: 10px;
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .navbar { padding: 0 20px; height: auto; flex-direction: column; padding-bottom: 20px; gap: 10px;}
            .main-container { padding-top: 220px; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
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
            <a href="familiar.php" class="nav-item">
                <i class="fas fa-home"></i> Inicio
            </a>
            <a href="ver_progreso_familiar.php" class="nav-item">
                <i class="fas fa-chart-line"></i> Mi Progreso
            </a>
            <a href="lista_profesionales.php" class="nav-item active">
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

    <div class="main-container">
        <h1 class="section-title">Contacta con nuestros profesionales</h1>

        <div class="profesionales-grid">
            <?php if (count($profesionales) > 0): ?>
                <?php foreach ($profesionales as $profesional):
                    $ruta_foto = ($profesional['foto'] === 'default.png') ? '../frontend/imagenes/admin.jpg' : 'uploads/' . htmlspecialchars($profesional['foto']);
                    ?>
                    <div class="profesional-card" onclick="location.href='chat.php?destinatario_id=<?= $profesional['id'] ?>'">
                        <img src="<?= $ruta_foto ?>" alt="Perfil">
                        <h2><?= htmlspecialchars($profesional['nombre']) ?></h2>
                        <p><?= htmlspecialchars($profesional['email']) ?></p>
                        <div class="chat-indicator">
                            <i class="fas fa-comment-dots"></i> Iniciar Chat
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: white; text-align: center;">No hay profesionales disponibles en este momento.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>