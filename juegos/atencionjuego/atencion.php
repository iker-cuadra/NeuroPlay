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
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #887d7dff;
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
            position: relative;
            width: min(900px, 100%);
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

        /* FLECHA VOLVER (NEGRA) */
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

        /* PASTILLA SUPERIOR */
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
            margin-top: 26px; /* deja sitio a la flecha */
            margin-bottom: 10px;
            text-align: center;
            flex: 0 0 auto;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 30px;
            color: #111827;
        }

        .game-header p {
            margin: 4px 0;
            font-size: 15px;
            color: #6b7280;
        }

        .game-header p strong {
            color: #111827;
        }

        /* MARCADOR (Puntuación + Tiempo) */
        .status-bar {
            display: flex;
            gap: 18px;
            justify-content: center;
            align-items: center;
            margin-top: 4px;
            font-size: 15px;
            color: #111827;
            font-weight: 600;
        }

        .status-pill {
            background: #f3f4f6;
            padding: 8px 14px;
            border-radius: 999px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }

        .timer-value {
            font-family: monospace;
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
        }

        #zona-atencion {
            flex: 1;
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
        }

        .instructions {
            text-align: center;
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .symbol-grid {
            display: grid;
            gap: 14px;
            justify-content: center;
            margin-top: 8px;
        }

        .symbol-card {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #f9fafb;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            user-select: none;
        }

        .symbol-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(0,0,0,0.30);
        }

        .symbol-card.wrong {
            background: #b91c1c;
        }

        .symbol-card.correct {
            background: #16a34a;
        }

        .motivacion {
            margin-top: 8px;
            font-size: 15px;
            color: #16a34a;
            font-weight: 600;
            text-align: center;
            min-height: 22px;
            flex: 0 0 auto;
        }

        /* OVERLAY FINAL */
        .game-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.45);
            border-radius: inherit;
            display: none; /* se muestra al final */
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .overlay-content {
            background: rgba(17,24,39,0.96);
            padding: 20px 24px;
            border-radius: 18px;
            color: #fff;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.45);
            max-height: 90%;
            overflow-y: auto;
        }

        .overlay-content h3 {
            margin-top: 8px;
            margin-bottom: 6px;
            font-size: 1.4rem;
        }

        .overlay-content p {
            margin: 4px 0;
        }

        .overlay-buttons {
            margin-top: 14px;
        }

        /* BOTONES (mismo estilo que el resto de páginas) */
        .btn-game {
            background: #4a4a4a;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 22px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(0,0,0,0.25);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin: 0 6px;
        }

        .btn-game:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(0,0,0,0.30);
        }

        @media (max-width: 768px) {
            .game-container {
                padding: 16px 14px 12px 14px;
            }

            .game-header h2 {
                font-size: 24px;
            }

            .symbol-card {
                width: 70px;
                height: 70px;
                font-size: 30px;
            }

            .status-bar {
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>

<div class="game-wrapper">
    <div class="game-container">

        <!-- Flecha volver al panel de usuario -->
        <a href="../../usuario.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>

        <!-- Pastilla superior -->
        <div class="game-title-pill">Juego: Atención</div>

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
                    <span id="time-left" class="timer-value">01:30</span>
                </div>
            </div>
        </div>

        <!-- Cuerpo del juego -->
        <div class="game-body">
            <div id="zona-atencion">
                <!-- El tablero se genera por JavaScript -->
            </div>
        </div>

        <!-- Mensaje motivacional -->
        <div id="motivacion" class="motivacion"></div>

        <!-- OVERLAY FINAL -->
        <div id="game-overlay" class="game-overlay">
            <div id="overlay-content" class="overlay-content"></div>
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
    let attentionTimeLeft   = 90;   // segundos totales
    let attentionTimerInt   = null; // intervalo del cronómetro
    let roundTimeoutId      = null; // timeout para cambiar figuras cada 10s
    let gameScore           = 0;
    let gameEnded           = false;

    const TOTAL_TIME = 90;         // 1:30 minutos

    // ============================
    //  UTILIDADES DE UI
    // ============================
    function updateScoreboard() {
        const scoreEl = document.getElementById('score');
        const timeEl  = document.getElementById('time-left');

        if (scoreEl) scoreEl.textContent = gameScore;

        if (timeEl) {
            const m = Math.floor(attentionTimeLeft / 60);
            const s = attentionTimeLeft % 60;
            timeEl.textContent =
                String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
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
        const i = Math.floor(Math.random() * mensajes.length);
        return mensajes[i];
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
    //  GUARDAR RESULTADO EN PHP
    // ============================
    function saveAttentionResult(score, seconds, dificultad) {
        fetch('../../guardar_resultado.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tipo_juego: 'atencion',
                puntuacion: score,
                tiempo_segundos: seconds,
                dificultad: dificultad
            })
        })
        .then(res => res.json())
        .then(data => {
            console.log('Resultado atención guardado:', data);
        })
        .catch(err => {
            console.error('Error al guardar resultado de atención:', err);
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

        gameEnded         = false;
        attentionTimeLeft = TOTAL_TIME;
        gameScore         = 0;

        // Limpiar temporizadores anteriores
        if (attentionTimerInt) clearInterval(attentionTimerInt);
        if (roundTimeoutId)    clearTimeout(roundTimeoutId);
        attentionTimerInt = null;
        roundTimeoutId    = null;

        updateScoreboard();
        if (area) {
            area.style.pointerEvents = "auto";
        }

        // Arrancar primera ronda
        startAttentionRound(area);

        // Arrancar cronómetro general
        attentionTimerInt = setInterval(() => {
            attentionTimeLeft--;
            if (attentionTimeLeft < 0) attentionTimeLeft = 0;
            updateScoreboard();

            if (attentionTimeLeft <= 0) {
                endAttentionGame();
            }
        }, 1000);
    }

    function endAttentionGame() {
        if (gameEnded) return;
        gameEnded = true;

        if (attentionTimerInt) clearInterval(attentionTimerInt);
        if (roundTimeoutId)    clearTimeout(roundTimeoutId);
        attentionTimerInt = null;
        roundTimeoutId    = null;

        const area = document.getElementById('zona-atencion');
        const motivacionDiv = document.getElementById('motivacion');

        if (area) {
            area.style.pointerEvents = "none";
        }

        // Mensaje motivacional bajo el juego
        if (motivacionDiv) {
            motivacionDiv.textContent =
                getMotivationalMessage() + " Puntuación final: " + gameScore + " puntos.";
        }

        // Guardar resultado (tiempo total 90s)
        saveAttentionResult(gameScore, TOTAL_TIME, dificultadTexto);

        // Overlay con resumen + botones
        const overlayContent = document.getElementById("overlay-content");
        if (overlayContent) {
            overlayContent.innerHTML = `
                <i class="fas fa-eye" style="font-size:3rem; color:#facc15; margin-bottom:6px;"></i>
                <h3>¡Tiempo terminado!</h3>
                <p>Puntuación final: <strong>${gameScore}</strong> puntos.</p>
                <p>Tiempo de juego: <strong>01:30</strong></p>

                <div class="overlay-buttons">
                    <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                    <button id="btn-volver" class="btn-game">Volver al panel</button>
                </div>
            `;
        }

        showOverlay();

        // Listeners de los botones del overlay
        const btnRestart = document.getElementById("btn-restart");
        const btnVolver  = document.getElementById("btn-volver");

        if (btnRestart) {
            btnRestart.onclick = () => {
                startNewAttentionGame();
            };
        }

        if (btnVolver) {
            btnVolver.onclick = () => {
                window.location.href = "../../usuario.php";
            };
        }
    }

    // ============================
    //  RONDAS Y SÍMBOLOS
    // ============================
    function startAttentionRound(area) {
        if (gameEnded || !area) return;

        // Determinar parámetros según dificultad
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
        } else { // difícil
            symbolCount = 12;
            symbolPairs = [
                { base: '⬤', different: '◯' },
                { base: '◆', different: '◇' },
                { base: '■', different: '□' },
                { base: '▲', different: '△' }
            ];
        }

        // Limpiar área y crear instrucciones + grid
        area.innerHTML = "";

        const instructions = document.createElement('p');
      
        
        area.appendChild(instructions);

        const grid = document.createElement('div');
        grid.className = "symbol-grid";
        grid.style.gridTemplateColumns =
            `repeat(${Math.ceil(Math.sqrt(symbolCount))}, 90px)`;
        area.appendChild(grid);

        generateAttentionExercise(grid, symbolCount, symbolPairs);

        // Programar cambio automático de figuras cada 10 segundos
        if (roundTimeoutId) clearTimeout(roundTimeoutId);
        roundTimeoutId = setTimeout(() => {
            if (!gameEnded && attentionTimeLeft > 0) {
                startAttentionRound(area);
            }
        }, 10000);
    }

    function generateAttentionExercise(container, symbolCount, symbolPairs) {
        container.innerHTML = "";

        const pair = symbolPairs[Math.floor(Math.random() * symbolPairs.length)];

        const symbols = new Array(symbolCount).fill(pair.base);
        const differentIndex = Math.floor(Math.random() * symbolCount);
        symbols[differentIndex] = pair.different;

        symbols.forEach((symbol, index) => {
            const card = document.createElement('div');
            card.className = "symbol-card";
            card.textContent = symbol;

            card.addEventListener('click', () => {
                if (gameEnded) return;

                if (index === differentIndex) {
                    // Correcto
                    gameScore += 10;
                    card.classList.add("correct");
                    updateScoreboard();

                    // Nueva ronda inmediatamente (reinicia timeout de 10s)
                    const area = document.getElementById('zona-atencion');
                    setTimeout(() => {
                        if (!gameEnded && attentionTimeLeft > 0) {
                            startAttentionRound(area);
                        }
                    }, 250);
                } else {
                    // Incorrecto
                    gameScore = Math.max(0, gameScore - 5);
                    card.classList.add("wrong");
                    updateScoreboard();

                    setTimeout(() => {
                        card.classList.remove("wrong");
                    }, 400);
                }
            });

            container.appendChild(card);
        });
    }

    // ============================
    //  INICIALIZACIÓN
    // ============================
    document.addEventListener("DOMContentLoaded", function () {
        startNewAttentionGame();
    });

    // Por si en algún momento quisieras limpiar timers al salir manualmente
    function cleanupAttentionGame() {
        if (attentionTimerInt) clearInterval(attentionTimerInt);
        if (roundTimeoutId)    clearTimeout(roundTimeoutId);
    }
</script>

</body>
</html>
