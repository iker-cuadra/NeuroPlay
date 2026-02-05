<?php
// atencio.php  (ruta: juegos/atencionjuego/atencio.php)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

// Solo usuarios pueden acceder
requireRole("usuario");

$usuario_id = $_SESSION["usuario_id"] ?? 0;
if (!$usuario_id) {
    header("Location: ../../login.php");
    exit;
}

// OBTENER DIFICULTAD ASIGNADA PARA ATENCIÓN
// Inicializar variable con valor por defecto
$dificultad_atencion = "Intermedio";

try {
    $stmt = $conexion->prepare("
        SELECT dificultad_atencion
        FROM dificultades_asignadas
        WHERE usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetchColumn();

    // Solo actualizar si se encontró un resultado
    if ($result !== false && !empty($result)) {
        $dificultad_atencion = $result;
    }
} catch (Exception $e) {
    // En caso de error, mantener el valor por defecto
    $dificultad_atencion = "Intermedio";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Juego de Atención</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            font-size: 18px;
        }

        /* --- FONDO MESH ANIMADO 8s --- */
        .canvas-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            background: #e5e5e5;
            background-image:
                radial-gradient(at 0% 0%, hsla(253, 16%, 7%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225, 39%, 30%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339, 49%, 30%, 1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(321, 0%, 100%, 1) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(0, 0%, 80%, 1) 0, transparent 50%);
            background-size: 200% 200%;
            animation: meshMove 8s infinite alternate ease-in-out;
        }

        @keyframes meshMove {
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 100% 100%;
            }
        }

        /* ENVOLTORIO A PANTALLA COMPLETA */
        .game-wrapper {
            height: 100vh;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px;
            box-sizing: border-box;
        }

        /* CONTENEDOR DEL JUEGO (TARJETA PREMIUM) */
        .game-container {
            position: relative;
            width: min(1100px, 100%);
            height: 100%;
            max-height: 100%;

            background: linear-gradient(180deg, #ffffff 0%, #fbfbfb 100%);
            border-radius: 26px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow:
                0 18px 40px rgba(0, 0, 0, 0.18),
                0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;

            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 20px 16px 20px;
            box-sizing: border-box;
        }

        /* Barra/acento superior sutil */
        .game-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 10px;
            background: linear-gradient(90deg, #4a4a4a 0%, #2f3742 50%, #4a4a4a 100%);
            opacity: 0.95;
            z-index: 1;
        }

        /* FLECHA VOLVER (COMO BOTÓN) */
        .back-arrow {
            position: absolute;
            top: 16px;
            left: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            cursor: pointer;
            text-decoration: none;
            z-index: 3;

            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(6px);
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.8;
            transform: translateX(-2px);
        }

        /* INFO SUPERIOR DERECHA (Timer y Dificultad) */
        .top-right-info {
            position: absolute;
            top: 20px;
            right: 20px;
            text-align: right;
            z-index: 3;
        }

        .difficulty-badge {
            background: rgba(74, 74, 74, 0.95);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .timer-display {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #4a4a4a;
            color: #111827;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 800;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 80px;
        }

        .timer-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        /* PASTILLA SUPERIOR */
        .game-title-pill {
            margin-top: 4px;
            margin-bottom: 0;
            padding: 6px 18px;
            border-radius: 999px;
            background: #4a4a4a;
            font-size: 34px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.4px;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.18);
            position: relative;
            z-index: 2;
        }

        .game-header {
            margin-top: 15px;
            margin-bottom: 8px;
            text-align: center;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 38px;
            color: #1f2937;
            letter-spacing: 0.2px;
        }

        .game-header p {
            margin: 4px 0;
            font-size: 18px;
            color: #4b5563;
            line-height: 1.25;
        }

        .game-header p strong {
            color: #111827;
        }

        /* CUERPO DEL JUEGO */
        .game-body {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            gap: 30px;
        }

        /* PUNTUACIÓN A LA IZQUIERDA */
        .score-sidebar {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
        }

        .score-pill {
            background: rgba(74, 74, 74, 0.95);
            color: #ffffff;
            padding: 20px 24px;
            border-radius: 18px;
            font-size: 22px;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.18);
            text-align: center;
            min-width: 140px;
        }

        .score-pill .score-label {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.85;
            margin-bottom: 4px;
        }

        .score-pill .score-value {
            font-size: 42px;
            font-weight: 800;
        }

        #zona-atencion {
            flex: 1;
            width: 100%;
            max-width: 850px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .symbol-grid {
            display: grid;
            gap: 20px;
            justify-content: center;
            transition: opacity 0.3s ease;
        }

        .symbol-card {
            width: 140px;
            height: 140px;
            border-radius: 20px;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 66px;
            color: #f9fafb;
            cursor: pointer;
            user-select: none;

            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 14px 24px rgba(0, 0, 0, 0.20);
            transition: transform 0.3s ease, box-shadow 0.3s ease, filter 0.3s ease, background 0.4s ease, opacity 0.4s ease;
        }

        .symbol-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 30px rgba(0, 0, 0, 0.24);
            filter: brightness(1.05);
        }

        .symbol-card.wrong {
            background: #b91c1c;
        }

        .symbol-card.correct {
            background: #16a34a;
        }

        .motivacion {
            margin-top: 8px;
            font-size: 19px;
            color: #16a34a;
            font-weight: 700;
            text-align: center;
            min-height: 24px;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        /* OVERLAYS */
        .game-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.50);
            border-radius: inherit;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .overlay-content {
            text-align: center;
            color: #fff;
            background: rgba(17, 24, 39, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 22px;
            padding: 18px 18px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
        }

        .overlay-content h3 {
            margin-top: 8px;
            margin-bottom: 6px;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .overlay-content p {
            margin: 6px 0;
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.05rem;
        }

        .overlay-buttons {
            margin-top: 14px;
        }

        /* BOTONES */
        .btn-game {
            background: #4a4a4a;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 22px;
            font-size: 18px;
            cursor: pointer;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.22);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin: 0 6px;
        }

        .btn-game:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(0, 0, 0, 0.28);
        }

        @media (max-height: 820px) {
            .symbol-card {
                width: 120px;
                height: 120px;
                font-size: 58px;
            }

            .symbol-grid {
                gap: 18px;
            }

            .game-header h2 {
                font-size: 34px;
            }

            .game-title-pill {
                font-size: 30px;
            }

            .score-pill .score-value {
                font-size: 36px;
            }
        }

        @media (max-height: 720px) {
            .symbol-card {
                width: 110px;
                height: 110px;
                font-size: 52px;
            }

            .symbol-grid {
                gap: 16px;
            }

            .game-header h2 {
                font-size: 32px;
            }

            .game-title-pill {
                font-size: 28px;
                padding: 6px 16px;
            }

            .score-pill .score-value {
                font-size: 32px;
            }
        }

        @media (max-width: 768px) {
            .game-container {
                padding: 16px 14px 12px 14px;
            }

            .game-body {
                flex-direction: column;
                gap: 20px;
            }

            .score-sidebar {
                flex-direction: row;
            }

            .symbol-card {
                width: 105px;
                height: 105px;
                font-size: 50px;
            }

            .top-right-info {
                top: 12px;
                right: 12px;
            }

            .difficulty-badge {
                font-size: 14px;
                padding: 5px 12px;
            }

            .timer-display {
                font-size: 18px;
                padding: 6px 14px;
            }
        }
    </style>
</head>

<body>

    <div class="canvas-bg"></div>

    <div class="game-wrapper">
        <div class="game-container">

            <!-- Flecha volver al panel de usuario -->
            <a href="../../usuario.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
                </svg>
            </a>

            <!-- Info superior derecha -->
            <div class="top-right-info">
                <div class="difficulty-badge">
                    Dificultad: <?= htmlspecialchars($dificultad_atencion) ?>
                </div>
                <div class="timer-display">
                    <i class="far fa-clock"></i>
                    <span id="time-left" class="timer-value">01:00</span>
                </div>
            </div>

            <!-- Pastilla superior -->
            <div class="game-title-pill">Atención</div>

            <!-- Cabecera -->
            <div class="game-header">
                <h2>Encuentra el símbolo diferente</h2>
                <p>
                    Observa todos los símbolos y pulsa sobre el que <strong>NO</strong> es igual a los demás.
                </p>
            </div>

            <!-- Cuerpo del juego -->
            <div class="game-body">
                <!-- Puntuación a la izquierda -->
                <div class="score-sidebar">
                    <div class="score-pill">
                        <div class="score-label">Puntuación</div>
                        <div class="score-value" id="score">0</div>
                    </div>
                </div>

                <!-- Zona de juego -->
                <div id="zona-atencion"></div>
            </div>

            <!-- Mensaje motivacional -->
            <div id="motivacion" class="motivacion"></div>

            <!-- OVERLAY FINAL -->
            <div id="game-overlay" class="game-overlay">
                <div id="overlay-content" class="overlay-content"></div>
            </div>

            <!-- OVERLAY INICIAL -->
            <div id="start-overlay" class="game-overlay" style="display:flex; z-index: 6;">
                <div class="overlay-content">
                    <p style="margin:0 0 14px 0; font-size:24px; font-weight:800;">¿Listo para jugar?</p>
                    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                        <button id="btn-start" class="btn-game">Empezar</button>
                        <button id="btn-start-back" class="btn-game">Volver</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // ============================
        //  DIFICULTAD DESDE PHP
        // ============================
        const dificultadTexto = "<?= htmlspecialchars($dificultad_atencion, ENT_QUOTES) ?>";

        function mapDifficultyCode(texto) {
            const t = (texto || "").toLowerCase();
            if (t === "fácil" || t === "facil") return "facil";
            if (t === "difícil" || t === "dificil") return "dificil";
            return "medio";
        }

        let currentDifficulty = mapDifficultyCode(dificultadTexto);

        // ============================
        //  VARIABLES GLOBALES
        // ============================
        const TOTAL_TIME = 60; // segundos
        let attentionTimeLeft = TOTAL_TIME;
        let attentionTimerInt = null;
        let roundTimeoutId = null;
        let gameScore = 0;
        let gameEnded = false;

        // métricas
        let aciertos = 0;
        let fallos = 0;
        let rondas = 0;

        // tiempos de reacción
        let roundStartMs = 0;

        // eventos para DB (si existe atencion_eventos)
        // { estimulo: 1 si es el diferente, respuesta: 1 si clicó diferente, tiempo_reaccion: ms }
        let eventos = [];

        // ============================
        //  UTILIDADES UI
        // ============================
        function updateScoreboard() {
            const scoreEl = document.getElementById('score');
            const timeEl = document.getElementById('time-left');

            if (scoreEl) scoreEl.textContent = String(gameScore);

            if (timeEl) {
                const m = Math.floor(attentionTimeLeft / 60);
                const s = attentionTimeLeft % 60;
                timeEl.textContent = String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
            }
        }

        function getMotivationalMessage() {
            const mensajes = [
                "¡Buen trabajo! Tu atención va mejorando.",
                "¡Genial! Has mantenido la concentración todo el tiempo.",
                "¡Muy bien! Cada ronda refuerza tu capacidad de foco.",
                "¡Excelente! Has completado el ejercicio de atención.",
                "¡Lo estás haciendo de maravilla!"
            ];
            return mensajes[Math.floor(Math.random() * mensajes.length)];
        }

        function showOverlay() {
            const overlay = document.getElementById("game-overlay");
            if (overlay) overlay.style.display = "flex";
        }

        function hideOverlay() {
            const overlay = document.getElementById("game-overlay");
            if (overlay) overlay.style.display = "none";
        }

        // ============================
        //  GUARDAR RESULTADO
        // ============================
        function saveAttentionResult() {
            // tiempo real jugado (si termina antes por lo que sea)
            const tiempoJugado = Math.max(0, TOTAL_TIME - attentionTimeLeft);

            // puntuación normalizada (0..100) además de la score cruda si quieres
            // aquí usamos una métrica simple: ratio de aciertos sobre intentos.
            const intentos = aciertos + fallos;
            const precision = intentos > 0 ? (aciertos / intentos) : 0;
            const puntuacionNormalizada = Math.round(precision * 100);

            const detalles = {
                juego: "atencion",
                dificultad: dificultadTexto,
                tiempo_total_segundos: tiempoJugado,
                score_cruda: gameScore,
                puntuacion_normalizada: puntuacionNormalizada,
                rondas: rondas,
                aciertos: aciertos,
                fallos: fallos,
                precision: precision,
                eventos: eventos
            };

            return fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_juego: 'atencion',

                    // panel principal
                    puntuacion: puntuacionNormalizada,
                    tiempo_segundos: tiempoJugado,
                    dificultad: dificultadTexto,

                    // columnas nuevas (si existen)
                    aciertos: aciertos,
                    fallos: fallos,
                    nivel_alcanzado: rondas,
                    detalles_json: JSON.stringify(detalles),

                    // para tabla atencion_eventos (si existe)
                    eventos: eventos
                })
            })
                .then(r => r.json())
                .then(data => {
                    console.log('Respuesta guardar_resultado (atencion):', data);
                    return data;
                })
                .catch(err => {
                    console.error('Error al guardar resultado de atención:', err);
                    return { ok: false };
                });
        }

        // ============================
        //  INICIO / FIN DE PARTIDA
        // ============================
        function startNewAttentionGame() {
            const area = document.getElementById('zona-atencion');
            const motivacionDiv = document.getElementById('motivacion');

            if (motivacionDiv) motivacionDiv.textContent = "";
            hideOverlay();

            // reset estado
            gameEnded = false;
            attentionTimeLeft = TOTAL_TIME;
            gameScore = 0;

            aciertos = 0;
            fallos = 0;
            rondas = 0;
            eventos = [];

            if (attentionTimerInt) clearInterval(attentionTimerInt);
            if (roundTimeoutId) clearTimeout(roundTimeoutId);
            attentionTimerInt = null;
            roundTimeoutId = null;

            updateScoreboard();
            if (area) area.style.pointerEvents = "auto";

            startAttentionRound(area);

            attentionTimerInt = setInterval(() => {
                attentionTimeLeft--;
                if (attentionTimeLeft < 0) attentionTimeLeft = 0;
                updateScoreboard();

                if (attentionTimeLeft <= 0) endAttentionGame();
            }, 1000);
        }

        function endAttentionGame() {
            if (gameEnded) return;
            gameEnded = true;

            if (attentionTimerInt) clearInterval(attentionTimerInt);
            if (roundTimeoutId) clearTimeout(roundTimeoutId);
            attentionTimerInt = null;
            roundTimeoutId = null;

            const area = document.getElementById('zona-atencion');
            const motivacionDiv = document.getElementById('motivacion');

            if (area) area.style.pointerEvents = "none";

            const tiempoJugado = Math.max(0, TOTAL_TIME - attentionTimeLeft);
            const intentos = aciertos + fallos;
            const precision = intentos > 0 ? (aciertos / intentos) : 0;
            const puntuacionNormalizada = Math.round(precision * 100);

            if (motivacionDiv) {
                motivacionDiv.textContent =
                    getMotivationalMessage() +
                    " Puntuación final: " + puntuacionNormalizada + " / 100.";
            }

            saveAttentionResult().finally(() => {
                const overlayContent = document.getElementById("overlay-content");
                if (overlayContent) {
                    overlayContent.innerHTML = `
                     <i class="fas fa-trophy" style="font-size:3rem; color:#facc15; margin-bottom:8px;"></i>
                     <h3>¡Juego terminado!</h3>
                    <p>Puntuación (0-100): <strong>${puntuacionNormalizada}</strong></p>
                    <p>Score (cruda): <strong>${gameScore}</strong></p>
                    <p>Aciertos: <strong>${aciertos}</strong> | Fallos: <strong>${fallos}</strong></p>
                    <p>Tiempo jugado: <strong>${String(Math.floor(tiempoJugado / 60)).padStart(2, '0')}:${String(tiempoJugado % 60).padStart(2, '0')}</strong></p>

                    <div class="overlay-buttons">
                        <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                        <button id="btn-volver" class="btn-game">Volver al panel</button>
                    </div>
                `;
                }

                showOverlay();

                const btnRestart = document.getElementById("btn-restart");
                const btnVolver = document.getElementById("btn-volver");

                if (btnRestart) btnRestart.onclick = () => startNewAttentionGame();
                if (btnVolver) btnVolver.onclick = () => window.location.href = "../../usuario.php";
            });
        }

        // ============================
        //  RONDAS Y SÍMBOLOS
        // ============================
        function startAttentionRound(area) {
            if (gameEnded || !area) return;

            rondas++;

            let symbolCount;
            let symbolPairs;

            if (currentDifficulty === 'facil') {
                symbolCount = 6;
                symbolPairs = [
                    { base: '★', different: '◆' },
                    { base: '■', different: '▲' },
                    { base: '●', different: '◆' },
                    { base: '▲', different: '■' }
                ];
            } else if (currentDifficulty === 'medio') {
                symbolCount = 9;
                symbolPairs = [
                    { base: '◆', different: '✦' },
                    { base: '■', different: '⬛' },
                    { base: '●', different: '◎' },
                    { base: '▲', different: '△' }
                ];
            } else {
                symbolCount = 12;
                symbolPairs = [
                    { base: '⬤', different: '◯' },
                    { base: '◆', different: '◇' },
                    { base: '■', different: '□' },
                    { base: '▲', different: '△' }
                ];
            }

            area.innerHTML = "";

            const grid = document.createElement('div');
            grid.className = "symbol-grid";
            grid.style.gridTemplateColumns = `repeat(${Math.ceil(Math.sqrt(symbolCount))}, 140px)`;
            area.appendChild(grid);

            generateAttentionExercise(grid, symbolCount, symbolPairs);

            if (roundTimeoutId) clearTimeout(roundTimeoutId);
            roundTimeoutId = setTimeout(() => {
                if (!gameEnded && attentionTimeLeft > 0) startAttentionRound(area);
            }, 15000);
        }

        function generateAttentionExercise(container, symbolCount, symbolPairs) {
            container.style.opacity = '0';

            setTimeout(() => {
                container.innerHTML = "";

                const pair = symbolPairs[Math.floor(Math.random() * symbolPairs.length)];
                const symbols = new Array(symbolCount).fill(pair.base);
                const differentIndex = Math.floor(Math.random() * symbolCount);
                symbols[differentIndex] = pair.different;

                // marca inicio ronda para reaction time
                roundStartMs = performance.now();

                symbols.forEach((symbol, index) => {
                    const card = document.createElement('div');
                    card.className = "symbol-card";
                    card.textContent = symbol;
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';

                    card.addEventListener('click', () => {
                        if (gameEnded) return;

                        const rt = Math.max(0, performance.now() - roundStartMs); // ms
                        const esDiferente = (index === differentIndex);

                        // estimulo: si la carta clicada era el diferente
                        // respuesta: 1 si clicó la correcta (diferente), 0 si no
                        eventos.push({
                            estimulo: esDiferente ? 1 : 0,
                            respuesta: esDiferente ? 1 : 0,
                            tiempo_reaccion: rt
                        });

                        if (esDiferente) {
                            aciertos++;
                            gameScore += 10;
                            card.classList.add("correct");
                            updateScoreboard();

                            const area = document.getElementById('zona-atencion');
                            setTimeout(() => {
                                if (!gameEnded && attentionTimeLeft > 0) startAttentionRound(area);
                            }, 600);
                        } else {
                            fallos++;
                            gameScore = Math.max(0, gameScore - 5);
                            card.classList.add("wrong");
                            updateScoreboard();

                            setTimeout(() => card.classList.remove("wrong"), 800);
                        }
                    });

                    container.appendChild(card);

                    setTimeout(() => {
                        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, index * 50);
                });

                container.style.transition = 'opacity 0.3s ease';
                container.style.opacity = '1';
            }, 300);
        }

        // ============================
        //  INICIALIZACIÓN
        // ============================
        document.addEventListener("DOMContentLoaded", function () {
            const startOverlay = document.getElementById("start-overlay");
            const btnStart = document.getElementById("btn-start");
            const btnStartBack = document.getElementById("btn-start-back");
            const zona = document.getElementById("zona-atencion");

            if (zona) zona.style.pointerEvents = "none";

            if (btnStart) {
                btnStart.addEventListener("click", function () {
                    if (startOverlay) startOverlay.style.display = "none";
                    if (zona) zona.style.pointerEvents = "auto";
                    startNewAttentionGame();
                });
            }

            if (btnStartBack) {
                btnStartBack.addEventListener("click", function () {
                    window.location.href = "../../usuario.php";
                });
            }
        });
    </script>

</body>

</html>