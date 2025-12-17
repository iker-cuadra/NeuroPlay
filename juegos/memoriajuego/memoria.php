<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

// Solo usuarios pueden acceder
requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"];

// OBTENER DIFICULTAD ASIGNADA PARA MEMORIA (Fácil / Intermedio / Difícil)
$stmt = $conexion->prepare("
    SELECT dificultad_memoria
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_memoria = $stmt->fetchColumn();

// Dificultad por defecto si no hay asignada
if (!$dificultad_memoria) {
    $dificultad_memoria = "Intermedio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Juego de Memoria</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f2f2;
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
            transition: opacity 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.7;
        }

        /* CONTENEDOR CENTRAL DEL JUEGO */
        .game-container {
            height: calc(100vh - 160px); /* solo restamos el header */
            width: 90%;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .game-header {
            margin-bottom: 16px;
            text-align: center;
        }

        .game-header h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
        }

        .game-header p {
            margin: 4px 0;
            font-size: 16px;
            color: #555;
        }

        .game-header p strong {
            color: #111;
        }

        /* TIME DISPLAY */
        .timer {
            font-weight: bold;
            font-size: 18px;
            margin-top: 4px;
        }

        /* GRID DEL JUEGO DE MEMORIA */
        .memory-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(60px, 1fr));
            gap: 16px;
            width: 100%;
            max-width: 520px;
            margin-top: 10px;
        }

        .memory-card {
            background: #4a4a4a;
            color: white;
            border-radius: 12px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            user-select: none;
        }

        /* Fondo con imagen cuando la carta está oculta */
        .memory-card.hidden-symbol {
            background: #887d7dff url('neuroplay.png') center/cover no-repeat;
            color: transparent;
        }

        .memory-card.revealed {
            background: #4a4a4a;
            color: #fff;
        }

        .memory-card.matched {
            background: #7bc47f;
            color: #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,0.25);
            cursor: default;
        }

        .memory-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        /* BOTONES INFERIORES */
        .buttons-row {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-game {
            background: #4a4a4a;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 20px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-game:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(0,0,0,0.25);
        }

        .motivacion {
            margin-top: 14px;
            font-size: 16px;
            color: #2e7d32;
            font-weight: 600;
            text-align: center;
            min-height: 22px; /* para que no salte el layout al aparecer */
        }

        @media (max-width: 768px) {
            .game-container {
                width: 95%;
                padding: 16px;
            }

            .memory-card {
                height: 70px;
                font-size: 26px;
            }

            .buttons-row {
                flex-direction: column;
            }

            .btn-game {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <a href="../../usuario.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>
        <div class="user-role">Juego: Memoria</div>
    </div>

    <!-- CONTENEDOR DEL JUEGO -->
    <div class="game-container">
        <div class="game-header">
            <h2>Encuentra las parejas</h2>
            <p>Dificultad asignada: <strong><?= htmlspecialchars($dificultad_memoria) ?></strong></p>
            <p class="timer">Tiempo: <span id="timer">00:00</span></p>
        </div>

        <div id="zona-memoria" class="memory-grid">
            <!-- Aquí se cargan las cartas por JavaScript -->
        </div>

        <div class="buttons-row">
            <button id="btn-restart" class="btn-game">Jugar otra vez</button>
            <button id="btn-volver" class="btn-game">Volver</button>
        </div>

        <div id="motivacion" class="motivacion"></div>
    </div>

    <script>
        // Dificultad desde PHP (Fácil / Intermedio / Difícil)
        const dificultadMemoriaBD = "<?= htmlspecialchars($dificultad_memoria, ENT_QUOTES) ?>";

        // Normalizamos a 'facil' / 'medio' / 'dificil' para la lógica interna
        let currentDifficulty = 'medio';
        if (dificultadMemoriaBD === 'Fácil') {
            currentDifficulty = 'facil';
        } else if (dificultadMemoriaBD === 'Difícil') {
            currentDifficulty = 'dificil';
        } else {
            currentDifficulty = 'medio';
        }

        let motivacionDiv = null;

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
        function guardarResultadoMemoria(puntos, segundos) {
            console.log('Enviando resultado memoria...', puntos, segundos, dificultadMemoriaBD);

            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tipo_juego: 'memoria',
                    puntuacion: puntos,
                    tiempo_segundos: segundos,
                    dificultad: dificultadMemoriaBD
                })
            })
            .then(r => r.json())
            .then(data => {
                console.log('Respuesta guardar_resultado (memoria):', data);
            })
            .catch(err => {
                console.error('Error en fetch memoria:', err);
            });
        }

        // Mensajes motivacionales aleatorios
        function obtenerMensajeMotivacion() {
            const mensajes = [
                "¡Fantástico! Tu memoria está en plena forma.",
                "¡Muy bien! Cada partida fortalece tu mente.",
                "¡Lo has hecho genial! Sigue así.",
                "¡Gran trabajo! Tu concentración mejora cada día.",
                "¡Excelente! Has completado todas las parejas.",
            ];
            const idx = Math.floor(Math.random() * mensajes.length);
            return mensajes[idx];
        }

        // ==========================
        //  FUNCIÓN PRINCIPAL
        // ==========================
        function loadMemoryGame(area) {
            // Limpiar mensaje
            if (motivacionDiv) motivacionDiv.textContent = "";
            resetTimer();

            if (currentDifficulty === 'facil') {
                loadMemoryGameFacil(area);
            } else if (currentDifficulty === 'medio') {
                loadMemoryGameMedio(area);
            } else {
                loadMemoryGameDificil(area);
            }
        }

        // MEMORIA - FÁCIL (4 pares, 8 cartas)
        function loadMemoryGameFacil(area) {
            const pairs = 4;
            const symbols = ['★', '◆', '●', '■'];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(symbols[i], symbols[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - INTERMEDIO (6 pares, 12 cartas)
        function loadMemoryGameMedio(area) {
            const pairs = 6;
            const symbols = ['★', '◆', '●', '■', '▲', '♦'];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(symbols[i], symbols[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - DIFÍCIL (8 pares, 16 cartas)
        function loadMemoryGameDificil(area) {
            const pairs = 8;
            const symbols = ['★', '◆', '●', '■', '▲', '♦', '♣', '♠'];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(symbols[i], symbols[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // ==========================
        //  CREAR TABLERO DE MEMORIA
        // ==========================
        function createMemoryGrid(area, cards, columns) {
            area.innerHTML = "";
            area.style.gridTemplateColumns = `repeat(${columns}, minmax(60px, 1fr))`;

            let firstCard = null;
            let secondCard = null;
            let lockBoard = false;
            let matchedPairs = 0;

            cards.forEach((symbol, index) => {
                const card = document.createElement("div");
                card.classList.add("memory-card", "hidden-symbol");
                card.dataset.symbol = symbol;
                card.dataset.index = index;
                card.textContent = symbol;

                card.addEventListener("click", function () {
                    if (lockBoard) return;
                    if (card.classList.contains("matched")) return;
                    if (card === firstCard) return;

                    // Mostrar la carta
                    card.classList.remove("hidden-symbol");
                    card.classList.add("revealed");

                    if (!firstCard) {
                        firstCard = card;
                        return;
                    }

                    secondCard = card;
                    lockBoard = true;

                    const isMatch = firstCard.dataset.symbol === secondCard.dataset.symbol;

                    if (isMatch) {
                        firstCard.classList.add("matched");
                        secondCard.classList.add("matched");
                        firstCard = null;
                        secondCard = null;
                        lockBoard = false;

                        matchedPairs++;
                        if (matchedPairs === cards.length / 2) {
                            // Juego completado
                            clearInterval(timerInterval);
                            const puntosFinales = 100;
                            const segundosTotales = elapsedSeconds;

                            guardarResultadoMemoria(puntosFinales, segundosTotales);

                            setTimeout(() => {
                                if (motivacionDiv) {
                                    motivacionDiv.textContent = obtenerMensajeMotivacion() +
                                        ` (Tiempo: ${document.getElementById('timer').textContent})`;
                                } else {
                                    alert("¡Has completado el juego de memoria!");
                                }
                            }, 300);
                        }
                    } else {
                        setTimeout(() => {
                            firstCard.classList.add("hidden-symbol");
                            firstCard.classList.remove("revealed");

                            secondCard.classList.add("hidden-symbol");
                            secondCard.classList.remove("revealed");

                            firstCard = null;
                            secondCard = null;
                            lockBoard = false;
                        }, 800);
                    }
                });

                area.appendChild(card);
            });

            // Empezamos el temporizador cuando el tablero está listo
            startTimer();
        }

        // Iniciar juego al cargar la página
        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-memoria");
            motivacionDiv = document.getElementById("motivacion");

            loadMemoryGame(area);

            // Botón Jugar otra vez
            const btnRestart = document.getElementById("btn-restart");
            btnRestart.addEventListener("click", function () {
                loadMemoryGame(area);
            });

            // Botón Volver
            const btnVolver = document.getElementById("btn-volver");
            btnVolver.addEventListener("click", function () {
                window.location.href = "../../usuario.php";
            });
        });
    </script>

</body>
</html>
