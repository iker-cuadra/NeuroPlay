<?php
// evaluar_usuario.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/conexion.php";
require_once "includes/auth.php";

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
    $profesional_id = (int)($_SESSION["usuario_id"] ?? 0);
    $fecha          = date('Y-m-d H:i:s');

    $memoria      = trim($_POST["memoria"] ?? "Fácil");
    $logica       = trim($_POST["logica"] ?? "Fácil");
    $razonamiento = trim($_POST["razonamiento"] ?? "Fácil");
    $atencion     = trim($_POST["atencion"] ?? "Fácil");

    try {
        if ($user_id_post > 0 && $profesional_id > 0) {

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

    header("Location: evaluar_usuario.php?user_id=" . $user_id_post);
    exit;
}

// -------------------------
// OBTENER ID DE USUARIO Y DATOS DE PERFIL
// -------------------------
$user_id = (int)($_GET['user_id'] ?? 0);
$usuario = null;

if ($user_id > 0) {
    try {
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

if (!$usuario) {
    header("Location: gestionar_users.php");
    exit;
}

// -------------------------
// FOTO PERFIL
// -------------------------
function resolverRutaFotoPerfil(array $usuario): string {
    $rol  = strtolower(trim((string)($usuario['rol'] ?? 'usuario')));
    $foto = trim((string)($usuario['foto'] ?? ''));

    $defaultPorRol = [
        'usuario'      => 'default_usuario.png',
        'familiar'     => 'default_familiar.png',
        'profesional'  => 'default_profesional.png',
    ];

    $defaults = [
        '',
        'default.png',
        'default_usuario.png',
        'default_familiar.png',
        'default_profesional.png',
    ];

    $fotoSeguro = $foto !== '' ? basename($foto) : '';
    $uploadsDirFisico = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

    if ($fotoSeguro !== '' && !in_array($fotoSeguro, $defaults, true)) {
        if (file_exists($uploadsDirFisico . $fotoSeguro)) {
            return 'uploads/' . $fotoSeguro;
        }
    }

    $defaultElegido = $defaultPorRol[$rol] ?? 'default.png';
    if (!file_exists($uploadsDirFisico . $defaultElegido)) {
        $defaultElegido = 'default.png';
    }

    return 'uploads/' . $defaultElegido;
}

$ruta_foto = resolverRutaFotoPerfil($usuario);

// -------------------------
// DIFICULTADES ASIGNADAS
// -------------------------
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

$niveles_opciones = [
    'Fácil'       => 'Fácil',
    'Intermedio'  => 'Intermedio',
    'Difícil'     => 'Difícil'
];

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

    $asignador        = htmlspecialchars($res['asignador_nombre'] ?? '');
    $fecha_raw        = !empty($res['fecha_actualizacion']) ? strtotime($res['fecha_actualizacion']) : false;
    $fecha_formateada = $fecha_raw ? date('d/m/Y H:i', $fecha_raw) : 'N/A';

    foreach ($niveles_actuales as $key => $valor) {
        $niveles_actuales[$key]['asignador'] = $asignador ?: 'N/A';
        $niveles_actuales[$key]['fecha']     = $fecha_formateada;
    }

    $ultima_actualizacion = $fecha_formateada;
    $ultimo_profesional   = $asignador ?: 'N/A';
}

// -------------------------
// HISTORIAL (NO mostramos detalles_json)
// -------------------------
$stmt_hist = $conexion->prepare("
    SELECT id, tipo_juego,
           puntuacion,
           tiempo_segundos,
           dificultad,
           fecha_juego,
           aciertos,
           fallos,
           nivel_alcanzado,
           detalles_json
    FROM resultados_juego
    WHERE usuario_id = ?
    ORDER BY fecha_juego DESC
    LIMIT 30
");
$stmt_hist->execute([$user_id]);
$historialResultados = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// Helpers
// -------------------------
function formatSecondsToMMSS($segundos) {
    $segundos = (int)$segundos;
    if ($segundos < 0) $segundos = 0;
    $m  = floor($segundos / 60);
    $s  = $segundos % 60;
    return sprintf('%02d:%02d', $m, $s);
}

function iconoJuego($tipo) {
    if ($tipo === 'memoria') return 'brain';
    if ($tipo === 'logica') return 'lightbulb';
    if ($tipo === 'razonamiento') return 'cogs';
    return 'bullseye';
}

$flash_success = $_SESSION["flash_success"];
$flash_error   = $_SESSION["flash_error"];
$_SESSION["flash_success"] = "";
$_SESSION["flash_error"]   = "";

// Juegos presentes y contadores
$juegosDisponibles = [];
$counts = ['memoria'=>0,'logica'=>0,'razonamiento'=>0,'atencion'=>0];
foreach ($historialResultados as $f) {
    $t = $f['tipo_juego'] ?? '';
    if ($t !== '') $juegosDisponibles[$t] = true;
    if (isset($counts[$t])) $counts[$t]++;
}
$totalCount = count($historialResultados);
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
    --bg: #f0f2f5;
    --card: #ffffff;
    --shadow: 0 12px 30px rgba(0,0,0,0.10);
    --radius: 20px;
    --text: #1d1d1f;
    --muted: #6c6c6c;
    --primary: #1e4db7;
    --pill-bg: #eef2f7;
    --pill-active: #3f3f3f;
}

* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: auto; }
body { font-family: 'Poppins', sans-serif; background: #887d7dff; color: var(--text); }

.layout { min-height: 100vh; display: flex; flex-direction: column; }
.header{
    width: 100%;
    height: var(--header-h);
    background-image: url('../frontend/imagenes/Banner.svg');
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

.update-info {
    text-align: right; font-size: 13px; color: #4a4a4a; padding-bottom: 10px;
}
.update-info strong { color: #2e7d32; font-weight: 700; }

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
.btn-save { background: var(--primary); color: white; }
.btn-save:hover { background: #1a42a0; transform: translateY(-1px); }

/* HISTORIAL */
.history-card {
    margin-top: 24px;
    padding: 16px 18px;
    background: #fcfcfd;
    border-radius: 18px;
    border: 1px solid #eef0f3;
    box-shadow: 0 6px 16px rgba(0,0,0,0.04);
}

/* ---------- Filtros estilo "píldora" como tu ejemplo ---------- */
.filter-shell{
    width: 100%;
    background: var(--pill-bg);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 999px;
    padding: 8px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin: 12px 0 14px 0;
}

.filter-label{
    font-weight: 800;
    color: #1d1d1f;
    padding: 0 6px 0 10px;
    white-space: nowrap;
}

.filter-btn{
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 10px 14px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    color: #111;
    transition: background .18s ease, transform .18s ease, color .18s ease;
    white-space: nowrap;
}
.filter-btn i{ opacity: 0.9; }

.filter-btn:hover{
    transform: translateY(-1px);
    background: rgba(255,255,255,0.65);
}

.filter-btn.active{
    background: var(--pill-active);
    color: #fff;
    box-shadow: 0 10px 18px rgba(0,0,0,0.14);
}
.filter-btn.active i{ opacity: 1; }

.filter-count{
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    background: rgba(0,0,0,0.08);
}
.filter-btn.active .filter-count{
    background: rgba(255,255,255,0.22);
}

/* Tabla */
.table-wrap{
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
}

.history-table {
    width: 100%;
    min-width: 860px;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 14px;
    background: #fff;
}
.history-table thead th {
    text-align: left;
    padding: 8px 6px;
    border-bottom: 1px solid #e3e6ec;
    color: #777;
    font-weight: 600;
}
.history-table tbody td {
    padding: 8px 6px;
    border-bottom: 1px solid #f1f2f6;
    vertical-align: top;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.history-tag { display: inline-block; padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.history-tag.memoria { background:#e0f7fa; color:#006064; }
.history-tag.logica { background:#e8f5e9; color:#2e7d32; }
.history-tag.razonamiento { background:#fff3e0; color:#e65100; }
.history-tag.atencion { background:#ede7f6; color:#4527a0; }

.details-container {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 10px;
}
.details-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}
.stat-box {
    background: white;
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid #e3e6ec;
    text-align: center;
}
.stat-box .stat-label {
    font-size: 12px;
    color: #777;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.stat-box .stat-value {
    font-size: 24px;
    font-weight: 800;
    color: #2e7d32;
}
.stat-box .stat-value.error { color: #c62828; }
.stat-box .stat-value.neutral { color: #1976d2; }

.game-specific-info {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    border: 1px solid #e3e6ec;
}
.game-specific-info h5 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #555;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.game-specific-info p {
    margin: 5px 0;
    font-size: 13px;
    color: #666;
    line-height: 1.6;
}
.info-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 5px;
}
.info-badge.success { background: #e8f5e9; color: #2e7d32; }
.info-badge.error { background: #ffebee; color: #c62828; }

.no-results{
    padding: 12px 12px;
    border: 1px dashed #d6dbe6;
    background: #ffffff;
    border-radius: 14px;
    color: #555;
    font-weight: 600;
    margin-top: 10px;
    display:none;
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
                <img src="<?= htmlspecialchars($ruta_foto) ?>" alt="Foto de <?= htmlspecialchars($usuario["nombre"]) ?>">
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
                <p style="font-size:14px; color:var(--muted);">Últimas 30 partidas jugadas por este usuario.</p>

                <?php if (empty($historialResultados)): ?>
                    <p>Este usuario aún no tiene partidas registradas.</p>
                <?php else: ?>

                    <!-- FILTROS estilo píldora -->
                    <div class="filter-shell" role="group" aria-label="Filtrar historial por juego">
                        <div class="filter-label">Ver:</div>

                        <button type="button" class="filter-btn active" data-filter="all">
                            <i class="fas fa-layer-group"></i>
                            Todos
                            <span class="filter-count"><?= (int)$totalCount ?></span>
                        </button>

                        <?php if (!empty($juegosDisponibles['memoria'])): ?>
                            <button type="button" class="filter-btn" data-filter="memoria">
                                <i class="fas fa-brain"></i>
                                Memoria
                                <span class="filter-count"><?= (int)$counts['memoria'] ?></span>
                            </button>
                        <?php endif; ?>

                        <?php if (!empty($juegosDisponibles['logica'])): ?>
                            <button type="button" class="filter-btn" data-filter="logica">
                                <i class="fas fa-lightbulb"></i>
                                Lógica
                                <span class="filter-count"><?= (int)$counts['logica'] ?></span>
                            </button>
                        <?php endif; ?>

                        <?php if (!empty($juegosDisponibles['razonamiento'])): ?>
                            <button type="button" class="filter-btn" data-filter="razonamiento">
                                <i class="fas fa-cogs"></i>
                                Razonamiento
                                <span class="filter-count"><?= (int)$counts['razonamiento'] ?></span>
                            </button>
                        <?php endif; ?>

                        <?php if (!empty($juegosDisponibles['atencion'])): ?>
                            <button type="button" class="filter-btn" data-filter="atencion">
                                <i class="fas fa-bullseye"></i>
                                Atención
                                <span class="filter-count"><?= (int)$counts['atencion'] ?></span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="no-results" class="no-results">
                        No hay resultados para este filtro.
                    </div>

                    <div class="table-wrap">
                        <table class="history-table" id="history-table">
                            <thead>
                            <tr>
                                <th style="width:160px;">Fecha</th>
                                <th style="width:180px;">Juego</th>
                                <th style="width:140px;">Dificultad</th>
                                <th style="width:120px; text-align:center;">Puntuación</th>
                                <th style="width:120px; text-align:center;">Tiempo</th>
                                <th style="width:140px;">Detalles</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historialResultados as $fila):
                                $tipo = $fila['tipo_juego'] ?? '';

                                // No mostramos JSON, solo lo leemos por si lo usas más adelante
                                $detalles_json_arr = !empty($fila['detalles_json']) ? json_decode($fila['detalles_json'], true) : null;

                                $aciertos = isset($fila['aciertos']) ? (int)$fila['aciertos'] : 0;
                                $fallos   = isset($fila['fallos']) ? (int)$fila['fallos'] : 0;
                                $nivel_alcanzado = isset($fila['nivel_alcanzado']) ? (string)$fila['nivel_alcanzado'] : '';

                                $fechaCell = 'N/A';
                                if (!empty($fila['fecha_juego'])) {
                                    $fechaCell = date('d/m/Y H:i', strtotime($fila['fecha_juego']));
                                }

                                $rowId = (int)$fila['id'];
                            ?>
                                <tr class="history-row" data-game="<?= htmlspecialchars($tipo) ?>">
                                    <td style="white-space: nowrap;"><?= htmlspecialchars($fechaCell) ?></td>
                                    <td>
                                        <span class="history-tag <?= htmlspecialchars($tipo) ?>">
                                            <i class="fas fa-<?= iconoJuego($tipo) ?>"></i>
                                            <?= ucfirst(htmlspecialchars($tipo)) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($fila['dificultad']) ?></strong></td>
                                    <td style="text-align: center;">
                                        <strong style="font-size: 18px;"><?= (int)$fila['puntuacion'] ?>%</strong>
                                    </td>
                                    <td style="text-align: center; font-family: monospace; font-weight: 600;">
                                        <?= formatSecondsToMMSS($fila['tiempo_segundos']) ?>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            onclick="toggleDetails(<?= $rowId ?>)"
                                            style="background: var(--primary); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 700;">
                                            <i class="fas fa-chevron-down"></i> Ver más
                                        </button>
                                    </td>
                                </tr>

                                <tr id="details-<?= $rowId ?>" class="details-row" data-game="<?= htmlspecialchars($tipo) ?>" style="display:none; background:#fdfdfd;">
                                    <td colspan="6" style="padding: 0;">
                                        <div class="details-container">

                                            <div class="details-stats">
                                                <div class="stat-box">
                                                    <div class="stat-label">Puntuación Final</div>
                                                    <div class="stat-value"><?= (int)$fila['puntuacion'] ?>%</div>
                                                </div>

                                                <div class="stat-box">
                                                    <div class="stat-label">Tiempo Total</div>
                                                    <div class="stat-value neutral"><?= formatSecondsToMMSS($fila['tiempo_segundos']) ?></div>
                                                </div>

                                                <div class="stat-box">
                                                    <div class="stat-label">Dificultad</div>
                                                    <div class="stat-value neutral" style="font-size: 18px;"><?= htmlspecialchars($fila['dificultad']) ?></div>
                                                </div>

                                                <div class="stat-box">
                                                    <div class="stat-label">Aciertos</div>
                                                    <div class="stat-value"><?= (int)$aciertos ?></div>
                                                </div>

                                                <div class="stat-box">
                                                    <div class="stat-label">Fallos</div>
                                                    <div class="stat-value error"><?= (int)$fallos ?></div>
                                                </div>

                                                <div class="stat-box">
                                                    <div class="stat-label">Nivel alcanzado</div>
                                                    <div class="stat-value neutral" style="font-size: 16px;">
                                                        <?= $nivel_alcanzado !== '' ? htmlspecialchars($nivel_alcanzado) : 'N/A' ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($tipo === 'memoria'): ?>
                                                <div class="game-specific-info">
                                                    <h5><i class="fas fa-brain"></i> Detalles del Juego de Memoria</h5>
                                                    <p><strong>Aciertos/Fallos:</strong>
                                                        <span class="info-badge success"><?= (int)$aciertos ?> aciertos</span>
                                                        <span class="info-badge error"><?= (int)$fallos ?> fallos</span>
                                                    </p>
                                                </div>

                                            <?php elseif ($tipo === 'logica'): ?>
                                                <div class="game-specific-info">
                                                    <h5><i class="fas fa-lightbulb"></i> Detalles del Juego de Lógica</h5>
                                                    <p><strong>Aciertos/Fallos:</strong>
                                                        <span class="info-badge success"><?= (int)$aciertos ?> aciertos</span>
                                                        <span class="info-badge error"><?= (int)$fallos ?> fallos</span>
                                                    </p>
                                                </div>

                                            <?php elseif ($tipo === 'razonamiento'): ?>
                                                <!-- Pedido: NO mostrar rondas. Solo aciertos y fallos -->
                                                <div class="game-specific-info">
                                                    <h5><i class="fas fa-cogs"></i> Detalles del Juego de Razonamiento</h5>
                                                    <p><strong>Aciertos/Fallos:</strong>
                                                        <span class="info-badge success"><?= (int)$aciertos ?> aciertos</span>
                                                        <span class="info-badge error"><?= (int)$fallos ?> fallos</span>
                                                    </p>
                                                </div>

                                            <?php elseif ($tipo === 'atencion'): ?>
                                                <div class="game-specific-info">
                                                    <h5><i class="fas fa-bullseye"></i> Detalles del Juego de Atención</h5>
                                                    <p><strong>Aciertos/Fallos:</strong>
                                                        <span class="info-badge success"><?= (int)$aciertos ?> aciertos</span>
                                                        <span class="info-badge error"><?= (int)$fallos ?> fallos</span>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function closeAllDetails() {
    document.querySelectorAll('tr[id^="details-"]').forEach(r => {
        r.style.display = 'none';
    });
}

function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    if (!row) return;

    const isOpen = (row.style.display === 'table-row');

    closeAllDetails();
    row.style.display = isOpen ? 'none' : 'table-row';
}

function applyFilter(filter) {
    closeAllDetails();

    const rows = document.querySelectorAll('.history-row');
    const noResults = document.getElementById('no-results');

    let visibleCount = 0;

    rows.forEach(r => {
        const game = (r.dataset.game || '').toLowerCase();
        const show = (filter === 'all') ? true : (game === filter);
        r.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    // Siempre ocultamos detalles al filtrar
    document.querySelectorAll('.details-row').forEach(r => {
        r.style.display = 'none';
    });

    if (noResults) noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const btns = document.querySelectorAll('.filter-btn');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = (btn.dataset.filter || 'all').toLowerCase();
            applyFilter(filter);
        });
    });

    applyFilter('all');
});
</script>
</body>
</html>
