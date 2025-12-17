<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");

// OBTENER DIFICULTAD ASIGNADA PARA LÓGICA (Fácil / Intermedio / Difícil)
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
    $dificultad_logica = "Intermedio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Juego de Lógica - Sudoku 4x4</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #d7dde5; /* más contraste de fondo */
            color: #111;
        }

        /* HEADER SUPERIOR */
        .header {
            width: 100%;
            height: 160px;
            background-image: url('../../imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
        }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            font-size: 20px;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }

        /* FLECHA DE VOLVER */
        .back-arrow {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            cursor: pointer;
            text-decoration: none;
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.7;
            transform: translateX(-2px);
        }

        /* CONTENEDOR CENTRAL DEL JUEGO */
        .game-container {
            height: calc(100vh - 160px); /* solo header, sin footer */
            width: 90%;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .game-header {
            margin-bottom: 16px;
            text-align: center;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 26px;
            color: #222;
        }

        .game-header p {
            margin: 3px 0;
            font-size: 15px;
            color: #555;
        }

        .game-header p strong {
            color: #111;
        }

        .timer {
            font-weight: bold;
            font-size: 18px;
            margin-top: 4px;
        }

        /* CONTENEDOR LÓGICA */
        .logic-area {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .sudoku-grid {
            display: grid;
            grid-template-columns: repeat(4, 64px);
            grid-template-rows: repeat(4, 64px);
            gap: 8px;
            padding: 14px;
            border-radius: 18px;
            background: #f1f3f6;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.10);
        }

        .sudoku-cell {
            width: 64px;
            height: 64px;
            border-radius: 10px;
            border: 2px solid #555;              /* bordes más oscuros */
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            box-sizing: border-box;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            background: #ffffff;                  /* fondo claro */
            color: #111;                          /* texto muy contrastado */
        }

        .sudoku-cell:disabled {
            background: #2f3742;                  /* fondo oscuro */
            color: #ffffff;                       /* texto blanco */
            border-color: #10141a;
            font-weight: 700;
        }

        .sudoku-cell:focus {
            outline: none;
            border-color: #0070f3;                /* azul intenso al foco */
            box-shadow: 0 0 0 3px rgba(0,112,243,0.35);
        }

        /* LÍNEAS MÁS GRUESAS PARA BLOQUES 2x2 */
        .sudoku-cell:nth-child(1),
        .sudoku-cell:nth-child(2),
        .sudoku-cell:nth-child(3),
        .sudoku-cell:nth-child(4),
        .sudoku-cell:nth-child(9),
        .sudoku-cell:nth-child(10),
        .sudoku-cell:nth-child(11),
        .sudoku-cell:nth-child(12) {
            border-top-width: 3px;
        }

        .sudoku-cell:nth-child(1),
        .sudoku-cell:nth-child(5),
        .sudoku-cell:nth-child(9),
        .sudoku-cell:nth-child(13) {
            border-left-width: 3px;
        }

        .sudoku-cell:nth-child(4),
        .sudoku-cell:nth-child(8),
        .sudoku-cell:nth-child(12),
        .sudoku-cell:nth-child(16) {
            border-right-width: 3px;
        }

        .sudoku-cell:nth-child(13),
        .sudoku-cell:nth-child(14),
        .sudoku-cell:nth-child(15),
        .sudoku-cell:nth-child(16),
        .sudoku-cell:nth-child(5),
        .sudoku-cell:nth-child(6),
        .sudoku-cell:nth-child(7),
        .sudoku-cell:nth-child(8) {
            border-bottom-width: 3px;
        }

        /* MENSAJE / FEEDBACK */
        .logic-message {
            margin-top: 16px;
            font-size: 16px;
            color: #1b5e20;      /* verde oscuro */
            font-weight: 600;
            min-height: 22px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .game-container {
                width: 95%;
                padding: 18px;
            }

            .sudoku-grid {
                grid-template-columns: repeat(4, 56px);
                grid-template-rows: repeat(4, 56px);
            }

            .sudoku-cell {
                width: 56px;
                height: 56px;
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <a href="../../usuario.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>
        <div class="user-role">Juego: Lógica</div>
    </div>

    <!-- CONTENEDOR CENTRAL -->
    <div class="game-container">
        <div class="game-header">
            <h2>Sudoku 4x4</h2>
            <p>Completa el tablero con los números del <strong>1 al 4</strong>, sin repetir en filas ni columnas.</p>
            <p>Dificultad asignada: <strong><?= htmlspecialchars($dificultad_logica) ?></strong></p>
            <p class="timer">Tiempo: <span id="timer">00:00</span></p>
        </div>

        <div class="logic-area">
            <div id="zona-logica"></div>
        </div>

        <div id="logic-message" class="logic-message"></div>
    </div>

    <script>
        // Dificultad desde PHP (Fácil / Intermedio / Difícil)
        const dificultadLogicaBD = "<?= htmlspecialchars($dificultad_logica, ENT_QUOTES) ?>";

        // Normalizamos a 'facil' / 'medio' / 'dificil' si quisieras variar celdas vacías
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
            console.log('Enviando resultado lógica...', puntos, segundos, dificultadLogicaBD);

            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tipo_juego: 'logica',
                    puntuacion: puntos,
                    tiempo_segundos: segundos,
                    dificultad: dificultadLogicaBD
                })
            })
            .then(r => r.json())
            .then(data => {
                console.log('Respuesta guardar_resultado (logica):', data);
            })
            .catch(err => {
                console.error('Error en fetch logica:', err);
            });
        }

        // Stub (por si en el futuro quieres hacer algo más con la puntuación)
        function saveScore(tipo, puntuacion) {
            console.log("Puntuación guardada (solo consola):", tipo, puntuacion);
        }

        function loadLogicGame(area) {
            area.innerHTML = ""; // limpiar cada vez
            resetTimer();

            if (currentDifficulty === 'facil') {
                loadLogicGameFacil(area);
            } else if (currentDifficulty === 'medio') {
                loadLogicGameMedio(area);
            } else {
                loadLogicGameDificil(area);
            }
        }

        // LÓGICA - FÁCIL (6 celdas vacías)
        function loadLogicGameFacil(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 6);
            createSudokuGrid(area, puzzle, solution);
        }

        // LÓGICA - MEDIO (8 celdas vacías)
        function loadLogicGameMedio(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 8);
            createSudokuGrid(area, puzzle, solution);
        }

        // LÓGICA - DIFÍCIL (10 celdas vacías)
        function loadLogicGameDificil(area) {
            const solution = generateSudoku4x4();
            const puzzle = createPuzzleFromSolution(solution, 10);
            createSudokuGrid(area, puzzle, solution);
        }

        // Una solución base fija 4x4
        function generateSudoku4x4() {
            //  1 2 3 4
            //  3 4 1 2
            //  2 3 4 1
            //  4 1 2 3
            return [
                1,2,3,4,
                3,4,1,2,
                2,3,4,1,
                4,1,2,3
            ];
        }

        function createPuzzleFromSolution(solution, emptyCells) {
            const puzzle = [...solution];
            const indices = [];

            // Índices aleatorios únicos a vaciar
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
                        // Permitir solo números del 1 al 4
                        this.value = this.value.replace(/[^1-4]/g, '');
                        checkSudoku(grid, solution);
                    });
                }

                grid.appendChild(cell);
            });

            area.appendChild(grid);
            startTimer(); // empezamos cronómetro cuando el tablero está listo
        }

        function checkSudoku(grid, solution) {
            const cells = grid.querySelectorAll('input');
            let correct = 0;
            let filled = 0;

            cells.forEach((cell, index) => {
                if (cell.value !== "") filled++;
                if (cell.value == solution[index]) {
                    correct++;
                }
            });

            const msg = document.getElementById('logic-message');

            // Si todas las celdas están correctas:
            if (correct === 16) {
                clearInterval(timerInterval);
                gameScore = 100;
                saveScore('logica', gameScore);

                const segundosTotales = elapsedSeconds;
                guardarResultadoLogica(gameScore, segundosTotales);

                msg.textContent = "¡Muy bien! Has completado el sudoku correctamente. Tiempo: "
                    + document.getElementById('timer').textContent;
            } else if (filled === 16) {
                msg.textContent = "Hay algún número que no encaja, prueba a revisar.";
            } else {
                msg.textContent = "";
            }
        }

        // Iniciar al cargar
        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-logica");
            loadLogicGame(area);
        });
    </script>

</body>
</html>
