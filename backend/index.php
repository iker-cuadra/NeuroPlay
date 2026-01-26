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
    $error = "E-mail o contraseña incorrectos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Centro Pere Bas - Acceso</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
 
html, body {
    height: 100%;
    font-family: 'Poppins', sans-serif;
    background-color: #000;
    overflow: hidden;
}
 
/* --- FONDO MESH ANIMADO --- */
.canvas-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 0;
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
 
.login-wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    width: 100vw;
    height: 100vh;
}
 
.login-left {
    flex: 0 0 50%;
    background: url('imagenes/imglogin.svg') no-repeat center center;
    background-size: cover;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}
 
.login-right {
    flex: 0 0 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}
 
/* --- LOGIN CARD CON TRANSPARENCIA GLASS --- */
.login-card {
    width: 100%;
    max-width: 440px;
    padding: 50px 40px;
    border-radius: 40px;
    background: rgba(0, 0, 0, 0.35); /* Oscurecido */
    backdrop-filter: blur(25px) saturate(180%);
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 30px 60px rgba(0,0,0,0.25);
    text-align: center;
    color: #fff;
}
 
 
.login-card h1 {
    font-size: 30px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}
 
.subtitle {
    color: rgba(255,255,255,0.7);
    margin-bottom: 40px;
    font-size: 14px;
}
 
/* --- INPUTS TRANSPARENTES --- */
input {
    width: 100%;
    padding: 16px 20px;
    margin-bottom: 18px;
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,0.3);
    background: rgba(255, 255, 255, 0.15);
    font-size: 16px;
    color: #fff;
    outline: none;
    transition: all 0.3s ease;
}
 
input::placeholder {
    color: rgba(255,255,255,0.6);
}
 
input:focus {
    background: rgba(255, 255, 255, 0.25);
    border-color: #ffffff;
    box-shadow: 0 0 0 4px rgba(255,255,255,0.2);
}
 
/* --- BOTÓN TRANSPARENTE PREMIUM --- */
button {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px 24px;
    border-radius: 16px; /* MISMO RADIO QUE EL OTRO BOTÓN */
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    background: rgba(255,255,255,0.05);
    border: 1.5px solid rgba(255,255,255,0.7);
    cursor: pointer;
    overflow: hidden;
    transition:
        transform 0.25s cubic-bezier(.2,.8,.2,1),
        box-shadow 0.25s cubic-bezier(.2,.8,.2,1),
        background 0.3s ease,
        border-color 0.3s ease;
}
 
button::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        120deg,
        transparent 20%,
        rgba(255,255,255,0.25),
        transparent 80%
    );
    opacity: 0;
    transform: translateX(-60%);
    transition: opacity 0.35s ease, transform 0.35s ease;
}
 
button:hover {
    background: rgba(255,255,255,0.12);
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.35);
    border-color: #fff;
}
 
button:hover::after {
    opacity: 1;
    transform: translateX(60%);
}
 
button:active {
    transform: scale(0.97);
}
 
/* --- MENSAJE DE ERROR --- */
.error-msg {
    color: #ff6b6b;
    background: rgba(255,107,107,0.1);
    padding: 12px;
    border-radius: 14px;
    margin-bottom: 25px;
    font-size: 14px;
    border: 1px solid rgba(255,107,107,0.3);
}
 
/* RESPONSIVO */
@media (max-width: 992px) {
    .login-wrapper { flex-direction: column; }
    .login-left { flex: 0 0 35%; width: 100%; }
    .login-right { flex: 0 0 65%; width: 100%; }
}
</style>
</head>
<body>
 
<div class="canvas-bg"></div>
 
<div class="login-wrapper">
    <div class="login-left"></div>
 
    <div class="login-right">
        <div class="login-card">
            <h1>Centro Pere Bas</h1>
            <p class="subtitle">Bienvenido</p>
 
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
 
            <form method="POST">
                <input type="email" name="email" placeholder="Correo electrónico" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Iniciar Sesión</button>
            </form>
        </div>
    </div>
</div>
 
</body>
</html>