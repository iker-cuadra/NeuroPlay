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
            font-family: Arial, Helvetica, sans-serif;
            background: #887d7dff;
            overflow: hidden; /* sin scroll */
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

        /* CONTENEDOR DEL JUEGO */
        .game-container {
            position: relative; /* necesario para el overlay */
            width: min(1000px, 100%);
            height: 100%;
            max-height: 100%;
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 20px 16px 20px;
            box-sizing: border-box;
        }

        /* FLECHA DE VOLVER (NEGRA) */
        .back-arrow {
            position: absolute;
            top: 12px;
            left: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            cursor: pointer;
            text-decoration: none;
            z-index: 3;
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.7;
            transform: translateX(-2px);
        }

        /* PASTILLA SUPERIOR "Juego: Memoria" */
        .game-title-pill {
            margin-top: 2px;
            margin-bottom: 4px;
            padding: 4px 14px;
            border-radius: 999px;
            background: #f3f4f6;
            font-size: 13px;
            font-weight: bold;
            color: #555;
        }

        .game-header {
            margin-top: 26px; /* espacio por flecha */
            margin-bottom: 8px;
            text-align: center;
            flex: 0 0 auto;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 30px;
        }

        .game-header p {
            margin: 2px 0;
            font-size: 15px;
            color: #555;
        }

        .game-header p strong {
            color: #111;
        }

        .timer {
            font-weight: bold;
            font-size: 17px;
            margin-top: 2px;
        }

        /* ZONA CENTRAL */
        .game-body {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4px 0;
            box-sizing: border-box;
        }

        /* GRID DEL JUEGO DE MEMORIA */
        .memory-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(80px, 1fr));
            gap: 16px;
            width: 100%;
            max-width: 580px;
        }

        .memory-card {
            background: #4a4a4a;
            color: transparent;
            border-radius: 14px;
            /* ALTURA RESPONSIVE, MÁS GRANDE */
            height: clamp(80px, 18vh, 140px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
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
            color: transparent; /* no mostramos texto, solo color de fondo */
        }

        .memory-card.matched {
            box-shadow: 0 3px 10px rgba(0,0,0,0.25);
            cursor: default;
            border: 3px solid #22c55e;
        }

        .memory-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        .motivacion {
            margin-top: 8px;
            font-size: 15px;
            color: #2e7d32;
            font-weight: 600;
            text-align: center;
            min-height: 22px;
            flex: 0 0 auto;
        }

        /* BOTÓN REUTILIZADO PARA EL OVERLAY */
        .btn-game {
            background: #4a4a4a;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 24px;
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

        /* OVERLAY AL TERMINAR EL JUEGO */
        .game-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.45);
            border-radius: inherit;
            display: none;          /* oculto inicialmente */
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .overlay-content {
            text-align: center;
            color: #fff;
        }

        .overlay-content p {
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
        }

        /* AJUSTES EN ALTURAS BAJAS PARA QUE SIGA ENTRANDO TODO SIN SCROLL */
        @media (max-height: 760px) {
            .memory-card {
                height: clamp(70px, 16vh, 120px);
            }
            .memory-grid {
                gap: 12px;
                max-width: 540px;
            }
            .game-header h2 {
                font-size: 26px;
            }
        }

        @media (max-height: 680px) {
            .memory-card {
                height: clamp(65px, 15vh, 105px);
            }
            .memory-grid {
                gap: 10px;
                max-width: 500px;
            }
            .game-header h2 {
                font-size: 24px;
            }
        }

        @media (max-height: 620px) {
            .memory-card {
                height: clamp(60px, 14vh, 95px);
            }
            .memory-grid {
                gap: 8px;
                max-width: 460px;
            }
            .game-header {
                margin-top: 20px;
            }
        }

        /* MÓVIL */
        @media (max-width: 768px) {
            .game-container {
                padding: 16px 12px 12px 12px;
            }

            .memory-grid {
                max-width: 420px;
            }
        }
    </style>
</head>
<body>

    <div class="game-wrapper">
        <div class="game-container">
            <!-- Flecha volver (negra) -->
            <a href="../../usuario.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
                </svg>
            </a>

            <!-- Pastilla con nombre del juego -->
            <div class="game-title-pill">
                Juego: Memoria
            </div>

            <!-- Cabecera del juego -->
            <div class="game-header">
                <h2>Encuentra las parejas de colores</h2>
                <p>Dificultad asignada: <strong><?= htmlspecialchars($dificultad_memoria) ?></strong></p>
                <p class="timer">Tiempo: <span id="timer">00:00</span></p>
            </div>

            <!-- Zona flexible del tablero -->
            <div class="game-body">
                <div id="zona-memoria" class="memory-grid">
                    <!-- Aquí se cargan las cartas por JavaScript -->
                </div>
            </div>

            <!-- Mensaje motivacional -->
            <div id="motivacion" class="motivacion"></div>

            <!-- OVERLAY AL FINALIZAR EL JUEGO -->
            <div id="game-overlay" class="game-overlay">
                <div class="overlay-content">
                    <p>¡Partida finalizada!</p>
                    <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                </div>
            </div>
        </div>
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
            if (motivacionDiv) motivacionDiv.textContent = "";
            resetTimer();

            // Ocultamos overlay si estaba visible
            const overlay = document.getElementById('game-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }

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
            const colors = [
                '#EF4444', // rojo
                '#3B82F6', // azul
                '#10B981', // verde
                '#F97316'  // naranja
            ];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(colors[i], colors[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - INTERMEDIO (6 pares, 12 cartas)
        function loadMemoryGameMedio(area) {
            const pairs = 6;
            const colors = [
                '#EF4444', // rojo
                '#3B82F6', // azul
                '#10B981', // verde
                '#F97316', // naranja
                '#A855F7', // morado
                '#FACC15'  // amarillo
            ];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(colors[i], colors[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - DIFÍCIL (8 pares, 16 cartas)
        function loadMemoryGameDificil(area) {
            const pairs = 8;
            const colors = [
                '#EF4444', // rojo
                '#3B82F6', // azul
                '#10B981', // verde
                '#F97316', // naranja
                '#A855F7', // morado
                '#FACC15', // amarillo
                '#EC4899', // rosa
                '#22C55E'  // verde lima
            ];
            const cards = [];

            for (let i = 0; i < pairs; i++) {
                cards.push(colors[i], colors[i]);
            }
            cards.sort(() => Math.random() - 0.5);

            createMemoryGrid(area, cards, 4);
        }

        // ==========================
        //  CREAR TABLERO DE MEMORIA
        // ==========================
        function createMemoryGrid(area, cards, columns) {
            area.innerHTML = "";
            area.style.gridTemplateColumns = `repeat(${columns}, minmax(80px, 1fr))`;

            let firstCard = null;
            let secondCard = null;
            let lockBoard = false;
            let matchedPairs = 0;

            cards.forEach((color, index) => {
                const card = document.createElement("div");
                card.classList.add("memory-card", "hidden-symbol");
                card.dataset.color = color;
                card.dataset.index = index;

                card.addEventListener("click", function () {
                    if (lockBoard) return;
                    if (card.classList.contains("matched")) return;
                    if (card === firstCard) return;

                    // Mostrar la carta (color)
                    card.classList.remove("hidden-symbol");
                    card.classList.add("revealed");
                    card.style.backgroundColor = card.dataset.color;
                    card.style.backgroundImage = 'none';

                    if (!firstCard) {
                        firstCard = card;
                        return;
                    }

                    secondCard = card;
                    lockBoard = true;

                    const isMatch = firstCard.dataset.color === secondCard.dataset.color;

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
                                    motivacionDiv.textContent =
                                        obtenerMensajeMotivacion() +
                                        ` (Tiempo: ${document.getElementById('timer').textContent})`;
                                }

                                // Mostrar overlay con "Jugar otra vez"
                                const overlay = document.getElementById('game-overlay');
                                if (overlay) {
                                    overlay.style.display = 'flex';
                                }
                            }, 300);
                        }
                    } else {
                        setTimeout(() => {
                            firstCard.classList.add("hidden-symbol");
                            firstCard.classList.remove("revealed");
                            firstCard.style.backgroundColor = '';
                            firstCard.style.backgroundImage = '';

                            secondCard.classList.add("hidden-symbol");
                            secondCard.classList.remove("revealed");
                            secondCard.style.backgroundColor = '';
                            secondCard.style.backgroundImage = '';

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

            // Botón Jugar otra vez (solo en overlay)
            const btnRestart = document.getElementById("btn-restart");
            const overlay = document.getElementById("game-overlay");

            if (btnRestart && overlay) {
                btnRestart.addEventListener("click", function () {
                    overlay.style.display = 'none';
                    loadMemoryGame(area);
                });
            }
        });
    </script>

</body>
</html>
