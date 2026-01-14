<?php
// guardar_resultado.php
// Archivo en la RAÍZ del proyecto

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once "includes/conexion.php";
require_once "includes/auth.php";

// Solo usuarios pueden guardar resultados
try {
    requireRole("usuario");
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;

// 1) LEER DATOS (JSON o POST normal)
$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

// Si no viene JSON (por si algún juego envia formulario normal)
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// 2) LIMPIAR / VALIDAR
$tipo_juego      = $data['tipo_juego']      ?? '';
$puntuacion      = isset($data['puntuacion']) ? (int)$data['puntuacion'] : 0;
$tiempo_segundos = isset($data['tiempo_segundos']) ? (int)$data['tiempo_segundos'] : 0;
$dificultad      = $data['dificultad']      ?? '';

// valores válidos según tu ENUM de la tabla
$validGames = ['memoria', 'logica', 'razonamiento', 'atencion'];

if ($usuario_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Usuario no válido en sesión'
    ]);
    exit;
}

if (!in_array($tipo_juego, $validGames, true)) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'tipo_juego no válido',
        'debug_tipo_juego_recibido' => $tipo_juego
    ]);
    exit;
}

try {
    // 3) INSERTAR EN LA TABLA resultados_juego
    $stmt = $conexion->prepare("
        INSERT INTO resultados_juego
            (usuario_id, tipo_juego, puntuacion, tiempo_segundos, dificultad)
        VALUES
            (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        $tipo_juego,
        $puntuacion,
        $tiempo_segundos,
        $dificultad
    ]);

    echo json_encode([
        'ok'        => true,
        'insert_id' => $conexion->lastInsertId(),
        'debug'     => [
            'usuario_id'      => $usuario_id,
            'tipo_juego'      => $tipo_juego,
            'puntuacion'      => $puntuacion,
            'tiempo_segundos' => $tiempo_segundos,
            'dificultad'      => $dificultad
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'       => false,
        'error'    => 'Error al guardar en la base de datos',
        'sqlError' => $e->getMessage()
    ]);
}
