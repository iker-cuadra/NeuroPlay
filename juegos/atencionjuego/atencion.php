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
$stmt = $conexion->prepare("
    SELECT dificultad_atencion
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_atencion = $stmt->fetchColumn();

// Valor por defecto si no hay registro
if (!$dificultad_atencion) {
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
            width: min(900px, 100%);
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

        /* PASTILLA SUPERIOR */
        .game-title-pill {
            margin-top: 4px;
            margin-bottom: 6px;
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
            margin-top: 30px;
            margin-bottom: 10px;
            text-align: center;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 36px;
            color: #1f2937;
            letter-spacing: 0.2px;
        }

        .game-header p {
            margin: 4px 0;
            font-size: 19px;
            color: #4b5563;
            line-height: 1.25;
        }

        .game-header p strong {
            color: #111827;
        }

        /* MARCADOR (Puntuación + Tiempo) */
        .status-bar {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .status-pill {
            background: rgba(243, 244, 246, 0.95);
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.08);
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .timer-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        /* CUERPO DEL JUEGO */
        .game-body {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 6px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }

        #zona-atencion {
            flex: 1;
            width: 100%;
            max-width: 720px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .instructions {
            text-align: center;
            font-size: 18px;
            color: #4b5563;
            margin-bottom: 10px;
        }

        .symbol-grid {
            display: grid;
            gap: 14px;
            justify-content: center;
            margin-top: 8px;
            transition: opacity 0.3s ease;
        }

        .symbol-card {
            width: 92px;
            height: 92px;
            border-radius: 18px;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: #f9fafb;
            cursor: pointer;
            user-select: none;

            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease, filter 0.3s ease, background 0.4s ease, opacity 0.4s ease;
        }

        .symbol-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 26px rgba(0, 0, 0, 0.22);
            filter: brightness(1.03);
        }

        .symbol-card.wrong {
            background: #b91c1c;
        }

        .symbol-card.correct {
            background: #16a34a;
        }

        .motivacion {
            margin-top: 10px;
            font-size: 20px;
            color: #16a34a;
            font-weight: 700;
            text-align: center;
            min-height: 24px;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        /* OVERLAY (se usa para final e inicio) */

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

        .overlay-content p {
            margin: 0 0 14px 0;
            font-size: 24px;
            font-weight: 800;
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

            <!-- Pastilla superior -->
            <div class="game-title-pill">Atención</div>

            <!-- Cabecera -->
            <div class="game-header">
                <h2>Encuentra el símbolo diferente</h2>
                <p>Observa todos los símbolos y pulsa sobre el que <strong>NO</strong> es igual a los demás.</p>
                <p>
                    Dificultad asignada:
                    <strong id="dificultad-label"><?= htmlspecialchars($dificultad_atencion) ?></strong>
                </p>

                <div class="status-bar">
                    <div class="status-pill">
                        Puntuación: <span id="score">0</span>
                    </div>
                    <div class="status-pill">
                        Tiempo restante:
                        <span id="time-left" class="timer-value">01:00</span>
                    </div>
                </div>
            </div>

            <!-- Cuerpo del juego -->
            <div class="game-body">
                <div id="zona-atencion"></div>
            </div>

            <!-- Mensaje motivacional -->
            <div id="motivacion" class="motivacion"></div>

            <!-- OVERLAY FINAL -->
            <div id="game-overlay" class="game-overlay">
                <div id="overlay-content" class="overlay-content"></div>
            </div>

            <!-- OVERLAY INICIAL (antes de empezar) -->
            <div id="start-overlay" class="game-overlay" style="display:flex; z-index: 6;">
                <div class="overlay-content">
                    <p>¿Listo para jugar?</p>
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
        let dificultadTexto = "<?= htmlspecialchars($dificultad_atencion, ENT_QUOTES) ?>";

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
        let attentionTimeLeft = 60;
        let attentionTimerInt = null;
        let roundTimeoutId = null;
        let gameScore = 0;
        let gameEnded = false;

        const TOTAL_TIME = 60;

        // ============================
        //  UTILIDADES DE UI
        // ============================
        function updateScoreboard() {
            const scoreEl = document.getElementById('score');
            const timeEl = document.getElementById('time-left');

            if (scoreEl) scoreEl.textContent = gameScore;

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
        function saveAttentionResult(score, seconds, dificultad) {
            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_juego: 'atencion',
                    puntuacion: score,
                    tiempo_segundos: seconds,
                    dificultad: dificultad
                })
            })
                .then(res => res.json())
                .catch(err => console.error('Error al guardar resultado de atención:', err));
        }

        // ============================
        //  INICIO / FIN DE PARTIDA
        // ============================
        function startNewAttentionGame() {
            const area = document.getElementById('zona-atencion');
            const motivacionDiv = document.getElementById('motivacion');
            if (motivacionDiv) motivacionDiv.textContent = "";

            hideOverlay();

            gameEnded = false;
            attentionTimeLeft = TOTAL_TIME;
            gameScore = 0;

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

            if (motivacionDiv) {
                motivacionDiv.textContent = getMotivationalMessage() + " Puntuación final: " + gameScore + " puntos.";
            }

            saveAttentionResult(gameScore, TOTAL_TIME, dificultadTexto);

            const overlayContent = document.getElementById("overlay-content");
            if (overlayContent) {
                overlayContent.innerHTML = `
                    <i class="fas fa-eye" style="font-size:3rem; color:#facc15; margin-bottom:6px;"></i>
                    <h3>¡Tiempo terminado!</h3>
                    <p>Puntuación final: <strong>${gameScore}</strong> puntos.</p>
                    <p>Tiempo de juego: <strong>01:00</strong></p>

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
        }

        // ============================
        //  RONDAS Y SÍMBOLOS
        // ============================
        function startAttentionRound(area) {
            if (gameEnded || !area) return;

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

            const instructions = document.createElement('p');
            instructions.className = "instructions";
            instructions.textContent = "Pulsa el símbolo diferente lo más rápido posible.";
            area.appendChild(instructions);

            const grid = document.createElement('div');
            grid.className = "symbol-grid";
            grid.style.gridTemplateColumns = `repeat(${Math.ceil(Math.sqrt(symbolCount))}, 92px)`;
            area.appendChild(grid);

            generateAttentionExercise(grid, symbolCount, symbolPairs);

            if (roundTimeoutId) clearTimeout(roundTimeoutId);
            roundTimeoutId = setTimeout(() => {
                if (!gameEnded && attentionTimeLeft > 0) startAttentionRound(area);
            }, 15000);
        }

        function generateAttentionExercise(container, symbolCount, symbolPairs) {
            // Fade out anterior
            container.style.opacity = '0';

            setTimeout(() => {
                container.innerHTML = "";

                const pair = symbolPairs[Math.floor(Math.random() * symbolPairs.length)];
                const symbols = new Array(symbolCount).fill(pair.base);
                const differentIndex = Math.floor(Math.random() * symbolCount);
                symbols[differentIndex] = pair.different;

                symbols.forEach((symbol, index) => {
                    const card = document.createElement('div');
                    card.className = "symbol-card";
                    card.textContent = symbol;
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';

                    card.addEventListener('click', () => {
                        if (gameEnded) return;

                        if (index === differentIndex) {
                            gameScore += 10;
                            card.classList.add("correct");
                            updateScoreboard();

                            const area = document.getElementById('zona-atencion');
                            setTimeout(() => {
                                if (!gameEnded && attentionTimeLeft > 0) startAttentionRound(area);
                            }, 600);
                        } else {
                            gameScore = Math.max(0, gameScore - 5);
                            card.classList.add("wrong");
                            updateScoreboard();

                            setTimeout(() => card.classList.remove("wrong"), 800);
                        }
                    });

                    container.appendChild(card);

                    // Animar entrada de cada carta con delay escalonado
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, index * 50);
                });

                // Fade in del contenedor
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

            // Bloquear interacción hasta empezar
            if (zona) zona.style.pointerEvents = "none";

            // No iniciar juego aquí; solo cuando pulses Empezar
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