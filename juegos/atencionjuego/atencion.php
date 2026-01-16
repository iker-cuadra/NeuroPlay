<?php
// atencion.php  (ruta: juegos/atencionjuego/atencion.php)

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
    $dificultad_atencion = "Medio";
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
            margin: 0; padding: 0; height: 100%; overflow: hidden;
            font-family: Arial, Helvetica, sans-serif; background: #887d7dff;
        }
        .game-wrapper {
            height: 100vh; width: 100%; display: flex;
            justify-content: center; align-items: center; padding: 12px; box-sizing: border-box;
        }
        .game-container {
            position: relative; width: min(900px, 100%); height: 100%; max-height: 100%;
            background: #ffffff; border-radius: 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            display: flex; flex-direction: column; align-items: center;
            padding: 18px 20px 16px 20px; box-sizing: border-box;
        }
        .back-arrow {
            position: absolute; top: 12px; left: 12px; display: flex;
            align-items: center; justify-content: center; width: 38px; height: 38px;
            cursor: pointer; text-decoration: none; z-index: 3;
        }
        .game-title-pill {
            margin-top: 2px; margin-bottom: 4px; padding: 4px 14px;
            border-radius: 999px; background: #f3f4f6; font-size: 13px;
            font-weight: bold; color: #555;
        }
        .game-header { margin-top: 26px; margin-bottom: 10px; text-align: center; flex: 0 0 auto; }
        .game-header h2 { margin: 0 0 6px 0; font-size: 30px; color: #111827; }
        .game-header p { margin: 4px 0; font-size: 15px; color: #6b7280; }
        .status-bar { display: flex; gap: 18px; justify-content: center; align-items: center; margin-top: 4px; font-size: 15px; color: #111827; font-weight: 600; }
        .status-pill { background: #f3f4f6; padding: 8px 14px; border-radius: 999px; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
        .game-body { flex: 1 1 auto; width: 100%; display: flex; justify-content: center; align-items: center; padding-top: 6px; box-sizing: border-box; }
        #zona-atencion { flex: 1; width: 100%; max-width: 700px; display: flex; flex-direction: column; align-items: center; overflow: hidden; }
        .symbol-grid { display: grid; gap: 14px; justify-content: center; margin-top: 8px; }
        .symbol-card {
            width: 90px; height: 90px; border-radius: 16px; background: #111827;
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; color: #f9fafb; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); transition: all 0.15s ease; user-select: none;
        }
        .symbol-card:hover { transform: translateY(-2px); box-shadow: 0 7px 18px rgba(0,0,0,0.30); }
        .symbol-card.wrong { background: #b91c1c; }
        .symbol-card.correct { background: #16a34a; }
        .motivacion { margin-top: 8px; font-size: 15px; color: #16a34a; font-weight: 600; text-align: center; min-height: 22px; flex: 0 0 auto; }
        .game-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.45); border-radius: inherit; display: none; align-items: center; justify-content: center; z-index: 5; }
        .overlay-content { background: rgba(17,24,39,0.96); padding: 20px 24px; border-radius: 18px; color: #fff; max-width: 440px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.45); }
        .btn-game { background: #4a4a4a; color: #fff; border: none; border-radius: 20px; padding: 10px 22px; font-size: 15px; cursor: pointer; font-weight: 600; margin: 0 6px; }
    </style>
</head>
<body>

<div class="game-wrapper">
    <div class="game-container">
        <a href="../../usuario.php" class="back-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" height="26" width="26" viewBox="0 0 24 24" fill="#000000"><path d="M14.7 20.3 6.4 12l8.3-8.3 1.4 1.4L9.2 12l6.9 6.9Z"/></svg>
        </a>
        <div class="game-title-pill">Juego: Atención</div>
        <div class="game-header">
            <h2>Encuentra el símbolo diferente</h2>
            <p>Dificultad asignada: <strong id="dificultad-label"><?= htmlspecialchars($dificultad_atencion) ?></strong></p>
            <div class="status-bar">
                <div class="status-pill">Puntuación: <span id="score">0</span></div>
                <div class="status-pill">Tiempo: <span id="time-left" class="timer-value">01:30</span></div>
            </div>
        </div>
        <div class="game-body"><div id="zona-atencion"></div></div>
        <div id="motivacion" class="motivacion"></div>
        <div id="game-overlay" class="game-overlay"><div id="overlay-content" class="overlay-content"></div></div>
    </div>
</div>

<script>
    // ============================
    // VARIABLES Y CONFIGURACIÓN
    // ============================
    let dificultadTexto = "<?= htmlspecialchars($dificultad_atencion, ENT_QUOTES) ?>";
    function mapDifficultyCode(texto) {
        const t = (texto || "").toLowerCase();
        if (t.includes("fácil") || t.includes("facil")) return "facil";
        if (t.includes("difícil") || t.includes("dificil")) return "dificil";
        return "medio";
    }

    let currentDifficulty = mapDifficultyCode(dificultadTexto);
    
    // --- CAMBIO AQUÍ: Duración de 10 segundos ---
    const TOTAL_TIME = 10; 
    let attentionTimeLeft = TOTAL_TIME;
    
    let attentionTimerInt = null;
    let roundTimeoutId = null;
    let gameScore = 0;
    let gameEnded = false;

    // Captura de eventos para PHP
    let listaEventos = [];
    let tiempoUltimoClic = Date.now();

    function registrarEvento(esCorrecto) {
        const ahora = Date.now();
        const reaccion = (ahora - tiempoUltimoClic) / 1000;
        tiempoUltimoClic = ahora;
        listaEventos.push({
            estimulo: 1, 
            respuesta: esCorrecto ? 1 : 0,
            tiempo_reaccion: reaccion.toFixed(2)
        });
    }

    // ============================
    // GUARDAR RESULTADO
    // ============================
    function saveAttentionResult(score, seconds, dificultad) {
        if (listaEventos.length === 0) {
            listaEventos.push({ estimulo: 0, respuesta: 0, tiempo_reaccion: 0 });
        }

        const dataAEnviar = {
            tipo_juego: 'atencion',
            puntuacion: parseInt(score),
            tiempo_segundos: parseInt(seconds),
            dificultad: dificultad,
            eventos: listaEventos
        };

        fetch('../../guardar_resultado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataAEnviar)
        })
        .then(res => res.json())
        .then(data => {
            console.log('Servidor respondió:', data);
            if(data.ok) alert("¡Resultado guardado correctamente en la base de datos!");
        })
        .catch(err => console.error('Error al guardar:', err));
    }

    // ============================
    // LÓGICA DEL JUEGO
    // ============================
    function updateScoreboard() {
        document.getElementById('score').textContent = gameScore;
        const m = Math.floor(attentionTimeLeft / 60);
        const s = attentionTimeLeft % 60;
        // Actualiza el texto del tiempo restante
        document.getElementById('time-left').textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }

    function startNewAttentionGame() {
        gameEnded = false;
        attentionTimeLeft = TOTAL_TIME; // Reinicia a 10
        gameScore = 0;
        listaEventos = [];
        tiempoUltimoClic = Date.now();
        document.getElementById("game-overlay").style.display = "none";
        
        if (attentionTimerInt) clearInterval(attentionTimerInt);
        
        startAttentionRound();
        updateScoreboard();

        attentionTimerInt = setInterval(() => {
            attentionTimeLeft--;
            updateScoreboard();
            if (attentionTimeLeft <= 0) {
                clearInterval(attentionTimerInt);
                endAttentionGame();
            }
        }, 1000);
    }

    function endAttentionGame() {
        if (gameEnded) return;
        gameEnded = true;
        clearInterval(attentionTimerInt);
        clearTimeout(roundTimeoutId);

        // Enviamos TOTAL_TIME (10) para que conste en la BD
        saveAttentionResult(gameScore, TOTAL_TIME, dificultadTexto);

        document.getElementById("overlay-content").innerHTML = `
            <h3>¡Prueba terminada!</h3>
            <p>Puntuación: <strong>${gameScore}</strong></p>
            <p>Tiempo: 10 segundos</p>
            <div style="margin-top:15px">
                <button onclick="startNewAttentionGame()" class="btn-game">Probar otra vez</button>
                <button onclick="window.location.href='../../usuario.php'" class="btn-game">Ir al Panel</button>
            </div>`;
        document.getElementById("game-overlay").style.display = "flex";
    }

    function startAttentionRound() {
        if (gameEnded) return;
        const area = document.getElementById('zona-atencion');
        if (!area) return;
        area.innerHTML = "";
        
        let config = {
            facil: { count: 6, pairs: [{b:'★', d:'◆'}, {b:'●', d:'■'}] },
            medio: { count: 9, pairs: [{b:'◆', d:'✦'}, {b:'●', d:'◎'}] },
            dificil: { count: 12, pairs: [{b:'⬤', d:'◯'}, {b:'■', d:'□'}] }
        }[currentDifficulty];

        const grid = document.createElement('div');
        grid.className = "symbol-grid";
        grid.style.gridTemplateColumns = `repeat(${Math.ceil(Math.sqrt(config.count))}, 90px)`;
        
        const pair = config.pairs[Math.floor(Math.random() * config.pairs.length)];
        const diffIdx = Math.floor(Math.random() * config.count);

        for (let i = 0; i < config.count; i++) {
            const card = document.createElement('div');
            card.className = "symbol-card";
            card.textContent = (i === diffIdx) ? pair.d : pair.b;
            card.onclick = () => {
                if (gameEnded) return;
                if (i === diffIdx) {
                    gameScore += 10;
                    registrarEvento(true);
                    card.classList.add("correct");
                    setTimeout(startAttentionRound, 200);
                } else {
                    gameScore = Math.max(0, gameScore - 5);
                    registrarEvento(false);
                    card.classList.add("wrong");
                    setTimeout(() => card.classList.remove("wrong"), 400);
                }
                updateScoreboard();
            };
            grid.appendChild(card);
        }
        area.appendChild(grid);

        if (roundTimeoutId) clearTimeout(roundTimeoutId);
        roundTimeoutId = setTimeout(startAttentionRound, 5000); // Cambia símbolos cada 5s si no hace clic
    }

    document.addEventListener("DOMContentLoaded", startNewAttentionGame);
</script>
</body>
</html>