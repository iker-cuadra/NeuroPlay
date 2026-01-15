<?php
// Asegúrate de iniciar la sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/conexion.php";
require_once "includes/auth.php";

// 1. Solo familiares pueden acceder a esta vista
requireRole("familiar");

$familiar_id = $_SESSION["usuario_id"];
$usuario = null;

// ---------------------------------------------------------
// 2. BUSCAR EL USUARIO (MAYOR) ASOCIADO A ESTE FAMILIAR
// ---------------------------------------------------------
try {
    // [OJO] Aquí asumo que en la tabla 'usuarios' tienes una columna 'familiar_id' 
    // que indica quién es el familiar de ese usuario mayor.
    // Si tu relación está en otra tabla, cambia esta consulta.
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE familiar_id = ? LIMIT 1");
    $stmt->execute([$familiar_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no encuentra por 'familiar_id', intenta buscar si el usuario actual tiene un 'usuario_asociado_id'
    // (Descomenta y usa esto si tu base de datos funciona al revés)
    /*
    if (!$usuario) {
        $stmtSelf = $conexion->prepare("SELECT usuario_asociado_id FROM usuarios WHERE id = ?");
        $stmtSelf->execute([$familiar_id]);
        $link = $stmtSelf->fetch(PDO::FETCH_ASSOC);
        if ($link && $link['usuario_asociado_id']) {
            $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE id = ?");
            $stmt->execute([$link['usuario_asociado_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    */

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// Si no hay usuario asociado, mostramos mensaje y salimos
if (!$usuario) {
    echo "<div style='padding:20px; font-family:sans-serif;'>No tienes ningún usuario asociado para ver su progreso. Contacta con el centro.</div>";
    exit;
}

$user_id = (int)$usuario['id'];

// Lógica para determinar la ruta de la foto del usuario mayor
$ruta_foto = 'uploads/default.png';
if (!empty($usuario['foto']) && $usuario['foto'] !== 'default.png') {
    $ruta_foto = 'uploads/' . htmlspecialchars($usuario['foto']);
}

// ----------------------------------------------------
// 3. CARGAR DATOS DE DIFICULTADES (Solo lectura)
// ----------------------------------------------------
$stmt_eval = $conexion->prepare("
    SELECT dificultad_memoria,
           dificultad_logica,
           dificultad_razonamiento,
           dificultad_atencion,
           fecha_actualizacion,
           (SELECT nombre FROM usuarios WHERE id = asignado_por) AS asignador_nombre
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt_eval->execute([$user_id]);
$res = $stmt_eval->fetch(PDO::FETCH_ASSOC);

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
// 4. HISTORIAL DE RESULTADOS
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
"); // He aumentado el limit a 20 para que el familiar vea más historial
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Progreso de <?= htmlspecialchars($usuario["nombre"]) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
/* MISMOS ESTILOS QUE EL ORIGINAL */
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
body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); background: #887d7dff; }

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
.profile-info .role-badge {
    margin-top: 5px;
    display: inline-block;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}
.role-badge.usuario{ background:#e0f7fa; color:#00796b; }
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
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.eval-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 700;
    color: #4a4a4a;
    display: flex;
    align-items: center;
    gap: 8px;
}
/* Estilo modificado para solo lectura */
.read-only-level {
    font-size: 18px;
    font-weight: 600;
    color: #4a4a4a;
    background: #fff;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #eee;
    text-align: center;
    margin-bottom: 5px;
}
.read-only-level span {
    display: block;
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.level-badge {
    color: #1e4db7;
    font-weight: 800;
    font-size: 20px;
}

.last-update-info {
    font-size: 11px;
    color: #999;
    line-height: 1.4;
    margin-top: 10px;
    text-align: center;
}

/* HISTORIAL */
.history-card {
    margin-top: 24px;
    padding: 16px 18px;
    background: #fcfcfd;
    border-radius: 18px;
    border: 1px solid #eef0f3;
    box-shadow: 0 6px 16px rgba(0,0,0,0.04);
}
.history-card h2 { margin: 0 0 8px 0; font-size: 19px; font-weight: 800; }
.history-intro { margin: 0 0 12px 0; font-size: 14px; color: var(--muted); }
.no-history { font-size: 14px; color: var(--muted); }
.history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.history-table thead th { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e3e6ec; color: #777; font-weight: 600; }
.history-table tbody td { padding: 8px 6px; border-bottom: 1px solid #f1f2f6; }
.history-table tbody tr:last-child td { border-bottom: none; }
.history-tag { display: inline-block; padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; }
.history-tag.memoria { background:#e0f7fa; color:#006064; }
.history-tag.logica { background:#e8f5e9; color:#2e7d32; }
.history-tag.razonamiento { background:#fff3e0; color:#e65100; }
.history-tag.atencion { background:#ede7f6; color:#4527a0; }

</style>
</head>

<body>
<div class="layout">
    <div class="header">
        <a href="familiar.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Área Familiar</div>
    </div>

    <div class="page-content">
        <div class="panel">

            <h1><i class="fas fa-chart-line"></i> Progreso Cognitivo</h1>

            <div class="profile-card">
                <img src="<?= $ruta_foto ?>" alt="Foto de <?= htmlspecialchars($usuario["nombre"]) ?>">
                <div class="profile-info">
                    <h2><?= htmlspecialchars($usuario["nombre"]) ?></h2>
                    <p>Usuario del Centro</p>
                    <span class="role-badge usuario">Usuario</span>
                </div>
            </div>

            <div class="evaluation-grid">
                <div class="eval-item">
                    <h3><i class="fas fa-lightbulb"></i> Lógica</h3>
                    <div class="read-only-level">
                        <span>Nivel Actual</span>
                        <div class="level-badge"><?= htmlspecialchars($niveles_actuales['logica']['nivel']) ?></div>
                    </div>
                    <div class="last-update-info">
                        Actualizado: <?= $niveles_actuales['logica']['fecha'] ?>
                    </div>
                </div>

                <div class="eval-item">
                    <h3><i class="fas fa-brain"></i> Memoria</h3>
                    <div class="read-only-level">
                        <span>Nivel Actual</span>
                        <div class="level-badge"><?= htmlspecialchars($niveles_actuales['memoria']['nivel']) ?></div>
                    </div>
                    <div class="last-update-info">
                        Actualizado: <?= $niveles_actuales['memoria']['fecha'] ?>
                    </div>
                </div>

                <div class="eval-item">
                    <h3><i class="fas fa-cogs"></i> Razonamiento</h3>
                    <div class="read-only-level">
                        <span>Nivel Actual</span>
                        <div class="level-badge"><?= htmlspecialchars($niveles_actuales['razonamiento']['nivel']) ?></div>
                    </div>
                    <div class="last-update-info">
                        Actualizado: <?= $niveles_actuales['razonamiento']['fecha'] ?>
                    </div>
                </div>

                <div class="eval-item">
                    <h3><i class="fas fa-bullseye"></i> Atención</h3>
                    <div class="read-only-level">
                        <span>Nivel Actual</span>
                        <div class="level-badge"><?= htmlspecialchars($niveles_actuales['atencion']['nivel']) ?></div>
                    </div>
                    <div class="last-update-info">
                        Actualizado: <?= $niveles_actuales['atencion']['fecha'] ?>
                    </div>
                </div>
            </div>

            <div class="history-card">
                <h2><i class="fas fa-history"></i> Historial de partidas</h2>
                <p class="history-intro">
                    Aquí puedes ver cómo le ha ido a tu familiar en sus últimos juegos. Haz clic en las filas de "Razonamiento" para ver detalles.
                </p>

                <?php if (empty($historialResultados)): ?>
                    <p class="no-history">Aún no hay partidas registradas.</p>
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
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($fila['fecha_juego'])) ?>
                                </td>
                                <td>
                                    <span class="history-tag <?= htmlspecialchars($fila['tipo_juego']) ?>">
                                        <?= ucfirst(htmlspecialchars($fila['tipo_juego'])) ?>
                                        <?php if($fila['tipo_juego'] === 'razonamiento'): ?> 
                                            <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px; opacity: 0.7;"></i>
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
                                        <div style="padding: 15px; border-left: 5px solid #e65100; margin: 10px; background: #fff; border-radius: 8px; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);">
                                            <h4 style="margin: 0 0 10px 0; font-size: 13px; color: #e65100; text-transform: uppercase; letter-spacing: 0.5px;">Desglose de Rondas</h4>
                                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                <?php foreach ($rondas as $r): ?>
                                                    <div style="border: 1px solid #eee; padding: 8px 12px; border-radius: 10px; background: #fcfcfd; display: flex; align-items: center; gap: 8px;">
                                                        <span style="font-weight: 700; color: #666;">R<?= $r['ronda'] ?></span>
                                                        <span>
                                                            <?= $r['correcta'] 
                                                                ? '<i class="fas fa-check-circle" style="color: #2e7d32;"></i>' 
                                                                : '<i class="fas fa-times-circle" style="color: #c62828;"></i>' ?>
                                                        </span>
                                                        <small style="color: #888; font-family: monospace;"><?= $r['tiempo_segundos'] ?>s</small>
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
        if (detalle.style.display === 'none') {
            detalle.style.display = 'table-row';
        } else {
            detalle.style.display = 'none';
        }
    }
}
</script>

</body>
</html>