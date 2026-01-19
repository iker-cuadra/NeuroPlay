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

// Obtener lista de familiares para el desplegable
$stmtFam = $conexion->query("SELECT id, nombre FROM usuarios WHERE rol = 'familiar' ORDER BY nombre ASC");
$familiares = $stmtFam->fetchAll();

// Obtener lista de usuarios para asociar cuando se crea un familiar
$stmtUsu = $conexion->query("SELECT id, nombre FROM usuarios WHERE rol = 'usuario' ORDER BY nombre ASC");
$usuarios = $stmtUsu->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $rol_seleccionado = $_POST['rol'] ?? "usuario";
    $familiar_id = ($rol_seleccionado === "usuario" && !empty($_POST['familiar_id'])) ? $_POST['familiar_id'] : null;

    $usuario_asociado_id = ($rol_seleccionado === "familiar" && !empty($_POST['usuario_asociado_id'])) ? (int)$_POST['usuario_asociado_id'] : null;

    $fotoNombre = fotoDefaultPorRol($rol_seleccionado);

    if ($nombre && $email && $password) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetchColumn() > 0) {
            $error = "Este email ya está registrado.";
        } else {
            if (!empty($_FILES["foto"]["name"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
                $directorio_subida = "uploads/";
                if (!is_dir($directorio_subida)) mkdir($directorio_subida, 0777, true);

                $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
                $permitidos = ["jpg","jpeg","png","webp"];

                if (in_array($ext, $permitidos, true)) {
                    $fotoNombre = uniqid("foto_", true).".".$ext;
                    $rutaFinal = $directorio_subida.$fotoNombre;
                    if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $rutaFinal)) {
                        $error = "Error crítico: No se pudo guardar la imagen en el servidor.";
                    }
                } else {
                    $error = "Formato de imagen no válido. Usa JPG, PNG o WEBP.";
                }
            }

            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $conexion->prepare("
                        INSERT INTO usuarios (nombre, email, password_hash, rol, foto, familiar_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nombre,$email,$hash,$rol_seleccionado,$fotoNombre,$familiar_id]);

                    if ($rol_seleccionado === "familiar" && !empty($usuario_asociado_id)) {
                        $nuevo_familiar_id = (int)$conexion->lastInsertId();
                        $stmtUp = $conexion->prepare("
                            UPDATE usuarios
                            SET familiar_id = ?
                            WHERE id = ?
                        ");
                        $stmtUp->execute([$nuevo_familiar_id, $usuario_asociado_id]);
                    }

                    $success = "Usuario creado correctamente.";
                    $nombre = $email = "";
                    $rol_seleccionado = "usuario";
                } catch(PDOException $e) {
                    $error = "Error en la base de datos: ".$e->getMessage();
                }
            }
        }
    } else {
        $error = "Todos los campos (Nombre, Email y Contraseña) son obligatorios.";
    }
}

$previewDefault = "uploads/".fotoDefaultPorRol($rol_seleccionado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Usuario - NeuroPlay</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root{ --header-h:160px; }

/* MODIFICADO: Permitir scroll en el body */
html, body{
    margin:0; padding:0; min-height:100%;
    font-family: 'Poppins', sans-serif;
    overflow-x:hidden; 
    overflow-y: auto; /* Habilitar scroll vertical */
}

.canvas-bg{
    position:fixed; top:0; left:0; width:100%; height:100%;
    z-index:-1;
    background-image:
        radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
        radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
        radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
        radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
        radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
    background-size:200% 200%;
    animation: meshMove 8s infinite alternate ease-in-out;
}
@keyframes meshMove{0%{background-position:0% 0%;}100%{background-position:100% 100%;}}

/* MODIFICADO: Cambiado de height:100vh a min-height */
.layout{ min-height:100vh; display:flex; flex-direction:column; position:relative; z-index:1; }

.header{
    width:100%; height:var(--header-h);
    background-image:url('imagenes/Banner.svg');
    background-size:cover; background-position:center;
    position:relative; flex:0 0 auto;
}
.back-arrow{
    position:absolute; top:15px; left:15px; width:38px; height:38px;
    display:flex; align-items:center; justify-content:center; text-decoration:none;
}
.user-role{
    position:absolute; bottom:10px; left:20px; color:white; font-weight:700; font-size:18px;
}

.page-content{
    flex:1 1 auto; display:flex; justify-content:center; align-items:flex-start; /* Alineado arriba para que el scroll empiece bien */
    padding:40px 20px;
}

.container{
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(10px);
    padding:30px 40px; border-radius:20px;
    width:100%; max-width:900px;
    box-shadow:0 10px 40px rgba(0,0,0,0.12);
    margin-bottom: 20px; /* Margen extra para dispositivos móviles */
}

.form-flex{ display:flex; flex-wrap:wrap; gap:40px; }
.form-column{ flex:1; min-width:300px; }
.form-group{ margin-bottom:20px; }
label{ font-weight:500; display:block; margin-bottom:8px; color:#444; }
input, select{ width:100%; padding:12px; border-radius:10px; border:1px solid #ddd; font-size:14px; box-sizing:border-box; font-family:inherit; }

button[type="submit"]{
    width:100%; padding:15px;
    background: rgba(0, 0, 0, 0.05);
    color: #333;
    font-weight: 700;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius:12px;
    cursor:pointer;
    font-size:16px;
    margin-top:10px;
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

button[type="submit"]:hover{
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.segmented-control{ display:flex; background:#f0f0f0; border-radius:12px; padding:4px; gap:4px; }
.segmented-control input{ display:none; }
.control-option{ flex:1; text-align:center; padding:10px; border-radius:10px; cursor:pointer; font-size:13px; transition:0.2s; color:#666; }
.segmented-control input:checked + .control-option{ background:white; color:#000; font-weight:600; box-shadow:0 2px 8px rgba(0,0,0,0.1); }

.alert{ padding:12px; border-radius:10px; margin-bottom:20px; font-size:14px; text-align:center; }
.error{ background:#ffe3e3; color:#b71c1c; border:1px solid #ffcdd2; }
.success{ background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }

#preview-container{
    width:110px; height:110px; border-radius:50%;
    overflow:hidden; background:#fff; margin:15px auto;
    border:3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}
#preview-container:hover { transform: scale(1.05); }
#preview-container img{ width:100%; height:100%; object-fit:cover; }

#familiar-selection, #usuario-selection {
    max-height:0; overflow:hidden; opacity:0;
    transition:all 0.4s ease;
    background: rgba(0,0,0,0.02);
    border-radius:12px; margin-bottom: 0;
}
#familiar-selection.active, #usuario-selection.active{
    max-height:150px; opacity:1; padding:15px; margin-bottom: 20px;
    border: 1px solid #ddd;
}

.file-upload-wrapper{
    position: relative; display:flex; flex-direction: column; align-items:center; gap:8px; margin-top:5px;
}
.file-upload-wrapper input[type="file"]{
    position:absolute; left:0; top:0; width:100%; height:100%;
    opacity:0; cursor:pointer;
}
#select-file-btn{
    background: #f8f9fa; border: 1px dashed #adb5bd;
    color:#495057; padding:10px 20px;
    border-radius:10px; cursor:pointer; font-weight:600;
    transition: all 0.3s ease; width: 100%;
}
#select-file-btn:hover{ background: #e9ecef; border-color: #6c757d; }
#file-name{ font-size:12px; color:#888; font-style: italic; }

/* Responsive */
@media (max-width: 600px) {
    .container { padding: 20px; }
    .form-flex { gap: 20px; }
}
</style>
</head>
<body>
<div class="canvas-bg"></div>

<div class="layout">
    <div class="header">
        <a href="gestionar_users.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Crear usuario</div>
    </div>

    <div class="page-content">
        <div class="container">
            <h2 style="text-align:center; margin-top:0; font-weight:700; color:#333;">Nuevo perfil de usuario</h2>

            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-flex">
                <div class="form-column">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nombre </label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej. Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="usuario@correo.com" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Contraseña </label>
                        <input type="password" name="password" placeholder="********" required>
                    </div>

                    <div id="familiar-selection" class="form-group">
                        <label><i class="fas fa-users"></i> Asociar a familiar</label>
                        <select name="familiar_id">
                            <option value="">-- Sin familiar asociado --</option>
                            <?php foreach($familiares as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="usuario-selection" class="form-group">
                        <label><i class="fas fa-user-check"></i> Asociar a usuario</label>
                        <select name="usuario_asociado_id">
                            <option value="">-- Sin usuario asociado --</option>
                            <?php foreach($usuarios as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-column">
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Rol asignado</label>
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
                        <label><i class="fas fa-camera"></i> Foto de perfil</label>
                        <div id="preview-container">
                            <img id="img-preview" src="<?= htmlspecialchars($previewDefault) ?>" alt="Vista previa">
                        </div>
                        <div class="file-upload-wrapper">
                            <button type="button" id="select-file-btn"><i class="fas fa-image"></i> Cambiar imagen</button>
                            <span id="file-name">Imagen por defecto</span>
                            <input type="file" name="foto" id="foto-input" accept="image/*">
                        </div>
                    </div>

                    <button type="submit"><i class="fas fa-check-circle"></i> Guardar usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const fotoInput = document.getElementById('foto-input');
const imgPreview = document.getElementById('img-preview');
const familiarDiv = document.getElementById('familiar-selection');
const usuarioDiv = document.getElementById('usuario-selection');
const selectFileBtn = document.getElementById('select-file-btn');
const fileNameSpan = document.getElementById('file-name');

const defaults = {
    usuario: 'uploads/default_usuario.png',
    familiar: 'uploads/default_familiar.png',
    profesional: 'uploads/default.png'
};

function updateRoleVisuals(){
    const rol = document.querySelector('input[name="rol"]:checked').value;
    
    // Toggle Selects
    familiarDiv.classList.toggle('active', rol === 'usuario');
    usuarioDiv.classList.toggle('active', rol === 'familiar');
    
    // Actualizar imagen por defecto si no hay archivo seleccionado
    if(!fotoInput.files || fotoInput.files.length === 0){
        imgPreview.src = defaults[rol];
    }
}

selectFileBtn.addEventListener('click', ()=> fotoInput.click());

fotoInput.addEventListener('change', function(){
    const file = this.files && this.files[0];
    if(file){
        imgPreview.src = URL.createObjectURL(file);
        fileNameSpan.textContent = file.name;
    }
});

document.querySelectorAll('input[name="rol"]').forEach(radio=>{
    radio.addEventListener('change', updateRoleVisuals);
});

// Inicializar
updateRoleVisuals();
</script>
</body>
</html>