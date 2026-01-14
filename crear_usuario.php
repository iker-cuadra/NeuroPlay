<?php
require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo los profesionales pueden acceder a esta página
requireRole("profesional");

$error = "";
$success = "";

$nombre = "";
$email = "";
$rol_seleccionado = "usuario";

/**
 * Devuelve el filename por defecto según rol (SIN "uploads/").
 */
function fotoDefaultPorRol(string $rol): string {
    if ($rol === "usuario") return "default_usuario.png";
    if ($rol === "familiar") return "default_familiar.png";
    return "default.png"; // profesional
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $rol_seleccionado = $_POST['rol'] ?? "usuario";

    // Foto por defecto por si el usuario no sube ninguna (depende del rol)
    // IMPORTANTE: Guardamos SOLO el nombre del archivo, no "uploads/..."
    $fotoNombre = fotoDefaultPorRol($rol_seleccionado);

    if ($nombre && $email && $password) {
        // Verificar si el email ya existe
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetchColumn() > 0) {
            $error = "Este email ya está registrado.";
        } else {
            // PROCESAMIENTO DE LA FOTO
            if (!empty($_FILES["foto"]["name"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {

                // Crear carpeta uploads si no existe
                $directorio_subida = "uploads/";
                if (!is_dir($directorio_subida)) {
                    mkdir($directorio_subida, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
                $permitidos = ["jpg", "jpeg", "png", "webp"];

                if (in_array($ext, $permitidos, true)) {
                    // Generar nombre único para evitar que se sobrescriban fotos con el mismo nombre
                    $fotoNombre = uniqid("foto_", true) . "." . $ext;
                    $rutaFinal = $directorio_subida . $fotoNombre;

                    if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $rutaFinal)) {
                        $error = "Error crítico: No se pudo guardar la imagen en el servidor.";
                    }
                } else {
                    $error = "Formato de imagen no válido. Usa JPG, PNG o WEBP.";
                }
            }

            // Si no hay errores, procedemos a guardar en la Base de Datos
            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $conexion->prepare("
                        INSERT INTO usuarios (nombre, email, password_hash, rol, foto)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    // IMPORTANTE: guardamos $fotoNombre (solo filename)
                    $stmt->execute([$nombre, $email, $hash, $rol_seleccionado, $fotoNombre]);

                    $success = "Usuario creado correctamente.";
                    // Limpiar variables para vaciar el formulario
                    $nombre = $email = "";
                    $rol_seleccionado = "usuario";
                } catch (PDOException $e) {
                    $error = "Error en la base de datos: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Todos los campos (Nombre, Email y Contraseña) son obligatorios.";
    }
}

// Preview por defecto (según rol actual seleccionado)
$previewDefault = "uploads/" . fotoDefaultPorRol($rol_seleccionado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario - NeuroPlay</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root{
            --header-h: 160px;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Poppins', sans-serif;
            background: #887d7dff;
            overflow-x: hidden;
        }

        .layout{
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER (igual que en el primer archivo) */
        .header{
            width: 100%;
            height: var(--header-h);
            background-image: url('imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
            flex: 0 0 auto;
        }

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

        .user-role{
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        /* Contenido central */
        .page-content {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 30px 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .form-flex {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
        }

        .form-column {
            flex: 1;
            min-width: 300px;
        }

        /* Inputs y botones */
        .form-group { margin-bottom: 20px; }

        label {
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
            color: #444;
        }

        input[type="text"], input[type="email"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 14px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 15px;
            background: #4a4a4a;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover { background: #222; transform: translateY(-2px); }

        /* Selector de Rol (Segmented Control) */
        .segmented-control {
            display: flex;
            background: #f0f0f0;
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
        }

        .segmented-control input { display: none; }

        .control-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
            color: #666;
        }

        .segmented-control input:checked + .control-option {
            background: white;
            color: #000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Alert Box */
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .error { background: #ffe3e3; color: #b71c1c; border: 1px solid #ffcdd2; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        /* Preview de imagen */
        #preview-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            background: #eee;
            margin: 10px auto;
            border: 2px solid #ddd;
        }
        #preview-container img { width: 100%; height: 100%; object-fit: cover; }

        /* Footer */
        .page-footer { height: 25vh; }
        .page-footer img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>

<div class="layout">

    <!-- HEADER igual que el del primer archivo -->
    <div class="header">
        <a href="gestionar_users.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Crear usuario</div>
    </div>

    <div class="page-content">
        <div class="container">
            <h2 style="text-align:center; margin-top:0; font-weight:700;">Nuevo Perfil de Usuario</h2>

            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-flex">

                <div class="form-column">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nombre Completo</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej. Juan Pérez" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Correo Electrónico</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="usuario@correo.com" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Contraseña Provisional</label>
                        <input type="password" name="password" placeholder="********" required>
                    </div>
                </div>

                <div class="form-column">
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Rol Asignado</label>
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
                        <label><i class="fas fa-camera"></i> Fotografía de Perfil</label>
                        <input type="file" name="foto" id="foto-input" accept="image/*">
                        <div id="preview-container">
                            <img id="img-preview" src="<?= htmlspecialchars($previewDefault) ?>" alt="Vista previa">
                        </div>
                    </div>

                    <button type="submit"><i class="fas fa-save"></i> Guardar Usuario</button>
                </div>

            </form>
        </div>
    </div>


</div>

<script>
    const fotoInput = document.getElementById('foto-input');
    const imgPreview = document.getElementById('img-preview');

    const defaults = {
        usuario: 'uploads/default_usuario.png',
        familiar: 'uploads/default_familiar.png',
        profesional: 'uploads/default.png'
    };

    function rolSeleccionado() {
        const checked = document.querySelector('input[name="rol"]:checked');
        return checked ? checked.value : 'usuario';
    }

    function setPreviewDefaultSiNoHayArchivo() {
        if (!fotoInput.files || fotoInput.files.length === 0) {
            const rol = rolSeleccionado();
            imgPreview.src = defaults[rol] || defaults.usuario;
        }
    }

    // Preview de imagen antes de subirla
    fotoInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (file) {
            imgPreview.src = URL.createObjectURL(file);
        } else {
            setPreviewDefaultSiNoHayArchivo();
        }
    });

    // Si cambia el rol y NO hay archivo seleccionado, cambia el default preview
    document.querySelectorAll('input[name="rol"]').forEach(radio => {
        radio.addEventListener('change', setPreviewDefaultSiNoHayArchivo);
    });

    // Al cargar, fija el default según rol actual
    setPreviewDefaultSiNoHayArchivo();
</script>

</body>
</html>
