<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");

// OBTENER DIFICULTAD ASIGNADA PARA LÓGICA (Fácil / Medio / Difícil)
$usuario_id = $_SESSION["usuario_id"];

// Inicializar variable con valor por defecto
$dificultad_logica = "Medio";

try {
    $stmt = $conexion->prepare("
        SELECT dificultad_logica
        FROM dificultades_asignadas
        WHERE usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetchColumn();
    
    // Solo actualizar si se encontró un resultado
    if ($result !== false && !empty($result)) {
        $dificultad_logica = $result;
    }
} catch (Exception $e) {
    // En caso de error, mantener el valor por defecto
    $dificultad_logica = "Medio";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Juego de Lógica - Sudoku 4x4</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, Helvetica, sans-serif;
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

        /* ZONA CENTRAL DEL TABLERO */
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
        }

        .logic-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            width: 100%;
            flex-wrap: wrap;
        }

        .sudoku-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(90px, 1fr));
            gap: 14px;
            padding: 20px;
            border-radius: 20px;
            background: #f1f3f6;
            box-shadow: inset 0 0 8px rgba(0, 0, 0, 0.10);
            max-width: 550px;
            width: 100%;
        }

        .sudoku-cell {
            width: 100%;
            height: clamp(90px, 16vh, 130px);
            border-radius: 14px;
            border: 2px solid rgba(17, 24, 39, 0.55);
            text-align: center;
            font-size: 42px;
            font-weight: 800;
            box-sizing: border-box;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.12);
            background: #ffffff;
            color: #111;
            transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sudoku-cell.drag-over {
            border-color: #0070f3;
            box-shadow: 0 0 0 3px rgba(0, 112, 243, 0.35), 0 12px 22px rgba(0, 0, 0, 0.12);
            transform: scale(1.05);
        }

        .sudoku-cell.disabled {
            background: #2f3742;
            color: #ffffff;
            border-color: #10141a;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .sudoku-cell.error-shake {
            animation: shake 0.4s ease-in-out;
            border-color: #dc2626;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* NÚMEROS ARRASTRABLES */
        .numbers-palette {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 20px;
            border-radius: 20px;
            background: #f1f3f6;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.12);
        }

        .draggable-number {
            width: 85px;
            height: 85px;
            border-radius: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            font-size: 42px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
            user-select: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .draggable-number[data-number="1"] {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #ffffff;
        }

        .draggable-number[data-number="2"] {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            color: #ffffff;
        }

        .draggable-number[data-number="3"] {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: #ffffff;
        }

        .draggable-number[data-number="4"] {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%);
            color: #ffffff;
        }

        .draggable-number:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.25);
        }

        .draggable-number:active {
            cursor: grabbing;
        }

        .draggable-number.dragging {
            opacity: 0.5;
        }

        /* MENSAJE / FEEDBACK */
        .logic-message {
            margin-top: 8px;
            font-size: 19px;
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
            box-shadow: 0 10px 18px rgba(0,  0, 0, 0.22);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin: 0 6px;
        }

        .btn-game:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(0, 0, 0, 0.28);
        }

        /* Ajustes para alturas más bajas */
        @media (max-height: 820px) {
            .sudoku-grid { max-width: 480px; gap: 12px; }
            .sudoku-cell { height: clamp(80px, 14vh, 115px); font-size: 38px; }
            .draggable-number { width: 75px; height: 75px; font-size: 38px; }
            .game-header h2 { font-size: 34px; }
            .game-title-pill { font-size: 30px; }
        }

        @media (max-height: 720px) {
            .sudoku-grid { max-width: 420px; gap: 10px; }
            .sudoku-cell { height: clamp(70px, 12vh, 100px); font-size: 34px; }
            .draggable-number { width: 65px; height: 65px; font-size: 34px; }
            .game-header h2 { font-size: 32px; }
            .game-title-pill { font-size: 28px; padding: 6px 16px; }
        }

        @media (max-width: 768px) {
            .game-container { padding: 16px 14px 12px 14px; }
            .logic-area { flex-direction: column; }
            .numbers-palette { flex-direction: row; }
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
            <!-- Flecha volver -->
            <a href="../../usuario.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
                </svg>
            </a>

            <!-- Info superior derecha -->
            <div class="top-right-info">
                <div class="difficulty-badge">
                    Dificultad: <?= htmlspecialchars($dificultad_logica) ?>
                </div>
                <div class="timer-display">
                    <i class="far fa-clock"></i>
                    <span id="timer">00:00</span>
                </div>
            </div>

            <!-- Pastilla superior -->
            <div class="game-title-pill">Lógica</div>

            <!-- Cabecera -->
            <div class="game-header">
                <h2>Sudoku 4x4</h2>
                <p>Arrastra los números del <strong>1 al 4</strong> a las casillas vacías, sin repetir en filas ni columnas.</p>
            </div>

            <!-- Cuerpo (tablero) -->
            <div class="game-body">
                <div class="logic-area">
                    <div id="zona-logica"></div>
                    <div id="numbers-palette" class="numbers-palette"></div>
                </div>
            </div>

            <!-- Mensaje / feedback -->
            <div id="logic-message" class="logic-message"></div>

            <!-- OVERLAY FINAL -->
            <div id="game-overlay" class="game-overlay">
                <div id="overlay-content" class="overlay-content"></div>
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
        let currentSolution = [];

        // ====== NUEVO: métricas para registrar ======
        let totalVacias = 0;       // número de casillas que estaban vacías al inicio
        let aciertos = 0;          // colocaciones correctas (casillas editables)
        let fallos = 0;            // intentos incorrectos (drops incorrectos)
        let puzzleInicial = [];    // para detalles_json

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
        function guardarResultadoLogica(payload) {
            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
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

            // ====== NUEVO: reset métricas ======
            aciertos = 0;
            fallos = 0;
            totalVacias = 0;
            puzzleInicial = [];

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
            createNumbersPalette();
        }

        function loadLogicGameMedio(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 8);
            createSudokuGrid(area, puzzle, solution);
            createNumbersPalette();
        }

        function loadLogicGameDificil(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 10);
            createSudokuGrid(area, puzzle, solution);
            createNumbersPalette();
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

        function createNumbersPalette() {
            const palette = document.getElementById('numbers-palette');
            palette.innerHTML = '';

            for (let i = 1; i <= 4; i++) {
                const numberDiv = document.createElement('div');
                numberDiv.className = 'draggable-number';
                numberDiv.textContent = i;
                numberDiv.draggable = true;
                numberDiv.dataset.number = i;

                numberDiv.addEventListener('dragstart', handleDragStart);
                numberDiv.addEventListener('dragend', handleDragEnd);

                palette.appendChild(numberDiv);
            }
        }

        function handleDragStart(e) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', e.target.dataset.number);
            e.target.classList.add('dragging');
        }

        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
        }

        function createSudokuGrid(area, puzzle, solution) {
            currentSolution = solution;

            // ====== NUEVO: total vacías + snapshot del puzzle ======
            puzzleInicial = [...puzzle];
            totalVacias = puzzle.filter(v => v === 0).length;

            const grid = document.createElement('div');
            grid.className = 'sudoku-grid';

            puzzle.forEach((value, index) => {
                const cell = document.createElement('div');
                cell.className = 'sudoku-cell';
                cell.dataset.index = index;

                if (value !== 0) {
                    cell.textContent = value;
                    cell.classList.add('disabled');
                } else {
                    cell.textContent = "";
                    
                    cell.addEventListener('dragover', handleDragOver);
                    cell.addEventListener('dragleave', handleDragLeave);
                    cell.addEventListener('drop', handleDrop);
                }

                grid.appendChild(cell);
            });

            area.appendChild(grid);
            startTimer();
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            const cell = e.currentTarget;
            cell.classList.remove('drag-over');

            // Si ya está bloqueada, no hacer nada (extra seguridad)
            if (cell.classList.contains('disabled')) return;

            const number = parseInt(e.dataTransfer.getData('text/plain'));
            const index = parseInt(cell.dataset.index);

            // Verificar si el número es correcto
            if (number === currentSolution[index]) {
                cell.textContent = number;
                cell.classList.add('disabled');

                // ====== NUEVO: sumar acierto SOLO si era una casilla vacía original ======
                aciertos++;

                checkSudoku();
            } else {
                // ====== NUEVO: sumar fallo por intento incorrecto ======
                fallos++;

                // Animación de error
                cell.classList.add('error-shake');
                setTimeout(() => {
                    cell.classList.remove('error-shake');
                }, 400);
                
                const msg = document.getElementById('logic-message');
                msg.textContent = "❌ ¡Incorrecto! Ese número no va ahí. Sigue intentándolo";
                msg.style.color = '#b91c1c';
                msg.style.fontWeight = '800';
                setTimeout(() => {
                    msg.textContent = "";
                    msg.style.color = '#1b5e20';
                }, 3000);
            }
        }

        function checkSudoku() {
            const cells = document.querySelectorAll('.sudoku-cell');
            let filled = 0;

            cells.forEach((cell) => {
                if (cell.textContent !== "") filled++;
            });

            const msg = document.getElementById('logic-message');

            if (filled === 16) {
                clearInterval(timerInterval);

                const segundosTotales = elapsedSeconds;

                // ====== NUEVO: puntuación calculada por vacías (debería dar 100 al completar bien) ======
                const puntuacionCalc = totalVacias > 0 ? Math.round((aciertos / totalVacias) * 100) : 100;
                gameScore = puntuacionCalc;

                // ====== NUEVO: detalles_json ======
                const detalles = {
                    juego: 'logica',
                    dificultad: dificultadLogicaBD,
                    total_vacias: totalVacias,
                    aciertos: aciertos,
                    fallos: fallos,
                    tiempo_segundos: segundosTotales,
                    puzzle_inicial: puzzleInicial
                };

                // ====== NUEVO: payload completo ======
                guardarResultadoLogica({
                    tipo_juego: 'logica',
                    puntuacion: gameScore,
                    tiempo_segundos: segundosTotales,
                    dificultad: dificultadLogicaBD,
                    aciertos: aciertos,
                    fallos: fallos,
                    nivel_alcanzado: totalVacias,
                    detalles_json: JSON.stringify(detalles)
                });

                msg.textContent = "";
                msg.style.color = '#1b5e20';

                // Mostrar resumen final
                mostrarResumenFinal(segundosTotales);
            }
        }

        function mostrarResumenFinal(tiempoTotal) {
            const overlayContent = document.getElementById('overlay-content');
            const min = String(Math.floor(tiempoTotal / 60)).padStart(2, '0');
            const sec = String(tiempoTotal % 60).padStart(2, '0');
            const tiempoFormateado = `${min}:${sec}`;

            overlayContent.innerHTML = `
                <i class="fas fa-trophy" style="font-size:3rem; color:#facc15; margin-bottom:8px;"></i>
                <p>¡Juego completado!</p>
                <p style="font-size:18px; font-weight:normal;">Aciertos: <strong>${aciertos}</strong> | Fallos: <strong>${fallos}</strong></p>
                <p style="font-size:18px; font-weight:normal;">Tiempo jugado: <strong>${tiempoFormateado}</strong></p>
                
                <div style="margin-top: 14px;">
                    <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                    <button id="btn-volver" class="btn-game">Volver al panel</button>
                </div>
            `;

            showOverlay();

            // Re-asignar eventos a los nuevos botones
            const btnRestart = document.getElementById('btn-restart');
            const btnVolver = document.getElementById('btn-volver');

            if (btnRestart) {
                btnRestart.addEventListener('click', function () {
                    hideOverlay();
                    loadLogicGame(document.getElementById("zona-logica"));
                });
            }

            if (btnVolver) {
                btnVolver.addEventListener('click', function () {
                    window.location.href = "../../usuario.php";
                });
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-logica");

            // Bloquear interacción hasta Empezar
            const zona = document.getElementById('zona-logica');
            const palette = document.getElementById('numbers-palette');
            if (zona) zona.style.pointerEvents = "none";
            if (palette) palette.style.pointerEvents = "none";

            const startOverlay = document.getElementById('start-overlay');
            const btnStart = document.getElementById('btn-start');
            const btnStartBack = document.getElementById('btn-start-back');

            if (btnStart) {
                btnStart.addEventListener('click', function () {
                    if (startOverlay) startOverlay.style.display = 'none';
                    if (zona) zona.style.pointerEvents = "auto";
                    if (palette) palette.style.pointerEvents = "auto";
                    loadLogicGame(area);
                });
            }

            if (btnStartBack) {
                btnStartBack.addEventListener('click', function () {
                    window.location.href = "../../usuario.php";
                });
            }
        });
    </script>

</body>

</html>