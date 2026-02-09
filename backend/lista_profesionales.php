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

// -------------------------
// FOTO POR DEFECTO SEGÚN ROL
// -------------------------
function foto_por_defecto_por_rol(string $rol): string {
    switch ($rol) {
        case "usuario": return "default_usuario.png";
        case "familiar": return "default_familiar.png";
        case "profesional": return "default_profesional.png";
        default: return "default.png";
    }
}

function foto_a_mostrar(?string $foto, string $rol): string {
    $foto = trim((string)$foto);

    // Si no hay foto guardada -> usar default por rol
    if ($foto === "") {
        return foto_por_defecto_por_rol($rol);
    }

    // Si la foto guardada es alguna "default", entonces mostrar la default del rol actual
    $defaults = [
        "default.png",
        "default_usuario.png",
        "default_familiar.png",
        "default_profesional.png"
    ];

    if (in_array($foto, $defaults, true)) {
        return foto_por_defecto_por_rol($rol);
    }

    // Si no es default, es foto manual -> se respeta
    return $foto;
}

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root{
            --header-h: 160px;
            --primary: #3b82f6;
            --text-dark: #1f2937;
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { 
            height: 100%;
            font-family: 'Poppins', sans-serif; 
            background-color: transparent;
        }

        /* FONDO DINÁMICO ANIMADO */
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
            background-size: 200% 200%;
            animation: meshMove 8s infinite alternate ease-in-out;
        }

        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        /* PARTÍCULAS FLOTANTES DECORATIVAS */
        .floating-particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: radial-gradient(circle, rgba(255,255,255,0.8), rgba(255,255,255,0));
            border-radius: 50%;
            animation: float linear infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        .particle:nth-child(1) {
            left: 10%;
            width: 8px;
            height: 8px;
            animation-duration: 15s;
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            left: 25%;
            width: 5px;
            height: 5px;
            animation-duration: 12s;
            animation-delay: 2s;
        }

        .particle:nth-child(3) {
            left: 40%;
            width: 10px;
            height: 10px;
            animation-duration: 18s;
            animation-delay: 4s;
        }

        .particle:nth-child(4) {
            left: 60%;
            width: 6px;
            height: 6px;
            animation-duration: 14s;
            animation-delay: 1s;
        }

        .particle:nth-child(5) {
            left: 75%;
            width: 9px;
            height: 9px;
            animation-duration: 16s;
            animation-delay: 3s;
        }

        .particle:nth-child(6) {
            left: 90%;
            width: 7px;
            height: 7px;
            animation-duration: 13s;
            animation-delay: 5s;
        }

        .layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        /* HEADER CON ANIMACIONES */
        .header {
            width: 100%;
            height: var(--header-h);
            background-image: url('../frontend/imagenes/fondo.svg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            position: relative;
            flex: 0 0 auto;
            opacity: 0;
            transform: translateY(-30px);
            animation: headerSlideDown 0.8s ease forwards 0.2s;
        }

        @keyframes headerSlideDown {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* TÍTULO CENTRADO */
        .center-title {
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
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.6s;
            margin: 0;
            z-index: 10;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        /* BOTÓN VOLVER MEJORADO */
        .back-arrow {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.4s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .back-arrow:hover { 
            transform: translateX(-8px) scale(1.15);
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .back-arrow:active {
            transform: translateX(-5px) scale(1.05);
        }

        .back-arrow svg {
            transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .back-arrow:hover svg {
            transform: translateX(-3px);
        }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.8s;
        }

        /* CONTENIDO */
        .page-content {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 16px;
            overflow-y: auto;
        }

        .panel { 
            width: min(1200px, 95vw); 
            background: transparent;
            border-radius: 24px; 
            padding: 20px; 
            margin-bottom: 30px;
        }

        /* HEADER DEL PANEL CON GLASSMORPHISM */
        .panel-header {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px 40px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            opacity: 0;
            animation: fadeInUp 0.8s ease forwards 0.4s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .panel-title { 
            font-size: 36px; 
            font-weight: 800; 
            margin-bottom: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            gap: 15px; 
            color: white;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
        }

        .panel-title i {
            font-size: 38px;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.3));
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            font-weight: 400;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* GRID DE PROFESIONALES */
        .profesionales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            padding: 10px;
        }

        /* TARJETA PROFESIONAL ESTILO CRISTAL */
        .profesional-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 30px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5),
                        inset 0 -1px 0 rgba(255, 255, 255, 0.1);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.4);
            overflow: hidden;
            opacity: 0;
            transform: scale(0.9) translateY(30px);
            animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .profesional-card:nth-child(1) { animation-delay: 0.5s; }
        .profesional-card:nth-child(2) { animation-delay: 0.6s; }
        .profesional-card:nth-child(3) { animation-delay: 0.7s; }
        .profesional-card:nth-child(4) { animation-delay: 0.8s; }
        .profesional-card:nth-child(5) { animation-delay: 0.9s; }
        .profesional-card:nth-child(6) { animation-delay: 1s; }

        @keyframes cardPop {
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .profesional-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5),
                        inset 0 -1px 0 rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* IMAGEN CON ANIMACIÓN */
        .prof-image-wrapper {
            position: relative;
            z-index: 1;
            margin-bottom: 25px;
        }

        .profesional-card img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.4s ease;
        }

        .profesional-card:hover img {
            transform: scale(1.04);
            box-shadow: 0 10px 28px rgba(59, 130, 246, 0.25);
            border-color: rgba(59, 130, 246, 0.5);
        }

        /* INFORMACIÓN DEL PROFESIONAL */
        .prof-info {
            text-align: center;
            margin-bottom: 25px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .prof-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: #3b82f6;
            background: rgba(239, 246, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 16px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Efecto de brillo en el badge */
        .prof-role::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: rotate(45deg);
            animation: badgeShine 3s ease-in-out infinite;
        }

        @keyframes badgeShine {
            0%, 100% { transform: translateX(-100%) rotate(45deg); }
            50% { transform: translateX(100%) rotate(45deg); }
        }

        .profesional-card:hover .prof-role {
            transform: scale(1.02);
            background: rgba(239, 246, 255, 0.7);
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .prof-role i {
            font-size: 0.8rem;
            animation: iconBounce 2s ease-in-out infinite;
        }

        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .profesional-card h2 {
            font-size: 1.4rem;
            margin: 0 0 12px;
            color: #1f2937;
            font-weight: 700;
            line-height: 1.3;
        }

        .prof-email {
            font-size: 0.85rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(249, 250, 251, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            margin: 0;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .prof-email i {
            color: #3b82f6;
            font-size: 0.9rem;
        }

        /* BOTÓN CON EFECTO CRISTAL */
        .chat-button {
            width: 100%;
            color: #3b82f6;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid rgba(59, 130, 246, 0.5);
            border-radius: 16px;
            margin-top: auto;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        .profesional-card:hover .chat-button {
            background: rgba(59, 130, 246, 0.85);
            color: white;
            border-color: rgba(59, 130, 246, 0.7);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.25),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
        }

        .chat-button i {
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .profesional-card:hover .chat-button i {
            transform: translateX(3px);
        }

        .chat-button span {
            position: relative;
            z-index: 2;
        }

        /* ESTADO VACÍO MEJORADO */
        .empty-state {
            width: 100%;
            text-align: center;
            padding: 100px 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .empty-state i {
            font-size: 50px;
            color: rgba(255, 255, 255, 0.8);
        }

        .empty-state h3 {
            font-size: 26px;
            color: white;
            margin-bottom: 12px;
            font-weight: 700;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.3);
        }

        .empty-state p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .center-title { font-size: 36px; }
            .panel-title { font-size: 30px; }
            .profesionales-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
            }
        }

        @media (max-width: 600px) {
            .center-title { font-size: 26px; letter-spacing: 2px; }
            .panel { padding: 15px; }
            .panel-header {
                padding: 25px 20px;
            }
            .panel-title { 
                font-size: 24px;
                flex-direction: column;
                gap: 10px;
            }
            .subtitle {
                font-size: 14px;
            }
            .profesionales-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .profesional-card {
                padding: 30px 25px;
            }
            .profesional-card img {
                width: 110px;
                height: 110px;
            }
        }
    </style>
</head>
<body>

    <div class="canvas-bg"></div>
    
    <!-- PARTÍCULAS FLOTANTES -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="layout">
        <div class="header">
            <h1 class="center-title">Centro Pere Bas</h1>
            <a href="familiar.php" class="back-arrow" aria-label="Volver">
                <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
                </svg>
            </a>
            <div class="user-role">Chat Profesional</div>
        </div>

        <div class="page-content">
            <div class="panel">
                <div class="panel-header">
                    <h1 class="panel-title">
                        <i class="fas fa-user-md"></i>
                        <span>Nuestros Profesionales</span>
                    </h1>
                    <p class="subtitle">Conecta directamente con el equipo especializado de nuestro centro</p>
                </div>

                <?php if (count($profesionales) > 0): ?>
                    <div class="profesionales-grid">
                        <?php foreach ($profesionales as $profesional): ?>
                            <div class="profesional-card" onclick="location.href='chat.php?destinatario_id=<?= $profesional['id'] ?>'">
                                
                                <div class="prof-image-wrapper">
                                    <img src="uploads/<?= htmlspecialchars(foto_a_mostrar($profesional['foto'] ?? '', $profesional['rol']), ENT_QUOTES) ?>" 
                                         alt="<?= htmlspecialchars($profesional['nombre']) ?>">
                                </div>
                                
                                <div class="prof-info">
                                    <span class="prof-role">
                                        <i class="fas fa-stethoscope"></i>
                                        Profesional
                                    </span>
                                    <h2><?= htmlspecialchars($profesional['nombre']) ?></h2>
                                    <p class="prof-email">
                                        <i class="fas fa-envelope"></i>
                                        <?= htmlspecialchars($profesional['email']) ?>
                                    </p>
                                </div>
                                
                                <div class="chat-button">
                                    <i class="fas fa-comments"></i>
                                    <span>Abrir Chat</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3>No hay profesionales disponibles</h3>
                        <p>En este momento no hay profesionales activos en el sistema. Por favor, inténtalo más tarde.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>