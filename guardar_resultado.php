<?php
// guardar_resultado.php - Versión Unificada

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once "includes/conexion.php";
require_once "includes/auth.php";

try {
    // Solo usuarios pueden guardar resultados
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
$data = json_decode($rawBody, true);

// Soporte para envío tradicional si no es JSON
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$tipo_juego      = strtolower($data['tipo_juego'] ?? '');
$dificultad      = $data['dificultad'] ?? 'facil';
$tiempo_segundos = (int)($data['tiempo_segundos'] ?? 0);
$validGames      = ['memoria', 'logica', 'razonamiento', 'atencion'];

if (!in_array($tipo_juego, $validGames, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tipo_juego no válido']);
    exit;
}

/* =====================================================
    LÓGICA ESPECÍFICA: CALCULAR PUNTUACIÓN
===================================================== */
if ($tipo_juego === 'razonamiento') {
    if (!isset($data['rondas']) || !is_array($data['rondas']) || count($data['rondas']) === 0) {
        echo json_encode(['ok' => false, 'msg' => 'Razonamiento sin rondas: ignorado']);
        exit;
    }
    $total_rondas = count($data['rondas']);
    $aciertos = 0;
    foreach ($data['rondas'] as $r) {
        if (!empty($r['correcta'])) $aciertos++;
    }
    $puntuacion = round(($aciertos / $total_rondas) * 100);
} else {
    $puntuacion = (int)($data['puntuacion'] ?? 0);
}

/* =====================================================
    VALIDACIÓN EXTRA: ATENCIÓN
===================================================== */
if ($tipo_juego === 'atencion' && (!isset($data['eventos']) || empty($data['eventos']))) {
    echo json_encode(['ok' => false, 'msg' => 'Atención sin eventos: ignorado']);
    exit;
}

try {
    $conexion->beginTransaction();

    // 2) INSERTAR EN resultados_juego
    $stmt = $conexion->prepare("
        INSERT INTO resultados_juego 
            (usuario_id, tipo_juego, puntuacion, tiempo_segundos, dificultad, fecha_juego) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$usuario_id, $tipo_juego, $puntuacion, $tiempo_segundos, $dificultad]);
    $resultado_id = $conexion->lastInsertId();

    // 3) GUARDAR DETALLES SEGÚN EL JUEGO
    
    // Si es RAZONAMIENTO: guardar rondas
    if ($tipo_juego === 'razonamiento') {
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

    // Si es ATENCIÓN: guardar eventos
    if ($tipo_juego === 'atencion') {
        $stmt_a = $conexion->prepare("
            INSERT INTO atencion_eventos (resultado_id, estimulo, respuesta, tiempo_reaccion) 
            VALUES (?, ?, ?, ?)
        ");
        foreach ($data['eventos'] as $evento) {
            $stmt_a->execute([
                $resultado_id,
                (int)($evento['estimulo'] ?? 0),
                (int)($evento['respuesta'] ?? 0),
                (int)($evento['tiempo_reaccion'] ?? 0)
            ]);
        }
    }

    $conexion->commit();

    echo json_encode([
        'ok'           => true,
        'resultado_id' => $resultado_id,
        'puntuacion'   => $puntuacion,
        'detalle'      => "Datos de $tipo_juego guardados correctamente"
    ]);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error de base de datos',
        'msg'   => $e->getMessage()
    ]);
}
?>