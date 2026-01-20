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
    <title>Progreso - Centro Pere Bas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root{
            --nav-height: 80px;
            --primary: #3b82f6;
            --bg: #f0f2f5;
            --card: rgba(255, 255, 255, 0.9);
            --text-dark: #1f2937;
        }

        html, body { margin: 0; padding: 0; min-height: 100%; font-family: 'Poppins', sans-serif; background: #e5e5e5; }

        /* FONDO ANIMADO */
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
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; box-sizing: border-box; z-index: 1000; box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-text { font-size: 20px; font-weight: 700; color: var(--text-dark); }
        .nav-links { display: flex; gap: 25px; }
        .nav-item { text-decoration: none; color: #4b5563; font-weight: 500; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: rgba(59, 130, 246, 0.08); }
        .user-actions { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: #e0f2fe; color: #0369a1; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn-logout { text-decoration: none; color: #dc2626; font-weight: 600; font-size: 13px; border: 1px solid #fecaca; padding: 7px 15px; border-radius: 10px; background: white; transition: 0.2s; }
        .btn-logout:hover { background: #fef2f2; }

        /* CONTENIDO */
        .layout { padding-top: calc(var(--nav-height) + 30px); padding-bottom: 40px; display: flex; justify-content: center; }
        .panel { width: min(1100px, 95vw); background: var(--card); border-radius: 24px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); backdrop-filter: blur(10px); }
        
        .panel-title { font-size: 26px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #111; border-bottom: 2px solid #eee; padding-bottom: 15px; }

        /* PERFIL */
        .profile-card { display: flex; align-items: center; gap: 20px; padding: 20px; background: rgba(255,255,255,0.5); border-radius: 18px; border: 1px solid rgba(255,255,255,0.8); margin-bottom: 30px; }
        .profile-card img { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-info h2 { margin: 0; font-size: 22px; }
        .profile-info p { margin: 5px 0; color: #666; font-size: 14px; }

        /* GRID EVALUACION */
        .evaluation-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .eval-item { background: white; padding: 20px; border-radius: 20px; text-align: center; border: 1px solid #eee; transition: 0.3s; }
        .eval-item:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .eval-item h3 { font-size: 15px; color: #555; margin-bottom: 15px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .level-badge { font-size: 22px; font-weight: 800; color: var(--primary); }
        .last-update { font-size: 11px; color: #999; margin-top: 10px; }

        /* GRÁFICAS */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: white; padding: 20px; border-radius: 20px; border: 1px solid #eee; height: 300px; }
        .chart-card h4 { margin: 0 0 15px 0; font-size: 14px; color: #888; text-transform: uppercase; text-align: center; }

        /* TABLA HISTORIAL */
        .history-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid #eee; }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .history-table th { text-align: left; padding: 12px; color: #888; font-weight: 600; border-bottom: 2px solid #f5f5f5; }
        .history-table td { padding: 12px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .history-tag { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .history-tag.memoria { background: #e0f2fe; color: #0369a1; }
        .history-tag.logica { background: #dcfce7; color: #166534; }
        .history-tag.razonamiento { background: #fef3c7; color: #92400e; }
        .history-tag.atencion { background: #f3e8ff; color: #6b21a8; }

        @media (max-width: 800px) {
            .charts-grid { grid-template-columns: 1fr; }
            .navbar { padding: 0 20px; }
            .nav-links { display: none; } /* Ocultar links en movil para simplicidad */
        }
    </style>
</head>
<body>

    <div class="canvas-bg"></div>

    <nav class="navbar">
        <div class="brand">
            <div class="brand-text">Centro de día Pere Bas</div>
        </div>
        <div class="nav-links">
            <a href="familiar.php" class="nav-item">
                <i class="fas fa-home"></i> Inicio
            </a>
            <a href="ver_progreso_familiar.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> Mi Progreso
            </a>
            <a href="lista_profesionales.php" class="nav-item">
                <i class="fas fa-comments"></i> Chat Profesional
            </a>
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
                    <h2><?= htmlspecialchars($usuario["nombre"]) ?></h2>
                    <p>Usuario del centro bajo su seguimiento</p>
                    <span class="role-badge" style="background:#f3f4f6; color:#374151;">Usuario Activo</span>
                </div>
            </div>

            <div class="evaluation-grid">
                <?php 
                $iconos = ['logica'=>'lightbulb', 'memoria'=>'brain', 'razonamiento'=>'cogs', 'atencion'=>'bullseye'];
                foreach($niveles_actuales as $cat => $info): ?>
                <div class="eval-item">
                    <h3><i class="fas fa-<?= $iconos[$cat] ?>"></i> <?= ucfirst($cat) ?></h3>
                    <div class="level-badge"><?= htmlspecialchars($info['nivel']) ?></div>
                    <div class="last-update">Actualizado: <?= $info['fecha'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h4>Evolución Memoria</h4>
                    <canvas id="chartMemoria"></canvas>
                </div>
                <div class="chart-card">
                    <h4>Evolución Lógica</h4>
                    <canvas id="chartLogica"></canvas>
                </div>
                <div class="chart-card">
                    <h4>Evolución Razonamiento</h4>
                    <canvas id="chartRazonamiento"></canvas>
                </div>
                <div class="chart-card">
                    <h4>Evolución Atención</h4>
                    <canvas id="chartAtencion"></canvas>
                </div>
            </div>

            <div class="history-card">
                <h3><i class="fas fa-history"></i> Historial Reciente</h3>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Categoría</th>
                            <th>Dificultad</th>
                            <th>Puntuación</th>
                            <th>Tiempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historialResultados as $fila): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($fila['fecha_juego'])) ?></td>
                            <td><span class="history-tag <?= $fila['tipo_juego'] ?>"><?= ucfirst($fila['tipo_juego']) ?></span></td>
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
        const datos = <?= $jsonGraficas ?>;
        const config = (color) => ({
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });

        function render(id, cat, color) {
            const ctx = document.getElementById(id);
            if(!ctx || !datos[cat].data.length) return;
            new Chart(ctx, {
                ...config(color),
                data: {
                    labels: datos[cat].labels,
                    datasets: [{
                        data: datos[cat].data,
                        borderColor: color,
                        backgroundColor: color + '22',
                        fill: true,
                        tension: 0.3
                    }]
                }
            });
        }

        render('chartMemoria', 'memoria', '#3b82f6');
        render('chartLogica', 'logica', '#10b981');
        render('chartRazonamiento', 'razonamiento', '#f59e0b');
        render('chartAtencion', 'atencion', '#8b5cf6');
    </script>
</body>
</html>