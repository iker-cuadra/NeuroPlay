<?php 
// Asegúrate de incluir tu conexión y autenticación
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo permite acceso a profesionales
requireRole("profesional");

// --- Lógica para obtener todos los usuarios con rol 'familiar' ---
if (!isset($conexion) || !($conexion instanceof PDO)) {
    die("Error de configuración: La variable \$conexion no es un objeto PDO válido.");
}

try {
    // Incluimos el usuario asociado (u2) mediante LEFT JOIN (u1 = familiar)
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
    die("Error al cargar la lista de familiares: " . $e->getMessage());
}

// ----------------------------------------------------
// FOTO DE PERFIL (predeterminada por rol o subida por usuario)
// ----------------------------------------------------
function resolverRutaFotoPerfil(array $u): string {
    $rol  = strtolower(trim((string)($u['rol'] ?? 'usuario')));
    $foto = trim((string)($u['foto'] ?? ''));

    // Defaults por rol (en uploads/)
    $defaultPorRol = [
        'usuario'      => 'default_usuario.png',
        'familiar'     => 'default_familiar.png',
        'profesional'  => 'default_profesional.png',
    ];

    // Nombres que consideramos "default genérico" (no personalizado)
    $defaults = [
        '',
        'default.png',
        'default_usuario.png',
        'default_familiar.png',
        'default_profesional.png',
    ];

    // Seguridad: evitar rutas tipo ../
    $fotoSeguro = $foto !== '' ? basename($foto) : '';

    // Carpeta de uploads
    $uploadsDirFisico = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

    // Si hay foto personalizada y existe, usarla
    if ($fotoSeguro !== '' && !in_array($fotoSeguro, $defaults, true)) {
        if (file_exists($uploadsDirFisico . $fotoSeguro)) {
            return 'uploads/' . $fotoSeguro;
        }
        // Si no existe físicamente, caer al default por rol
    }

    // Si no hay foto o es default genérico, escoger por rol
    $defaultElegido = $defaultPorRol[$rol] ?? 'default.png';

    // Si el default por rol no existe, caer a default.png
    if (!file_exists($uploadsDirFisico . $defaultElegido)) {
        $defaultElegido = 'default.png';
    }

    return 'uploads/' . $defaultElegido;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Familiares - Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root{
            --header-h: 160px;
        }

        body {
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
    background: transparent; /* Quitamos el gris #887d7dff */
}
.canvas-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    z-index: -1;
    background: #e5e5e5;
    background-image:
        radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
        radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
        radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
        radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
        radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
    background-size: 200% 200%;
    animation: meshMove 12s infinite alternate ease-in-out;
}
@keyframes meshMove {
    0% { background-position: 0% 0%; }
    100% { background-position: 100% 100%; }
}

        /* HEADER (igual que el del primer archivo) */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('../frontend/imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        /* Flecha de volver (INTACTA: mismas propiedades/hover que tu código) */
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
            cursor: pointer;
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.75;
            transform: translateX(-2px);
        }

        /* Etiqueta inferior (igual que el primer archivo) */
        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* SECCIÓN DE TARJETAS – igual estilo que lista_profesionales */
        .main-section {
            max-width: 1200px;
            margin: 40px auto;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            padding: 0 20px;
        }

        .familiar-card {
    text-align: center;
    width: 260px;
    padding: 30px;
    border-radius: 20px;
    /* CAMBIO: Blanco semi-transparente con desenfoque */
    background: rgba(255, 255, 255, 0.85); 
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    cursor: pointer;
}

        .familiar-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border-color: #ddd;
        }

        .familiar-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid #7a7676;
        }

        .familiar-card h2 {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: #2c3e50;
        }

        .familiar-card p {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 10px;
        }

        .asociado {
            font-size: 0.85rem;
            color: #4a4a4a;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .asociado span {
            font-weight: 700;
            color: #2563eb;
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

        .no-data {
            text-align: center;
            width: 100%;
            margin-top: 50px;
            color: #999;
            font-size: 1.1rem;
        }

        @media (max-width: 600px) {
            .familiar-card {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="canvas-bg"></div> <div class="header">
        <a href="profesional.php" class="back-arrow" aria-label="Volver al panel del profesional">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>

        <div class="user-role">Familiares</div>
    </div>

    <div class="main-section">
        <?php if (count($familiares) > 0): ?>
            <?php foreach ($familiares as $familiar):
                // Ruta de la foto: predeterminada por rol o subida por usuario
                $ruta_foto = resolverRutaFotoPerfil($familiar);

                $nombre_asociado = trim((string)($familiar['usuario_asociado'] ?? ''));
                ?>
                <div class="familiar-card"
                    onclick="window.location='chat.php?destinatario_id=<?= htmlspecialchars($familiar['id']) ?>'">
                    <img src="<?= htmlspecialchars($ruta_foto) ?>" alt="Perfil de <?= htmlspecialchars($familiar['nombre']) ?>">

                    <h2><?= htmlspecialchars($familiar['nombre']) ?></h2>
                    <p><?= htmlspecialchars($familiar['email']) ?></p>

                    <div class="asociado">
                        Usuario asociado:
                        <span><?= $nombre_asociado !== '' ? htmlspecialchars($nombre_asociado) : 'Sin asignar' ?></span>
                    </div>

                    <div class="chat-indicator">
                        <i class="fas fa-comment-dots"></i> Iniciar Conversación
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-user-slash" style="font-size: 3rem; display: block; margin-bottom: 10px;"></i>
                No se encontraron familiares registrados.
            </div>
        <?php endif; ?>
    </div>
    </div>
    </div>

</body>

</html>
