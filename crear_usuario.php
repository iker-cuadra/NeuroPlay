<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";

requireRole("profesional");

$error = "";
$success = "";

$nombre = "";
$email = "";
$rol_seleccionado = "usuario";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $rol_seleccionado = $_POST['rol'] ?? "usuario";

    $fotoNombre = "default.png";

    if ($nombre && $email && $password) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetchColumn() > 0) {
            $error = "Este email ya está registrado.";
        } else {
            if (!empty($_FILES["foto"]["name"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
                if (!is_dir("uploads")) mkdir("uploads", 0777, true);
                $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
                $permitidos = ["jpg", "jpeg", "png", "webp"];
                if (in_array($ext, $permitidos)) {
                    $fotoNombre = uniqid("foto_") . "." . $ext;
                    move_uploaded_file($_FILES["foto"]["tmp_name"], "uploads/" . $fotoNombre);
                }
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("
                INSERT INTO usuarios (nombre, email, password_hash, rol, foto)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $email, $hash, $rol_seleccionado, $fotoNombre]);
            $success = "Usuario creado correctamente.";
            $nombre = $email = "";
            $rol_seleccionado = "usuario";
        }
    } else {
        $error = "Todos los campos son obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear Usuario</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: 'Poppins', sans-serif;
    overflow: hidden;
    background: #f0f2f5;
}

/* Banner superior */
.page-header {
    height: 20vh;
    min-height: 120px;
    background-image: url("imagenes/Banner.svg");
    background-size: cover;
    background-position: center;
    position: relative;
    color: white;
    text-align: center;
    padding-top: 35px;
}

.back-arrow {
    position: absolute;
    top: 15px;
    left: 15px;
}

.back-arrow svg {
    fill: white;
}

/* Contenido central */
.page-content {
    height: 55vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

.container {
    background: white;
    padding: 20px 40px;
    border-radius: 20px;
    width: 900px;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    gap: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* Columnas del formulario */
.form-column {
    flex: 1;
}

/* Inputs y botones */
.form-group {
    margin-bottom: 15px;
}

label {
    font-weight: 500;
    display: block;
    margin-bottom: 6px;
}

input, button {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: 14px;
}

button {
    background: #4a4a4a;
    color: white;
    font-weight: 600;
    border: none;
    margin-top: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

button:hover {
    background: #5a5a5a;
}

.segmented-control {
    display: flex;
    background: #e9e9e9;
    border-radius: 10px;
    padding: 3px;
}

.segmented-control input {
    display: none;
}

.control-option {
    flex: 1;
    text-align: center;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
}

.segmented-control input:checked + .control-option {
    background: white;
    font-weight: 600;
    box-shadow: 0 0 4px rgba(0,0,0,0.15);
}

.error, .success {
    text-align: center;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 10px;
    font-size: 14px;
}

.error {
    background: #fee;
    color: #c00;
}

.success {
    background: #e8fff0;
    color: #0a7a3a;
}

/* Footer */
.page-footer {
    height: 25vh;
    min-height: 100px;
}

.page-footer img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="page-header">
    <a href="gestionar_users.php" class="back-arrow">
        <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24">
            <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
        </svg>
    </a>
    
</div>

<!-- CONTENIDO -->
<div class="page-content">
<div class="container">

    <form method="POST" enctype="multipart/form-data" style="width:100%; display:flex; gap:40px;">
        
        <div class="form-column">
            <h2 style="margin-bottom:15px; font-weight:600; text-align:center;">Crear Usuario</h2>

            <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
            <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
        </div>

        <div class="form-column">
            <div class="form-group">
                <label>Rol</label>
                <div class="segmented-control">
                    <input type="radio" id="u" name="rol" value="usuario" <?= $rol_seleccionado=="usuario"?"checked":"" ?>>
                    <label for="u" class="control-option">Usuario</label>

                    <input type="radio" id="f" name="rol" value="familiar" <?= $rol_seleccionado=="familiar"?"checked":"" ?>>
                    <label for="f" class="control-option">Familiar</label>

                    <input type="radio" id="p" name="rol" value="profesional" <?= $rol_seleccionado=="profesional"?"checked":"" ?>>
                    <label for="p" class="control-option">Profesional</label>
                </div>
            </div>

            <div class="form-group">
                <label>Foto</label>
                <input type="file" name="foto">
            </div>

            <button type="submit">Crear Usuario</button>
           
        </div>

    </form>
</div>
</div>



</body>
</html>
