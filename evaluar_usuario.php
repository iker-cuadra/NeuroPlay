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
// PROCESAR GUARDADO DE DIFICULTADES (POST)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_evaluation"])) {
    $user_id_post   = (int)($_POST["user_id"] ?? 0);
    $profesional_id = $_SESSION["usuario_id"] ?? 0;
    $fecha          = date('Y-m-d H:i:s');

    // Obtener los valores de dificultad del POST
    $memoria      = trim($_POST["memoria"] ?? "Fácil");
    $logica       = trim($_POST["logica"] ?? "Fácil");
    $razonamiento = trim($_POST["razonamiento"] ?? "Fácil");
    $atencion     = trim($_POST["atencion"] ?? "Fácil"); 

    try {
        if ($user_id_post > 0 && $profesional_id > 0) {

            // 1. Intentar actualizar la única fila para este usuario (UPSERT)
            $stmt_update = $conexion->prepare("
                UPDATE dificultades_asignadas
                SET dificultad_memoria       = ?,
                    dificultad_logica        = ?,
                    dificultad_razonamiento  = ?,
                    dificultad_atencion      = ?,
                    asignado_por             = ?,
                    fecha_actualizacion      = ?
                WHERE usuario_id = ?
            ");
            $stmt_update->execute([
                $memoria,
                $logica,
                $razonamiento,
                $atencion,
                $profesional_id,
                $fecha,
                $user_id_post
            ]);

            // 2. Si no se actualizó ninguna fila (rowCount === 0), insertamos una nueva
            if ($stmt_update->rowCount() === 0) {
                $stmt_insert = $conexion->prepare("
                    INSERT INTO dificultades_asignadas
                        (usuario_id,
                         dificultad_memoria,
                         dificultad_logica,
                         dificultad_razonamiento,
                         dificultad_atencion,
                         asignado_por,
                         fecha_actualizacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([
                    $user_id_post,
                    $memoria,
                    $logica,
                    $razonamiento,
                    $atencion,
                    $profesional_id,
                    $fecha
                ]);
            }

            $_SESSION["flash_success"] = "Niveles de dificultad guardados correctamente.";
        } else {
            $_SESSION["flash_error"] = "Error: ID de usuario o profesional no válido.";
        }
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error al guardar dificultades: " . $e->getMessage();
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: evaluar_usuario.php?user_id=" . $user_id_post);
    exit;
}

// -------------------------
// OBTENER ID DE USUARIO Y DATOS DE PERFIL (MODIFICADO CON JOIN PARA EL FAMILIAR)
// -------------------------
$user_id = (int)($_GET['user_id'] ?? 0);
$usuario = null;

if ($user_id > 0) {
    try {
        // Realizamos un LEFT JOIN con la misma tabla para obtener el nombre del familiar
        $stmt = $conexion->prepare("
            SELECT u1.id, u1.nombre, u1.email, u1.rol, u1.foto, u2.nombre AS nombre_familiar 
            FROM usuarios u1 
            LEFT JOIN usuarios u2 ON u1.familiar_id = u2.id 
            WHERE u1.id = ?
        ");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error de base de datos: " . $e->getMessage());
    }
}

// Redirigir si el ID no es válido o el usuario no existe
if (!$usuario) {
    header("Location: gestionar_users.php");
    exit;
}

// Lógica para determinar la ruta de la foto
$ruta_foto = 'uploads/default.png';
if ($usuario['rol'] === 'profesional' && $usuario['foto'] === 'default.png') {
    $ruta_foto = 'imagenes/admin.jpg';
} elseif (!empty($usuario['foto']) && $usuario['foto'] !== 'default.png') {
    $ruta_foto = 'uploads/' . htmlspecialchars($usuario['foto']);
}

// ----------------------------------------------------
// CARGAR DATOS DE DIFICULTADES (tabla dificultades_asignadas)
// ----------------------------------------------------
$stmt_eval = $conexion->prepare("
    SELECT dificultad_memoria,
           dificultad_logica,
           dificultad_razonamiento,
           dificultad_atencion,
           fecha_actualizacion,
           asignado_por,
           (SELECT nombre FROM usuarios WHERE id = asignado_por) AS asignador_nombre
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt_eval->execute([$user_id]);
$res = $stmt_eval->fetch(PDO::FETCH_ASSOC);

// Definición de niveles para el dropdown y por defecto
$niveles_opciones = [
    'Fácil'       => 'Fácil',
    'Intermedio'  => 'Intermedio',
    'Difícil'     => 'Difícil'
];

// Configuración de niveles por defecto
$niveles_actuales = [
    'memoria'      => ['nivel' => 'Fácil', 'asignador' => 'N/A', 'fecha' => 'N/A'],
    'logica'       => ['nivel' => 'Fácil', 'asignador' => 'N/A', 'fecha' => 'N/A'],
    'razonamiento' => ['nivel' => 'Fácil', 'asignador' => 'N/A', 'fecha' => 'N/A'],
    'atencion'     => ['nivel' => 'Fácil', 'asignador' => 'N/A', 'fecha' => 'N/A'],
];
$ultima_actualizacion = 'N/A';
$ultimo_profesional   = 'N/A';

if ($res) {
    $niveles_actuales['memoria']['nivel']      = htmlspecialchars($res['dificultad_memoria']      ?: 'Fácil');
    $niveles_actuales['logica']['nivel']       = htmlspecialchars($res['dificultad_logica']       ?: 'Fácil');
    $niveles_actuales['razonamiento']['nivel'] = htmlspecialchars($res['dificultad_razonamiento'] ?: 'Fácil');
    $niveles_actuales['atencion']['nivel']     = htmlspecialchars($res['dificultad_atencion']     ?: 'Fácil');

    $asignador        = htmlspecialchars($res['asignador_nombre']);
    $fecha_raw        = strtotime($res['fecha_actualizacion']);
    $fecha_formateada = $fecha_raw ? date('d/m/Y H:i', $fecha_raw) : 'N/A';

    foreach ($niveles_actuales as $key => $valor) {
        $niveles_actuales[$key]['asignador'] = $asignador ?: 'N/A';
        $niveles_actuales[$key]['fecha']     = $fecha_formateada;
    }

    $ultima_actualizacion = $fecha_formateada;
    $ultimo_profesional   = $asignador ?: 'N/A';
}

// ----------------------------------------------------
// HISTORIAL DE RESULTADOS (tabla resultados_juego)
// ----------------------------------------------------
$stmt_hist = $conexion->prepare("
    SELECT id, tipo_juego,
           puntuacion,
           tiempo_segundos,
           dificultad,
           fecha_juego
    FROM resultados_juego
    WHERE usuario_id = ?
    ORDER BY fecha_juego DESC
    LIMIT 20
");
$stmt_hist->execute([$user_id]);
$historialResultados = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener las rondas de razonamiento
function obtenerDetalleRondas($conexion, $resultado_id) {
    $stmt = $conexion->prepare("SELECT ronda, correcta, tiempo_segundos FROM razonamiento_rondas WHERE resultado_id = ? ORDER BY ronda ASC");
    $stmt->execute([$resultado_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para formatear segundos a mm:ss
function formatSecondsToMMSS($segundos) {
    $segundos = (int)$segundos;
    if ($segundos < 0) $segundos = 0;
    $m  = floor($segundos / 60);
    $s  = $segundos % 60;
    return sprintf('%02d:%02d', $m, $s);
}

// Leer y limpiar flash
$flash_success = $_SESSION["flash_success"];
$flash_error   = $_SESSION["flash_error"];
$_SESSION["flash_success"] = "";
$_SESSION["flash_error"]   = "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evaluación de <?= htmlspecialchars($usuario["nombre"]) ?></title>
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
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: auto; }
body { font-family: 'Poppins', sans-serif; background: #887d7dff; color: var(--text); }

.layout { min-height: 100vh; display: flex; flex-direction: column; }
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
.user-role {
    position: absolute; bottom: 10px; left: 20px;
    color: white; font-weight: 700; font-size: 18px;
}
.page-content {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 14px 16px;
}
.panel {
    width: min(1000px, 95vw);
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 14px;
}
.panel h1 {
    margin: 0 0 20px 0;
    font-size: 24px;
    font-weight: 800;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.flash{
    padding: 10px 12px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 15px;
}
.flash.success{ background:#e8fff0; color:#0a7a3a; border:1px solid #c9f2d7; }
.flash.error{ background:#ffecec; color:#c0392b; border:1px solid #ffd0d0; }
.profile-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: #fcfcfd;
    border-radius: 15px;
    border: 1px solid #eef0f3;
    margin-bottom: 20px;
}
.profile-card img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #7a7676;
    flex-shrink: 0;
}
.profile-info h2 {
    margin: 0 0 5px 0;
    font-size: 20px;
    font-weight: 700;
}
.profile-info p {
    margin: 0;
    font-size: 14px;
    color: var(--muted);
}

/* Nuevo estilo para la etiqueta del familiar */
.familiar-tag {
    display: block;
    margin-top: 5px;
    font-size: 13px;
    font-weight: 600;
    color: #ad1457;
}

.role-badge {
    margin-top: 8px;
    display: inline-block;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}
.role-badge.usuario{ background:#e0f7fa; color:#00796b; }
.role-badge.familiar{ background:#fce4ec; color:#ad1457; }
.role-badge.profesional{ background:#e8f5e9; color:#2e7d32; }

.evaluation-form {
    background: white;
    padding: 15px;
    border-radius: 15px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.evaluation-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-top: 10px;
    margin-bottom: 20px;
}

@media (max-width: 900px) { .evaluation-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { .evaluation-grid { grid-template-columns: 1fr; } }

.eval-item {
    padding: 15px;
    border-radius: 15px;
    border: 1px solid #e2e5ea;
    background: #fcfcfd;
}
.eval-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 700;
    color: #4a4a4a;
    display: flex;
    align-items: center; gap: 8px;
}
.current-level {
    font-size: 24px;
    font-weight: 800;
    color: #2e7d32;
    margin: 5px 0 10px 0;
}
.last-update-info {
    font-size: 12px;
    color: #999;
    line-height: 1.4;
    margin-top: 5px;
    padding-top: 8px;
    border-top: 1px dashed #eee;
}
.update-info {
    text-align: right; font-size: 13px; color: #4a4a4a; padding-bottom: 10px;
}
.update-info strong { color: #2e7d32; font-weight: 700; }
.form-actions {
    display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; flex-wrap: wrap;
}
.btn {
    display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 10px 14px; border-radius: 14px;
    text-decoration: none; font-weight: 700; border: none;
    cursor: pointer; transition: transform 0.2s ease, background 0.2s ease;
    white-space: nowrap; font-size: 14px;
}
.btn-save { background: #1e4db7; color: white; }
.btn-save:hover { background: #1a42a0; transform: translateY(-1px); }

/* HISTORIAL */
.history-card {
    margin-top: 24px; padding: 16px 18px; background: #fcfcfd; border-radius: 18px; border: 1px solid #eef0f3; box-shadow: 0 6px 16px rgba(0,0,0,0.04);
}
.history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.history-table thead th { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e3e6ec; color: #777; font-weight: 600; }
.history-table tbody td { padding: 8px 6px; border-bottom: 1px solid #f1f2f6; }
.history-tag { display: inline-block; padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; }
.history-tag.memoria { background:#e0f7fa; color:#006064; }
.history-tag.logica { background:#e8f5e9; color:#2e7d32; }
.history-tag.razonamiento { background:#fff3e0; color:#e65100; }
.history-tag.atencion { background:#ede7f6; color:#4527a0; }

/* Estilos para el desglose de rondas */
.rondas-container {
    padding: 15px;
    border-left: 5px solid #e65100;
    margin: 10px;
    background: #fff;
    border-radius: 8px;
}

.rondas-container h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #e65100;
}

.rondas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
}

.ronda-item {
    border: 1px solid #eee;
    padding: 8px 12px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    background: #fafafa;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ronda-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.ronda-item .ronda-num {
    font-weight: 700;
    font-size: 14px;
    color: #333;
}

.ronda-item .ronda-status {
    font-size: 18px;
}

.ronda-item .ronda-time {
    font-size: 11px;
    color: #666;
}

@media (max-width: 600px) {
    .rondas-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
</style>
</head>

<body>
<div class="layout">
    <div class="header">
        <a href="gestionar_users.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Evaluación del Usuario</div>
    </div>

    <div class="page-content">
        <div class="panel">

            <h1><i class="fas fa-chart-line"></i> Asignación de Niveles de Dificultad</h1>

            <?php if ($flash_success): ?>
                <div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash_success) ?></div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div class="flash error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($flash_error) ?></div>
            <?php endif; ?>

            <div class="profile-card">
                <img src="<?= $ruta_foto ?>" alt="Foto de <?= htmlspecialchars($usuario["nombre"]) ?>">
                <div class="profile-info">
                    <h2><?= htmlspecialchars($usuario["nombre"]) ?> (ID: <?= (int)$usuario["id"] ?>)</h2>

                    <?php if (!empty($usuario["nombre_familiar"])): ?>
                        <p class="familiar-tag">
                            <i class="fas fa-user-friends"></i> Familiar vinculado: <?= htmlspecialchars($usuario["nombre_familiar"]) ?>
                        </p>
                    <?php endif; ?>

                    <p>Email: <?= htmlspecialchars($usuario["email"]) ?></p>
                    <span class="role-badge <?= htmlspecialchars($usuario["rol"]) ?>">
                        <?= htmlspecialchars($usuario["rol"]) ?>
                    </span>
                </div>
            </div>

            <div class="update-info">
                Última actualización: <strong><?= $ultima_actualizacion ?></strong>.
                Asignado por: <strong><?= $ultimo_profesional ?></strong>
            </div>

            <form method="POST" class="evaluation-form">
                <input type="hidden" name="user_id" value="<?= (int)$usuario["id"] ?>">
                <input type="hidden" name="save_evaluation" value="1">

                <div class="evaluation-grid">
                    <?php 
                    $conf = [
                        'logica' => ['icon' => 'lightbulb', 'label' => 'Lógica'],
                        'memoria' => ['icon' => 'brain', 'label' => 'Memoria'],
                        'razonamiento' => ['icon' => 'cogs', 'label' => 'Razonamiento'],
                        'atencion' => ['icon' => 'bullseye', 'label' => 'Atención']
                    ];
                    foreach ($conf as $key => $info): ?>
                    <div class="eval-item">
                        <h3><i class="fas fa-<?= $info['icon'] ?>"></i> <?= $info['label'] ?></h3>
                        <div class="current-level">Nivel <?= htmlspecialchars($niveles_actuales[$key]['nivel']) ?></div>
                        <label for="<?= $key ?>">Asignar Nuevo Nivel:</label>
                        <select name="<?= $key ?>" id="<?= $key ?>" style="width:100%; padding:10px; border-radius:12px; border:1px solid #dde2ea;">
                            <?php foreach ($niveles_opciones as $valor => $etiqueta): ?>
                                <option value="<?= $valor ?>" <?= $niveles_actuales[$key]['nivel'] == $valor ? 'selected' : '' ?>>
                                    <?= $etiqueta ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="last-update-info">
                            Última Modificación: <?= $niveles_actuales[$key]['fecha'] ?><br>
                            Profesional: <?= $niveles_actuales[$key]['asignador'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn-save" type="submit">
                        <i class="fas fa-upload"></i> Guardar Nuevos Niveles
                    </button>
                </div>
            </form>

            <div class="history-card">
                <h2><i class="fas fa-history"></i> Historial de resultados</h2>
                <p style="font-size:14px; color:var(--muted);">Últimas 20 partidas jugadas por este usuario.</p>

                <?php if (empty($historialResultados)): ?>
                    <p>Este usuario aún no tiene partidas registradas.</p>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Juego</th>
                            <th>Dificultad</th>
                            <th>Puntuación</th>
                            <th>Tiempo</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historialResultados as $fila): ?>
                            <tr style="cursor: pointer;" onclick="toggleRondas(<?= $fila['id'] ?>)">
                                <td><?= date('d/m/Y H:i', strtotime($fila['fecha_juego'])) ?></td>
                                <td>
                                    <span class="history-tag <?= htmlspecialchars($fila['tipo_juego']) ?>">
                                        <?= ucfirst(htmlspecialchars($fila['tipo_juego'])) ?>
                                        <?php if($fila['tipo_juego'] === 'razonamiento'): ?> 
                                            <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px;"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($fila['dificultad']) ?></td>
                                <td><strong><?= (int)$fila['puntuacion'] ?>%</strong></td>
                                <td><?= formatSecondsToMMSS($fila['tiempo_segundos']) ?> min</td>
                            </tr>

                            <?php if ($fila['tipo_juego'] === 'razonamiento'): 
                                $rondas = obtenerDetalleRondas($conexion, $fila['id']); ?>
                                <tr id="rondas-<?= $fila['id'] ?>" style="display: none; background: #fdfdfd;">
                                    <td colspan="5" style="padding: 0;">
                                        <div class="rondas-container">
                                            <h4>Desglose de las 10 Rondas</h4>
                                            <div class="rondas-grid">
                                                <?php foreach ($rondas as $r): ?>
                                                    <div class="ronda-item">
                                                        <span class="ronda-num">R<?= $r['ronda'] ?></span>
                                                        <span class="ronda-status">
                                                            <?= $r['correcta'] ? '<i class="fas fa-check-circle" style="color: #2e7d32;"></i>' : '<i class="fas fa-times-circle" style="color: #c62828;"></i>' ?>
                                                        </span>
                                                        <span class="ronda-time"><?= $r['tiempo_segundos'] ?>s</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRondas(id) {
    const detalle = document.getElementById('rondas-' + id);
    if (detalle) {
        detalle.style.display = (detalle.style.display === 'none') ? 'table-row' : 'none';
    }
}
</script>
</body>
</html>