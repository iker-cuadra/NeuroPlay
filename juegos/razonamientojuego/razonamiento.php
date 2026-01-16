<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"] ?? 0;

// OBTENER DIFICULTAD ASIGNADA (tal cual está en la BD)
$stmt = $conexion->prepare("
    SELECT dificultad_razonamiento
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_razonamiento = $stmt->fetchColumn();

// Si no hay dificultad asignada, dejamos un valor por defecto
if (!$dificultad_razonamiento) {
    $dificultad_razonamiento = "Medio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Juego de Razonamiento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            margin-top: 26px; /* espacio para la flecha */
            margin-bottom: 8px;
            text-align: center;
            flex: 0 0 auto;
        }

        .game-header h2 {
            margin: 0 0 6px 0;
            font-size: 32px;
            color: #222;
        }

        .game-header p {
            margin: 4px 0;
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

        /* CUERPO DEL JUEGO */
        .game-body {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4px 0;
            box-sizing: border-box;
        }

        .reasoning-area {
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sequence-display {
            font-size: 2.5rem;
            padding: 20px;
            background: #111827;
            color: white;
            border-radius: 18px;
            margin-bottom: 18px;
            letter-spacing: 10px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
            text-align: center;
        }

        .sequence-question {
            margin: 0 0 18px 0;
            font-size: 1.05rem;
            color: #4b5563;
            text-align: center;
        }

        .pattern-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pattern-option {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: #374151;
            color: white;
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 4px solid transparent;
        }

        .pattern-option:hover {
            transform: translateY(-5px);
            background: #4b5563;
        }

        .pattern-option.correct {
            background: #16a34a !important;
            border-color: #bef264;
            transform: scale(1.05);
        }

        .pattern-option.incorrect {
            background: #b91c1c !important;
            border-color: #fca5a5;
            animation: shake 0.4s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .logic-message {
            margin-top: 10px;
            font-weight: bold;
            text-align: center;
            font-size: 0.95rem;
            min-height: 18px;
            color: #374151;
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

        .result-list {
            margin: 14px auto 10px auto;
            width: 100%;
            max-width: 320px;
            text-align: left;
            font-size: 0.95rem;
        }

        .result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid rgba(156,163,175,0.4);
        }

        .result-row:last-child {
            border-bottom: none;
        }

        .result-round {
            font-weight: 600;
        }

        .result-time {
            font-family: monospace;
        }

        .result-icon i {
            font-size: 1rem;
        }

        .overlay-buttons {
            margin-top: 14px;
        }

        /* BOTONES (mismo estilo que otras páginas) */
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
            .sequence-display {
                font-size: 2.1rem;
                padding: 18px;
                letter-spacing: 7px;
            }
            .pattern-option {
                width: 90px;
                height: 90px;
                font-size: 2.5rem;
            }
        }

        @media (max-height: 680px) {
            .sequence-display {
                margin-bottom: 12px;
            }
            .sequence-question {
                margin-bottom: 12px;
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
        <div class="game-title-pill">Juego: Razonamiento</div>

        <!-- Cabecera -->
        <div class="game-header">
            <h2>Secuencias lógicas</h2>
            <p>
                Dificultad asignada:
                <strong><?= htmlspecialchars($dificultad_razonamiento) ?></strong>
                &nbsp;|&nbsp;
                Ronda: <strong id="ronda-indicador">1/5</strong>
            </p>
            <p class="timer">
                <i class="far fa-clock"></i>
                Tiempo: <span id="tiempo">00:00</span>
            </p>
        </div>

        <!-- Cuerpo del juego -->
        <div class="game-body">
            <div id="zona-razonamiento" class="reasoning-area"></div>
        </div>

        <!-- Mensaje (si quisieras usarlo en el futuro) -->
        <div id="razonamiento-mensaje" class="logic-message"></div>

        <!-- OVERLAY FINAL -->
        <div id="game-overlay" class="game-overlay">
            <div id="overlay-content" class="overlay-content"></div>
        </div>

    </div>
</div>

<script>
    const dificultadRazonamientoBD = "<?= htmlspecialchars($dificultad_razonamiento, ENT_QUOTES) ?>";
    let currentDifficulty = dificultadRazonamientoBD;
    if (currentDifficulty === 'media') currentDifficulty = 'medio'; // por si tienes ese valor en BD

    const TOTAL_RONDAS = 5;
    let rondaActual = 0;
    let resultados = [];
    let segundos = 0;
    let timer = null;

    function iniciarTemporizador() {
        detenerTemporizador();
        segundos = 0;
        timer = setInterval(() => {
            segundos++;
            document.getElementById("tiempo").textContent =
                String(Math.floor(segundos / 60)).padStart(2,'0') + ":" +
                String(segundos % 60).padStart(2,'0');
        }, 1000);
    }

    function detenerTemporizador() {
        if (timer) clearInterval(timer);
    }

    function enviarDatosFinales() {
        const totalTiempo = resultados.reduce((acc, r) => acc + r.tiempo_segundos, 0);
        const aciertos = resultados.filter(r => r.correcta === 1).length;
        const puntuacionTotal = Math.round((aciertos / TOTAL_RONDAS) * 100);

        fetch('../../guardar_resultado.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                tipo_juego: 'razonamiento',
                dificultad: dificultadRazonamientoBD, // guardamos lo que viene de BD
                puntuacion: puntuacionTotal,
                tiempo_segundos: totalTiempo,
                rondas: resultados
            })
        })
        .then(r => r.json())
        .catch(err => console.error("Error al guardar:", err));
    }

    function showOverlay() {
        const overlay = document.getElementById("game-overlay");
        if (overlay) overlay.style.display = "flex";
    }

    function hideOverlay() {
        const overlay = document.getElementById("game-overlay");
        if (overlay) overlay.style.display = "none";
    }

    function loadReasoningGame(area) {
        document.getElementById("ronda-indicador").textContent = (rondaActual + 1) + "/" + TOTAL_RONDAS;

        // Base de patrones (puedes ampliarla si quieres más variedad)
        const patterns = [
            {seq:['★','◆','★','◆'], ans:'★'},
            {seq:['●','■','●','■'], ans:'●'},
            {seq:['▲','▼','▲','▼'], ans:'▲'},
            {seq:['■','■','◆','◆'], ans:'■'},
            {seq:['★','●','★','●'], ans:'★'},
            {seq:['▲','●','▲','●'], ans:'▲'}
        ];
        const p = patterns[Math.floor(Math.random() * patterns.length)];
        crearInterfaz(area, p);
    }

    function crearInterfaz(area, pattern) {
        area.innerHTML = "";

        const display = document.createElement("div");
        display.className = "sequence-display";
        display.textContent = pattern.seq.join(" ") + " ?";
        area.appendChild(display);

        const question = document.createElement("p");
        question.className = "sequence-question";
        question.textContent = "¿Qué símbolo continúa la secuencia?";
        area.appendChild(question);

        const options = document.createElement("div");
        options.className = "pattern-container";

        const symbols = ['★','◆','●','■','▲','▼'];
        let opts = [pattern.ans];
        while (opts.length < 3) {
            let s = symbols[Math.floor(Math.random()*symbols.length)];
            if (!opts.includes(s)) opts.push(s);
        }
        opts.sort(() => Math.random() - 0.5);

        opts.forEach(o => {
            const btn = document.createElement("div");
            btn.className = "pattern-option";
            btn.textContent = o;

            btn.onclick = () => {
                // Bloqueo de clics mientras resolvemos
                const todosLosBotones = options.querySelectorAll('.pattern-option');
                todosLosBotones.forEach(b => b.style.pointerEvents = 'none');

                detenerTemporizador();
                const esCorrecto = (o === pattern.ans);

                if (esCorrecto) {
                    btn.classList.add("correct");
                } else {
                    btn.classList.add("incorrect");
                    // Marcar la correcta para feedback
                    todosLosBotones.forEach(b => {
                        if (b.textContent === pattern.ans) b.classList.add("correct");
                    });
                }

                // Guardar resultado de la ronda (acierto o fallo)
                resultados.push({
                    ronda: rondaActual + 1,
                    tiempo_segundos: segundos,
                    correcta: esCorrecto ? 1 : 0
                });

                rondaActual++;

                setTimeout(() => {
                    if (rondaActual >= TOTAL_RONDAS) {
                        enviarDatosFinales();
                        mostrarResumenFinal();
                    } else {
                        iniciarTemporizador();
                        loadReasoningGame(area);
                    }
                }, 1200);
            };

            options.appendChild(btn);
        });

        area.appendChild(options);
    }

    function mostrarResumenFinal() {
        detenerTemporizador();

        const aciertos = resultados.filter(r => r.correcta === 1).length;
        const overlayContent = document.getElementById("overlay-content");
        const totalTiempo = resultados.reduce((acc, r) => acc + r.tiempo_segundos, 0);

        let resultRows = "";
        resultados.forEach(r => {
            resultRows += `
                <div class="result-row">
                    <span class="result-round">Ronda ${r.ronda}</span>
                    <span class="result-time">${r.tiempo_segundos}s</span>
                    <span class="result-icon">
                        ${r.correcta
                            ? '<i class="fas fa-check-circle" style="color:#22c55e;"></i>'
                            : '<i class="fas fa-times-circle" style="color:#f97316;"></i>'}
                    </span>
                </div>
            `;
        });

        overlayContent.innerHTML = `
            <i class="fas fa-trophy" style="font-size:3rem; color:#facc15; margin-bottom:8px;"></i>
            <h3>¡Ejercicio completado!</h3>
            <p>Has acertado <strong>${aciertos}</strong> de <strong>${TOTAL_RONDAS}</strong> secuencias.</p>
            <p>Tiempo total: <strong>${totalTiempo}s</strong></p>

            <div class="result-list">
                ${resultRows}
            </div>

            <div class="overlay-buttons">
                <button id="btn-restart" class="btn-game">Jugar otra vez</button>
                <button id="btn-volver" class="btn-game">Volver al panel</button>
            </div>
        `;

        showOverlay();

        const btnRestart = document.getElementById("btn-restart");
        const btnVolver  = document.getElementById("btn-volver");

        if (btnRestart) {
            btnRestart.addEventListener("click", () => {
                hideOverlay();
                reiniciarJuego();
            });
        }

        if (btnVolver) {
            btnVolver.addEventListener("click", () => {
                window.location.href = "../../usuario.php";
            });
        }
    }

    function reiniciarJuego() {
        rondaActual = 0;
        resultados = [];
        document.getElementById("razonamiento-mensaje").innerHTML = "";
        document.getElementById("ronda-indicador").textContent = "1/" + TOTAL_RONDAS;
        iniciarTemporizador();
        loadReasoningGame(document.getElementById("zona-razonamiento"));
    }

    document.addEventListener("DOMContentLoaded", () => {
        iniciarTemporizador();
        loadReasoningGame(document.getElementById("zona-razonamiento"));
    });
</script>

</body>
</html>
