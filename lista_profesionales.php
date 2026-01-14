<?php
// Asegúrate de incluir tu conexión y autenticación
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

// Obtener profesionales
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
            font-family: 'Poppins', sans-serif;
            background: #887d7dff;
        }

        /* HEADER igual que el del primer archivo */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        /* Flecha volver (igual que el primer archivo) */
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
        .back-arrow svg{ transition: opacity 0.2s ease-in-out; }
        .back-arrow:hover svg{ opacity: 0.75; }

        /* Etiqueta inferior (igual que el primer archivo) */
        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* SECCIÓN DE TARJETAS */
        .main-section {
            max-width: 1200px;
            margin: 40px auto;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            padding: 0 20px;
        }

        .profesional-card {
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

        .profesional-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border-color: #ddd;
        }

        .profesional-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid #7a7676;
        }

        .profesional-card h2 {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: #2c3e50;
        }

        .profesional-card p {
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
    </style>
</head>

<body>

    <div class="header">
        <!-- Flecha para volver al panel del familiar -->
        <a href="familiar.php" class="back-arrow" aria-label="Volver al panel del familiar">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>

        <div class="user-role">Profesionales</div>
    </div>

    <div class="main-section">
        <?php if (count($profesionales) > 0): ?>
            <?php foreach ($profesionales as $profesional):

                $ruta_foto = 'uploads/default.png';
                if ($profesional['rol'] === 'profesional' && $profesional['foto'] === 'default.png') {
                    $ruta_foto = 'imagenes/admin.jpg';
                } elseif (!empty($profesional['foto']) && $profesional['foto'] !== 'default.png') {
                    $ruta_foto = 'uploads/' . htmlspecialchars($profesional['foto']);
                }
                ?>
                <div class="profesional-card"
                    onclick="location.href='chat.php?destinatario_id=<?= htmlspecialchars($profesional['id']) ?>'">
                    <img src="<?= $ruta_foto ?>" alt="Perfil de <?= htmlspecialchars($profesional['nombre']) ?>">

                    <h2><?= htmlspecialchars($profesional['nombre']) ?></h2>
                    <p><?= htmlspecialchars($profesional['email']) ?></p>

                    <div class="chat-indicator">
                        <i class="fas fa-comment-dots"></i> Iniciar Conversación
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-user-slash" style="font-size: 3rem; display: block; margin-bottom: 10px;"></i>
                No se encontraron profesionales registrados.
            </div>
        <?php endif; ?>
    </div>

</body>

</html>
