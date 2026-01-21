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
            background: transparent;
            overflow: hidden;
            font-size: 18px;
        }

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

        .game-wrapper {
            height: 100vh;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px;
            box-sizing: border-box;
        }

        .game-container {
            position: relative;
            width: min(900px, 100%);
            height: 100%;
            max-height: 100%;
            background: linear-gradient(180deg, #ffffff 0%, #fbfbfb 100%);
            border-radius: 26px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 18px 40px rgba(0,0,0,0.18), 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 20px 16px 20px;
            box-sizing: border-box;
        }

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
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
            backdrop-filter: blur(6px);
        }

        .back-arrow svg {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-arrow:hover svg {
            opacity: 0.8;
            transform: translateX(-2px);
        }

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
            box-shadow: 0 10px 18px rgba(0,0,0,0.18);
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

        .reasoning-area {
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sequence-display {
            font-size: 2.6rem;
            padding: 20px 22px;
            background: #111827;
            color: white;
            border-radius: 20px;
            margin-bottom: 16px;
            letter-spacing: 10px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5), 0 14px 26px rgba(0,0,0,0.16);
            text-align: center;
            border: 1px solid rgba(255,255,255,0.10);
        }

        .sequence-question {
            margin: 0 0 16px 0;
            font-size: 1.05rem;
            color: #4b5563;
            text-align: center;
        }

        .pattern-container {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pattern-option {
            width: 104px;
            height: 104px;
            border-radius: 22px;
            background: #374151;
            color: white;
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.10);
            box-shadow: 0 12px 20px rgba(0,0,0,0.14);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .pattern-option:hover {
            transform: translateY(-4px);
            background: #4b5563;
            box-shadow: 0 16px 26px rgba(0,0,0,0.18);
        }

        .pattern-option.correct {
            background: #16a34a !important;
            border: 3px solid rgba(190,242,100,0.95);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 18px 30px rgba(0,0,0,0.20);
        }

        .pattern-option.incorrect {
            background: #b91c1c !important;
            border: 3px solid rgba(252,165,165,0.95);
            animation: shake 0.4s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .logic-message {
            margin-top: 10px;
            font-weight: 700;
            text-align: center;
            font-size: 1rem;
            min-height: 18px;
            color: #374151;
            flex: 0 0 auto;
            position: relative;
            z-index: 2;
        }

        .game-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.50);
            border-radius: inherit;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .overlay-content {
            text-align: center;
            color: #fff;
            background: rgba(17,24,39,0.55);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 22px;
            padding: 18px 18px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.35);
            backdrop-filter: blur(10px);
        }

        .overlay-content p {
            margin: 0 0 14px 0;
            font-size: 24px;
            font-weight: 800;
        }

        .overlay-buttons { margin-top: 14px; }

        .btn-game {
            background: #4a4a4a;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 22px;
            font-size: 18px;
            cursor: pointer;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(0,0,0,0.22);
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin: 0 6px;
        }

        .btn-game:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(0,0,0,0.28);
        }

        @media (max-width: 768px) {
            .game-container { padding: 16px 14px 12px 14px; }
            .sequence-display { font-size: 2.2rem; padding: 18px; letter-spacing: 7px; }
            .pattern-option { width: 92px; height: 92px; font-size: 2.6rem; }
            .game-title-pill { font-size: 30px; }
            .game-header h2 { font-size: 32px; }
        }

        @media (max-height: 680px) {
            .sequence-display { margin-bottom: 12px; font-size: 2rem; }
            .sequence-question { margin-bottom: 12px; }
            .game-title-pill { font-size: 28px; padding: 6px 16px; }
        }
    </style>
</head>
<body>

<div class="canvas-bg"></div>

<div class="game-wrapper">
    <div class="game-container">

        <a href="../../usuario.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000">
                <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
            </svg>
        </a>

        <div class="game-title-pill">Razonamiento</div>

        <div class="game-header">
            <h2>Secuencias lógicas</h2>
            <p>
                Dificultad asignada:
                <strong><?= htmlspecialchars($dificultad_razonamiento) ?></strong>
                &nbsp;|&nbsp;
                Ronda: <strong id="ronda-indicador">1/10</strong>
            </p>
            <p class="timer">
                <i class="far fa-clock"></i>
                Tiempo: <span id="tiempo">00:00</span>
            </p>
        </div>

        <div class="game-body">
            <div id="zona-razonamiento" class="reasoning-area"></div>
        </div>

        <div id="razonamiento-mensaje" class="logic-message"></div>

        <div id="game-overlay" class="game-overlay">
            <div id="overlay-content" class="overlay-content"></div>
        </div>

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
    const dificultadRazonamientoBD = "<?= htmlspecialchars($dificultad_razonamiento, ENT_QUOTES) ?>";
    let currentDifficulty = dificultadRazonamientoBD.toLowerCase();
    if (currentDifficulty === 'media') currentDifficulty = 'medio';
    if (currentDifficulty === 'fácil') currentDifficulty = 'facil';

    const TOTAL_RONDAS = 10;
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
                dificultad: dificultadRazonamientoBD,
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

        // PATRONES FÁCILES - Secuencias cortas (4 elementos) y simples
        const patternsFacil = [
            {seq:['★','◆','★','◆'], ans:'★'},
            {seq:['●','■','●','■'], ans:'●'},
            {seq:['▲','▼','▲','▼'], ans:'▲'},
            {seq:['★','★','◆','◆'], ans:'★'},
            {seq:['●','●','■','■'], ans:'●'},
            {seq:['▲','▲','▼','▼'], ans:'▲'},
            {seq:['■','■','■','■'], ans:'■'},
            {seq:['◆','◆','◆','◆'], ans:'◆'},
            {seq:['★','●','★','●'], ans:'★'},
            {seq:['▼','★','▼','★'], ans:'▼'},
        ];

        // PATRONES MEDIOS - Secuencias medianas (5-6 elementos) con más variación
        const patternsMedio = [
            {seq:['★','◆','●','★','◆'], ans:'●'},
            {seq:['■','▲','▼','■','▲'], ans:'▼'},
            {seq:['●','●','■','●','●'], ans:'■'},
            {seq:['★','▲','★','▲','★'], ans:'▲'},
            {seq:['◆','■','●','◆','■'], ans:'●'},
            {seq:['▼','▼','▲','▼','▼'], ans:'▲'},
            {seq:['★','●','▲','■','★'], ans:'●'},
            {seq:['■','■','◆','◆','■'], ans:'■'},
            {seq:['▲','●','★','▲','●'], ans:'★'},
            {seq:['◆','▼','◆','▼','◆'], ans:'▼'},
            {seq:['●','★','●','●','★'], ans:'●'},
            {seq:['■','▲','■','■','▲'], ans:'■'},
        ];

        // PATRONES DIFÍCILES - Secuencias largas (6-8 elementos) y complejas
        const patternsDificil = [
            {seq:['★','◆','●','■','★','◆'], ans:'●'},
            {seq:['▲','▼','★','●','▲','▼'], ans:'★'},
            {seq:['●','●','■','■','◆','◆'], ans:'●'},
            {seq:['★','▲','●','★','▲','●'], ans:'★'},
            {seq:['■','◆','▼','★','■','◆'], ans:'▼'},
            {seq:['▲','▲','●','●','■','■'], ans:'▲'},
            {seq:['★','●','▲','■','◆','▼'], ans:'★'},
            {seq:['◆','★','◆','★','◆','★'], ans:'◆'},
            {seq:['●','■','▲','●','■','▲'], ans:'●'},
            {seq:['▼','★','●','▼','★','●'], ans:'▼'},
            {seq:['■','■','◆','★','■','■'], ans:'◆'},
            {seq:['▲','●','★','■','▲','●'], ans:'★'},
            {seq:['◆','▼','★','●','◆','▼'], ans:'★'},
            {seq:['★','★','●','●','▲','▲'], ans:'★'},
        ];

        let patterns;
        if (currentDifficulty === 'facil') {
            patterns = patternsFacil;
        } else if (currentDifficulty === 'medio') {
            patterns = patternsMedio;
        } else {
            patterns = patternsDificil;
        }

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
        
        // En difícil, más opciones de respuesta
        const numOpciones = currentDifficulty === 'dificil' ? 4 : 3;
        
        while (opts.length < numOpciones) {
            let s = symbols[Math.floor(Math.random()*symbols.length)];
            if (!opts.includes(s)) opts.push(s);
        }
        opts.sort(() => Math.random() - 0.5);

        opts.forEach(o => {
            const btn = document.createElement("div");
            btn.className = "pattern-option";
            btn.textContent = o;

            btn.onclick = () => {
                const todosLosBotones = options.querySelectorAll('.pattern-option');
                todosLosBotones.forEach(b => b.style.pointerEvents = 'none');

                detenerTemporizador();
                const esCorrecto = (o === pattern.ans);

                if (esCorrecto) {
                    btn.classList.add("correct");
                } else {
                    btn.classList.add("incorrect");
                    todosLosBotones.forEach(b => {
                        if (b.textContent === pattern.ans) b.classList.add("correct");
                    });
                }

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

        overlayContent.innerHTML = `
            <i class="fas fa-trophy" style="font-size:3rem; color:#facc15; margin-bottom:8px;"></i>
            <p>¡Ejercicio completado!</p>
            <p style="font-size:18px; font-weight:normal;">Has acertado <strong>${aciertos}</strong> de <strong>${TOTAL_RONDAS}</strong> secuencias.</p>
            <p style="font-size:18px; font-weight:normal;">Tiempo total: <strong>${totalTiempo}s</strong></p>

            <div style="margin-top: 14px;">
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
        const startOverlay = document.getElementById("start-overlay");
        const btnStart = document.getElementById("btn-start");
        const btnStartBack = document.getElementById("btn-start-back");
        const zona = document.getElementById("zona-razonamiento");

        if (zona) zona.style.pointerEvents = "none";

        if (btnStart) {
            btnStart.addEventListener("click", () => {
                if (startOverlay) startOverlay.style.display = "none";
                if (zona) zona.style.pointerEvents = "auto";
                iniciarTemporizador();
                loadReasoningGame(zona);
            });
        }

        if (btnStartBack) {
            btnStartBack.addEventListener("click", () => {
                window.location.href = "../../usuario.php";
            });
        }
    });
</script>

</body>
</html>