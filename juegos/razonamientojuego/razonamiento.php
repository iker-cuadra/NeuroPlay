<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"] ?? 0;

// OBTENER DIFICULTAD ASIGNADA
$stmt = $conexion->prepare("
    SELECT dificultad_razonamiento
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_razonamiento = $stmt->fetchColumn();

if (!$dificultad_razonamiento) {
    $dificultad_razonamiento = "facil";
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
            overflow-y: auto;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f2f2f2;
        }

        .header {
            width: 100%;
            height: 160px;
            background-image: url('../../imagenes/Banner.svg');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .user-role {
            position: absolute;
            bottom: 10px;
            left: 20px;
            font-size: 20px;
            font-weight: bold;
            color: white;
        }

        .back-arrow {
            position: absolute;
            top: 15px;
            left: 15px;
        }

        .game-container {
            min-height: calc(100vh - 200px);
            width: 90%;
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sequence-display {
            font-size: 2.5rem;
            padding: 25px;
            background: #111827;
            color: white;
            border-radius: 20px;
            margin-bottom: 30px;
            letter-spacing: 10px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
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
            margin-top: 25px;
            font-weight: bold;
            text-align: center;
        }

        .btn-game {
            padding: 15px 30px;
            border-radius: 30px;
            border: none;
            background: #111827;
            color: white;
            cursor: pointer;
            font-weight: bold;
            margin: 10px;
            transition: 0.3s;
        }

        .btn-game:hover { background: #374151; }

        #tiempo { color: #1e4db7; font-size: 1.2rem; }
    </style>
</head>

<body>

<div class="header">
    <a href="../../usuario.php" class="back-arrow">
        <svg xmlns="http://www.w3.org/2000/svg" height="34" width="34" viewBox="0 0 24 24" fill="white">
            <path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/>
        </svg>
    </a>
    <div class="user-role">Juego: Razonamiento</div>
</div>

<div class="game-container">
    <h2 style="margin-bottom: 5px;">Secuencias Lógicas</h2>
    <p style="color: #666; margin-bottom: 20px;">Dificultad: <strong><?= ucfirst($dificultad_razonamiento) ?></strong> | Ronda: <strong id="ronda-indicador">1/5</strong></p>
    <p><i class="far fa-clock"></i> Tiempo: <strong id="tiempo">00:00</strong></p>

    <div id="zona-razonamiento"></div>
    <div id="razonamiento-mensaje" class="logic-message"></div>
</div>

<script>
let currentDifficulty = "<?= $dificultad_razonamiento ?>";
if (currentDifficulty === 'media') currentDifficulty = 'medio';

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
            dificultad: currentDifficulty,
            puntuacion: puntuacionTotal,
            tiempo_segundos: totalTiempo,
            rondas: resultados 
        })
    })
    .then(r => r.json())
    .catch(err => console.error("Error al guardar:", err));
}

function loadReasoningGame(area) {
    document.getElementById("ronda-indicador").textContent = (rondaActual + 1) + "/" + TOTAL_RONDAS;
    
    // Base de datos de patrones extendida
    const patterns = [
        {seq:['★','◆','★','◆'], ans:'★'},
        {seq:['●','■','●','■'], ans:'●'},
        {seq:['▲','▼','▲','▼'], ans:'▲'},
        {seq:['■','■','◆','◆'], ans:'■'},
        {seq:['★','●','★','●'], ans:'★'},
        {seq:['▲','●','▲','●'], ans:'▲'}
    ];
    const p = patterns[Math.floor(Math.random()*patterns.length)];
    crearInterfaz(area, p);
}

function crearInterfaz(area, pattern) {
    area.innerHTML = "";

    const display = document.createElement("div");
    display.className = "sequence-display";
    display.textContent = pattern.seq.join(" ") + " ?";
    area.appendChild(display);

    const options = document.createElement("div");
    options.className = "pattern-container";

    const symbols = ['★','◆','●','■','▲','▼'];
    let opts = [pattern.ans];
    while (opts.length < 3) {
        let s = symbols[Math.floor(Math.random()*symbols.length)];
        if (!opts.includes(s)) opts.push(s);
    }
    opts.sort(()=>Math.random()-0.5);

    opts.forEach(o=>{
        const btn = document.createElement("div");
        btn.className = "pattern-option";
        btn.textContent = o;
        
        btn.onclick = () => {
            // BLOQUEO DE CLICS PARA EVITAR ERRORES
            const todosLosBotones = options.querySelectorAll('.pattern-option');
            todosLosBotones.forEach(b => b.style.pointerEvents = 'none');

            detenerTemporizador();
            const esCorrecto = (o === pattern.ans);

            if (esCorrecto) {
                btn.classList.add("correct");
            } else {
                btn.classList.add("incorrect");
                // Mostramos la correcta para que el usuario aprenda
                todosLosBotones.forEach(b => {
                    if(b.textContent === pattern.ans) b.classList.add("correct");
                });
            }

            // GUARDAR RESULTADO SIEMPRE (ACIERTO O FALLO)
            resultados.push({
                ronda: rondaActual + 1,
                tiempo_segundos: segundos, 
                correcta: esCorrecto ? 1 : 0
            });

            rondaActual++;

            // Esperar 1 segundo antes de cambiar de ronda
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
    const area = document.getElementById("zona-razonamiento");
    const aciertos = resultados.filter(r => r.correcta === 1).length;
    
    area.innerHTML = `
        <div style="text-align:center;">
            <i class="fas fa-trophy" style="font-size:3rem; color:#facc15; margin-bottom:15px;"></i>
            <h3>¡Ejercicio completado!</h3>
            <p style="font-size:1.2rem;">Has acertado <strong>${aciertos}</strong> de <strong>${TOTAL_RONDAS}</strong></p>
        </div>
    `;

    let ul = "<div style='width:100%; max-width:300px; margin:20px auto; text-align:left;'>";
    resultados.forEach(r => {
        ul += `
            <div style='display:flex; justify-content:space-between; padding:8px; border-bottom:1px solid #eee;'>
                <span>Ronda ${r.ronda}</span>
                <span>${r.tiempo_segundos}s</span>
                <span>${r.correcta ? '<i class="fas fa-check-circle" style="color:green"></i>' : '<i class="fas fa-times-circle" style="color:red"></i>'}</span>
            </div>`;
    });
    ul += "</div>";
    area.innerHTML += ul;

    document.getElementById("razonamiento-mensaje").innerHTML = `
        <button class="btn-game" onclick="reiniciarJuego()">Jugar otra vez</button>
        <button class="btn-game" onclick="window.location.href='../../usuario.php'">Volver al inicio</button>
    `;
}

function reiniciarJuego() {
    rondaActual = 0;
    resultados = [];
    document.getElementById("razonamiento-mensaje").innerHTML = "";
    iniciarTemporizador();
    loadReasoningGame(document.getElementById("zona-razonamiento"));
}

document.addEventListener("DOMContentLoaded", ()=>{
    iniciarTemporizador();
    loadReasoningGame(document.getElementById("zona-razonamiento"));
});
</script>

</body>
</html>