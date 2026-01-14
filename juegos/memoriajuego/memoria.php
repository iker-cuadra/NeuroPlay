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
        /* ==========================
           NUEVA ESTÉTICA (DARK UI)
           - Fondo oscuro
           - Contraste alto en cartas/botones/tipografía
           - Misma funcionalidad
        ========================== */

        :root{
            --bg-0: #070A12;
            --bg-1: #0B1020;
            --bg-2: #0F172A;

            --panel: rgba(255,255,255,.06);
            --panel-border: rgba(255,255,255,.10);
            --shadow: 0 18px 50px rgba(0,0,0,.55);

            --text: #E5E7EB;
            --muted: rgba(229,231,235,.70);

            --accent: #7C3AED;     /* morado */
            --accent-2: #22C55E;   /* verde */
            --danger: #EF4444;     /* rojo */

            --card-bg: rgba(255,255,255,.07);
            --card-border: rgba(255,255,255,.12);
            --card-shadow: 0 14px 30px rgba(0,0,0,.40);

            --btn-bg: rgba(255,255,255,.08);
            --btn-border: rgba(255,255,255,.14);
            --btn-shadow: 0 12px 24px rgba(0,0,0,.35);

            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 14px;

            --header-h: 160px;
        }

        *{ box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background:
                radial-gradient(900px 450px at 15% 20%, rgba(124,58,237,.22), transparent 55%),
                radial-gradient(800px 380px at 85% 35%, rgba(34,197,94,.16), transparent 60%),
                linear-gradient(180deg, var(--bg-0), var(--bg-1) 40%, var(--bg-2));
            color: var(--text);
        }

        /* HEADER SUPERIOR (banner + overlay oscuro) */
        .header {
            width: 100%;
            height: var(--header-h);
            background-image: url('../../imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
            overflow: hidden;
        }

        .header::after{
            content:"";
            position:absolute;
            inset:0;
            background:
                linear-gradient(180deg, rgba(0,0,0,.55), rgba(0,0,0,.40) 55%, rgba(0,0,0,.70)),
                radial-gradient(650px 220px at 20% 20%, rgba(124,58,237,.35), transparent 60%),
                radial-gradient(600px 240px at 85% 35%, rgba(34,197,94,.22), transparent 60%);
            pointer-events:none;
        }

        .user-role {
            position: absolute;
            bottom: 12px;
            left: 18px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .2px;
            z-index: 2;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .user-role::before{
            content:"";
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: linear-gradient(180deg, var(--accent), #A78BFA);
            box-shadow: 0 0 0 4px rgba(124,58,237,.18);
        }

        /* FLECHA DE VOLVER (más moderna) */
        .back-arrow {
            position: absolute;
            top: 14px;
            left: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            cursor: pointer;
            text-decoration: none;
            z-index: 2;

            border-radius: 14px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.18);
            box-shadow: 0 14px 30px rgba(0,0,0,.35);
            backdrop-filter: blur(10px);
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .back-arrow:hover{
            transform: translateY(-2px);
            background: rgba(255,255,255,.14);
            border-color: rgba(255,255,255,.26);
        }

        .back-arrow:active{
            transform: translateY(0px);
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.9;
        }

        /* CONTENEDOR CENTRAL DEL JUEGO (glass oscuro) */
        .game-container {
            height: calc(100vh - var(--header-h));
            width: min(1180px, 92%);
            margin: 0 auto;
            border-radius: var(--radius-xl);

            background: linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.05));
            border: 1px solid var(--panel-border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);

            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 18px;
            overflow: hidden;
            min-height: 0;
        }

        .game-header {
            text-align: center;
            flex: 0 0 auto;
            padding: 10px 12px 8px;
            border-radius: var(--radius-lg);
            background: rgba(0,0,0,.18);
            border: 1px solid rgba(255,255,255,.10);
            width: min(820px, 100%);
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: .2px;
        }

        .game-header p {
            margin: 3px 0;
            font-size: 14px;
            color: var(--muted);
        }

        .game-header p strong {
            color: var(--text);
            font-weight: 900;
        }

        /* TIME DISPLAY */
        .timer {
            font-weight: 900;
            font-size: 15px;
            margin-top: 4px;
            color: var(--text);
        }

        .timer span{
            display:inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            margin-left: 6px;
            background: rgba(124,58,237,.14);
            border: 1px solid rgba(124,58,237,.26);
        }

        /* GRID (AUTO-AJUSTE SIN SCROLL) */
        .memory-grid {
            --cols: 4;
            --card: 92px;
            --gap: 14px;

            display: grid;
            grid-template-columns: repeat(var(--cols), var(--card));
            gap: var(--gap);

            width: 100%;
            justify-content: center;
            align-content: center;

            flex: 1 1 auto;
            min-height: 0;
            margin-top: 12px;
        }

        /* CARTA: más contrastada y moderna */
        .memory-card {
            width: var(--card);
            height: var(--card);
            border-radius: 18px;

            background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.06));
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);

            color: transparent;
            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;
            user-select: none;

            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease, border-color .16s ease;
            position: relative;
            overflow: hidden;
        }

        .memory-card::before{
            content:"";
            position:absolute;
            inset:-40%;
            background: radial-gradient(circle at 30% 30%, rgba(124,58,237,.28), transparent 45%),
                        radial-gradient(circle at 70% 70%, rgba(34,197,94,.18), transparent 50%);
            transform: rotate(12deg);
            opacity: .55;
            pointer-events:none;
        }

        .memory-card.hidden-symbol {
            background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.06));
        }

        /* Logo en la carta oculta */
        .memory-card.hidden-symbol::after{
            content:"";
            position:absolute;
            inset: 18%;
            background: url('neuroplay.png') center/contain no-repeat;
            opacity: .92;
            filter: drop-shadow(0 10px 18px rgba(0,0,0,.45));
        }

        .memory-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 40px rgba(0,0,0,.48);
            filter: brightness(1.03);
            border-color: rgba(255,255,255,.20);
        }

        .memory-card:active{
            transform: translateY(-1px);
        }

        .memory-card.revealed {
            color: transparent;
        }

        /* Coincidencia */
        .memory-card.matched {
            cursor: default;
            border: 2px solid rgba(34,197,94,.85);
            box-shadow: 0 20px 44px rgba(0,0,0,.52);
        }

        .memory-card.matched::after{
            content:"✓";
            position:absolute;
            top: 10px;
            right: 12px;
            font-weight: 1000;
            color: rgba(34,197,94,.95);
            text-shadow: 0 10px 18px rgba(0,0,0,.45);
            font-size: 18px;
        }

        /* BOTONES INFERIORES (pill, gradiente) */
        .buttons-row {
            margin-top: 10px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            flex: 0 0 auto;
            width: 100%;
        }

        .btn-game {
            border: none;
            cursor: pointer;
            font-weight: 900;
            letter-spacing: .2px;

            padding: 10px 16px;
            border-radius: 999px;

            background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.06));
            color: var(--text);
            border: 1px solid var(--btn-border);
            box-shadow: var(--btn-shadow);
            backdrop-filter: blur(10px);

            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease, background .16s ease, border-color .16s ease;
            min-width: 170px;
        }

        .btn-game:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px rgba(0,0,0,.44);
            filter: brightness(1.04);
            border-color: rgba(255,255,255,.22);
        }

        .btn-game:active {
            transform: translateY(0);
            box-shadow: var(--btn-shadow);
        }

        /* Botón primario visual (reiniciar) */
        #btn-restart{
            background: linear-gradient(180deg, rgba(124,58,237,.42), rgba(124,58,237,.18));
            border-color: rgba(124,58,237,.40);
        }
        #btn-restart:hover{
            background: linear-gradient(180deg, rgba(124,58,237,.52), rgba(124,58,237,.22));
        }

        /* Botón secundario (volver) */
        #btn-volver{
            background: linear-gradient(180deg, rgba(34,197,94,.30), rgba(34,197,94,.12));
            border-color: rgba(34,197,94,.32);
        }
        #btn-volver:hover{
            background: linear-gradient(180deg, rgba(34,197,94,.38), rgba(34,197,94,.16));
        }

        /* Mensaje motivación */
        .motivacion {
            margin-top: 10px;
            font-size: 14px;
            color: rgba(229,231,235,.92);
            font-weight: 800;
            text-align: center;
            min-height: 22px;
            flex: 0 0 auto;

            width: min(820px, 100%);
            padding: 10px 12px;
            border-radius: var(--radius-lg);
            background: rgba(0,0,0,.18);
            border: 1px solid rgba(255,255,255,.10);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .game-container {
                width: 95%;
                padding: 14px 14px;
            }

            .game-header h2 { font-size: 18px; }

            .btn-game { width: 100%; min-width: 0; }
        }

        @media (prefers-reduced-motion: reduce){
            *{ transition:none !important; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <a href="../../usuario.php" class="back-arrow" aria-label="Volver">
            <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z" />
            </svg>
        </a>
        <div class="user-role">Juego: Memoria</div>
    </div>

    <!-- CONTENEDOR DEL JUEGO -->
    <div class="game-container">
        <div class="game-header">
            <h2>Encuentra las parejas de colores</h2>
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
                headers: { 'Content-Type': 'application/json' },
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
            return mensajes[Math.floor(Math.random() * mensajes.length)];
        }

        // ==========================
        //  AUTO-AJUSTE: cartas lo más grandes posible SIN scroll
        // ==========================
        const gridFitState = { count: 0, cols: 4 };

        function fitGridToContainer() {
            const area = document.getElementById("zona-memoria");
            if (!area || !gridFitState.count) return;

            const cols = gridFitState.cols;
            const rows = Math.ceil(gridFitState.count / cols);

            const container = document.querySelector(".game-container");
            const header = document.querySelector(".game-header");
            const buttons = document.querySelector(".buttons-row");
            const motiv = document.getElementById("motivacion");

            if (!container || !header || !buttons || !motiv) return;

            const gap = Math.max(10, Math.min(18, Math.floor(container.clientWidth / 80)));
            area.style.setProperty("--gap", gap + "px");

            const cs = getComputedStyle(container);
            const padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
            const padX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);

            const availableW = container.clientWidth - padX;
            const availableH =
                container.clientHeight
                - padY
                - header.offsetHeight
                - buttons.offsetHeight
                - motiv.offsetHeight
                - 18; // margen de seguridad

            const sizeByW = Math.floor((availableW - gap * (cols - 1)) / cols);
            const sizeByH = Math.floor((availableH - gap * (rows - 1)) / rows);

            const minCard = 34;
            const cardSize = Math.max(minCard, Math.min(sizeByW, sizeByH));

            area.style.setProperty("--cols", cols);
            area.style.setProperty("--card", cardSize + "px");
        }

        window.addEventListener("resize", () => {
            requestAnimationFrame(fitGridToContainer);
        });

        // ==========================
        //  FUNCIÓN PRINCIPAL
        // ==========================
        function loadMemoryGame(area) {
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
            const colors = ['#EF4444','#3B82F6','#10B981','#F97316'];
            const cards = [];
            for (let i = 0; i < pairs; i++) cards.push(colors[i], colors[i]);
            cards.sort(() => Math.random() - 0.5);
            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - INTERMEDIO (6 pares, 12 cartas)
        function loadMemoryGameMedio(area) {
            const pairs = 6;
            const colors = ['#EF4444','#3B82F6','#10B981','#F97316','#A855F7','#FACC15'];
            const cards = [];
            for (let i = 0; i < pairs; i++) cards.push(colors[i], colors[i]);
            cards.sort(() => Math.random() - 0.5);
            createMemoryGrid(area, cards, 4);
        }

        // MEMORIA - DIFÍCIL (8 pares, 16 cartas)
        function loadMemoryGameDificil(area) {
            const pairs = 8;
            const colors = ['#EF4444','#3B82F6','#10B981','#F97316','#A855F7','#FACC15','#EC4899','#22C55E'];
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
            gridFitState.count = cards.length;
            gridFitState.cols = columns;

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

                    // Ocultamos el overlay decorativo para que el color se vea limpio
                    card.style.setProperty('--_dummy', '1'); // no-op
                    card.querySelector?.(':scope'); // no-op, evita warnings
                    card.style.filter = 'none';

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
                            clearInterval(timerInterval);
                            const puntosFinales = 100;
                            const segundosTotales = elapsedSeconds;

                            guardarResultadoMemoria(puntosFinales, segundosTotales);

                            setTimeout(() => {
                                if (motivacionDiv) {
                                    motivacionDiv.textContent =
                                        obtenerMensajeMotivacion() +
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
                            firstCard.style.backgroundColor = '';
                            firstCard.style.backgroundImage = '';
                            firstCard.style.filter = '';

                            secondCard.classList.add("hidden-symbol");
                            secondCard.classList.remove("revealed");
                            secondCard.style.backgroundColor = '';
                            secondCard.style.backgroundImage = '';
                            secondCard.style.filter = '';

                            firstCard = null;
                            secondCard = null;
                            lockBoard = false;
                        }, 800);
                    }
                });

                area.appendChild(card);
            });

            requestAnimationFrame(() => {
                fitGridToContainer();
                startTimer();
            });
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
