<?php
// guardar_resultado.php - Versión Final Blindada (con aciertos/fallos/detalles)

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
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
if ($usuario_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Usuario no válido en sesión'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) LEER DATOS (JSON o POST)
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST; // fallback
}

$tipo_juego      = strtolower(trim((string)($data['tipo_juego'] ?? '')));
$dificultad      = trim((string)($data['dificultad'] ?? 'Intermedio'));
$tiempo_segundos = (int)($data['tiempo_segundos'] ?? 0);
$puntuacion      = (int)($data['puntuacion'] ?? 0);

// Campos nuevos
$aciertos        = isset($data['aciertos']) ? (int)$data['aciertos'] : null;
$fallos          = isset($data['fallos']) ? (int)$data['fallos'] : null;
$nivel_alcanzado = isset($data['nivel_alcanzado']) ? (int)$data['nivel_alcanzado'] : null;

// Para detalles: acepta "detalles_json" ya como string JSON, o "detalles" como array
$detalles_json = null;
if (isset($data['detalles_json'])) {
    $detalles_json = is_string($data['detalles_json']) ? $data['detalles_json'] : json_encode($data['detalles_json'], JSON_UNESCAPED_UNICODE);
} elseif (isset($data['detalles'])) {
    $detalles_json = json_encode($data['detalles'], JSON_UNESCAPED_UNICODE);
}

$validGames = ['memoria', 'logica', 'razonamiento', 'atencion'];
if (!in_array($tipo_juego, $validGames, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tipo_juego no válido: ' . $tipo_juego], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------
// CÁLCULOS AUTOMÁTICOS (si faltan)
// ------------------------------

// RAZONAMIENTO: si vienen rondas, calculamos aciertos/fallos y % si no viene puntuación o si quieres recalcular
if ($tipo_juego === 'razonamiento' && isset($data['rondas']) && is_array($data['rondas'])) {
    $total_rondas = count($data['rondas']);
    if ($total_rondas > 0) {
        $ac = 0;
        foreach ($data['rondas'] as $r) {
            if (!empty($r['correcta'])) $ac++;
        }
        $fa = $total_rondas - $ac;

        if ($aciertos === null) $aciertos = $ac;
        if ($fallos === null)   $fallos   = $fa;

        // Recalcular puntuación a porcentaje (manteniendo tu lógica)
        $puntuacion = (int)round(($ac / $total_rondas) * 100);

        // Si no hay detalles_json, guardamos un resumen mínimo
        if ($detalles_json === null) {
            $detalles_json = json_encode([
                'total_rondas' => $total_rondas,
                'aciertos'     => $ac,
                'fallos'       => $fa
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

// ATENCIÓN: si vienen eventos, podemos inferir aciertos/fallos si no los envías
// Asumimos: acierto cuando respuesta == estimulo (ajústalo si tu lógica es distinta)
if ($tipo_juego === 'atencion' && $aciertos === null && $fallos === null && isset($data['eventos']) && is_array($data['eventos'])) {
    $ac = 0; $fa = 0;
    foreach ($data['eventos'] as $ev) {
        $estimulo  = isset($ev['estimulo']) ? (int)$ev['estimulo'] : null;
        $respuesta = isset($ev['respuesta']) ? (int)$ev['respuesta'] : null;
        if ($estimulo === null || $respuesta === null) continue;

        if ($respuesta === $estimulo) $ac++;
        else $fa++;
    }
    $aciertos = $ac;
    $fallos   = $fa;

    if ($detalles_json === null) {
        $detalles_json = json_encode([
            'total_eventos' => count($data['eventos']),
            'aciertos'      => $ac,
            'fallos'        => $fa
        ], JSON_UNESCAPED_UNICODE);
    }
}

// LOGICA / MEMORIA: si no mandas aciertos/fallos, no inventamos.
// (Lo correcto es que el JS de cada juego los envíe.)
if ($aciertos === null) $aciertos = 0;
if ($fallos === null)   $fallos   = 0;

// Seguridad básica
if ($tiempo_segundos < 0) $tiempo_segundos = 0;
if ($puntuacion < 0) $puntuacion = 0;
if ($puntuacion > 100) $puntuacion = 100;

try {
    $conexion->beginTransaction();

    // 2) INSERTAR EN resultados_juego (PANEL)
    // OJO: requiere que existan las columnas aciertos/fallos/nivel_alcanzado/detalles_json en tu tabla
    $stmt = $conexion->prepare("
        INSERT INTO resultados_juego 
            (usuario_id, tipo_juego, puntuacion, tiempo_segundos, dificultad, fecha_juego, aciertos, fallos, nivel_alcanzado, detalles_json) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        $tipo_juego,
        $puntuacion,
        $tiempo_segundos,
        $dificultad,
        $aciertos,
        $fallos,
        $nivel_alcanzado,
        $detalles_json
    ]);

    $resultado_id = (int)$conexion->lastInsertId();

    // 3) GUARDAR DETALLES POR JUEGO

    // RAZONAMIENTO -> razonamiento_rondas
    if ($tipo_juego === 'razonamiento' && !empty($data['rondas']) && is_array($data['rondas'])) {
        $stmt_r = $conexion->prepare("
            INSERT INTO razonamiento_rondas (resultado_id, ronda, correcta, tiempo_segundos) 
            VALUES (?, ?, ?, ?)
        ");
        foreach ($data['rondas'] as $ronda) {
            $stmt_r->execute([
                $resultado_id,
                (int)($ronda['ronda'] ?? 0),
                (int)($ronda['correcta'] ?? 0),
                (int)($ronda['tiempo_segundos'] ?? 0)
            ]);
        }
    }

    // ATENCIÓN -> atencion_eventos (si existe)
    if ($tipo_juego === 'atencion' && !empty($data['eventos']) && is_array($data['eventos'])) {
        $checkTable = $conexion->query("SHOW TABLES LIKE 'atencion_eventos'")->rowCount();

        if ($checkTable > 0) {
            $stmt_a = $conexion->prepare("
                INSERT INTO atencion_eventos (resultado_id, estimulo, respuesta, tiempo_reaccion) 
                VALUES (?, ?, ?, ?)
            ");
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
        'aciertos' => $aciertos,
        'fallos' => $fallos,
        'msg' => "Resultado guardado con éxito"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error de BD',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
