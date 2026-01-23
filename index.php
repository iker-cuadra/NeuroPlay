<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario["password_hash"])) {
        loginUser($usuario);
        switch ($usuario["rol"]) {
            case "profesional": header("Location: profesional.php"); break;
            case "usuario": header("Location: usuario.php"); break;
            case "familiar": header("Location: familiar.php"); break;
        }
        exit;
    }
    $error = "ACCESO DENEGADO";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OVERRIDE | Pere Bas</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;900&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Orbitron', sans-serif;
        background-color: #000;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    /* --- FONDO MESH HIPER-ACELERADO --- */
    .canvas-bg {
        position: fixed;
        inset: 0;
        z-index: 0;
        background: #000;
        background-image:
            radial-gradient(at 0% 0%, #ff00ff 0, transparent 40%),
            radial-gradient(at 50% 50%, #00ffff 0, transparent 40%),
            radial-gradient(at 100% 100%, #ff0000 0, transparent 40%);
        background-size: 150% 150%;
        animation: meshHyper 3s infinite alternate linear; 
        opacity: 0.6;
    }

    @keyframes meshHyper {
        0% { background-position: 0% 0%; filter: hue-rotate(0deg); }
        100% { background-position: 100% 100%; filter: hue-rotate(360deg); }
    }

    /* --- CONTENEDOR LED ULTRA-RÁPIDO --- */
    .led-card-container {
        position: relative;
        width: 100%;
        max-width: 420px;
        padding: 5px; /* Borde más grueso */
        background: #000;
        border-radius: 20px;
        overflow: hidden;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 0 100px rgba(0, 255, 255, 0.4);
    }

    .led-card-container::before {
        content: '';
        position: absolute;
        width: 200%;
        height: 200%;
        background: conic-gradient(
            #ff0000, #ff00ff, #00ffff, #00ff00, #ffff00, #ff0000
        );
        /* Rotación ultra rápida: 1s */
        animation: rotateLED 1s linear infinite;
    }

    @keyframes rotateLED {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Fondo de la tarjeta (Tapa el centro del gradiente) */
    .led-card-container::after {
        content: '';
        position: absolute;
        inset: 4px;
        background: rgba(0,0,0,0.9);
        border-radius: 18px;
        z-index: 1;
    }

    .login-card {
        position: relative;
        z-index: 2;
        width: 100%;
        padding: 40px;
        text-align: center;
    }

    /* --- TÍTULO CON GLITCH --- */
    .glitch {
        font-size: 36px;
        font-weight: 900;
        color: #fff;
        text-transform: uppercase;
        position: relative;
        animation: glitchAnim 0.2s infinite;
    }

    @keyframes glitchAnim {
        0% { transform: translate(0); text-shadow: 2px 2px #ff00ff; }
        25% { transform: translate(-2px, 2px); text-shadow: -2px -2px #00ffff; }
        50% { transform: translate(2px, -2px); text-shadow: 2px -2px #ff0000; }
        100% { transform: translate(0); }
    }

    .subtitle {
        color: #00ffff;
        font-size: 10px;
        letter-spacing: 5px;
        margin-bottom: 30px;
        display: block;
    }

    /* --- INPUTS --- */
    input {
        width: 100%;
        padding: 15px;
        margin-bottom: 15px;
        background: #111;
        border: 2px solid #333;
        color: #00ff00; /* Texto verde matrix */
        font-family: 'Orbitron', sans-serif;
        font-size: 14px;
        outline: none;
        transition: 0.1s;
    }

    input:focus {
        border-color: #00ffff;
        box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
        background: #000;
    }

    /* --- BOTÓN EPILEPTICO --- */
    button {
        width: 100%;
        padding: 15px;
        background: #fff;
        color: #000;
        border: none;
        font-weight: 900;
        cursor: pointer;
        text-transform: uppercase;
        animation: strobe 0.1s infinite;
    }

    @keyframes strobe {
        0% { background: #fff; color: #000; }
        50% { background: #00ffff; color: #fff; box-shadow: 0 0 30px #00ffff; }
        100% { background: #ff00ff; color: #fff; box-shadow: 0 0 30px #ff00ff; }
    }

    button:hover {
        animation: none;
        background: #00ff00;
        color: #000;
        transform: scale(1.1);
    }

    .error-msg {
        color: #ff0000;
        font-size: 12px;
        margin-bottom: 20px;
        border: 1px solid #ff0000;
        padding: 5px;
        animation: blink 0.2s infinite;
    }

    @keyframes blink {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }

</style>
</head>
<body>

<div class="canvas-bg"></div>

<div class="led-card-container">
    <div class="login-card">
        <h1 class="glitch" data-text="PERE BAS">PERE BAS</h1>
        <span class="subtitle">SYSTEM OVERRIDE</span>

        <?php if ($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="USER_ID" required>
            <input type="password" name="password" placeholder="ACCESS_KEY" required>
            <button type="submit">INICIAR_SEC_LOGIN</button>
        </form>
    </div>
</div>

</body>
</html>