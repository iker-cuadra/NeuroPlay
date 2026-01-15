<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/conexion.php";
require_once "includes/auth.php";

requireRole("profesional");

// -------------------------
// PROCESAR GUARDADO (POST)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_evaluation"])) {
    $user_id_post   = (int)($_POST["user_id"] ?? 0);
    $profesional_id = $_SESSION["usuario_id"] ?? 0;
    $fecha          = date('Y-m-d H:i:s');

    $memoria      = $_POST["memoria"] ?? "Fácil";
    $logica       = $_POST["logica"] ?? "Fácil";
    $razonamiento = $_POST["razonamiento"] ?? "Fácil";
    $atencion     = $_POST["atencion"] ?? "Fácil"; 

    try {
        $stmt_update = $conexion->prepare("UPDATE dificultades_asignadas SET dificultad_memoria=?, dificultad_logica=?, dificultad_razonamiento=?, dificultad_atencion=?, asignado_por=?, fecha_actualizacion=? WHERE usuario_id=?");
        $stmt_update->execute([$memoria, $logica, $razonamiento, $atencion, $profesional_id, $fecha, $user_id_post]);

        if ($stmt_update->rowCount() === 0) {
            $stmt_insert = $conexion->prepare("INSERT INTO dificultades_asignadas (usuario_id, dificultad_memoria, dificultad_logica, dificultad_razonamiento, dificultad_atencion, asignado_por, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->execute([$user_id_post, $memoria, $logica, $razonamiento, $atencion, $profesional_id, $fecha]);
        }
        $_SESSION["flash_success"] = "Configuración actualizada.";
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Error: " . $e->getMessage();
    }
    header("Location: evaluar_usuario.php?user_id=" . $user_id_post);
    exit;
}

// -------------------------
// OBTENER DATOS
// -------------------------
$user_id = (int)($_GET['user_id'] ?? 0);
$stmt = $conexion->prepare("SELECT u1.*, u2.nombre AS nombre_familiar FROM usuarios u1 LEFT JOIN usuarios u2 ON u1.familiar_id = u2.id WHERE u1.id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) { header("Location: gestionar_users.php"); exit; }

$stmt_eval = $conexion->prepare("SELECT d.*, u.nombre AS asignador FROM dificultades_asignadas d LEFT JOIN usuarios u ON d.asignado_por = u.id WHERE d.usuario_id = ?");
$stmt_eval->execute([$user_id]);
$res = $stmt_eval->fetch(PDO::FETCH_ASSOC);

$niveles = ['memoria', 'logica', 'razonamiento', 'atencion'];
$actuales = [];
foreach($niveles as $n) { $actuales[$n] = $res["dificultad_$n"] ?? 'Fácil'; }

$stmt_hist = $conexion->prepare("SELECT * FROM resultados_juego WHERE usuario_id = ? ORDER BY fecha_juego DESC LIMIT 6");
$stmt_hist->execute([$user_id]);
$historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

$flash_success = $_SESSION["flash_success"] ?? "";
$flash_error = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación - <?= htmlspecialchars($usuario["nombre"]) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --header-h: 160px;
            --radius: 20px;
            --card: #ffffff;
            --shadow: 0 12px 30px rgba(0,0,0,0.10);
            --primary: #4a4a4a;
            --border: #eef0f3;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { 
            height: 100%;
            font-family: 'Poppins', sans-serif; 
            overflow: hidden;
            background-color: transparent;
        }

        .canvas-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: #e5e5e5;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                              radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%), 
                              radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 200% 200%; animation: meshMove 8s infinite alternate ease-in-out;  
        }
        @keyframes meshMove { 0% { background-position: 0% 0%; } 100% { background-position: 100% 100%; } }

        .layout { height: 100vh; display: flex; flex-direction: column; }

        .header {
            width: 100%; height: var(--header-h);
            background: url('imagenes/Banner.svg') center/cover;
            position: relative; flex: 0 0 auto;
        }

        .back-arrow {
            position: absolute; top: 15px; left: 15px;
            text-decoration: none; display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .back-arrow:hover { transform: scale(1.2) translateX(-3px); }

        .user-role-label {
            position: absolute; bottom: 10px; left: 20px;
            color: white; font-weight: 700; font-size: 18px;
        }

        .page-content {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 14px 16px;
            overflow: hidden;
            min-height: 0;
        }

        .panel { 
            width: min(1200px, 95vw);
            background: var(--card); 
            border-radius: var(--radius); 
            box-shadow: var(--shadow);
            padding: 16px; /* Igual al panel de gestión */
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
            overflow: hidden; /* El contenedor base no scrollea */
        }

        /* Contenedor con scroll para el contenido interno del panel */
        .panel-scroll {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-card {
            display: flex; align-items: center; gap: 15px; padding: 12px;
            background: #fafbfc; border-radius: 16px; border: 1px solid var(--border);
        }

        .profile-card img {
            width: 58px; height: 58px; border-radius: 50%; 
            object-fit: cover; border: 3px solid white;
        }

        .profile-info h2 { font-size: 18px; font-weight: 800; }

        .eval-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 12px; 
        }

        .eval-item { 
            padding: 15px; border-radius: 16px; border: 1px solid var(--border); 
            background: white; text-align: center;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .eval-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
            border-color: #4a4a4a;
        }

        .icon-box { font-size: 20px; color: var(--primary); margin-bottom: 8px; }

        .current-level { font-size: 16px; font-weight: 800; color: #4a4a4a; margin-bottom: 10px; }

        .custom-select { 
            width: 100%; padding: 8px; border-radius: 10px; border: 1px solid #dde2ea;
            font-family: inherit; font-size: 12px; font-weight: 600; cursor: pointer;
        }

        .btn-save { 
            width: 100%; background: var(--primary); color: white; padding: 14px; 
            border-radius: 14px; font-weight: 700; border: none; cursor: pointer;
            transition: all 0.3s ease; font-size: 14px;
        }
        .btn-save:hover { background: #333; transform: translateY(-2px); }

        .flash { padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 10px; }
        .success { background: #e8fff0; color: #0a7a3a; border: 1px solid #c9f2d7; }
        .error { background: #ffecec; color: #c0392b; border: 1px solid #ffd0d0; }

        .history-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .history-table th { text-align: left; padding: 8px 10px; color: #888; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .history-table tr { background: #fdfdfd; box-shadow: 0 2px 5px rgba(0,0,0,0.02); } 
        .history-table td { padding: 12px 10px; font-size: 13px; }
        .history-table tr td:first-child { border-radius: 12px 0 0 12px; }
        .history-table tr td:last-child { border-radius: 0 12px 12px 0; }

        @media (max-width: 900px) {
            .eval-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="canvas-bg"></div>
    <div class="layout">
        <header class="header">
            <a href="gestionar_users.php" class="back-arrow">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="white"><path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/></svg>
            </a>
            <div class="user-role-label">Evaluación de Entrenamiento</div>
        </header>

        <main class="page-content">
            <div class="panel">
                <?php if ($flash_success): ?><div class="flash success"><?= $flash_success ?></div><?php endif; ?>
                <?php if ($flash_error): ?><div class="flash error"><?= $flash_error ?></div><?php endif; ?>

                <div class="panel-scroll">
                    <div class="profile-card">
                        <img src="uploads/<?= $usuario['foto'] ?: 'default.png' ?>">
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($usuario['nombre']) ?></h2>
                            <p style="font-size: 13px; color: #666;"><?= htmlspecialchars($usuario['email']) ?></p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">
                        <input type="hidden" name="save_evaluation" value="1">
                        
                        <div class="eval-grid">
                            <?php 
                            $secciones = [
                                'logica' => ['Lógica', 'lightbulb'],
                                'memoria' => ['Memoria', 'brain'],
                                'razonamiento' => ['Razonamiento', 'gears'],
                                'atencion' => ['Atención', 'eye']
                            ];
                            foreach($secciones as $key => $s): ?>
                                <div class="eval-item">
                                    <div class="icon-box"><i class="fas fa-<?= $s[1] ?>"></i></div>
                                    <p style="font-size: 10px; color: #888; font-weight: 800; text-transform: uppercase;"><?= $s[0] ?></p>
                                    <div class="current-level"><?= $actuales[$key] ?></div>
                                    <select name="<?= $key ?>" class="custom-select">
                                        <option value="Fácil" <?= $actuales[$key]=='Fácil'?'selected':'' ?>>Fácil</option>
                                        <option value="Intermedio" <?= $actuales[$key]=='Intermedio'?'selected':'' ?>>Intermedio</option>
                                        <option value="Difícil" <?= $actuales[$key]=='Difícil'?'selected':'' ?>>Difícil</option>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn-save" style="margin-top: 15px;">
                            <i class="fas fa-save" style="margin-right: 8px;"></i> Guardar Cambios
                        </button>
                    </form>

                    <div class="history-section">
                        <h3 style="font-size: 15px; margin: 10px 0; font-weight: 800;">Últimos Resultados</h3>
                        <table class="history-table">
                            <thead>
                                <tr><th>Fecha</th><th>Juego</th><th>Nivel</th><th>Puntaje</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historial)): ?>
                                    <tr><td colspan="4" style="text-align:center; color:#999;">No hay resultados registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach($historial as $h): ?>
                                    <tr>
                                        <td><?= date('d/m/y', strtotime($h['fecha_juego'])) ?></td>
                                        <td style="text-transform:capitalize; font-weight:600;"><?= $h['tipo_juego'] ?></td>
                                        <td><?= $h['dificultad'] ?></td>
                                        <td><span style="font-weight:700; color:#2e7d32;"><?= $h['puntuacion'] ?>%</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>