<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";
 
// Asegúrate de iniciar sesión si no está en auth.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
 
// Requerir autenticación básica para el chat
// requireLogin();
 
// Obtener el ID del destinatario
$destinatario_id = $_GET['destinatario_id'] ?? null;
$usuario_actual_id = $_SESSION['usuario_id'] ?? null;
 
if (!$destinatario_id || !$usuario_actual_id) {
    die("Destinatario no especificado o usuario no autenticado.");
}
 
// 0. LÓGICA DE ELIMINACIÓN DE MENSAJES
if (isset($_GET['eliminar_id'])) {
    $mensaje_id = (int)$_GET['eliminar_id'];
    $destinatario_id = (int)$destinatario_id;
 
    try {
        // Solo permitir que el remitente elimine su propio mensaje
        // y que el mensaje pertenezca a esta conversación
        $stmt_delete = $conexion->prepare("
            DELETE FROM mensajes
            WHERE id = ?
              AND remitente_id = ?
              AND (destinatario_id = ? OR remitente_id = ?)
        ");
        // Aseguramos que el remitente (usuario_actual_id) sea el que está eliminando
        $stmt_delete->execute([$mensaje_id, $usuario_actual_id, $destinatario_id, $destinatario_id]);
       
        // Redirigir de vuelta al chat
        header("Location: chat.php?destinatario_id=" . $destinatario_id);
        exit;
 
    } catch (PDOException $e) {
        // En un entorno de producción, registrar este error
    }
}
 
 
// 1. Obtener datos del destinatario para el título
$stmt_dest = $conexion->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$stmt_dest->execute([$destinatario_id]);
$destinatario = $stmt_dest->fetch(PDO::FETCH_ASSOC);
 
if (!$destinatario) {
    die("Destinatario no encontrado.");
}
$nombre_destinatario = htmlspecialchars($destinatario['nombre']);
$nombre_chat = "Chat con " . $nombre_destinatario;
 
 
// 2. Lógica para enviar mensajes
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mensaje'])) {
    $mensaje = trim($_POST['mensaje']);
 
    if (!empty($mensaje)) {
        try {
            $stmt_insert = $conexion->prepare("
                INSERT INTO mensajes (remitente_id, destinatario_id, mensaje, fecha)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt_insert->execute([$usuario_actual_id, $destinatario_id, $mensaje]);
            header("Location: chat.php?destinatario_id=" . $destinatario_id);
            exit;
        } catch (PDOException $e) {
            // Manejar error de inserción
        }
    }
}
 
// 3. Obtener historial de mensajes (IMPORTANTE: Seleccionar el ID del mensaje)
$stmt_mensajes = $conexion->prepare("
    SELECT
        m.id, m.mensaje, m.fecha, m.remitente_id, u.nombre AS remitente_nombre
    FROM mensajes m
    JOIN usuarios u ON m.remitente_id = u.id
    WHERE (m.remitente_id = ? AND m.destinatario_id = ?)
        OR (m.remitente_id = ? AND m.destinatario_id = ?)
    ORDER BY m.fecha ASC
");
$stmt_mensajes->execute([$usuario_actual_id, $destinatario_id, $destinatario_id, $usuario_actual_id]);
$mensajes = $stmt_mensajes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nombre_chat ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Estilos Generales y Reset */
        body {
            font-family: 'Poppins', sans-serif;
           background: #887d7dff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
 
        /* Contenedor Principal */
        .chat-container {
            width: 95%;
            max-width: 900px;
            height: 95vh;
            background: #ffffff;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.25);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
       
        /* Encabezado del Chat (Fijo y Moderno) */
        .chat-header {
            position: sticky;
            top: 0;
            background: #4a4a4a;
            color: white;
            padding: 15px 20px;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
       
        .chat-header h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0 20px;
            flex-grow: 1;
            text-align: center;
        }
 
        .back-button {
            color: white;
            font-size: 18px;
            text-decoration: none;
            padding: 0 10px 0 0;
            cursor: pointer;
            transition: opacity 0.2s;
        }
 
        .back-button:hover {
            opacity: 0.8;
        }
 
        /* Área de Mensajes */
        .messages-area {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f7f7f7;
        }
       
        /* Burbujas de Mensajes */
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 20px;
            line-height: 1.4;
            word-wrap: break-word;
            font-size: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
            position: relative; /* CLAVE para posicionar el botón de eliminar */
        }
 
        /* Botón de Eliminación (NUEVO) */
        .delete-btn {
            position: absolute;
            top: -10px;
            background: #f75555;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            z-index: 20;
            opacity: 0; /* Oculto por defecto */
            transition: opacity 0.2s, transform 0.2s;
        }
       
        /* Mensajes Enviados (Tú - a la derecha) */
        .sent {
            align-self: flex-end;
            background-color: #6d6dff;
            color: white;
            border-bottom-right-radius: 4px;
        }
 
        /* Mostrar botón en HOVER del mensaje enviado */
        .sent .delete-btn {
            right: -10px;
        }
 
        .sent:hover .delete-btn {
            opacity: 1;
            transform: scale(1);
        }
 
        /* Mensajes Recibidos (Otros - a la izquierda) */
        .received {
            align-self: flex-start;
            background-color: #ffffff;
            color: #1d1d1f;
            border-bottom-left-radius: 4px;
        }
       
        /* Ocultamos el botón en mensajes recibidos */
        .received .delete-btn {
            display: none;
        }
 
        .message-time {
            display: block;
            font-size: 11px;
            margin-top: 5px;
            text-align: right;
            opacity: 0.8;
            color: inherit;
        }
 
        .received .message-time {
            color: #4a4a4a;
        }
 
        /* Campo de Entrada (Footer fijo) */
        .input-area {
            position: sticky;
            bottom: 0;
            padding: 15px 20px;
            background: #ffffff;
            border-top: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
 
        .input-area input[type="text"] {
            flex-grow: 1;
            padding: 12px 18px;
            border-radius: 25px;
            border: 1px solid #dcdcdc;
            font-size: 16px;
            background: #f7f7f7;
            transition: border-color 0.2s;
        }
 
        .input-area input[type="text"]:focus {
            border-color: #6d6dff;
            outline: none;
            background: white;
        }
 
        .send-button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #6d6dff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
 
        .send-button:hover {
            background-color: #5a5ac7;
        }
       
        .send-button:active {
            transform: scale(0.95);
        }
 
        /* Media Query para móvil */
        @media (max-width: 600px) {
            .chat-container {
                width: 100%;
                height: 100vh;
                border-radius: 0;
                box-shadow: none;
            }
            .messages-area {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
 
<div class="chat-container">
 
    <div class="chat-header">
        <a href="#" onclick="goBack()" class="back-button"><i class="fas fa-arrow-left"></i></a>
        <h1><?= $nombre_chat ?></h1>
        <div style="width: 40px; height: 1px;"></div>
    </div>
 
    <div class="messages-area" id="messages-area">
        <?php if (empty($mensajes)): ?>
            <p style="text-align: center; color: #999; margin-top: 20px;">Comienza una conversación...</p>
        <?php endif; ?>
 
        <?php foreach ($mensajes as $m):
            $clase = ($m['remitente_id'] == $usuario_actual_id) ? 'sent' : 'received';
            $hora = date("H:i", strtotime($m['fecha']));
        ?>
            <div class="message <?= $clase ?>">
                <?php if ($clase === 'sent'): ?>
                    <a href="chat.php?destinatario_id=<?= $destinatario_id ?>&eliminar_id=<?= (int)$m['id'] ?>"
                       onclick="return confirm('¿Seguro que quieres eliminar este mensaje?');"
                       class="delete-btn"
                       title="Eliminar mensaje">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
                <?= htmlspecialchars($m['mensaje']) ?>
                <span class="message-time"><?= $hora ?></span>
            </div>
        <?php endforeach; ?>
    </div>
 
    <div class="input-area">
        <form method="POST" action="chat.php?destinatario_id=<?= $destinatario_id ?>" style="display: flex; width: 100%;">
            <input type="text" name="mensaje" placeholder="Escribe tu mensaje..." required autocomplete="off">
            <button type="submit" class="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
 
</div>
 
<script>
    // Función para volver a la página anterior en el historial del navegador
    function goBack() {
        window.history.back();
    }
 
    // Scroll al final al cargar
    const messagesArea = document.getElementById('messages-area');
    messagesArea.scrollTop = messagesArea.scrollHeight;
</script>
 
</body>
</html>