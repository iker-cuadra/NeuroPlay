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
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE familiar_id = ? LIMIT 1");
    $stmt->execute([$familiar_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// Si no hay usuario asociado
if (!$usuario) {
    echo "<div style='padding:20px; font-family:sans-serif;'>No tienes ningún usuario asociado para ver su progreso. Contacta con el centro.</div>";
    exit;
}

$user_id = (int)$usuario['id'];

// Lógica para foto
$ruta_foto = 'uploads/default.png';
if (!empty($usuario['foto']) && $usuario['foto'] !== 'default.png') {
    $ruta_foto = 'uploads/' . htmlspecialchars($usuario['foto']);
}

// ----------------------------------------------------
// 3. CARGAR DATOS DE DIFICULTADES
// ----------------------------------------------------
$stmt_eval = $conexion->prepare("
    SELECT dificultad_memoria, dificultad_logica, dificultad_razonamiento, dificultad_atencion,
           fecha_actualizacion, (SELECT nombre FROM usuarios WHERE id = asignado_por) AS asignador_nombre
    FROM dificultades_asignadas WHERE usuario_id = ?
");
$stmt_eval->execute([$user_id]);
$res = $stmt_eval->fetch(PDO::FETCH_ASSOC);

$niveles_actuales = [
    'memoria'      => ['nivel' => 'Fácil', 'fecha' => 'N/A'],
    'logica'       => ['nivel' => 'Fácil', 'fecha' => 'N/A'],
    'razonamiento' => ['nivel' => 'Fácil', 'fecha' => 'N/A'],
    'atencion'     => ['nivel' => 'Fácil', 'fecha' => 'N/A'],
];

if ($res) {
    $fecha_raw = strtotime($res['fecha_actualizacion']);
    $fecha_f = $fecha_raw ? date('d/m/Y', $fecha_raw) : 'N/A';
    
    $niveles_actuales['memoria'] = ['nivel' => $res['dificultad_memoria'] ?: 'Fácil', 'fecha' => $fecha_f];
    $niveles_actuales['logica'] = ['nivel' => $res['dificultad_logica'] ?: 'Fácil', 'fecha' => $fecha_f];
    $niveles_actuales['razonamiento'] = ['nivel' => $res['dificultad_razonamiento'] ?: 'Fácil', 'fecha' => $fecha_f];
    $niveles_actuales['atencion'] = ['nivel' => $res['dificultad_atencion'] ?: 'Fácil', 'fecha' => $fecha_f];
}

// ----------------------------------------------------
// 4. HISTORIAL Y GRÁFICAS
// ----------------------------------------------------
$stmt_hist = $conexion->prepare("SELECT id, tipo_juego, puntuacion, tiempo_segundos, dificultad, fecha_juego FROM resultados_juego WHERE usuario_id = ? ORDER BY fecha_juego DESC LIMIT 50");
$stmt_hist->execute([$user_id]);
$historialResultados = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

$datosGraficas = ['memoria'=>['labels'=>[],'data'=>[]],'logica'=>['labels'=>[],'data'=>[]],'razonamiento'=>['labels'=>[],'data'=>[]],'atencion'=>['labels'=>[],'data'=>[]]];
foreach (array_reverse($historialResultados) as $juego) {
    $tipo = $juego['tipo_juego'];
    if (isset($datosGraficas[$tipo])) {
        $datosGraficas[$tipo]['labels'][] = date('d/m', strtotime($juego['fecha_juego']));
        $datosGraficas[$tipo]['data'][] = (int)$juego['puntuacion'];
    }
}
$jsonGraficas = json_encode($datosGraficas);

// Función para obtener detalles de rondas
function obtenerDetalleRondas($conexion, $resultado_id) {
    $stmt = $conexion->prepare("SELECT ronda, correcta, tiempo_segundos FROM razonamiento_rondas WHERE resultado_id = ? ORDER BY ronda ASC");
    $stmt->execute([$resultado_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatSecondsToMMSS($segundos) {
    $segundos = (int)$segundos;
    return sprintf('%02d:%02d', floor($segundos / 60), $segundos % 60);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Progreso - Centro Pere Bas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root{
            --header-h: 160px;
            --primary: #3b82f6;
            --text-dark: #1f2937;
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { 
            height: 100%;
            font-family: 'Poppins', sans-serif; 
            background-color: transparent;
        }

        /* FONDO DINÁMICO ANIMADO */
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

        .layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        /* HEADER CON ANIMACIONES */
        .header {
            width: 100%;
            height: var(--header-h);
            background-image: url('../frontend/imagenes/fondo.svg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            position: relative;
            flex: 0 0 auto;
            opacity: 0;
            transform: translateY(-30px);
            animation: headerSlideDown 0.8s ease forwards 0.2s;
        }

        @keyframes headerSlideDown {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* TÍTULO CENTRADO */
        .center-title {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: 700;
            font-size: 48px;
            text-transform: uppercase;
            letter-spacing: 3px;
            white-space: nowrap;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.6s;
            margin: 0;
            z-index: 10;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        /* BOTÓN VOLVER */
        .back-arrow {
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
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.4s;
        }
        .back-arrow:hover { transform: scale(1.2) translateX(-3px); }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            color: white;
            font-weight: 700;
            font-size: 18px;
            opacity: 0;
            animation: fadeIn 0.6s ease forwards 0.8s;
        }

        /* CONTENIDO */
        .page-content {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px 16px;
            overflow-y: auto;
        }

        .panel { 
            width: min(1150px, 95vw); 
            background: var(--card-bg); 
            border-radius: 24px; 
            padding: 35px; 
            box-shadow: 0 12px 40px rgba(0,0,0,0.15); 
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .panel-title { 
            font-size: 26px; 
            font-weight: 800; 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: #111; 
            border-bottom: 2px solid #f0f0f0; 
            padding-bottom: 15px; 
        }

        /* PERFIL Y EVALUACIÓN */
        .profile-card { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            padding: 20px; 
            background: #fcfcfd; 
            border-radius: 18px; 
            border: 1px solid #eef0f3; 
            margin-bottom: 30px; 
        }
        .profile-card img { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid #7a7676; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }

        .evaluation-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        .eval-item { 
            background: white; 
            padding: 20px; 
            border-radius: 20px; 
            text-align: center; 
            border: 1px solid #eee; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .level-badge { 
            font-size: 22px; 
            font-weight: 800; 
            color: var(--primary); 
            margin: 5px 0; 
        }
        .last-update {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }

        /* GRÁFICAS */
        .charts-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 25px; 
            margin-bottom: 40px; 
        }
        .chart-card { 
            background: white; 
            padding: 20px; 
            border-radius: 20px; 
            border: 1px solid #eee; 
            height: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* TABLA Y DESPLIEGUE RONDAS */
        .history-card { 
            background: white; 
            padding: 25px; 
            border-radius: 20px; 
            border: 1px solid #eee; 
            overflow-x: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .history-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .history-table th { 
            text-align: left; 
            padding: 12px; 
            color: #888; 
            border-bottom: 2px solid #f5f5f5; 
        }
        .history-table td { 
            padding: 15px 12px; 
            border-bottom: 1px solid #f9f9f9; 
            vertical-align: top; 
        }
        
        .history-tag { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-size: 12px; 
            font-weight: 600; 
            display: inline-block; 
            margin-bottom: 5px; 
        }
        .history-tag.memoria { background: #e0f2fe; color: #0369a1; }
        .history-tag.logica { background: #dcfce7; color: #166534; }
        .history-tag.razonamiento { background: #fef3c7; color: #92400e; }
        .history-tag.atencion { background: #f3e8ff; color: #6b21a8; }

        /* BOTÓN DESPLEGAR */
        .btn-ver-rondas {
            background: none; 
            border: 1px solid var(--primary); 
            color: var(--primary);
            padding: 4px 10px; 
            border-radius: 8px; 
            font-size: 11px; 
            font-weight: 600;
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: 0.2s;
            margin-top: 5px;
        }
        .btn-ver-rondas:hover { 
            background: var(--primary); 
            color: white; 
        }

        /* CONTENEDOR CON ANIMACIÓN */
        .rondas-wrapper {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            margin-top: 0; 
            padding: 0 12px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid transparent;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .rondas-wrapper.abierto {
            max-height: 500px;
            opacity: 1;
            margin-top: 10px;
            padding: 12px;
            border-color: #edf2f7;
        }

        .summary-box { 
            font-weight: 700; 
            color: #4a5568; 
            display: block; 
            margin-bottom: 8px; 
            font-size: 13px; 
        }
        .pills-container { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 6px; 
        }
        .round-pill { 
            font-size: 11px; 
            padding: 3px 8px; 
            border-radius: 20px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
        }
        .round-success { 
            background: #dcfce7; 
            color: #166534; 
            border: 1px solid #bbf7d0; 
        }
        .round-error { 
            background: #fee2e2; 
            color: #991b1b; 
            border: 1px solid #fecaca; 
        }

        @media (max-width: 900px) {
            .center-title { font-size: 36px; }
            .charts-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .center-title { font-size: 26px; letter-spacing: 2px; }
        }
    </style>
</head>
<body>

    <div class="canvas-bg"></div>

    <div class="layout">
        <div class="header">
            <h1 class="center-title">Centro Pere Bas</h1>
            <a href="familiar.php" class="back-arrow" aria-label="Volver">
                <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
                </svg>
            </a>
            <div class="user-role">Progreso del Usuario</div>
        </div>

        <div class="page-content">
            <div class="panel">
                <h1 class="panel-title"><i class="fas fa-chart-bar"></i> Seguimiento Cognitivo</h1>

                <div class="profile-card">
                    <img src="<?= $ruta_foto ?>" alt="Usuario">
                    <div class="profile-info">
                        <h2 style="margin:0;"><?= htmlspecialchars($usuario["nombre"]) ?></h2>
                        <p style="margin:5px 0; color:#666; font-size:14px;">Seguimiento detallado del progreso</p>
                    </div>
                </div>

                <div class="evaluation-grid">
                    <?php 
                    $iconos = ['logica'=>'lightbulb', 'memoria'=>'brain', 'razonamiento'=>'cogs', 'atencion'=>'bullseye'];
                    foreach($niveles_actuales as $cat => $info): ?>
                    <div class="eval-item">
                        <h3 style="font-size:14px; color:#666; margin:0;"><i class="fas fa-<?= $iconos[$cat] ?>"></i> <?= ucfirst($cat) ?></h3>
                        <div class="level-badge"><?= htmlspecialchars($info['nivel']) ?></div>
                        <div class="last-update">Actualizado: <?= $info['fecha'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="charts-grid">
                    <div class="chart-card"><canvas id="chartMemoria"></canvas></div>
                    <div class="chart-card"><canvas id="chartLogica"></canvas></div>
                    <div class="chart-card"><canvas id="chartRazonamiento"></canvas></div>
                    <div class="chart-card"><canvas id="chartAtencion"></canvas></div>
                </div>

                <div class="history-card">
                    <h3><i class="fas fa-history"></i> Historial de Actividades</h3>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Juego / Detalle</th>
                                <th>Dificultad</th>
                                <th>Puntuación</th>
                                <th>Tiempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialResultados as $fila): ?>
                            <tr>
                                <td style="font-size:13px;"><?= date('d/m/Y H:i', strtotime($fila['fecha_juego'])) ?></td>
                                <td>
                                    <span class="history-tag <?= $fila['tipo_juego'] ?>"><?= ucfirst($fila['tipo_juego']) ?></span>
                                    
                                    <?php if ($fila['tipo_juego'] === 'razonamiento'): 
                                        $rondas = obtenerDetalleRondas($conexion, $fila['id']);
                                        if (!empty($rondas)):
                                            $aciertos = 0; $fallos = 0;
                                            foreach($rondas as $r) { if($r['correcta']) $aciertos++; else $fallos++; }
                                    ?>
                                        <button class="btn-ver-rondas" onclick="toggleDetalle(this)">
                                            <i class="fas fa-chevron-down"></i> Ver Rondas
                                        </button>

                                        <div class="rondas-wrapper">
                                            <span class="summary-box">
                                                Resultado: <span style="color:#10b981"><?= $aciertos ?> ✓</span> | <span style="color:#ef4444"><?= $fallos ?> ✗</span>
                                            </span>
                                            <div class="pills-container">
                                                <?php foreach ($rondas as $r): ?>
                                                    <span class="round-pill <?= $r['correcta'] ? 'round-success' : 'round-error' ?>">
                                                        R<?= $r['ronda'] ?>: <?= $r['correcta'] ? 'Acierto' : 'Fallo' ?> (<?= $r['tiempo_segundos'] ?>s)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; endif; ?>
                                </td>
                                <td><?= $fila['dificultad'] ?></td>
                                <td><strong><?= (int)$fila['puntuacion'] ?>%</strong></td>
                                <td><?= formatSecondsToMMSS($fila['tiempo_segundos']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Función para desplegar/ocultar rondas
        function toggleDetalle(btn) {
            const wrapper = btn.nextElementSibling;
            const isOpened = wrapper.classList.contains('abierto');
            
            wrapper.classList.toggle('abierto');
            
            if (isOpened) {
                btn.innerHTML = '<i class="fas fa-chevron-down"></i> Ver Rondas';
            } else {
                btn.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar Rondas';
            }
        }

        // Configuración de gráficas
        const datos = <?= $jsonGraficas ?>;
        function render(id, cat, color) {
            const ctx = document.getElementById(id);
            if(!ctx || !datos[cat].data.length) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datos[cat].labels,
                    datasets: [{
                        label: 'Evolución ' + cat,
                        data: datos[cat].data,
                        borderColor: color, backgroundColor: color + '22',
                        fill: true, tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }
        render('chartMemoria', 'memoria', '#3b82f6');
        render('chartLogica', 'logica', '#10b981');
        render('chartRazonamiento', 'razonamiento', '#f59e0b');
        render('chartAtencion', 'atencion', '#8b5cf6');
    </script>
</body>
</html>