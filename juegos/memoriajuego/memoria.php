<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

// Solo usuarios pueden acceder
requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"];

// OBTENER DIFICULTAD ASIGNADA PARA MEMORIA (Fácil / Medio / Difícil)
$stmt = $conexion->prepare("
    SELECT dificultad_memoria
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_memoria = $stmt->fetchColumn();

// Dificultad por defecto si no hay asignada
if (!$dificultad_memoria) {
    $dificultad_memoria = "Medio";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Juego de Memoria</title>

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

        /* FLECHA DE VOLVER (COMO BOTÓN) */
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

        .timer {
            font-weight: 800;
            font-size: 22px;
            margin-top: 4px;
            color: #111827;
        }

        /* CUERPO CENTRO */
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

        /* GRID DEL JUEGO DE MEMORIA */
        .memory-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(70px, 1fr));
            gap: 18px;
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            justify-items: center;
        }

        .memory-card {
            width: 100%;
            max-width: 110px;
            height: clamp(80px, 14vh, 120px);
            background: #4a4a4a;
            color: transparent;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            cursor: pointer;
            user-select: none;

            border: 1px solid rgba(0, 0, 0, 0.10);
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.14);
            transition: transform 0.5s ease, box-shadow 0.18s ease, filter 0.18s ease;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
        }

        .memory-card:hover:not(.preview-mode):not(.matched) {
            transform: translateY(-3px);
            box-shadow: 0 16px 26px rgba(0, 0, 0, 0.18);
        }

        /* Fondo con imagen cuando la carta está oculta */
        .memory-card.hidden-symbol {
            background: #887d7dff url('neuroplay.png') center/cover no-repeat;
            color: transparent;
        }

        .memory-card.revealed {
            color: transparent;
            transform: rotateY(0deg);
        }

        .memory-card.matched {
            box-shadow: 0 14px 26px rgba(0, 0, 0, 0.18);
            cursor: default;
            border: 3px solid #22c55e;
            transform: translateY(-1px);
        }

        /* Animación de volteo */
        .memory-card.flipping {
            transform: rotateY(180deg);
        }

        .memory-card.preview-mode {
            cursor: default;
            pointer-events: none;
        }

        /* Mensaje motivacional */
        .motivacion {
            margin-top: 10px;
            font-size: 20px;
            color: #1b5e20;
            font-weight: 700;
            text-align: center;
            min-height: 24px;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        /* OVERLAY FINAL DE PARTIDA */
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

        @media (max-height: 760px) {
            .memory-grid {
                gap: 16px;
                max-width: 500px;
            }

            .memory-card {
                height: clamp(75px, 13vh, 110px);
            }

            .game-header h2 {
                font-size: 32px;
            }

            .game-title-pill {
                font-size: 30px;
            }
        }

        @media (max-height: 680px) {
            .memory-grid {
                gap: 14px;
                max-width: 460px;
            }

            .memory-card {
                height: clamp(70px, 12vh, 100px);
            }

            .game-header h2 {
                font-size: 30px;
            }

            .game-title-pill {
                font-size: 28px;
                padding: 6px 16px;
            }
        }

        @media (max-width: 768px) {
            .game-container {
                padding: 16px 14px 12px 14px;
            }
        }
    </style>
</head>

<body>

    <div class="game-wrapper">
        <div class="game-container">
            <!-- Flecha volver -->
            <a href="../../usuario.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                    <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
                </svg>
            </a>

            <!-- Pastilla superior -->
            <div class="game-title-pill">
                Memoria
            </div>

            <!-- Cabecera -->
            <div class="game-header">
                <h2>Encuentra las parejas de colores</h2>
                <p>Dificultad asignada: <strong><?= htmlspecialchars($dificultad_memoria) ?></strong></p>
                <p class="timer">Tiempo: <span id="timer">00:00</span></p>
            </div>

            <!-- Cuerpo (tablero) -->
            <div class="game-body">
                <div id="zona-memoria" class="memory-grid">
                    <!-- Aquí se cargan las cartas por JavaScript -->
                </div>
            </div>

            <!-- Mensaje motivacional -->
            <div id="motivacion" class="motivacion"></div>

            <!-- OVERLAY FINAL -->
            <div id="game-overlay" class="game-overlay">
                <div class="overlay-content">
                    <p>¡Has completado el juego de memoria!</p>
                    <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                    <button id="btn-volver" class="btn-game">Volver al panel</button>
                </div>
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
        // Dificultad desde PHP (Fácil / Medio / Difícil)
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_juego: 'memoria',
                    puntuacion: puntos,
                    tiempo_segundos: segundos,
                    dificultad: dificultadMemoriaBD
                })
            })
                .then(r => r.json())
                .then(data => console.log('Respuesta guardar_resultado (memoria):', data))
                .catch(err => console.error('Error en fetch memoria:', err));
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

        function showOverlay() {
            const overlay = document.getElementById('game-overlay');
            if (overlay) overlay.style.display = 'flex';
        }

        function hideOverlay() {
            const overlay = document.getElementById('game-overlay');
            if (overlay) overlay.style.display = 'none';
        }

        // ==========================
        //  FUNCIÓN PRINCIPAL
        // ==========================
        function loadMemoryGame(area) {
            if (motivacionDiv) motivacionDiv.textContent = "";
            resetTimer();
            hideOverlay();

            if (currentDifficulty === 'facil') {
                loadMemoryGameFacil(area);
            } else if (currentDifficulty === 'medio') {
                loadMemoryGameMedio(area);
            } else {
                loadMemoryGameDificil(area);
            }
        }

        // MEMORIA - FÁCIL (4 pares, 8 cartas) - COLORES OSCUROS
        function loadMemoryGameFacil(area) {
            const pairs = 4;
            const colors = ['#d20000', '#061f70', '#04f600a4', '#8c00ff'];
            const cards = [];
            for (let i = 0; i < pairs; i++) cards.push(colors[i], colors[i]);
            cards.sort(() => Math.random() - 0.5);
            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - Medio (6 pares, 12 cartas) - COLORES OSCUROS
        function loadMemoryGameMedio(area) {
            const pairs = 6;
            const colors = ['#d20000', '#061f70', '#04f600a4', '#8c00ff', '#000000', '#ffae00'];
            const cards = [];
            for (let i = 0; i < pairs; i++) cards.push(colors[i], colors[i]);
            cards.sort(() => Math.random() - 0.5);
            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - DIFÍCIL (8 pares, 16 cartas) - COLORES OSCUROS
        function loadMemoryGameDificil(area) {
            const pairs = 8;
            const colors = ['#d20000', '#061f70', '#04f600a4', '#8c00ff', '#000000', '#ffae00', '#eeff00e2', '#397061'];
            const cards = [];
            for (let i = 0; i < pairs; i++) cards.push(colors[i], colors[i]);
            cards.sort(() => Math.random() - 0.5);
            createMemoryGrid(area, cards, 4);
        }

        // ==========================
        //  CREAR TABLERO DE MEMORIA
        // ==========================
        function createMemoryGrid(area, cards, columns) {
            area.innerHTML = "";
            area.style.gridTemplateColumns = `repeat(${columns}, minmax(70px, 1fr))`;

            let firstCard = null;
            let secondCard = null;
            let lockBoard = false;
            let matchedPairs = 0;

            const allCards = [];

            cards.forEach((color, index) => {
                const card = document.createElement("div");
                card.classList.add("memory-card", "hidden-symbol", "preview-mode");
                card.dataset.color = color;
                card.dataset.index = index;

                allCards.push(card);
                area.appendChild(card);
            });

            // PREVIEW: Mostrar todas las cartas por 3 segundos
            setTimeout(() => {
                allCards.forEach(card => {
                    card.classList.remove("hidden-symbol");
                    card.classList.add("revealed", "flipping");
                    card.style.backgroundColor = card.dataset.color;
                    card.style.backgroundImage = 'none';
                });

                // Después de 3 segundos, ocultar todas y habilitar el juego
                setTimeout(() => {
                    allCards.forEach(card => {
                        card.classList.add("hidden-symbol");
                        card.classList.remove("revealed", "flipping", "preview-mode");
                        card.style.backgroundColor = '';
                        card.style.backgroundImage = '';
                    });

                    // Ahora sí, habilitar el juego
                    startTimer();
                    enableCardClicks();
                }, 3000);
            }, 100);

            function enableCardClicks() {
                allCards.forEach(card => {
                    card.addEventListener("click", function handleClick() {
                        if (lockBoard) return;
                        if (card.classList.contains("matched")) return;
                        if (card === firstCard) return;
                        if (card.classList.contains("preview-mode")) return;

                        // Mostrar la carta (color)
                        card.classList.remove("hidden-symbol");
                        card.classList.add("revealed", "flipping");
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
                            setTimeout(() => {
                                firstCard.classList.remove("flipping");
                                secondCard.classList.remove("flipping");
                                firstCard.classList.add("matched");
                                secondCard.classList.add("matched");

                                firstCard = null;
                                secondCard = null;
                                lockBoard = false;

                                matchedPairs++;
                                if (matchedPairs === cards.length / 2) {
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
                                        showOverlay();
                                    }, 300);
                                }
                            }, 500);
                        } else {
                            setTimeout(() => {
                                firstCard.classList.add("hidden-symbol");
                                firstCard.classList.remove("revealed", "flipping");
                                firstCard.style.backgroundColor = '';
                                firstCard.style.backgroundImage = '';

                                secondCard.classList.add("hidden-symbol");
                                secondCard.classList.remove("revealed", "flipping");
                                secondCard.style.backgroundColor = '';
                                secondCard.style.backgroundImage = '';

                                firstCard = null;
                                secondCard = null;
                                lockBoard = false;
                            }, 800);
                        }
                    });
                });
            }
        }

        // Iniciar juego al cargar la página
        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-memoria");
            motivacionDiv = document.getElementById("motivacion");

            // Bloquear interacción hasta Empezar
            if (area) area.style.pointerEvents = "none";

            const startOverlay = document.getElementById("start-overlay");
            const btnStart = document.getElementById("btn-start");
            const btnStartBack = document.getElementById("btn-start-back");

            if (btnStart) {
                btnStart.addEventListener("click", function () {
                    if (startOverlay) startOverlay.style.display = "none";
                    if (area) area.style.pointerEvents = "auto";
                    loadMemoryGame(area);
                });
            }

            if (btnStartBack) {
                btnStartBack.addEventListener("click", function () {
                    window.location.href = "../../usuario.php";
                });
            }

            const btnRestart = document.getElementById("btn-restart");
            const btnVolver = document.getElementById("btn-volver");

            if (btnRestart) {
                btnRestart.addEventListener("click", function () {
                    hideOverlay();
                    loadMemoryGame(area);
                });
            }

            if (btnVolver) {
                btnVolver.addEventListener("click", function () {
                    window.location.href = "../../usuario.php";
                });
            }
        });
    </script>

</body>

</html>