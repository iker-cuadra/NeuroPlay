<?php
// Asegúrate de iniciar la sesión antes de cualquier salida
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo profesionales pueden acceder
requireRole("profesional");

// -------------------------
// Flash messages (sesión)
// -------------------------
if (!isset($_SESSION["flash_success"])) $_SESSION["flash_success"] = "";
if (!isset($_SESSION["flash_error"])) $_SESSION["flash_error"] = "";

// -------------------------
// CSRF token simple
// -------------------------
if (!isset($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(16));
}

// -------------------------
// FILTRO POR ROL (GET)
// -------------------------
$filtro_rol = $_GET["filtro_rol"] ?? "todos";
$roles_validos = ["todos", "usuario", "familiar", "profesional"];
if (!in_array($filtro_rol, $roles_validos, true)) {
    $filtro_rol = "todos";
}

// Helper para mantener filtro en enlaces
function qs_keep(array $extra = []): string {
    $keep = [];
    if (isset($_GET["filtro_rol"]) && $_GET["filtro_rol"] !== "todos") {
        $keep["filtro_rol"] = $_GET["filtro_rol"];
    }
    unset($keep["eliminar_id"]);

    $all = array_merge($keep, $extra);
    return $all ? ("?" . http_build_query($all)) : "";
}

// -------------------------
// ELIMINAR USUARIO (GET)
// -------------------------
if (isset($_GET['eliminar_id'])) {
    $eliminar_id = (int)$_GET['eliminar_id'];

    try {
        // 1) Eliminar mensajes asociados
        $stmt_msg = $conexion->prepare("DELETE FROM mensajes WHERE remitente_id = ? OR destinatario_id = ?");
        $stmt_msg->execute([$eliminar_id, $eliminar_id]);

        // 2) Obtener foto
        $stmt_foto = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmt_foto->execute([$eliminar_id]);
        $foto_a_eliminar = $stmt_foto->fetchColumn();

        // 3) Eliminar archivo foto si aplica (si no es un default)
        $defaults = ["default.png", "default_usuario.png", "default_familiar.png"];
        if (!empty($foto_a_eliminar) && !in_array($foto_a_eliminar, $defaults, true) && file_exists('uploads/' . $foto_a_eliminar)) {
            unlink('uploads/' . $foto_a_eliminar);
        }

        // 4) Eliminar usuario
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$eliminar_id]);

        $_SESSION["flash_success"] = "Usuario eliminado correctamente.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al eliminar: " . $e->getMessage();
    }

    header("Location: gestionar_users.php" . qs_keep());
    exit;
}

// -------------------------
// ACTUALIZAR USUARIO (POST)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_id"])) {

    // CSRF check
    if (!isset($_POST["csrf"]) || $_POST["csrf"] !== $_SESSION["csrf"]) {
        $_SESSION["flash_error"] = "Token inválido. Recarga la página e inténtalo de nuevo.";
        header("Location: gestionar_users.php" . qs_keep());
        exit;
    }

    $id = (int)($_POST["update_id"] ?? 0);
    $nombre = trim($_POST["nombre"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $rol = trim($_POST["rol"] ?? "usuario");
    $newPassword = $_POST["password"] ?? "";
    
    // --- CAMBIO AQUÍ: Capturamos el familiar_id si se envía ---
    $familiar_id = (!empty($_POST["familiar_id"])) ? (int)$_POST["familiar_id"] : null;

    if ($id <= 0 || $nombre === "" || $email === "" || $rol === "") {
        $_SESSION["flash_error"] = "Nombre, email y rol son obligatorios.";
        header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
        exit;
    }

    // Verificar que el usuario existe y coger su foto actual
    $stmt = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $foto_actual = $stmt->fetchColumn();

    if ($foto_actual === false) {
        $_SESSION["flash_error"] = "El usuario no existe.";
        header("Location: gestionar_users.php" . qs_keep());
        exit;
    }

    // Verificar email único (excepto el propio usuario)
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?");
    $stmt->execute([$email, $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $_SESSION["flash_error"] = "Ese email ya está registrado por otro usuario.";
        header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
        exit;
    }

    // -------------------------
    // Manejo de nueva foto (opcional)
    // -------------------------
    $foto_nueva = $foto_actual ?: "default.png";

    if (isset($_FILES["foto"]) && $_FILES["foto"]["name"] !== "") {

        if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
            $errores = [
                UPLOAD_ERR_INI_SIZE   => "La imagen supera el límite permitido por el servidor (php.ini).",
                UPLOAD_ERR_FORM_SIZE  => "La imagen supera el límite permitido por el formulario.",
                UPLOAD_ERR_PARTIAL    => "La imagen se subió parcialmente. Inténtalo de nuevo.",
                UPLOAD_ERR_NO_FILE    => "No se seleccionó ninguna imagen.",
                UPLOAD_ERR_NO_TMP_DIR => "Falta la carpeta temporal del servidor.",
                UPLOAD_ERR_CANT_WRITE => "No se pudo guardar la imagen (permisos del servidor).",
                UPLOAD_ERR_EXTENSION  => "Una extensión de PHP bloqueó la subida de la imagen."
            ];
            $_SESSION["flash_error"] = $errores[$_FILES["foto"]["error"]] ?? "Error al subir la imagen.";
            header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
            exit;
        }

        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        $tmp = $_FILES["foto"]["tmp_name"];
        if (@getimagesize($tmp) === false) {
            $_SESSION["flash_error"] = "El archivo seleccionado no es una imagen válida.";
            header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
            exit;
        }

        $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
        $permitidos = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($ext, $permitidos, true)) {
            $_SESSION["flash_error"] = "Formato de imagen no permitido (JPG, PNG, WEBP).";
            header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
            exit;
        }

        $foto_nueva = uniqid("foto_", true) . "." . $ext;

        if (!move_uploaded_file($tmp, "uploads/" . $foto_nueva)) {
            $_SESSION["flash_error"] = "No se pudo subir la imagen (permisos o ruta).";
            header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
            exit;
        }

        $defaults = ["default.png", "default_usuario.png", "default_familiar.png"];
        if (!empty($foto_actual) && !in_array($foto_actual, $defaults, true) && file_exists("uploads/" . $foto_actual)) {
            unlink("uploads/" . $foto_actual);
        }
    }

    try {
        // --- CAMBIO AQUÍ: Se añade familiar_id a las consultas de UPDATE ---
        if ($newPassword !== "") {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("
                UPDATE usuarios
                SET nombre = ?, email = ?, rol = ?, password_hash = ?, foto = ?, familiar_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $email, $rol, $hash, $foto_nueva, $familiar_id, $id]);
        } else {
            $stmt = $conexion->prepare("
                UPDATE usuarios
                SET nombre = ?, email = ?, rol = ?, foto = ?, familiar_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $email, $rol, $foto_nueva, $familiar_id, $id]);
        }

        $_SESSION["flash_success"] = "Usuario actualizado correctamente.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al actualizar: " . $e->getMessage();
    }

    header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
    exit;
}

// -------------------------
// USER A EDITAR (GET)
// -------------------------
$editar_user = null;
if (isset($_GET["editar_id"])) {
    $editar_id = (int)$_GET["editar_id"];
    // --- CAMBIO AQUÍ: Se añade familiar_id al SELECT ---
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto, familiar_id FROM usuarios WHERE id = ?");
    $stmt->execute([$editar_id]);
    $editar_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// -------------------------
// LISTA USUARIOS (con filtro)
// -------------------------
if ($filtro_rol === "todos") {
    $stmt = $conexion->query("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE rol = ? ORDER BY id DESC");
    $stmt->execute([$filtro_rol]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Flash (leer y limpiar)
$flash_success = $_SESSION["flash_success"];
$flash_error = $_SESSION["flash_error"];
$_SESSION["flash_success"] = "";
$_SESSION["flash_error"] = "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
:root{
    --header-h: 160px;
    --bg: transparent;
    --card: #ffffff;
    --shadow: 0 12px 30px rgba(0,0,0,0.10);
    --radius: 20px;
    --text: #1d1d1f;
    --muted: #6c6c6c;
    --btn: #4a4a4a;
    --btn-hover: #5a5a5a;
}

*{ box-sizing: border-box; margin: 0; padding: 0; }

html, body{
    height: 100%;
    overflow: hidden;
    font-family: 'Poppins', sans-serif;
    background-color: transparent; /* <-- aquí */
}


/* --- FONDO MESH ANIMADO --- */
.canvas-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: -1;
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

.layout{
    height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
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

.page-content{
    flex: 1 1 auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 14px 16px;
    overflow: hidden;
    min-height: 0;
}

.panel{
    width: min(1200px, 95vw);
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    height: 100%;
    overflow: hidden;
    min-height: 0;
}

.panel-header{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.panel-header h1{
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.actions{
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
    white-space: nowrap;
    font-size: 14px;
}

.btn-primary{
    background: var(--btn);
    color: white;
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}
.btn-primary:hover{ background: var(--btn-hover); transform: translateY(-1px); }
.btn-primary:active{ transform: scale(0.98); }

.filter-buttons{
    display: inline-flex;
    gap: 8px;
    align-items: center;
    background: #eef0f3;
    border-radius: 16px;
    padding: 6px;
    border: 1px solid #dde2ea;
}

.filter-buttons .label{
    font-size: 13px;
    font-weight: 900;
    color: #333;
    padding: 0 8px 0 6px;
}

.filter-buttons a{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 900;
    font-size: 13px;
    color: #333;
    background: #ffffff;
    border: 1px solid #e7e9ee;
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease, border-color .18s ease;
}

.filter-buttons a:hover{
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(0,0,0,0.12);
    border-color: #d6dae3;
}

.filter-buttons a.active{
    background: #4a4a4a;
    color: #fff;
    border-color: #4a4a4a;
    box-shadow: 0 10px 24px rgba(0,0,0,0.16);
}

.filter-buttons a:active{
    transform: scale(0.98);
}

.flash{
    padding: 10px 12px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 14px;
}
.flash.success{ background:#e8fff0; color:#0a7a3a; border:1px solid #c9f2d7; }
.flash.error{ background:#ffecec; color:#c0392b; border:1px solid #ffd0d0; }

.edit-card{
    border: 1px solid #eef0f3;
    background: #fafbfc;
    border-radius: 18px;
    padding: 14px;
    display: flex;
    gap: 14px;
    align-items: stretch;
    flex: 0 0 auto;
}

.edit-left{
    width: 220px;
    min-width: 220px;
    background: white;
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.edit-photo{
    width: 92px;
    height: 92px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #f0f0f0;
    margin-bottom: 10px;
}

.edit-left .meta{
    text-align: center;
    font-size: 13px;
    color: var(--muted);
}

.edit-right{
    flex: 1;
    background: white;
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    position: relative;
}

.close-edit{
    position: absolute;
    top: 10px;
    right: 10px;
    text-decoration: none;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef0f3;
    color: #333;
    font-weight: 900;
    transition: background 0.2s;
}
.close-edit:hover{ background:#e2e5ea; }

.edit-right h2{
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 800;
}

.form-grid{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.form-group label{
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #444;
    margin-bottom: 6px;
}

.form-group input,
.form-group select{
    width: 100%;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid #dde2ea;
    background: #fcfcfd;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus{
    outline: none;
    border-color: #b9c0cc;
    box-shadow: 0 0 0 3px rgba(74,74,74,0.08);
}

.form-actions{
    margin-top: 10px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.btn-save{
    background: #2e7d32;
    color: white;
}
.btn-save:hover{ filter: brightness(1.05); transform: translateY(-1px); }
.btn-save:active{ transform: scale(0.98); }

.btn-cancel{
    background: #eef0f3;
    color: #333;
    border-radius: 14px;
    padding: 10px 14px;
    text-decoration: none;
    font-weight: 800;
}
.btn-cancel:hover{ background:#e2e5ea; transform: translateY(-1px); }
.btn-cancel:active{ transform: scale(0.98); }

.table-wrap{
    width: 100%;
    overflow-x: auto;
    flex: 1 1 auto;
    overflow-y: auto;
    min-height: 0;
}

table{
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0 12px;
}

thead th{
    text-align: left;
    font-size: 13px;
    color: var(--muted);
    font-weight: 800;
    padding: 10px 12px;
    background: white;
    border-bottom: 1px solid #eceff3;
    position: sticky;
    top: 0;
    z-index: 2;
}

th:nth-child(1), th:nth-child(5){ text-align:center; }

tbody tr{
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
tbody tr:hover{
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(0,0,0,0.12);
}

td{
    padding: 14px 12px;
    font-size: 14px;
    border: none;
    vertical-align: middle;
    overflow-wrap: anywhere;
    word-break: break-word;
}

td:nth-child(1), td:nth-child(5){ text-align:center; }

.user-photo{
    width: 58px;
    height: 58px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #f0f0f0;
}

.role-badge{
    display: inline-block;
    padding: 6px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 900;
    text-transform: capitalize;
}
.role-badge.usuario{ background:#e0f7fa; color:#00796b; }
.role-badge.familiar{ background:#fce4ec; color:#ad1457; }
.role-badge.profesional{ background:#e8f5e9; color:#2e7d32; }

.action-row{
    display: inline-flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.action-btn{
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 900;
    font-size: 13px;
    line-height: 1;
    border: 1px solid transparent;
    box-shadow: 0 8px 18px rgba(0,0,0,0.10);
    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease, background .18s ease, color .18s ease, border-color .18s ease;
    user-select: none;
    white-space: nowrap;
}

.action-btn i{ font-size: 14px; }

.action-btn:hover{
    transform: translateY(-2px);
    box-shadow: 0 14px 28px rgba(0,0,0,0.14);
    filter: brightness(1.02);
}

.action-btn:active{
    transform: translateY(0px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.10);
}

.btn-evaluar{
    background: linear-gradient(180deg, #ecfdf5, #dcfce7);
    border-color: #bbf7d0;
    color: #166534;
}
.btn-evaluar:hover{ background: linear-gradient(180deg, #dcfce7, #bbf7d0); }

.btn-editar{
    background: linear-gradient(180deg, #eef2ff, #e0e7ff);
    border-color: #c7d2fe;
    color: #1e3a8a;
}
.btn-editar:hover{ background: linear-gradient(180deg, #e0e7ff, #c7d2fe); }

.btn-eliminar{
    background: linear-gradient(180deg, #fff1f2, #ffe4e6);
    border-color: #fecdd3;
    color: #9f1239;
}
.btn-eliminar:hover{ background: linear-gradient(180deg, #ffe4e6, #fecdd3); }

@media (max-width: 980px){
    .edit-card{ flex-direction: column; }
    .edit-left{ width: 100%; min-width: auto; }
    .form-grid{ grid-template-columns: 1fr; }
}

@media (min-width: 1000px) {
    th:nth-child(5) { width: 340px; }
}
</style>
</head>

<body>
<div class="canvas-bg"></div>

<div class="layout">

    <div class="header">
        <a href="profesional.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Gestión de usuarios</div>
    </div>

    <div class="page-content">
        <div class="panel">

            <div class="panel-header">
                <h1>Panel de Gestión de Usuarios</h1>
                <div class="actions">

                    <div class="filter-buttons" aria-label="Filtro por rol">
                        <span class="label">Ver:</span>

                        <a class="<?= $filtro_rol==="todos" ? "active" : "" ?>"
                           href="gestionar_users.php<?= htmlspecialchars(qs_keep(["filtro_rol" => "todos"])) ?>">
                            <i class="fas fa-layer-group"></i> Todos
                        </a>

                        <a class="<?= $filtro_rol==="usuario" ? "active" : "" ?>"
                           href="gestionar_users.php<?= htmlspecialchars(qs_keep(["filtro_rol" => "usuario"])) ?>">
                            <i class="fas fa-user"></i> Usuarios
                        </a>

                        <a class="<?= $filtro_rol==="familiar" ? "active" : "" ?>"
                           href="gestionar_users.php<?= htmlspecialchars(qs_keep(["filtro_rol" => "familiar"])) ?>">
                            <i class="fas fa-users"></i> Familiares
                        </a>

                        <a class="<?= $filtro_rol==="profesional" ? "active" : "" ?>"
                           href="gestionar_users.php<?= htmlspecialchars(qs_keep(["filtro_rol" => "profesional"])) ?>">
                            <i class="fas fa-user-tie"></i> Profesionales
                        </a>
                    </div>

                    <a class="btn btn-primary" href="crear_usuario.php">
                        <i class="fas fa-plus"></i> Crear usuario
                    </a>
                </div>
            </div>

            <?php if ($flash_success): ?>
                <div class="flash success"><?= htmlspecialchars($flash_success) ?></div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div class="flash error"><?= htmlspecialchars($flash_error) ?></div>
            <?php endif; ?>

            <?php if ($editar_user): ?>
                <?php
                    $foto_edit = $editar_user["foto"] ?? "default.png";
                    if ($foto_edit === "") $foto_edit = "default.png";
                    $ruta_foto_edit = "uploads/" . $foto_edit;

                    $cache_edit = "";
                    if (file_exists($ruta_foto_edit)) {
                        $cache_edit = "?v=" . filemtime($ruta_foto_edit);
                    }
                ?>
                <div class="edit-card">
                    <div class="edit-left">
                        <img class="edit-photo" src="<?= htmlspecialchars($ruta_foto_edit) . $cache_edit ?>" alt="Foto">
                        <div class="meta">
                            <div><strong>Rol:</strong> <?= htmlspecialchars($editar_user["rol"]) ?></div>
                        </div>
                    </div>

                    <div class="edit-right">
                        <a class="close-edit" href="gestionar_users.php<?= htmlspecialchars(qs_keep()) ?>" title="Cerrar">✕</a>
                        <h2>Editar usuario: <?= htmlspecialchars($editar_user["nombre"]) ?></h2>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
                            <input type="hidden" name="update_id" value="<?= (int)$editar_user["id"] ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="nombre" value="<?= htmlspecialchars($editar_user["nombre"]) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($editar_user["email"]) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Rol</label>
                                    <select name="rol" required>
                                        <option value="usuario" <?= $editar_user["rol"]==="usuario"?"selected":"" ?>>Usuario</option>
                                        <option value="familiar" <?= $editar_user["rol"]==="familiar"?"selected":"" ?>>Familiar</option>
                                        <option value="profesional" <?= $editar_user["rol"]==="profesional"?"selected":"" ?>>Profesional</option>
                                    </select>
                                </div>

                                <?php if ($editar_user["rol"] === "usuario"): ?>
                                <div class="form-group">
                                    <label>Asignar/Cambiar Familiar</label>
                                    <select name="familiar_id">
                                        <option value="">Sin familiar asignado</option>
                                        <?php
                                        // Buscamos todos los usuarios que tengan el rol "familiar"
                                        $stmt_f = $conexion->query("SELECT id, nombre FROM usuarios WHERE rol = 'familiar' ORDER BY nombre ASC");
                                        $familiares = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
                                        foreach($familiares as $fam): ?>
                                            <option value="<?= $fam['id'] ?>" <?= (isset($editar_user['familiar_id']) && $editar_user['familiar_id'] == $fam['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($fam['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label>Nueva contraseña (opcional)</label>
                                    <input type="password" name="password" placeholder="Dejar vacío para no cambiarla">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($editar_user["rol"] === "usuario"): ?>
                                <div class="form-group">
                                    <label>Nueva contraseña (opcional)</label>
                                    <input type="password" name="password" placeholder="Dejar vacío para no cambiarla">
                                </div>
                                <?php endif; ?>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Nueva foto (opcional)</label>
                                    <input type="file" name="foto" accept="image/jpg, image/jpeg, image/png, image/webp">
                                </div>
                            </div>

                            <div class="form-actions">
                                <a class="btn-cancel" href="gestionar_users.php<?= htmlspecialchars(qs_keep()) ?>">Cancelar</a>
                                <button class="btn btn-save" type="submit">
                                    <i class="fas fa-save"></i> Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:100px;">Foto</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th style="width:130px;">Rol</th>
                            <th style="width:260px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($usuarios as $u):
                        $foto = $u["foto"] ?? "default.png";
                        if ($foto === "") $foto = "default.png";
                        $ruta_foto = "uploads/" . $foto;
                        $cache_row = "";
                        if (file_exists($ruta_foto)) { $cache_row = "?v=" . filemtime($ruta_foto); }
                    ?>
                        <tr>
                            <td><img class="user-photo" src="<?= htmlspecialchars($ruta_foto) . $cache_row ?>" alt="Foto"></td>
                            <td><?= htmlspecialchars($u["nombre"]) ?></td>
                            <td><?= htmlspecialchars($u["email"]) ?></td>
                            <td><span class="role-badge <?= htmlspecialchars($u["rol"]) ?>"><?= htmlspecialchars($u["rol"]) ?></span></td>
                            <td>
                                <div class="action-row">
                                    <?php if ($u['rol'] === 'usuario'): ?>
                                        <a class="action-btn btn-evaluar" href="evaluar_usuario.php<?= htmlspecialchars(qs_keep(["user_id" => (int)$u["id"]])) ?>"><i class="fas fa-cog"></i> Evaluar</a>
                                    <?php endif; ?>
                                    <a class="action-btn btn-editar" href="gestionar_users.php<?= htmlspecialchars(qs_keep(["editar_id" => (int)$u["id"]])) ?>"><i class="fas fa-pen"></i> Editar</a>
                                    <a class="action-btn btn-eliminar" href="gestionar_users.php<?= htmlspecialchars(qs_keep(["eliminar_id" => (int)$u["id"]])) ?>" onclick="return confirm('¿Seguro?');"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>