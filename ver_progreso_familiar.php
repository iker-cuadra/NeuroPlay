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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root{
            --nav-height: 80px;
            --primary: #3b82f6;
            --text-dark: #1f2937;
            --card-bg: rgba(255, 255, 255, 0.9);
        }

        html, body { margin: 0; padding: 0; min-height: 100%; font-family: 'Poppins', sans-serif; background: #e5e5e5; overflow-x: hidden; }

        .canvas-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; 
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                              radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                              radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                              radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
                              radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 200% 200%; animation: meshMove 8s infinite alternate ease-in-out;
        }
        @keyframes meshMove { 0% { background-position: 0% 0%; } 100% { background-position: 100% 100%; } }

        /* NAVBAR */
        .navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; box-sizing: border-box; z-index: 1000; box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .brand-text { font-size: 20px; font-weight: 700; color: var(--text-dark); }
        .nav-links { display: flex; gap: 15px; align-items: center; }
        .nav-item {
            text-decoration: none; color: #4b5563; font-weight: 500; font-size: 15px;
            display: flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 12px; transition: 0.2s;
        }
        .nav-item:hover { color: var(--primary); background: rgba(59, 130, 246, 0.05); }
        .nav-item.active { color: var(--primary); background: rgba(59, 130, 246, 0.1); font-weight: 600; }
        .user-actions { display: flex; align-items: center; gap: 20px; }
        .role-badge { background: #e0f2fe; color: #0369a1; padding: 8px 16px; border-radius: 12px; font-size: 14px; font-weight: 600; }
        .btn-logout { 
            text-decoration: none; color: #dc2626; font-weight: 600; font-size: 14px; 
            padding: 10px 20px; border: 1px solid #fecaca; border-radius: 12px; background: white; transition: 0.2s; 
        }
        .btn-logout:hover { background: #fef2f2; border-color: #dc2626; }

        /* CONTENIDO */
        .layout { padding-top: calc(var(--nav-height) + 30px); padding-bottom: 40px; display: flex; justify-content: center; }
        .panel { width: min(1150px, 95vw); background: var(--card-bg); border-radius: 24px; padding: 35px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); backdrop-filter: blur(10px); }
        .panel-title { font-size: 26px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #111; border-bottom: 2px solid #eee; padding-bottom: 15px; }

        /* PERFIL Y EVALUACIÓN */
        .profile-card { display: flex; align-items: center; gap: 20px; padding: 20px; background: rgba(255,255,255,0.5); border-radius: 18px; border: 1px solid rgba(255,255,255,0.8); margin-bottom: 30px; }
        .profile-card img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .evaluation-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .eval-item { background: white; padding: 20px; border-radius: 20px; text-align: center; border: 1px solid #eee; }
        .level-badge { font-size: 22px; font-weight: 800; color: var(--primary); margin: 5px 0; }

        /* GRÁFICAS */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: white; padding: 20px; border-radius: 20px; border: 1px solid #eee; height: 300px; }

        /* TABLA Y DESPLIEGUE RONDAS */
        .history-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid #eee; overflow-x: auto; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 12px; color: #888; border-bottom: 2px solid #f5f5f5; }
        .history-table td { padding: 15px 12px; border-bottom: 1px solid #f9f9f9; vertical-align: top; }
        
        .history-tag { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 5px; }
        .history-tag.memoria { background: #e0f2fe; color: #0369a1; }
        .history-tag.logica { background: #dcfce7; color: #166534; }
        .history-tag.razonamiento { background: #fef3c7; color: #92400e; }
        .history-tag.atencion { background: #f3e8ff; color: #6b21a8; }

        /* BOTÓN DESPLEGAR */
        .btn-ver-rondas {
            background: none; border: 1px solid var(--primary); color: var(--primary);
            padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s;
            margin-top: 5px;
        }
        .btn-ver-rondas:hover { background: var(--primary); color: white; }

        /* CONTENEDOR OCULTO */
        /* CONTENEDOR CON ANIMACIÓN */
.rondas-wrapper {
    max-height: 0;        /* Empezamos sin altura */
    overflow: hidden;     /* Escondemos el contenido que sobresale */
    opacity: 0;           /* Transparente */
    margin-top: 0; 
    padding: 0 12px;      /* Padding lateral solamente al inicio */
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid transparent;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); /* Animación suave */
}

/* Clase que aplicaremos con JavaScript */
.rondas-wrapper.abierto {
    max-height: 500px;    /* Un valor lo suficientemente alto */
    opacity: 1;
    margin-top: 10px;
    padding: 12px;
    border-color: #edf2f7;
}
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .summary-box { font-weight: 700; color: #4a5568; display: block; margin-bottom: 8px; font-size: 13px; }
        .pills-container { display: flex; flex-wrap: wrap; gap: 6px; }
        .round-pill { font-size: 11px; padding: 3px 8px; border-radius: 20px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .round-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .round-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 900px) {
            .charts-grid { grid-template-columns: 1fr; }
            .navbar { padding: 0 20px; flex-direction: column; height: auto; padding-bottom: 15px; }
            .nav-links { margin-top: 10px; flex-wrap: wrap; justify-content: center; }
            .layout { padding-top: 200px; }
        }
    </style>
</head>
<body>

    <div class="canvas-bg"></div>

    <nav class="navbar">
        <div class="brand"><div class="brand-text">Centro de día Pere Bas</div></div>
        <div class="nav-links">
            <a href="familiar.php" class="nav-item"><i class="fas fa-home"></i> Inicio</a>
            <a href="ver_progreso_familiar.php" class="nav-item active"><i class="fas fa-chart-line"></i> Mi Progreso</a>
            <a href="lista_profesionales.php" class="nav-item"><i class="fas fa-comments"></i> Chat Profesional</a>
        </div>
        <div class="user-actions">
            <span class="role-badge">Familiar</span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </nav>

    <div class="layout">
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Función para desplegar/ocultar rondas
        function toggleDetalle(btn) {
    const wrapper = btn.nextElementSibling;
    const isOpened = wrapper.classList.contains('abierto');
    
    // Alternamos la clase 'abierto'
    wrapper.classList.toggle('abierto');
    
    // Cambiamos el texto e icono del botón
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