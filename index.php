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
    $error = "SYSTEM_OVERLOAD: ACCESS_DENIED";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RAVE_CORE | Pere Bas</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@900&display=swap" rel="stylesheet">
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
 
    /* --- FONDO ESTROBOSCÓPICO DE COLORES DESLUMBRANTES --- */
    .strobe-bg {
        position: fixed;
        inset: 0;
        z-index: 0;
        /* Animación de cambio de color y destello ultra rápido */
        animation: strobeMaster 0.05s infinite;
    }
 
    @keyframes strobeMaster {
        0% { background: #ff0000; }
        20% { background: #00ff00; }
        40% { background: #0000ff; }
        60% { background: #ffff00; }
        80% { background: #ff00ff; }
        100% { background: #00ffff; }
    }
 
    /* Capa de ruido y distorsión para aumentar el efecto deslumbrante */
    .noise-overlay {
        position: fixed;
        inset: 0;
        z-index: 1;
        background: url('https://media.giphy.com/media/oEI9uWUicv9S/giphy.gif'); /* Ruido estático opcional */
        opacity: 0.2;
        pointer-events: none;
        mix-blend-mode: overlay;
    }
 
    /* --- TARJETA CENTRAL --- */
    .neon-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 450px;
        padding: 10px;
        background: #000;
        border-radius: 0; /* Estilo industrial cuadrado */
        box-shadow: 0 0 150px #fff;
        animation: cardVibrate 0.01s infinite;
    }
 
    @keyframes cardVibrate {
        0% { transform: translate(2px, -2px); }
        50% { transform: translate(-2px, 2px); }
        100% { transform: translate(0, 0); }
    }
 
    .login-card {
        background: #000;
        padding: 60px 40px;
        border: 5px solid #fff;
        text-align: center;
        position: relative;
    }
 
    /* TÍTULO QUE CAMBIA CON EL FONDO */
    .title {
        font-size: 45px;
        color: #fff;
        text-transform: uppercase;
        margin-bottom: 40px;
        letter-spacing: -2px;
        font-weight: 900;
        text-shadow: 0 0 20px #fff;
        animation: titleFlicker 0.1s infinite;
    }
 
    @keyframes titleFlicker {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); color: #ff0000; }
    }
 
    /* INPUTS RADICALES */
    input {
        width: 100%;
        padding: 20px;
        margin-bottom: 25px;
        background: #000;
        border: 4px solid #fff;
        color: #fff;
        font-family: 'Orbitron', sans-serif;
        font-size: 18px;
        text-transform: uppercase;
        outline: none;
    }
 
    input:focus {
        background: #fff;
        color: #000;
        box-shadow: 0 0 50px #fff;
    }
 
    /* BOTÓN EXPLOSIVO */
    button {
        width: 100%;
        padding: 25px;
        background: #fff;
        color: #000;
        border: none;
        font-family: 'Orbitron', sans-serif;
        font-size: 20px;
        font-weight: 900;
        cursor: pointer;
        transition: 0.05s;
        animation: buttonFlash 0.05s infinite;
    }
 
    @keyframes buttonFlash {
        0% { filter: invert(0); }
        100% { filter: invert(1); }
    }
 
    button:hover {
        transform: scale(1.1) rotate(2deg);
        background: #ff0000;
        color: #fff;
    }
 
    .error-box {
        background: #fff;
        color: #ff0000;
        padding: 15px;
        font-weight: 900;
        margin-bottom: 20px;
        border: 4px solid #ff0000;
    }
</style>
</head>
<body>
 
    <div class="strobe-bg"></div>
<div class="noise-overlay"></div>
 
    <div class="neon-container">
<div class="login-card">
<h1 class="title">PERE BAS</h1>
 
            <?php if ($error): ?>
<div class="error-box"><?= $error ?></div>
<?php endif; ?>
 
            <form method="POST">
<input type="email" name="email" placeholder="ID_USUARIO" required>
<input type="password" name="password" placeholder="PASS_CODE" required>
<button type="submit">ACCEDER_AHORA</button>
</form>
</div>
</div>
 
</body>
</html>