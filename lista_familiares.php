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
    // Consulta para seleccionar la información de los familiares
    $stmt = $conexion->prepare("
        SELECT id, nombre, email, foto
        FROM usuarios 
        WHERE rol = 'familiar'
        ORDER BY nombre ASC
    ");
    $stmt->execute();
    $familiares = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar la lista de familiares: " . $e->getMessage());
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
            background: #887d7dff;
        }

        /* HEADER (igual que el del primer archivo) */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('imagenes/Banner.svg');
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
            background: #fff;
            border: 1px solid #eee;
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
            margin-bottom: 20px;
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

    <div class="header">
        <!-- Flecha para volver al panel del profesional (INTACTA) -->
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
                // Ruta de la foto: default o subida
                $ruta_foto = (empty($familiar['foto']) || $familiar['foto'] === 'default.png')
                    ? 'imagenes/avatar_default.png'
                    : 'uploads/' . htmlspecialchars($familiar['foto']);
                ?>
                <div class="familiar-card"
                    onclick="window.location='chat.php?destinatario_id=<?= htmlspecialchars($familiar['id']) ?>'">
                    <img src="<?= $ruta_foto ?>" alt="Perfil de <?= htmlspecialchars($familiar['nombre']) ?>">

                    <h2><?= htmlspecialchars($familiar['nombre']) ?></h2>
                    <p><?= htmlspecialchars($familiar['email']) ?></p>

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

</body>

</html>
