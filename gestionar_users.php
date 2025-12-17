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

        // 3) Eliminar archivo foto si aplica
        if (!empty($foto_a_eliminar) && $foto_a_eliminar !== 'default.png' && file_exists('uploads/' . $foto_a_eliminar)) {
            unlink('uploads/' . $foto_a_eliminar);
        }

        // 4) Eliminar usuario
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$eliminar_id]);

        $_SESSION["flash_success"] = "Usuario eliminado correctamente.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al eliminar: " . $e->getMessage();
    }

    header("Location: gestionar_users.php");
    exit;
}

// -------------------------
// ACTUALIZAR USUARIO (POST)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_id"])) {

    // CSRF check
    if (!isset($_POST["csrf"]) || $_POST["csrf"] !== $_SESSION["csrf"]) {
        $_SESSION["flash_error"] = "Token inválido. Recarga la página e inténtalo de nuevo.";
        header("Location: gestionar_users.php");
        exit;
    }

    $id = (int)($_POST["update_id"] ?? 0);
    $nombre = trim($_POST["nombre"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $rol = trim($_POST["rol"] ?? "usuario");
    $newPassword = $_POST["password"] ?? "";

    if ($id <= 0 || $nombre === "" || $email === "" || $rol === "") {
        $_SESSION["flash_error"] = "Nombre, email y rol son obligatorios.";
        header("Location: gestionar_users.php?editar_id=" . $id);
        exit;
    }

    // Verificar que el usuario existe y coger su foto actual
    $stmt = $conexion->prepare("SELECT foto FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $foto_actual = $stmt->fetchColumn();

    if ($foto_actual === false) {
        $_SESSION["flash_error"] = "El usuario no existe.";
        header("Location: gestionar_users.php");
        exit;
    }

    // Verificar email único (excepto el propio usuario)
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?");
    $stmt->execute([$email, $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $_SESSION["flash_error"] = "Ese email ya está registrado por otro usuario.";
        header("Location: gestionar_users.php?editar_id=" . $id);
        exit;
    }

    // Manejo de nueva foto (opcional)
    $foto_nueva = $foto_actual ?: "default.png";

    if (!empty($_FILES["foto"]["name"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
        $permitidos = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($ext, $permitidos)) {
            $_SESSION["flash_error"] = "Formato de imagen no permitido (JPG, PNG, WEBP).";
            header("Location: gestionar_users.php?editar_id=" . $id);
            exit;
        }

        $foto_nueva = uniqid("foto_") . "." . $ext;

        if (!move_uploaded_file($_FILES["foto"]["tmp_name"], "uploads/" . $foto_nueva)) {
            $_SESSION["flash_error"] = "No se pudo subir la imagen (permisos o ruta).";
            header("Location: gestionar_users.php?editar_id=" . $id);
            exit;
        }

        // borrar foto anterior si era subida (no default)
        if (!empty($foto_actual) && $foto_actual !== "default.png" && file_exists("uploads/" . $foto_actual)) {
            unlink("uploads/" . $foto_actual);
        }
    }

    try {
        // Construir UPDATE dinámico (contraseña opcional)
        if ($newPassword !== "") {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("
                UPDATE usuarios
                SET nombre = ?, email = ?, rol = ?, password_hash = ?, foto = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $email, $rol, $hash, $foto_nueva, $id]);
        } else {
            $stmt = $conexion->prepare("
                UPDATE usuarios
                SET nombre = ?, email = ?, rol = ?, foto = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $email, $rol, $foto_nueva, $id]);
        }

        $_SESSION["flash_success"] = "Usuario actualizado correctamente.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al actualizar: " . $e->getMessage();
    }

    header("Location: gestionar_users.php?editar_id=" . $id);
    exit;
}

// -------------------------
// USER A EDITAR (GET)
// -------------------------
$editar_user = null;
if (isset($_GET["editar_id"])) {
    $editar_id = (int)$_GET["editar_id"];
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE id = ?");
    $stmt->execute([$editar_id]);
    $editar_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// -------------------------
// LISTA USUARIOS
// -------------------------
$stmt = $conexion->query("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    --footer-h: 160px;
    --bg: #f0f2f5;
    --card: #ffffff;
    --shadow: 0 12px 30px rgba(0,0,0,0.10);
    --radius: 20px;
    --text: #1d1d1f;
    --muted: #6c6c6c;
    --btn: #4a4a4a;
    --btn-hover: #5a5a5a;
}

*{ box-sizing: border-box; }

html, body{
    margin: 0;
    padding: 0;
    height: auto;
}

body{
    font-family: 'Poppins', sans-serif;
    background: var(--bg);
    overflow: auto;
    color: var(--text);
}

.layout{
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* HEADER banner */
.header{
    width: 100%;
    height: var(--header-h);
    background-image: url('imagenes/Banner.svg');
    background-size: cover;
    background-position: center;
    position: relative;
}

/* Flecha volver */
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

/* etiqueta inferior */
.user-role{
    position: absolute;
    bottom: 10px;
    left: 20px;
    color: white;
    font-weight: 700;
    font-size: 18px;
}

/* Central */
.page-content{
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 14px 16px;
}

/* Panel blanco */
.panel{
    width: min(1200px, 95vw);
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 14px;
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

.btn-ghost{
    background: #eef0f3;
    color: #333;
}
.btn-ghost:hover{ background: #e2e5ea; transform: translateY(-1px); }
.btn-ghost:active{ transform: scale(0.98); }

/* Flash */
.flash{
    padding: 10px 12px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 14px;
}
.flash.success{ background:#e8fff0; color:#0a7a3a; border:1px solid #c9f2d7; }
.flash.error{ background:#ffecec; color:#c0392b; border:1px solid #ffd0d0; }

/* EDIT PANEL */
.edit-card{
    border: 1px solid #eef0f3;
    background: #fafbfc;
    border-radius: 18px;
    padding: 14px;
    display: flex;
    gap: 14px;
    align-items: stretch;
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
    width: 86px;
    height: 86px;
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

/* Form horizontal */
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
}
.btn-cancel:hover{ background:#e2e5ea; transform: translateY(-1px); }
.btn-cancel:active{ transform: scale(0.98); }

/* Tabla dentro del panel */
.table-wrap{
    width: 100%;
    overflow-x: auto;
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
}

th:nth-child(1), th:nth-child(2), th:nth-child(6){ text-align:center; }

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

td:nth-child(1), td:nth-child(2), td:nth-child(6){ text-align:center; }

.user-photo{
    width: 46px;
    height: 46px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #f0f0f0;
}

/* Rol badge */
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

/* Acciones */
.action-row{
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn-edit{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 900;
    background: #e8f0ff;
    border: 1px solid #d7e4ff;
    color: #1e4db7;
    transition: transform .2s ease, filter .2s ease;
}
.btn-edit:hover{ transform: translateY(-1px); filter: brightness(1.03); }
.btn-edit:active{ transform: scale(0.95); }

.btn-eliminar{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 12px;
    text-decoration: none;
    color: #f75555;
    font-weight: 900;
    background: #ffebeb;
    border: 1px solid #ffd2d2;
    transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    white-space: nowrap;
}
.btn-eliminar:hover{
    background:#ff4d4f;
    color:#fff;
    transform: scale(1.03);
}
.btn-eliminar:active{ transform: scale(0.95); }

@media (max-width: 980px){
    .edit-card{ flex-direction: column; }
    .edit-left{ width: 100%; min-width: auto; }
    .form-grid{ grid-template-columns: 1fr; }
}

/* Ajuste de ancho para la columna de acciones tras añadir el botón "Evaluar" */
@media (min-width: 1000px) {
    th:nth-child(6) {
        width: 340px; /* Suficiente espacio para 3 botones */
    }
}
</style>
</head>

<body>
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
                    $ruta_foto_edit = 'uploads/default.png';
                    if ($editar_user['rol'] === 'profesional' && $editar_user['foto'] === 'default.png') {
                        $ruta_foto_edit = 'imagenes/admin.jpg';
                    } elseif (!empty($editar_user['foto']) && $editar_user['foto'] !== 'default.png') {
                        $ruta_foto_edit = 'uploads/' . htmlspecialchars($editar_user['foto']);
                    }
                ?>
                <div class="edit-card">
                    <div class="edit-left">
                        <img class="edit-photo" src="<?= $ruta_foto_edit ?>" alt="Foto">
                        <div class="meta">
                            <div><strong>ID:</strong> <?= (int)$editar_user["id"] ?></div>
                            <div><strong>Rol:</strong> <?= htmlspecialchars($editar_user["rol"]) ?></div>
                        </div>
                    </div>

                    <div class="edit-right">
                        <a class="close-edit" href="gestionar_users.php" title="Cerrar">✕</a>
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

                                <div class="form-group">
                                    <label>Nueva contraseña (opcional)</label>
                                    <input type="password" name="password" placeholder="Dejar vacío para no cambiarla">
                                </div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Nueva foto (opcional)</label>
                                    <input type="file" name="foto" accept="image/jpg, image/jpeg, image/png, image/webp">
                                </div>
                            </div>

                            <div class="form-actions">
                                <a class="btn btn-cancel" href="gestionar_users.php">Cancelar</a>
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
                            <th style="width:70px;">ID</th>
                            <th style="width:80px;">Foto</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th style="width:130px;">Rol</th>
                            <th style="width:260px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($usuarios as $u):
                        $ruta_foto = 'uploads/default.png';
                        if ($u['rol'] === 'profesional' && $u['foto'] === 'default.png') {
                            $ruta_foto = 'imagenes/admin.jpg';
                        } elseif (!empty($u['foto']) && $u['foto'] !== 'default.png') {
                            $ruta_foto = 'uploads/' . htmlspecialchars($u['foto']);
                        }
                    ?>
                        <tr>
                            <td><?= (int)$u["id"] ?></td>
                            <td>
                                <img class="user-photo"
                                     src="<?= $ruta_foto ?>"
                                     alt="Foto de <?= htmlspecialchars($u["nombre"]) ?>">
                            </td>
                            <td><?= htmlspecialchars($u["nombre"]) ?></td>
                            <td><?= htmlspecialchars($u["email"]) ?></td>
                            <td>
                                <span class="role-badge <?= htmlspecialchars($u["rol"]) ?>">
                                    <?= htmlspecialchars($u["rol"]) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-row">
                                    <?php if ($u['rol'] === 'usuario'): ?>
                                        <a class="btn-edit" href="evaluar_usuario.php?user_id=<?= (int)$u["id"] ?>" title="Ver Evaluación">
                                            <i class="fas fa-cog"></i> Evaluar
                                        </a>
                                    <?php endif; ?>

                                    <a class="btn-edit" href="gestionar_users.php?editar_id=<?= (int)$u["id"] ?>">
                                        <i class="fas fa-pen"></i> Editar
                                    </a>

                                    <a class="btn-eliminar"
                                       href="gestionar_users.php?eliminar_id=<?= (int)$u['id'] ?>"
                                       onclick="return confirm('¿Seguro que deseas eliminar a <?= htmlspecialchars($u['nombre']) ?>? Esta acción eliminará permanentemente todos sus datos, incluyendo el historial de chat.');">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
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
