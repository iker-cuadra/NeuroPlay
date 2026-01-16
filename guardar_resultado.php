<?php
// guardar_resultado.php - Versión Final Blindada

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once "includes/conexion.php";
require_once "includes/auth.php";

try {
    // Solo usuarios pueden guardar sus propios resultados
    requireRole("usuario");
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if ($usuario_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Usuario no válido en sesión']);
    exit;
}

// 1) LEER DATOS
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?: $_POST;

$tipo_juego      = strtolower($data['tipo_juego'] ?? '');
$dificultad      = $data['dificultad'] ?? 'Intermedio';
$tiempo_segundos = (int)($data['tiempo_segundos'] ?? 0);
$puntuacion      = (int)($data['puntuacion'] ?? 0);

$validGames = ['memoria', 'logica', 'razonamiento', 'atencion'];

if (!in_array($tipo_juego, $validGames, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tipo_juego no válido: ' . $tipo_juego]);
    exit;
}

// LÓGICA ESPECÍFICA RAZONAMIENTO (Cálculo de porcentaje si vienen rondas)
if ($tipo_juego === 'razonamiento' && isset($data['rondas']) && is_array($data['rondas'])) {
    $total_rondas = count($data['rondas']);
    if ($total_rondas > 0) {
        $aciertos = 0;
        foreach ($data['rondas'] as $r) {
            if (!empty($r['correcta'])) $aciertos++;
        }
        $puntuacion = round(($aciertos / $total_rondas) * 100);
    }
}

try {
    $conexion->beginTransaction();

    // 2) INSERTAR EN resultados_juego (ESTO ES LO QUE SE VE EN EL PANEL)
    $stmt = $conexion->prepare("
        INSERT INTO resultados_juego 
            (usuario_id, tipo_juego, puntuacion, tiempo_segundos, dificultad, fecha_juego) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$usuario_id, $tipo_juego, $puntuacion, $tiempo_segundos, $dificultad]);
    $resultado_id = $conexion->lastInsertId();

    // 3) GUARDAR DETALLES POR JUEGO
    
    // DETALLES DE RAZONAMIENTO (Tabla razonamiento_rondas sí existe en tu BD)
    if ($tipo_juego === 'razonamiento' && !empty($data['rondas'])) {
        $stmt_r = $conexion->prepare("INSERT INTO razonamiento_rondas (resultado_id, ronda, correcta, tiempo_segundos) VALUES (?, ?, ?, ?)");
        foreach ($data['rondas'] as $ronda) {
            $stmt_r->execute([
                $resultado_id,
                (int)($ronda['ronda'] ?? 0),
                (int)($ronda['correcta'] ?? 0),
                (int)($ronda['tiempo_segundos'] ?? 0)
            ]);
        }
    }

    // DETALLES DE ATENCIÓN (Verificamos si existe la tabla para no romper el proceso)
    if ($tipo_juego === 'atencion' && !empty($data['eventos'])) {
        // Comprobación de seguridad: ¿Existe la tabla atencion_eventos?
        $checkTable = $conexion->query("SHOW TABLES LIKE 'atencion_eventos'")->rowCount();
        
        if ($checkTable > 0) {
            $stmt_a = $conexion->prepare("INSERT INTO atencion_eventos (resultado_id, estimulo, respuesta, tiempo_reaccion) VALUES (?, ?, ?, ?)");
            foreach ($data['eventos'] as $evento) {
                $stmt_a->execute([
                    $resultado_id,
                    (int)($evento['estimulo'] ?? 1),
                    (int)($evento['respuesta'] ?? 1),
                    (float)($evento['tiempo_reaccion'] ?? 0)
                ]);
            }
        }
    }

    $conexion->commit();

    echo json_encode([
        'ok' => true,
        'resultado_id' => $resultado_id,
        'puntuacion' => $puntuacion,
        'msg' => "Resultado guardado con éxito"
    ]);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error de BD',
        'msg' => $e->getMessage()
    ]);
}