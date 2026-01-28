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
* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}
 
html, body {
    height: 100%;
    font-family: 'Poppins', sans-serif;
    overflow: hidden;
}
 
/* --- FONDO BLUR --- */
.login-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('../frontend/imagenes/fondo.svg') no-repeat center center;
    background-size: cover;
    filter: blur(8px);
    -webkit-filter: blur(8px);
    transform: scale(1.1);
}

/* Capa overlay */
.login-background::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
}
 
/* --- CONTENEDOR PRINCIPAL --- */
.login-container {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 40px 20px;
}
 
/* --- LOGO Y TÍTULO CON ANIMACIÓN --- */
.login-header {
    text-align: center;
    margin-bottom: 30px;
}

.logo-circle {
    width: 84px;
    height: 84px;
    margin: 0 auto 20px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
    opacity: 0;
    transform: scale(0.5) rotate(-180deg);
    animation: logoAppear 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards 0.2s;
}

@keyframes logoAppear {
    to {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

.logo-circle h2 {
    font-size: 32px;
    font-weight: 700;
    color: #2271b1;
    margin: 0;
}

.login-header h1 {
    font-size: 24px;
    font-weight: 400;
    color: #fff;
    margin: 0;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
    opacity: 0;
    animation: fadeInTitle 0.8s ease forwards 0.6s;
}

@keyframes fadeInTitle {
    to {
        opacity: 1;
    }
}
 
/* --- LOGIN CARD CON ANIMACIÓN --- */
.login-card {
    width: 100%;
    max-width: 420px;
    padding: 26px 24px 36px;
    background: rgba(50, 50, 50, 0.75);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    opacity: 0;
    transform: translateY(30px);
    animation: cardSlideUp 0.6s ease forwards 0.8s;
}

@keyframes cardSlideUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
 
/* --- INPUTS --- */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #ffffff;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    color: #2c3338;
    outline: none;
    transition: all 0.2s ease;
    font-family: 'Poppins', sans-serif;
}

input[type="email"]:focus,
input[type="password"]:focus {
    border-color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
}

input[type="email"]::placeholder,
input[type="password"]::placeholder {
    color: #a7aaad;
}
 
/* --- CHECKBOX REMEMBER ME --- */
.remember-me {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
}

.remember-me input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    cursor: pointer;
}

.remember-me label {
    color: #ffffff;
    font-size: 14px;
    cursor: pointer;
    user-select: none;
}
 
/* --- BOTÓN ESTILO NEUROPLAY --- */
button[type="submit"] {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    border-radius: 16px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 16px;
    color: #ffffff;
    background: rgba(34, 113, 177, 0.15);
    border: 1.5px solid rgba(34, 113, 177, 0.9);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    transition: 
        transform .25s cubic-bezier(.2,.8,.2,1), 
        box-shadow .25s cubic-bezier(.2,.8,.2,1), 
        background .25s ease, 
        border-color .25s ease;
    overflow: hidden;
}

/* Efecto luz sutil */
button[type="submit"]::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        120deg,
        transparent 20%,
        rgba(255,255,255,0.3),
        transparent 80%
    );
    opacity: 0;
    transform: translateX(-60%);
    transition: opacity .35s ease, transform .35s ease;
}

/* Hover PRO */
button[type="submit"]:hover {
    background: rgba(34, 113, 177, 0.25);
    transform: translateY(-1px);
    box-shadow: 0 12px 30px rgba(34, 113, 177, 0.4);
    border-color: #2271b1;
}

button[type="submit"]:hover::after {
    opacity: 1;
    transform: translateX(60%);
}

/* Click */
button[type="submit"]:active {
    transform: scale(0.97);
}
 
/* --- MENSAJE DE ERROR CON ANIMACIÓN --- */
.error-msg {
    color: #d63638;
    background: #fcf0f1;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    border-left: 4px solid #d63638;
    animation: shakeError 0.5s ease;
}

@keyframes shakeError {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

/* RESPONSIVO */
@media (max-width: 768px) {
    .login-card {
        padding: 20px 18px 30px;
    }
    
    .logo-circle {
        width: 72px;
        height: 72px;
    }
    
    .logo-circle h2 {
        font-size: 28px;
    }
    
    .login-header h1 {
        font-size: 20px;
    }
    
    button[type="submit"] {
        padding: 12px 20px;
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .login-card {
        padding: 18px 16px 26px;
    }
    
    .logo-circle {
        width: 64px;
        height: 64px;
    }
    
    .logo-circle h2 {
        font-size: 24px;
    }
    
    .login-header h1 {
        font-size: 18px;
    }
}
</style>
</head>
<body>
 
<div class="login-background"></div>
 
<div class="login-container">
    <div style="width: 100%; max-width: 420px;">
        <div class="login-header">
            <div class="logo-circle">
                <h2>PB</h2>
            </div>
            <h1>Centro Pere Bas</h1>
        </div>
 
        <div class="login-card">
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
 
            <form method="POST">
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" placeholder="ejemplo@correo.com" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Introduce tu contraseña" required autocomplete="current-password">
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Recuérdame</label>
                </div>
                
                <button type="submit">Iniciar Sesión</button>
            </form>
        </div>
    </div>
</div>
 
</body>
</html>