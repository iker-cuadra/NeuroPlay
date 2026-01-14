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

    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #887d7dff;
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
            text-shadow: 0 2px 4px rgba(0,0,0,0.35);
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
            height: calc(100vh - 160px); /* solo restamos el header */
            width: 90%;
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);

            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 22px;
            box-sizing: border-box;
        }

        .game-header {
            margin-bottom: 12px;
            text-align: center;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 24px;
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

        /* ÁREA DEL JUEGO */
        #zona-atencion {
            flex: 1;
            width: 100%;
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
        }

        .instructions {
            text-align: center;
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 14px;
        }

        .symbol-grid {
            display: grid;
            gap: 14px;
            justify-content: center;
            margin-top: 10px;
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

        /* BOTONES INFERIORES */
        .buttons-row {
            margin-top: 16px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-game {
            background: #4b5563;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 22px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-game:hover {
            background: #374151;
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(0,0,0,0.25);
        }

        .motivacion {
            margin-top: 10px;
            font-size: 15px;
            color: #16a34a;
            font-weight: 600;
            text-align: center;
            min-height: 22px;
        }

        @media (max-width: 768px) {
            .game-container {
                width: 95%;
                padding: 16px;
            }

            .symbol-card {
                width: 70px;
                height: 70px;
                font-size: 30px;
            }

            .status-bar {
                flex-direction: column;
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
        <div class="user-role">Juego: Atención</div>
    </div>

    <!-- CONTENEDOR DEL JUEGO -->
    <div class="game-container">
        <div class="game-header">
            <h2>Encuentra el símbolo diferente</h2>
            <p>Observa todos los símbolos y pulsa sobre el que NO es igual a los demás.</p>
            <p>Dificultad asignada: <strong id="dificultad-label"><?= htmlspecialchars($dificultad_atencion) ?></strong></p>

            <div class="status-bar">
                <div class="status-pill">
                    Puntuación: <span id="score">0</span>
                </div>
                <div class="status-pill">
                    Tiempo restante: <span id="time-left">01:30</span>
                </div>
            </div>
        </div>

        <div id="zona-atencion">
            <!-- El tablero se genera por JavaScript -->
        </div>

        <div class="buttons-row">
            <button id="btn-restart" class="btn-game">Jugar otra vez</button>
            <button id="btn-volver" class="btn-game">Volver</button>
        </div>

        <div id="motivacion" class="motivacion"></div>
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
        //  UTILIDADES
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

            gameEnded         = false;
            attentionTimeLeft = TOTAL_TIME;
            gameScore         = 0;

            // Limpiar temporizadores anteriores
            if (attentionTimerInt) clearInterval(attentionTimerInt);
            if (roundTimeoutId)    clearTimeout(roundTimeoutId);

            updateScoreboard();
            area.style.pointerEvents = "auto";

            // Arrancar primera ronda
            startAttentionRound(area);

            // Arrancar cronómetro general
            attentionTimerInt = setInterval(() => {
                attentionTimeLeft--;
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

            if (motivacionDiv) {
                motivacionDiv.textContent =
                    getMotivationalMessage() + " Puntuación final: " + gameScore + " puntos.";
            } else {
                alert("Tiempo terminado. Puntuación final: " + gameScore);
            }

            // Guardar resultado: se asume que el usuario ha jugado los 90s
            saveAttentionResult(gameScore, TOTAL_TIME, dificultadTexto);
        }

        // ============================
        //  RONDAS Y SÍMBOLOS
        // ============================
        function startAttentionRound(area) {
            if (gameEnded) return;

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
            instructions.className = "instructions";
            instructions.textContent = "Encuentra el símbolo diferente antes de que cambie la pantalla.";
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
            const area        = document.getElementById("zona-atencion");
            const btnRestart  = document.getElementById("btn-restart");
            const btnVolver   = document.getElementById("btn-volver");

            // Primera partida
            startNewAttentionGame();

            // Botón "Jugar otra vez"
            btnRestart.addEventListener("click", function () {
                startNewAttentionGame();
            });

            // Botón "Volver"
            btnVolver.addEventListener("click", function () {
                window.location.href = "../../usuario.php";
            });
        });

        // Por si en algún momento quisieras limpiar timers al salir manualmente
        function cleanupAttentionGame() {
            if (attentionTimerInt) clearInterval(attentionTimerInt);
            if (roundTimeoutId)    clearTimeout(roundTimeoutId);
        }
    </script>

</body>
</html>
