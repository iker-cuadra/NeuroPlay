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
$usuario_vinculado_id  = 0; // para cuando se crea un familiar
$familiar_vinculado_id = 0; // para cuando se crea un usuario

/**
 * Devuelve el filename por defecto según rol (SIN "uploads/").
 */
function fotoDefaultPorRol(string $rol): string {
    if ($rol === "usuario") return "default_usuario.png";
    if ($rol === "familiar") return "default_familiar.png";
    return "default.png"; // profesional
}

/**
 * Crea (si no existe) una relación usuario–familiar en la tabla relaciones_usuario_familiar.
 * - $usuarioId  -> id de la cuenta con rol 'usuario'
 * - $familiarId -> id de la cuenta con rol 'familiar'
 */
function vincularUsuarioFamiliar(PDO $conexion, int $usuarioId, int $familiarId): void {
    // Comprobar si ya existe la relación
    $stmtCheck = $conexion->prepare("
        SELECT COUNT(*) 
        FROM relaciones_usuario_familiar 
        WHERE usuario_id = ? AND familiar_id = ?
    ");
    $stmtCheck->execute([$usuarioId, $familiarId]);

    if ($stmtCheck->fetchColumn() > 0) {
        // Ya existe, no hacemos nada
        return;
    }

    // Insertar la relación
    $stmtRel = $conexion->prepare("
        INSERT INTO relaciones_usuario_familiar (usuario_id, familiar_id, fecha_creacion)
        VALUES (?, ?, NOW())
    ");
    $stmtRel->execute([$usuarioId, $familiarId]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $rol_seleccionado = $_POST['rol'] ?? "usuario";

    // IDs seleccionados para vinculación (si los hubiera)
    $usuario_vinculado_id  = isset($_POST['usuario_vinculado'])  ? (int)$_POST['usuario_vinculado']  : 0;
    $familiar_vinculado_id = isset($_POST['familiar_vinculado']) ? (int)$_POST['familiar_vinculado'] : 0;

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

                    // ID del nuevo usuario creado
                    $nuevoId = (int)$conexion->lastInsertId();

                    // Vinculación automática según rol:
                    // - Si creo un USUARIO y he seleccionado un FAMILIAR -> usuario_id = nuevoId, familiar_id = familiar_vinculado_id
                    // - Si creo un FAMILIAR y he seleccionado un USUARIO -> usuario_id = usuario_vinculado_id, familiar_id = nuevoId
                    if ($rol_seleccionado === "usuario" && $familiar_vinculado_id > 0) {
                        vincularUsuarioFamiliar($conexion, $nuevoId, $familiar_vinculado_id);
                    } elseif ($rol_seleccionado === "familiar" && $usuario_vinculado_id > 0) {
                        vincularUsuarioFamiliar($conexion, $usuario_vinculado_id, $nuevoId);
                    }

                    $success = "Usuario creado correctamente.";
                    // Limpiar variables para vaciar el formulario
                    $nombre = $email = "";
                    $rol_seleccionado = "usuario";
                    $usuario_vinculado_id  = 0;
                    $familiar_vinculado_id = 0;
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

// Listas para vinculación
$listaUsuarios = [];
$listaFamiliares = [];
try {
    $stmtU = $conexion->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'usuario' ORDER BY nombre");
    $listaUsuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    $stmtF = $conexion->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'familiar' ORDER BY nombre");
    $listaFamiliares = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si falla, no detenemos la página; simplemente no habrá opciones en los select
}
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
            /* Bajamos un poco la altura del header para que todo quepa mejor */
            --header-h: 140px;
        }

        /* Página sin scroll global: todo dentro del viewport */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            font-family: 'Poppins', sans-serif;
            background: transparent;
            overflow: hidden; /* Sin scroll de página */
        }

        /* Fondo animado */
        .canvas-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            z-index: -1;
            background: #e5e5e5;
            background-image:
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 200% 200%;
            animation: meshMove 12s infinite alternate ease-in-out;
        }
        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        .layout{
            height: 100vh;            /* Ocupa exactamente la altura de la ventana */
            display: flex;
            flex-direction: column;
        }

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
            top: 10px;
            left: 10px;
            width: 34px;
            height: 34px;
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

        /* Contenido central ocupa todo el alto restante sin generar scroll global */
        .page-content {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px 16px; /* margen más pequeño */
            box-sizing: border-box;
        }

        .container {
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px 26px;              /* menos padding vertical */
            border-radius: 20px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            box-sizing: border-box;
            max-height: calc(100vh - var(--header-h) - 24px); /* para encajar sin scroll global */
            overflow: hidden;               /* sin scroll interno visible */
        }

        .form-flex {
            display: flex;
            flex-wrap: wrap;
            gap: 24px; /* algo menos de gap vertical y horizontal */
        }

        .form-column {
            flex: 1;
            min-width: 280px;
        }

        .form-group { margin-bottom: 14px; }

        label {
            font-weight: 500;
            display: block;
            margin-bottom: 6px;
            color: #444;
            font-size: 13px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="file"],
        select {
            width: 100%;
            padding: 9px 10px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 13px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4a4a4a;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 15px;
            margin-top: 8px;
        }

        button:hover { background: #222; transform: translateY(-1px); }

        .segmented-control {
            display: flex;
            background: #f0f0f0;
            border-radius: 12px;
            padding: 3px;
            gap: 4px;
        }

        .segmented-control input { display: none; }

        .control-option {
            flex: 1;
            text-align: center;
            padding: 8px 6px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            transition: 0.2s;
            color: #666;
        }

        .segmented-control input:checked + .control-option {
            background: white;
            color: #000;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .alert {
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            text-align: center;
        }
        .error { background: #ffe3e3; color: #b71c1c; border: 1px solid #ffcdd2; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        #preview-container {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            overflow: hidden;
            background: #eee;
            margin: 8px auto;
            border: 2px solid #ddd;
        }
        #preview-container img { width: 100%; height: 100%; object-fit: cover; }

        .vinculo-help {
            font-size: 11px;
            color: #777;
            margin-top: 3px;
        }

        h2 {
            margin: 0 0 10px 0;
            font-size: 20px;
        }
    </style>
</head>
<body>
<div class="canvas-bg"></div>
<div class="layout">

    <div class="header">
        <a href="gestionar_users.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="30" width="30" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Crear usuario</div>
    </div>

    <div class="page-content">
        <div class="container">
            <h2 style="text-align:center; font-weight:700;">Nuevo Perfil de Usuario</h2>

            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-flex">

                <!-- COLUMNA IZQUIERDA: datos + VÍNCULO debajo de la contraseña -->
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

                    <!-- VINCULACIÓN DEBAJO DE CONTRASEÑA -->
                    <div class="form-group" id="vinculo-usuario" style="display:none;">
                        <label><i class="fas fa-link"></i> Vincular con familiar (opcional)</label>
                        <select name="familiar_vinculado">
                            <option value="0">— Sin vincular —</option>
                            <?php foreach ($listaFamiliares as $fam): ?>
                                <option value="<?= (int)$fam['id'] ?>"
                                    <?= $familiar_vinculado_id == (int)$fam['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fam['nombre']) ?> (<?= htmlspecialchars($fam['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="vinculo-help">
                            Se creará una relación entre este usuario y el familiar seleccionado.
                        </div>
                    </div>

                    <div class="form-group" id="vinculo-familiar" style="display:none;">
                        <label><i class="fas fa-link"></i> Vincular con usuario (opcional)</label>
                        <select name="usuario_vinculado">
                            <option value="0">— Sin vincular —</option>
                            <?php foreach ($listaUsuarios as $usr): ?>
                                <option value="<?= (int)$usr['id'] ?>"
                                    <?= $usuario_vinculado_id == (int)$usr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usr['nombre']) ?> (<?= htmlspecialchars($usr['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="vinculo-help">
                            Se creará una relación entre este familiar y el usuario seleccionado.
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: rol + foto + botón -->
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

    function actualizarBloqueVinculo() {
        const rol = rolSeleccionado();
        const bloqueUsuario  = document.getElementById('vinculo-usuario');
        const bloqueFamiliar = document.getElementById('vinculo-familiar');

        if (!bloqueUsuario || !bloqueFamiliar) return;

        if (rol === 'usuario') {
            bloqueUsuario.style.display  = 'block';  // mostrar select para familiar
            bloqueFamiliar.style.display = 'none';
        } else if (rol === 'familiar') {
            bloqueUsuario.style.display  = 'none';
            bloqueFamiliar.style.display = 'block';  // mostrar select para usuario
        } else {
            bloqueUsuario.style.display  = 'none';
            bloqueFamiliar.style.display = 'none';
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

    // Si cambia el rol, actualizamos preview y bloques de vínculo
    document.querySelectorAll('input[name="rol"]').forEach(radio => {
        radio.addEventListener('change', function () {
            setPreviewDefaultSiNoHayArchivo();
            actualizarBloqueVinculo();
        });
    });

    // Al cargar, fija el default según rol actual y muestra el bloque correcto
    document.addEventListener('DOMContentLoaded', function() {
        setPreviewDefaultSiNoHayArchivo();
        actualizarBloqueVinculo();
    });
</script>

</body>
</html>
