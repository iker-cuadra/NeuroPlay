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
    if (isset($_GET["p"])) {
        $keep["p"] = $_GET["p"];
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
        $stmt_msg = $conexion->prepare("DELETE FROM mensajes WHERE remitente_id = ? OR destinatario_id = ?");
        $stmt_msg->execute([$eliminar_id, $eliminar_id]);

        $stmt_foto = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmt_foto->execute([$eliminar_id]);
        $foto_a_eliminar = $stmt_foto->fetchColumn();

        $defaults = ["default.png", "default_usuario.png", "default_familiar.png"];
        if (!empty($foto_a_eliminar) && !in_array($foto_a_eliminar, $defaults, true) && file_exists('uploads/' . $foto_a_eliminar)) {
            unlink('uploads/' . $foto_a_eliminar);
        }

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
    $familiar_id = (!empty($_POST["familiar_id"])) ? (int)$_POST["familiar_id"] : null;

    if ($id <= 0 || $nombre === "" || $email === "" || $rol === "") {
        $_SESSION["flash_error"] = "Nombre, email y rol son obligatorios.";
        header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
        exit;
    }

    $stmt = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $foto_actual = $stmt->fetchColumn();

    if ($foto_actual === false) {
        $_SESSION["flash_error"] = "El usuario no existe.";
        header("Location: gestionar_users.php" . qs_keep());
        exit;
    }

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?");
    $stmt->execute([$email, $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $_SESSION["flash_error"] = "Ese email ya está registrado por otro usuario.";
        header("Location: gestionar_users.php" . qs_keep(["editar_id" => $id]));
        exit;
    }

    $foto_nueva = $foto_actual ?: "default.png";
    if (isset($_FILES["foto"]) && $_FILES["foto"]["name"] !== "") {
        if ($_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
            $foto_nueva = uniqid("foto_", true) . "." . $ext;
            move_uploaded_file($_FILES["foto"]["tmp_name"], "uploads/" . $foto_nueva);
            $defaults = ["default.png", "default_usuario.png", "default_familiar.png"];
            if (!empty($foto_actual) && !in_array($foto_actual, $defaults, true) && file_exists("uploads/" . $foto_actual)) {
                unlink("uploads/" . $foto_actual);
            }
        }
    }

    try {
        if ($newPassword !== "") {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, password_hash = ?, foto = ?, familiar_id = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $hash, $foto_nueva, $familiar_id, $id]);
        } else {
            $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, foto = ?, familiar_id = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $foto_nueva, $familiar_id, $id]);
        }
        $_SESSION["flash_success"] = "Usuario actualizado correctamente.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al actualizar: " . $e->getMessage();
    }

    header("Location: gestionar_users.php" . qs_keep());
    exit;
}

// -------------------------
// LÓGICA DE PAGINACIÓN
// -------------------------
$por_pagina = 11;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;

if ($filtro_rol === "todos") {
    $stmt_count = $conexion->query("SELECT COUNT(*) FROM usuarios");
} else {
    $stmt_count = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = ?");
    $stmt_count->execute([$filtro_rol]);
}
$total_usuarios = $stmt_count->fetchColumn();
$total_paginas = ceil($total_usuarios / $por_pagina);

// -------------------------
// USER A EDITAR (GET)
// -------------------------
$editar_user = null;
if (isset($_GET["editar_id"])) {
    $editar_id = (int)$_GET["editar_id"];
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto, familiar_id FROM usuarios WHERE id = ?");
    $stmt->execute([$editar_id]);
    $editar_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// -------------------------
// LISTA USUARIOS (con filtro y LIMIT)
// -------------------------
if ($filtro_rol === "todos") {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC LIMIT $por_pagina OFFSET $offset");
    $stmt->execute();
} else {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE rol = ? ORDER BY id DESC LIMIT $por_pagina OFFSET $offset");
    $stmt->execute([$filtro_rol]);
}
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    background-color: transparent;
}
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

/* ANIMACIÓN VOLVER */
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
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.back-arrow:hover { transform: scale(1.2) translateX(-3px); }

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
}
.panel-header{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.panel-header h1{ margin: 0; font-size: 22px; font-weight: 800; }
.actions{ display: flex; gap: 10px; align-items: center; }

/* ANIMACIÓN BOTÓN NUEVO */
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
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    font-size: 14px;
}
.btn-primary{ background: var(--btn); color: white; box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
.btn-primary:hover{ background: var(--btn-hover); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.btn-primary:active{ transform: translateY(-1px); }

/* ANIMACIÓN FILTROS */
.filter-buttons{
    display: inline-flex;
    gap: 8px;
    align-items: center;
    background: #eef0f3;
    border-radius: 16px;
    padding: 6px;
    border: 1px solid #dde2ea;
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
    transition: all .25s ease;
}
.filter-buttons a:hover:not(.active) { background: #f8f9fa; transform: translateY(-1px); }
.filter-buttons a.active{ background: #4a4a4a; color: #fff; border-color: #4a4a4a; transform: scale(1.05); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

/* ANIMACIÓN FILAS Y BOTONES ACCIÓN */
tbody tr{ background: #fff; border-radius: 18px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); transition: all 0.3s ease; }
tbody tr:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }

.action-btn{ 
    display: inline-flex; 
    align-items: center; 
    justify-content: center;
    gap: 8px; 
    padding: 10px 14px; 
    border-radius: 14px; 
    text-decoration: none; 
    font-weight: 900; 
    font-size: 13px; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
}
.action-btn:hover { transform: translateY(-4px) scale(1.1); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
.action-btn:active { transform: scale(0.9); }

.btn-evaluar{ background: linear-gradient(180deg, #ecfdf5, #dcfce7); color: #166534; }
.btn-editar{ background: linear-gradient(180deg, #eef2ff, #e0e7ff); color: #1e3a8a; }
.btn-eliminar{ background: linear-gradient(180deg, #fff1f2, #ffe4e6); color: #9f1239; }

/* ANIMACIÓN Y DISEÑO PAGINACIÓN */
.pagination-container { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 15px 0; margin-top: auto; }
.page-link { min-width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #fff; color: #4a4a4a; text-decoration: none; border-radius: 12px; font-weight: 700; border: 1px solid #e7e9ee; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.3s ease; }
.page-link:hover:not(.disabled):not(.active) { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-color: #4a4a4a; }
.page-link.active { background: #4a4a4a; color: #fff; border-color: #4a4a4a; box-shadow: 0 8px 20px rgba(74,74,74,0.3); transform: scale(1.1); }
.page-link.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; background: #f0f0f0; }

.table-wrap{ width: 100%; overflow-x: auto; flex: 1 1 auto; overflow-y: auto; }
table{ width: 100%; border-collapse: separate; border-spacing: 0 12px; }
thead th{ text-align: left; font-size: 13px; color: var(--muted); font-weight: 800; padding: 10px 12px; background: white; border-bottom: 1px solid #eceff3; position: sticky; top: 0; z-index: 2; }
.user-photo{ width: 58px; height: 58px; border-radius: 50%; object-fit: cover; border: 3px solid #f0f0f0; }
.role-badge{ display: inline-block; padding: 6px 10px; border-radius: 10px; font-size: 12px; font-weight: 900; text-transform: capitalize; }
.role-badge.usuario{ background:#e0f7fa; color:#00796b; }
.role-badge.familiar{ background:#fce4ec; color:#ad1457; }
.role-badge.profesional{ background:#e8f5e9; color:#2e7d32; }

/* Otros estilos de la edición... */
.edit-card{ border: 1px solid #eef0f3; background: #fafbfc; border-radius: 18px; padding: 14px; display: flex; gap: 14px; align-items: stretch; flex: 0 0 auto; margin-bottom: 10px;}
.edit-left{ width: 220px; background: white; border-radius: 16px; padding: 12px; box-shadow: 0 6px 16px rgba(0,0,0,0.06); display: flex; flex-direction: column; align-items: center; justify-content: center; }
.edit-photo{ width: 92px; height: 92px; border-radius: 50%; object-fit: cover; border: 3px solid #f0f0f0; margin-bottom: 10px; }
.edit-right{ flex: 1; background: white; border-radius: 16px; padding: 12px; position: relative; }
.close-edit{ position: absolute; top: 10px; right: 10px; text-decoration: none; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #eef0f3; color: #333; font-weight: 900; transition: all 0.2s; }
.close-edit:hover { background: #e0e2e6; transform: rotate(90deg); }
.form-grid{ display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group label{ display: block; font-size: 12px; font-weight: 700; color: #444; margin-bottom: 6px; }
.form-group input, .form-group select{ width: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid #dde2ea; font-size: 14px; transition: border 0.2s; }
</style>
</head>

<body>
<div class="canvas-bg"></div>
<div class="layout">
    <div class="header">
        <a href="profesional.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white"><path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/></svg>
        </a>
        <div class="user-role">Gestión de usuarios</div>
    </div>

    <div class="page-content">
        <div class="panel">
            <div class="panel-header">
                <h1>Panel de Gestión de Usuarios</h1>
                <div class="actions">
                    <div class="filter-buttons">
                        <span style="font-weight:900; padding:0 8px;">Ver:</span>
                        <a class="<?= $filtro_rol==="todos"?"active":"" ?>" href="gestionar_users.php<?= qs_keep(["filtro_rol"=>"todos", "p"=>1]) ?>"><i class="fas fa-layer-group"></i> Todos</a>
                        <a class="<?= $filtro_rol==="usuario"?"active":"" ?>" href="gestionar_users.php<?= qs_keep(["filtro_rol"=>"usuario", "p"=>1]) ?>"><i class="fas fa-user"></i> Usuarios</a>
                        <a class="<?= $filtro_rol==="familiar"?"active":"" ?>" href="gestionar_users.php<?= qs_keep(["filtro_rol"=>"familiar", "p"=>1]) ?>"><i class="fas fa-users"></i> Familiares</a>
                        <a class="<?= $filtro_rol==="profesional"?"active":"" ?>" href="gestionar_users.php<?= qs_keep(["filtro_rol"=>"profesional", "p"=>1]) ?>"><i class="fas fa-user-tie"></i> Pros</a>
                    </div>
                    <a class="btn btn-primary" href="crear_usuario.php"><i class="fas fa-plus"></i> Crear usuario</a>
                </div>
            </div>

            <?php if ($flash_success): ?><div class="flash success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
            <?php if ($flash_error): ?><div class="flash error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

            <?php if ($editar_user): ?>
                <div class="edit-card">
                    <div class="edit-left">
                        <img class="edit-photo" src="uploads/<?= $editar_user['foto'] ?: 'default.png' ?>">
                        <div style="font-size:13px; color:var(--muted)"><strong>Rol:</strong> <?= $editar_user["rol"] ?></div>
                    </div>
                    <div class="edit-right">
                        <a class="close-edit" href="gestionar_users.php<?= qs_keep() ?>">✕</a>
                        <h2 style="font-size:16px; margin-bottom:10px;">Editar: <?= htmlspecialchars($editar_user["nombre"]) ?></h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="update_id" value="<?= $editar_user["id"] ?>">
                            <div class="form-grid">
                                <div class="form-group"><label>Nombre</label><input type="text" name="nombre" value="<?= htmlspecialchars($editar_user["nombre"]) ?>" required></div>
                                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($editar_user["email"]) ?>" required></div>
                                <div class="form-group">
                                    <label>Rol</label>
                                    <select name="rol">
                                        <option value="usuario" <?= $editar_user["rol"]=="usuario"?"selected":"" ?>>Usuario</option>
                                        <option value="familiar" <?= $editar_user["rol"]=="familiar"?"selected":"" ?>>Familiar</option>
                                        <option value="profesional" <?= $editar_user["rol"]=="profesional"?"selected":"" ?>>Profesional</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Contraseña (opcional)</label><input type="password" name="password"></div>
                            </div>
                            <div style="margin-top:10px; text-align:right;">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:100px; text-align:center;">Foto</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th style="width:130px;">Rol</th>
                            <th style="width:260px; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td style="text-align:center;"><img class="user-photo" src="uploads/<?= $u['foto'] ?: 'default.png' ?>"></td>
                            <td><?= htmlspecialchars($u["nombre"]) ?></td>
                            <td><?= htmlspecialchars($u["email"]) ?></td>
                            <td><span class="role-badge <?= $u["rol"] ?>"><?= $u["rol"] ?></span></td>
                            <td>
                                <div style="display:flex; gap:10px; justify-content:center;">
                                    <?php if ($u['rol']==='usuario'): ?>
                                        <a class="action-btn btn-evaluar" href="evaluar_usuario.php<?= qs_keep(["user_id"=>$u["id"]]) ?>"><i class="fas fa-cog"></i></a>
                                    <?php endif; ?>
                                    <a class="action-btn btn-editar" href="gestionar_users.php<?= qs_keep(["editar_id"=>$u["id"]]) ?>"><i class="fas fa-pen"></i></a>
                                    <a class="action-btn btn-eliminar" href="gestionar_users.php<?= qs_keep(["eliminar_id"=>$u["id"]]) ?>" onclick="return confirm('¿Seguro?');"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <?php if ($pagina_actual <= 1): ?>
                    <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                <?php else: ?>
                    <a href="<?= qs_keep(['p' => $pagina_actual - 1]) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="<?= qs_keep(['p' => $i]) ?>" class="page-link <?= ($i == $pagina_actual) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($pagina_actual >= $total_paginas): ?>
                    <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                <?php else: ?>
                    <a href="<?= qs_keep(['p' => $pagina_actual + 1]) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>