<?php
// guardar_resultado.php
// Guarda en BD la puntuación y tiempo de cada partida (memoria / logica / razonamiento)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Solo aceptamos POST (se llama desde fetch)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . "/includes/conexion.php";
require_once __DIR__ . "/includes/auth.php";

// Debe haber usuario logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

// Leer JSON enviado por fetch()
$input = file_get_contents("php://input");
$data  = json_decode($input, true);

if (!is_array($data)) {
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$tipo_juego      = $data['tipo_juego']      ?? '';
$puntuacion      = isset($data['puntuacion'])      ? (int)$data['puntuacion']      : 0;
$tiempo_segundos = isset($data['tiempo_segundos']) ? (int)$data['tiempo_segundos'] : 0;
$dificultad      = $data['dificultad']      ?? '';

if ($tipo_juego === '' || $dificultad === '') {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    $stmt = $conexion->prepare("
        INSERT INTO resultados_juego
            (usuario_id, tipo_juego, puntuacion, tiempo_segundos, dificultad, fecha_juego)
        VALUES
            (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $usuario_id,
        $tipo_juego,
        $puntuacion,
        $tiempo_segundos,
        $dificultad
    ]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
}
