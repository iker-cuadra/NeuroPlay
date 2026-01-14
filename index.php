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
            case "profesional":
                header("Location: profesional.php");
                break;
            case "usuario":
                header("Location: usuario.php");
                break;
            case "familiar":
                header("Location: familiar.php");
                break;
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
<title>Centro Pere Bas - Iniciar sesión</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --app-bg: #887d7dff;
    --card-bg: #ffffff;
    --text: #111827;
    --muted: #6b7280;
    --border: #e5e7eb;
    --shadow: 0 18px 50px rgba(0,0,0,0.20);
    --shadow-soft: 0 10px 30px rgba(0,0,0,0.12);
    --radius: 22px;
}

*{ box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: 'Poppins', sans-serif;
    background: var(--app-bg);
}

/* Wrapper flex */
.login-wrapper {
    display: flex;
    min-height: 100vh;
    background: var(--app-bg);
}

/* Lado izquierdo con imagen (misma distribución) */
.login-left {
    flex: 1;
    background: url('imagenes/imglogin.svg') no-repeat center center;
    background-size: cover;
    position: relative;
}

/* Sutil capa para integrar con el fondo (mejora estética) */
.login-left::after{
    content:"";
    position:absolute;
    inset:0;
    background: linear-gradient(120deg, rgba(0,0,0,0.10), rgba(0,0,0,0.00));
    pointer-events:none;
}

/* Lado derecho con formulario (misma distribución) */
.login-right {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 48px 20px;
    background: transparent;
}

/* Tarjeta del login (más cuidada) */
.login-card {
    width: 100%;
    max-width: 480px;
    padding: 56px 44px;
    border-radius: var(--radius);
    background: rgba(255,255,255,0.94);
    border: 1px solid rgba(255,255,255,0.45);
    box-shadow: var(--shadow);
    text-align: center;
    backdrop-filter: blur(8px);
}

/* Título */
.login-card h1 {
    font-size: 38px;
    margin: 0 0 8px 0;
    font-weight: 700;
    color: var(--text);
    letter-spacing: .2px;
}

.login-card .subtitle{
    margin: 0 0 26px 0;
    color: var(--muted);
    font-size: 14px;
}

/* Error */
.error-msg {
    margin: 0 0 16px 0;
    padding: 10px 12px;
    border-radius: 12px;
    background: #ffecec;
    color: #c0392b;
    border: 1px solid #ffd0d0;
    font-size: 14px;
}

/* Inputs */
.login-card input {
    width: 100%;
    padding: 15px 18px;
    margin-bottom: 16px;
    border-radius: 14px;
    border: 1px solid var(--border);
    outline: none;
    font-size: 16px;
    color: var(--text);
    background: rgba(255,255,255,0.90);
    transition: border-color .2s ease, box-shadow .2s ease, transform .08s ease;
}

.login-card input::placeholder{
    color: #9ca3af;
}

.login-card input:focus {
    border-color: rgba(122,118,118,0.85);
    box-shadow: 0 0 0 4px rgba(122,118,118,0.18);
}

/* Botón (mantengo tu estilo pero más limpio) */
.login-card button {
    width: 100%;
    padding: 15px;
    border-radius: 16px;
    border: none;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    z-index: 1;
    background: #7a7676;
    transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    box-shadow: var(--shadow-soft);
    margin-top: 6px;
}

.login-card button::before {
    content: "";
    position: absolute;
    inset: 0;
    left: -120%;
    width: 320%;
    height: 100%;
    background: linear-gradient(90deg, #7a7676, #968c8c, #c9beb6);
    transition: all 0.45s ease;
    z-index: -1;
}

.login-card button:hover::before {
    left: 0;
}

.login-card button:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 40px rgba(0,0,0,0.28);
    filter: brightness(1.01);
}

.login-card button:active{
    transform: translateY(0px);
}

/* Responsivo (misma lógica) */
@media(max-width: 992px){
    .login-wrapper {
        flex-direction: column;
    }
    .login-left {
        width: 100%;
        height: 260px;
        background-size: contain;
        background-repeat: no-repeat;
        margin-bottom: 18px;
    }
    .login-right {
        padding: 18px 15px 28px;
    }
    .login-card {
        max-width: 92%;
        padding: 38px 24px;
        border-radius: 20px;
    }
    .login-card h1 {
        font-size: 30px;
    }
    .login-card input {
        font-size: 15px;
        padding: 14px 16px;
    }
    .login-card button {
        font-size: 17px;
        padding: 14px;
    }
}
</style>
</head>
<body>

<div class="login-wrapper">

    <!-- Lado izquierdo con imagen -->
    <div class="login-left"></div>

    <!-- Lado derecho con formulario -->
    <div class="login-right">
        <div class="login-card">

            <h1>Centro Pere Bas</h1>
            <p class="subtitle">Acceso a la plataforma</p>

            <?php if ($error): ?>
                <p class="error-msg"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <input type="email" name="email" placeholder="E-mail" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Iniciar Sesión</button>
            </form>

        </div>
    </div>

</div>

</body>
</html>
