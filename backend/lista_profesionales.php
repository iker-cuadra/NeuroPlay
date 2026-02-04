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

        /* BOTÓN VOLVER */
        .back-arrow {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.4s;
        }
        .back-arrow:hover { transform: scale(1.2) translateX(-3px); }

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
            padding: 30px 16px;
            overflow-y: auto;
        }

        .panel { 
            width: min(1150px, 95vw); 
            background: transparent;
            border-radius: 24px; 
            padding: 35px; 
            box-shadow: none;
            backdrop-filter: none;
            margin-bottom: 30px;
        }

        .panel-title { 
            font-size: 26px; 
            font-weight: 800; 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: white;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2); 
            padding-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .subtitle {
            text-align: center;
            color: white;
            font-size: 15px;
            margin-bottom: 30px;
            font-weight: 400;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* CONTENEDOR DE PROFESIONALES CON SCROLL LATERAL */
        .profesionales-wrapper {
            position: relative;
            width: 100%;
            max-width: 1000px;
            margin: 30px auto 0;
            padding: 0 90px;
        }

        .profesionales-scroll {
            display: flex;
            gap: 30px;
            overflow: hidden;
            scroll-behavior: smooth;
            padding: 20px 10px 30px;
            scroll-snap-type: x mandatory;
            justify-content: flex-start;
            width: 960px;
        }

        .profesionales-scroll::-webkit-scrollbar {
            display: none;
        }

        /* FLECHAS DE NAVEGACIÓN MEJORADAS */
        .scroll-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 55px;
            height: 55px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            border: 3px solid rgba(59, 130, 246, 0.2);
            backdrop-filter: blur(10px);
        }

        .scroll-arrow:hover {
            background: #3b82f6;
            transform: translateY(-50%) scale(1.15);
            box-shadow: 0 15px 40px rgba(59, 130, 246, 0.4);
            border-color: #3b82f6;
        }

        .scroll-arrow:hover i {
            color: white;
            transform: scale(1.2);
        }

        .scroll-arrow:active {
            transform: translateY(-50%) scale(1.05);
        }

        .scroll-arrow i {
            font-size: 22px;
            color: #3b82f6;
            transition: all 0.3s ease;
        }

        .scroll-arrow.left {
            left: -80px;
        }

        .scroll-arrow.right {
            right: -80px;
        }

        .scroll-arrow.disabled {
            opacity: 0.2;
            cursor: not-allowed;
            pointer-events: none;
            transform: translateY(-50%) scale(0.9);
        }

        /* TARJETAS PROFESIONALES SIN HOVER */
        .profesional-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 30px 30px;
            border-radius: 24px;
            background: white;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            border: 2px solid transparent;
            width: 300px;
            min-width: 300px;
            flex-shrink: 0;
            scroll-snap-align: start;
            opacity: 0;
            transform: translateX(-50px) translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .profesional-card.visible {
            opacity: 1;
            transform: translateX(0) translateY(0);
        }

        .profesional-card:nth-child(1).visible { transition-delay: 0.1s; }
        .profesional-card:nth-child(2).visible { transition-delay: 0.2s; }
        .profesional-card:nth-child(3).visible { transition-delay: 0.3s; }
        .profesional-card:nth-child(4).visible { transition-delay: 0.1s; }
        .profesional-card:nth-child(5).visible { transition-delay: 0.2s; }
        .profesional-card:nth-child(6).visible { transition-delay: 0.3s; }

        /* Solo efecto al hacer clic */
        .profesional-card:active {
            transform: scale(0.98);
        }

        /* Imagen del profesional */
        .profesional-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f3f4f6;
            margin-bottom: 20px;
        }

        /* Información del profesional */
        .prof-info {
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
        }

        .profesional-card h2 {
            font-size: 1.3rem;
            margin: 0 0 8px;
            color: #1f2937;
            font-weight: 600;
        }

        .prof-role {
            display: inline-block;
            font-size: 0.75rem;
            color: #3b82f6;
            background: #eff6ff;
            padding: 5px 14px;
            border-radius: 12px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .profesional-card p {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .profesional-card p i {
            font-size: 0.8rem;
        }

        /* Botón de acción */
        .chat-indicator {
            width: 100%;
            color: white;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 14px;
            margin-top: auto;
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        .chat-indicator i {
            font-size: 16px;
        }

        /* Estado vacío */
        .empty-state {
            width: 100%;
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 70px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: white;
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .empty-state p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .center-title { font-size: 36px; }
            .profesionales-wrapper {
                padding: 0 50px;
            }
            .scroll-arrow {
                width: 45px;
                height: 45px;
            }
            .profesional-card {
                min-width: 280px;
            }
        }

        @media (max-width: 600px) {
            .center-title { font-size: 26px; letter-spacing: 2px; }
            .panel { padding: 25px 20px; }
            .profesionales-wrapper {
                padding: 0 45px;
            }
            .scroll-arrow {
                width: 40px;
                height: 40px;
            }
            .scroll-arrow i {
                font-size: 16px;
            }
            .profesional-card {
                min-width: 260px;
            }
            .profesional-card img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>

    <div class="canvas-bg"></div>

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
                <h1 class="panel-title">
                    <i class="fas fa-user-md"></i> Nuestros Profesionales
                </h1>
                <p class="subtitle">Contacta directamente con los profesionales de nuestro centro</p>

                <div class="profesionales-wrapper">
                    <?php if (count($profesionales) > 0): ?>
                        <div class="scroll-arrow left" onclick="scrollProfesionales(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        
                        <div class="profesionales-scroll" id="profesionales-scroll">
                            <?php foreach ($profesionales as $profesional): ?>
                                <div class="profesional-card" onclick="location.href='chat.php?destinatario_id=<?= $profesional['id'] ?>'">
                                    <img src="uploads/<?= htmlspecialchars(foto_a_mostrar($profesional['foto'] ?? '', $profesional['rol']), ENT_QUOTES) ?>" 
                                         alt="<?= htmlspecialchars($profesional['nombre']) ?>">
                                    
                                    <div class="prof-info">
                                        <span class="prof-role">Profesional</span>
                                        <h2><?= htmlspecialchars($profesional['nombre']) ?></h2>
                                        <p><i class="fas fa-envelope"></i><?= htmlspecialchars($profesional['email']) ?></p>
                                    </div>
                                    <div class="chat-indicator">
                                        <i class="fas fa-paper-plane"></i>
                                        <span>Enviar Mensaje</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="scroll-arrow right" onclick="scrollProfesionales(1)">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-clock"></i>
                            <h3>No hay profesionales disponibles</h3>
                            <p>En este momento no hay profesionales activos en el sistema</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 0;
        const cardsPerPage = 3;
        
        function scrollProfesionales(direction) {
            const container = document.getElementById('profesionales-scroll');
            const cards = container.querySelectorAll('.profesional-card');
            const totalCards = cards.length;
            const maxPages = Math.ceil(totalCards / cardsPerPage);
            
            // Calcular nueva página
            currentPage += direction;
            if (currentPage < 0) currentPage = 0;
            if (currentPage >= maxPages) currentPage = maxPages - 1;
            
            // Calcular scroll position (ancho de tarjeta + gap) * cards por página
            const cardWidth = 300;
            const gap = 30;
            const scrollAmount = (cardWidth + gap) * cardsPerPage * currentPage;
            
            // Hacer scroll suave
            container.scrollTo({
                left: scrollAmount,
                behavior: 'smooth'
            });
            
            // Animar tarjetas visibles
            setTimeout(() => {
                animateVisibleCards();
            }, 100);
            
            // Actualizar flechas
            setTimeout(updateArrows, 400);
        }
        
        function animateVisibleCards() {
            const container = document.getElementById('profesionales-scroll');
            const cards = container.querySelectorAll('.profesional-card');
            
            // Remover clase visible de todas
            cards.forEach(card => card.classList.remove('visible'));
            
            // Agregar clase visible a las que están en la página actual
            const startIndex = currentPage * cardsPerPage;
            const endIndex = Math.min(startIndex + cardsPerPage, cards.length);
            
            for (let i = startIndex; i < endIndex; i++) {
                setTimeout(() => {
                    cards[i].classList.add('visible');
                }, (i - startIndex) * 100);
            }
        }
        
        function updateArrows() {
            const container = document.getElementById('profesionales-scroll');
            const leftArrow = document.querySelector('.scroll-arrow.left');
            const rightArrow = document.querySelector('.scroll-arrow.right');
            
            if (!container || !leftArrow || !rightArrow) return;
            
            const cards = container.querySelectorAll('.profesional-card');
            const totalCards = cards.length;
            const maxPages = Math.ceil(totalCards / cardsPerPage);
            
            // Deshabilitar flecha izquierda si está en la primera página
            if (currentPage <= 0) {
                leftArrow.classList.add('disabled');
            } else {
                leftArrow.classList.remove('disabled');
            }
            
            // Deshabilitar flecha derecha si está en la última página
            if (currentPage >= maxPages - 1) {
                rightArrow.classList.add('disabled');
            } else {
                rightArrow.classList.remove('disabled');
            }
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', () => {
            currentPage = 0;
            animateVisibleCards();
            updateArrows();
        });
    </script>
</body>
</html>