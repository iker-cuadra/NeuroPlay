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
 
<!-- Google Fonts Poppins -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
 
<style>
body, html {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: 'Poppins', sans-serif;
    background: #f5f5f5;
}
 
/* Wrapper flex */
.login-wrapper {
    display: flex;
    height: 100vh;
}
 
/* Lado izquierdo con imagen */
.login-left {
    flex: 1;
    background: url('imagenes/imglogin.svg') no-repeat center center;
    background-size: cover;
}
 
/* Lado derecho con formulario */
.login-right {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #fff;
    padding: 40px 20px;
}
 
/* Tarjeta del login más grande */
.login-card {
    width: 100%;
    max-width: 480px;
    padding: 60px 40px;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    text-align: center;
    background: #fff;
    box-sizing: border-box;
}
 
/* Título más grande */
.login-card h1 {
    font-size: 40px;
    margin-bottom: 30px;
    font-weight: 600;
}
 
/* Error */
.error-msg {
    color: #ff4d4f;
    margin-bottom: 15px;
    font-size: 14px;
}
 
/* Inputs más grandes */
.login-card input {
    width: 100%;
    padding: 16px 24px;
    margin-bottom: 25px;
    border-radius: 50px;
    border: 1px solid #ccc;
    outline: none;
    font-size: 18px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}
 
.login-card input:focus {
    border-color: #2575fc;
    box-shadow: 0 0 5px rgba(37,117,252,0.3);
}
 
/* Botón más grande */
.login-card button {
    width: 100%;
    padding: 16px;
    border-radius: 50px;
    border: none;
    font-size: 20px;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    z-index: 1;
    background: #7a7676;
    transition: all 0.3s ease;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}
 
.login-card button::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 300%;
    height: 100%;
    background: linear-gradient(90deg, #7a7676, #968c8c, #c9beb6);
    transition: all 0.4s ease;
    z-index: -1;
}
 
.login-card button:hover::before {
    left: 0;
}
 
.login-card button:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
 
/* Enlace de recuperar contraseña en gris */
.login-card .forgot-password {
    display: block;
    margin-top: 15px;
    font-size: 14px;
    color: #555555; /* gris oscuro */
    text-decoration: none;
    transition: all 0.3s ease;
}
 
.login-card .forgot-password:hover {
    text-decoration: underline;
    color: #333333; /* gris más oscuro al pasar el ratón */
}
 
/* Responsivo */
@media(max-width: 992px){
    .login-wrapper {
        flex-direction: column;
    }
    .login-left {
        width: 100%;
        height: 250px;
        background-size: contain;
        background-repeat: no-repeat;
        margin-bottom: 20px;
    }
    .login-right {
        padding: 20px 15px;
    }
    .login-card {
        max-width: 90%;
        padding: 40px 25px;
    }
    .login-card h1 {
        font-size: 32px;
    }
    .login-card input {
        font-size: 16px;
        padding: 14px 20px;
    }
    .login-card button {
        font-size: 18px;
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
 
            <?php if ($error): ?>
                <p class="error-msg"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
 
            <form method="POST">
                <input type="email" name="email" placeholder="E-mail o nombre de usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Iniciar Sesión</button>
            </form>
 
         
 
        </div>
    </div>
 
</div>
 
</body>
</html>
 
 