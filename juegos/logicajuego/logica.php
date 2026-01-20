<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");

// OBTENER DIFICULTAD ASIGNADA PARA LÓGICA (Fácil / Medio / Difícil)
$usuario_id = $_SESSION["usuario_id"];

$stmt = $conexion->prepare("
    SELECT dificultad_logica
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_logica = $stmt->fetchColumn();

// Dificultad por defecto si no hay asignada
if (!$dificultad_logica) {
    $dificultad_logica = "Medio";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Juego de Lógica - Sudoku 4x4</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            background: #887d7dff;
            overflow: hidden;
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
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(321,0%,100%,1) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(0,0%,80%,1) 0, transparent 50%);
            background-size: 200% 200%;
            animation: meshMove 8s infinite alternate ease-in-out;
        }

        @keyframes meshMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
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

        /* CONTENEDOR DEL JUEGO (TARJETA MEJORADA) */
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

        /* FLECHA DE VOLVER (MEJORADA COMO BOTÓN) */
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

        /* PASTILLA SUPERIOR "Lógica" */
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
            font-size: 38px;
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

        .timer {
            font-weight: 800;
            font-size: 22px;
            margin-top: 4px;
            color: #111827;
        }

        /* ZONA CENTRAL DEL TABLERO */
        .game-body {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 6px 0;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }

        .logic-area {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .sudoku-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(70px, 1fr));
            gap: 10px;
            padding: 14px;
            border-radius: 18px;
            background: #f1f3f6;
            box-shadow: inset 0 0 8px rgba(0, 0, 0, 0.10);
            max-width: 380px;
            width: 100%;
        }

        .sudoku-cell {
            width: 100%;
            height: clamp(70px, 14vh, 110px);
            border-radius: 12px;
            border: 2px solid rgba(17, 24, 39, 0.55);
            text-align: center;
            font-size: 36px;
            font-weight: 800;
            box-sizing: border-box;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.12);
            background: #ffffff;
            color: #111;
            transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
        }

        .sudoku-cell:focus {
            outline: none;
            border-color: #0070f3;
            box-shadow: 0 0 0 3px rgba(0, 112, 243, 0.35), 0 12px 22px rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }

        .sudoku-cell:disabled {
            background: #2f3742;
            color: #ffffff;
            border-color: #10141a;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        /* MENSAJE / FEEDBACK */
        .logic-message {
            margin-top: 10px;
            font-size: 20px;
            color: #1b5e20;
            font-weight: 700;
            min-height: 24px;
            text-align: center;
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

        .overlay-content p {
            margin: 0 0 14px 0;
            font-size: 24px;
            font-weight: 800;
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

        /* Ajustes para alturas más bajas */
        @media (max-height: 760px) {
            .sudoku-grid { max-width: 350px; gap: 8px; }
            .sudoku-cell { height: clamp(65px, 13vh, 100px); font-size: 32px; }
            .game-header h2 { font-size: 34px; }
            .game-title-pill { font-size: 30px; }
        }

        @media (max-height: 680px) {
            .sudoku-grid { max-width: 330px; gap: 8px; }
            .sudoku-cell { height: clamp(60px, 12vh, 90px); font-size: 30px; }
            .game-header h2 { font-size: 32px; }
            .game-title-pill { font-size: 28px; padding: 6px 16px; }
        }

        @media (max-width: 768px) {
            .game-container { padding: 16px 14px 12px 14px; }
        }
    </style>
</head>

<body>

    <div class="canvas-bg"></div>

    <div class="game-wrapper">
        <div class="game-container">
            <!-- Flecha volver -->
            <a href="../../usuario.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
                </svg>
            </a>

            <!-- Pastilla superior -->
            <div class="game-title-pill">Lógica</div>

            <!-- Cabecera -->
            <div class="game-header">
                <h2>Sudoku 4x4</h2>
                <p>Completa el tablero con los números del <strong>1 al 4</strong>, sin repetir en filas ni columnas.</p>
                <p>Dificultad asignada: <strong><?= htmlspecialchars($dificultad_logica) ?></strong></p>
                <p class="timer">Tiempo: <span id="timer">00:00</span></p>
            </div>

            <!-- Cuerpo (tablero) -->
            <div class="game-body">
                <div class="logic-area">
                    <div id="zona-logica"></div>
                </div>
            </div>

            <!-- Mensaje / feedback -->
            <div id="logic-message" class="logic-message"></div>

            <!-- OVERLAY FINAL -->
            <div id="game-overlay" class="game-overlay">
                <div class="overlay-content">
                    <p>¡Sudoku completado!</p>
                    <button id="btn-restart" class="btn-game" type="button">Jugar otra vez</button>
                    <button id="btn-volver" class="btn-game" type="button">Volver al panel</button>
                </div>
            </div>

            <!-- OVERLAY INICIAL (antes de empezar) -->
            <div id="start-overlay" class="game-overlay" style="display:flex; z-index: 6;">
                <div class="overlay-content">
                    <p>¿Listo para jugar?</p>
                    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                        <button id="btn-start" class="btn-game" type="button">Empezar</button>
                        <button id="btn-start-back" class="btn-game" type="button">Volver</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Dificultad desde PHP (Fácil / Medio / Difícil)
        const dificultadLogicaBD = "<?= htmlspecialchars($dificultad_logica, ENT_QUOTES) ?>";

        // Normalizamos a 'facil' / 'medio' / 'dificil'
        let currentDifficulty = 'medio';
        if (dificultadLogicaBD === 'Fácil') {
            currentDifficulty = 'facil';
        } else if (dificultadLogicaBD === 'Difícil') {
            currentDifficulty = 'dificil';
        } else {
            currentDifficulty = 'medio';
        }

        let gameScore = 0;

        // ---- Temporizador ----
        let elapsedSeconds = 0;
        let timerInterval = null;

        function updateTimerDisplay() {
            const timerEl = document.getElementById('timer');
            if (!timerEl) return;
            const min = String(Math.floor(elapsedSeconds / 60)).padStart(2, '0');
            const sec = String(elapsedSeconds % 60).padStart(2, '0');
            timerEl.textContent = `${min}:${sec}`;
        }

        function resetTimer() {
            clearInterval(timerInterval);
            elapsedSeconds = 0;
            updateTimerDisplay();
        }

        function startTimer() {
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                elapsedSeconds++;
                updateTimerDisplay();
            }, 1000);
        }

        // ---- Guardar resultado en la BD ----
        function guardarResultadoLogica(puntos, segundos) {
            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_juego: 'logica',
                    puntuacion: puntos,
                    tiempo_segundos: segundos,
                    dificultad: dificultadLogicaBD
                })
            }).catch(() => {});
        }

        function showOverlay() {
            const overlay = document.getElementById('game-overlay');
            if (overlay) overlay.style.display = 'flex';
        }

        function hideOverlay() {
            const overlay = document.getElementById('game-overlay');
            if (overlay) overlay.style.display = 'none';
        }

        function loadLogicGame(area) {
            area.innerHTML = "";
            resetTimer();
            hideOverlay();

            if (currentDifficulty === 'facil') {
                loadLogicGameFacil(area);
            } else if (currentDifficulty === 'medio') {
                loadLogicGameMedio(area);
            } else {
                loadLogicGameDificil(area);
            }
        }

        function loadLogicGameFacil(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 6);
            createSudokuGrid(area, puzzle, solution);
        }

        function loadLogicGameMedio(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 8);
            createSudokuGrid(area, puzzle, solution);
        }

        function loadLogicGameDificil(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 10);
            createSudokuGrid(area, puzzle, solution);
        }

        function generateSudoku4x4() {
            return [
                1, 2, 3, 4,
                3, 4, 1, 2,
                2, 3, 4, 1,
                4, 1, 2, 3
            ];
        }

        function createPuzzleFromSolution(solution, emptyCells) {
            const puzzle = [...solution];
            const indices = [];
            while (indices.length < emptyCells) {
                const index = Math.floor(Math.random() * 16);
                if (!indices.includes(index)) {
                    indices.push(index);
                    puzzle[index] = 0;
                }
            }
            return puzzle;
        }

        function createSudokuGrid(area, puzzle, solution) {
            const grid = document.createElement('div');
            grid.className = 'sudoku-grid';

            puzzle.forEach((value, index) => {
                const cell = document.createElement('input');
                cell.className = 'sudoku-cell';
                cell.type = 'text';
                cell.maxLength = 1;

                if (value !== 0) {
                    cell.value = value;
                    cell.disabled = true;
                } else {
                    cell.value = "";
                    cell.addEventListener('input', function () {
                        this.value = this.value.replace(/[^1-4]/g, '');
                        checkSudoku(grid, solution);
                    });
                }

                grid.appendChild(cell);
            });

            area.appendChild(grid);
            startTimer(); // arranca SOLO cuando se crea el tablero (tras pulsar Empezar)
        }

        function checkSudoku(grid, solution) {
            const cells = grid.querySelectorAll('input');
            let correct = 0;
            let filled = 0;

            cells.forEach((cell, index) => {
                if (cell.value !== "") filled++;
                if (cell.value == solution[index]) correct++;
            });

            const msg = document.getElementById('logic-message');

            if (correct === 16) {
                clearInterval(timerInterval);
                gameScore = 100;
                const segundosTotales = elapsedSeconds;

                guardarResultadoLogica(gameScore, segundosTotales);

                msg.textContent = "¡Muy bien! Has completado el sudoku correctamente. Tiempo: " +
                    document.getElementById('timer').textContent;

                showOverlay();
            } else if (filled === 16) {
                msg.textContent = "Hay algún número que no encaja, prueba a revisar.";
            } else {
                msg.textContent = "";
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-logica");

            // Bloquear interacción hasta Empezar
            const zona = document.getElementById('zona-logica');
            if (zona) zona.style.pointerEvents = "none";

            const startOverlay = document.getElementById('start-overlay');
            const btnStart = document.getElementById('btn-start');
            const btnStartBack = document.getElementById('btn-start-back');

            if (btnStart) {
                btnStart.addEventListener('click', function () {
                    if (startOverlay) startOverlay.style.display = 'none';
                    if (zona) zona.style.pointerEvents = "auto";
                    loadLogicGame(area);
                });
            }

            if (btnStartBack) {
                btnStartBack.addEventListener('click', function () {
                    window.location.href = "../../usuario.php";
                });
            }

            const btnRestart = document.getElementById('btn-restart');
            const btnVolver = document.getElementById('btn-volver');

            if (btnRestart) {
                btnRestart.addEventListener('click', function () {
                    hideOverlay();
                    loadLogicGame(area);
                });
            }

            if (btnVolver) {
                btnVolver.addEventListener('click', function () {
                    window.location.href = "../../usuario.php";
                });
            }
        });
    </script>

</body>

</html>
