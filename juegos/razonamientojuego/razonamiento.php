<?php
require_once "../../includes/conexion.php";
require_once "../../includes/auth.php";

requireRole("usuario");
$usuario_id = $_SESSION["usuario_id"] ?? 0;

// OBTENER DIFICULTAD ASIGNADA PARA RAZONAMIENTO
$stmt = $conexion->prepare("
    SELECT dificultad_razonamiento
    FROM dificultades_asignadas
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$dificultad_razonamiento = $stmt->fetchColumn();

// Dificultad por defecto si no hay asignada
if (!$dificultad_razonamiento) {
    $dificultad_razonamiento = "facil";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Juego de Razonamiento</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f2f2;
            color: #111827;
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
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
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
            height: calc(100vh - 160px - 160px);
            /* header + footer */
            width: 90%;
            margin: 0 auto;
            background: white;
            border-radius: 20px;

            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);

            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .game-header {
            width: 100%;
            text-align: center;
            margin-bottom: 16px;
        }

        .game-header h2 {
            margin: 0 0 4px 0;
            font-size: 24px;
        }

        .game-header p {
            margin: 2px 0;
            font-size: 15px;
            color: #4b5563;
        }

        .game-stats {
            margin-top: 8px;
            font-size: 14px;
            color: #111827;
        }

        .game-stats span {
            margin: 0 10px;
        }

        /* ÁREA DEL JUEGO */
        .reasoning-area {
            margin-top: 10px;
            width: 100%;
            max-width: 600px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sequence-display {
            width: 100%;
            text-align: center;
            font-size: 2rem;
            padding: 16px 10px;
            border-radius: 16px;
            background: #111827;
            color: #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            letter-spacing: 4px;
        }

        .pattern-container {
            margin-top: 20px;
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .pattern-option {
            width: 90px;
            height: 90px;
            border-radius: 18px;
            background: radial-gradient(circle at 30% 30%, #4b5563, #111827);
            border: 2px solid #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.2s ease;
            user-select: none;
        }

        .pattern-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
            border-color: #e5e7eb;
        }

        .pattern-option.correct {
            background: #16a34a;
            border-color: #bbf7d0;
        }

        .pattern-option.incorrect {
            background: #b91c1c;
            border-color: #fecaca;
        }

        .pattern-option span {
            font-size: 3rem;
            color: #f9fafb;
        }

        .question-text {
            text-align: center;
            font-size: 1.1em;
            margin: 20px 0 6px 0;
            color: #4b5563;
        }

        .logic-message {
            margin-top: 10px;
            font-size: 15px;
            min-height: 20px;
            text-align: center;
            font-weight: 600;
        }

        .logic-message.ok {
            color: #15803d;
        }

        .logic-message.err {
            color: #b91c1c;
        }

        @media (max-width: 768px) {
            .game-container {
                width: 95%;
                padding: 16px;
            }

            .sequence-display {
                font-size: 1.6rem;
                padding: 12px 8px;
            }

            .pattern-option {
                width: 80px;
                height: 80px;
            }

            .pattern-option span {
                font-size: 2.5rem;
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
        <div class="user-role">Juego: Razonamiento</div>
    </div>

    <!-- CONTENEDOR DEL JUEGO -->
    <div class="game-container">
        <div class="game-header">
            <h2>Secuencias Lógicas</h2>
            <p>Selecciona el símbolo que continúa la secuencia.</p>
            <p>Dificultad asignada:
                <strong>
                    <?php
                    $label = ucfirst($dificultad_razonamiento);
                    echo htmlspecialchars($label);
                    ?>
                </strong>
            </p>
            <div class="game-stats">
                <span>Tiempo: <strong id="tiempo">00:00</strong></span>
            </div>
        </div>

        <div id="zona-razonamiento" class="reasoning-area">
            <!-- Aquí va el juego por JavaScript -->
        </div>

        <div id="razonamiento-mensaje" class="logic-message"></div>
    </div>


    <script>
        // Dificultad desde PHP
        let currentDifficulty = "<?= htmlspecialchars($dificultad_razonamiento, ENT_QUOTES) ?>";
        if (currentDifficulty === 'media') {
            currentDifficulty = 'medio';
        }

        // ===== TEMPORIZADOR =====
        let segundos = 0;
        let timerInterval = null;

        function formatearTiempo(s) {
            const min = String(Math.floor(s / 60)).padStart(2, '0');
            const seg = String(s % 60).padStart(2, '0');
            return `${min}:${seg}`;
        }

        function iniciarTemporizador() {
            detenerTemporizador();
            segundos = 0;
            const t = document.getElementById('tiempo');
            if (t) t.textContent = formatearTiempo(segundos);

            timerInterval = setInterval(() => {
                segundos++;
                if (t) t.textContent = formatearTiempo(segundos);
            }, 1000);
        }

        function detenerTemporizador() {
            if (timerInterval !== null) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        }

        // ===== GUARDAR RESULTADO EN BD =====
        function guardarResultadoRazonamiento(puntuacion, tiempoSegundos) {
            fetch('../../guardar_resultado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tipo_juego: 'razonamiento',
                    dificultad: currentDifficulty,
                    puntuacion: puntuacion,
                    tiempo_segundos: tiempoSegundos
                })
            })
                .then(r => r.json())
                .then(data => {
                    console.log('Resultado razonamiento guardado:', data);
                })
                .catch(err => {
                    console.error('Error al guardar resultado de razonamiento', err);
                });
        }

        // ============================================
        // JUEGO DE RAZONAMIENTO
        // ============================================

        function loadReasoningGame(area) {
            if (currentDifficulty === 'facil') {
                loadReasoningGameFacil(area);
            } else if (currentDifficulty === 'medio') {
                loadReasoningGameMedio(area);
            } else {
                loadReasoningGameDificil(area);
            }
        }

        // RAZONAMIENTO - FÁCIL (Patrones simples de 2 elementos)
        function loadReasoningGameFacil(area) {
            const patterns = [
                { sequence: ['★', '◆', '★', '◆'], answer: '★' },
                { sequence: ['●', '■', '●', '■'], answer: '●' },
                { sequence: ['▲', '▼', '▲', '▼'], answer: '▲' }
            ];

            const pattern = patterns[Math.floor(Math.random() * patterns.length)];
            createReasoningInterface(area, pattern, ['★', '◆', '●', '■', '▲', '▼']);
        }

        // RAZONAMIENTO - MEDIO (Patrones de 3 elementos)
        function loadReasoningGameMedio(area) {
            const patterns = [
                { sequence: ['★', '◆', '●', '★', '◆'], answer: '●' },
                { sequence: ['■', '▲', '▼', '■', '▲'], answer: '▼' },
                { sequence: ['●', '●', '■', '●', '●'], answer: '■' }
            ];

            const pattern = patterns[Math.floor(Math.random() * patterns.length)];
            createReasoningInterface(area, pattern, ['★', '◆', '●', '■', '▲', '▼']);
        }

        // RAZONAMIENTO - DIFÍCIL (Patrones más largos)
        function loadReasoningGameDificil(area) {
            const patterns = [
                { sequence: ['★', '◆', '●', '■', '★', '◆', '●'], answer: '■' },
                { sequence: ['▲', '▲', '▼', '▼', '▲', '▲'], answer: '▼' },
                { sequence: ['★', '●', '●', '★', '■', '■', '★'], answer: '▲' }
            ];

            const pattern = patterns[Math.floor(Math.random() * patterns.length)];
            createReasoningInterface(area, pattern, ['★', '◆', '●', '■', '▲', '▼', '♦', '♠']);
        }

        // Función compartida para crear la interfaz de razonamiento
        function createReasoningInterface(area, pattern, availableSymbols) {
            area.innerHTML = '';

            const msgDiv = document.getElementById('razonamiento-mensaje');
            if (msgDiv) {
                msgDiv.textContent = '';
                msgDiv.className = 'logic-message';
            }

            const display = document.createElement('div');
            display.className = 'sequence-display';
            display.textContent = pattern.sequence.join(' ') + '  ?';
            area.appendChild(display);

            const question = document.createElement('p');
            question.className = 'question-text';
            question.textContent = '¿Qué símbolo continúa la secuencia?';
            area.appendChild(question);

            const options = document.createElement('div');
            options.className = 'pattern-container';

            const selectedOptions = [pattern.answer];

            // Agregar 2 opciones incorrectas
            while (selectedOptions.length < 3) {
                const opt = availableSymbols[Math.floor(Math.random() * availableSymbols.length)];
                if (!selectedOptions.includes(opt)) {
                    selectedOptions.push(opt);
                }
            }

            // Mezclar opciones
            selectedOptions.sort(() => Math.random() - 0.5);

            selectedOptions.forEach(opt => {
                const btn = document.createElement('div');
                btn.className = 'pattern-option';
                const span = document.createElement('span');
                span.textContent = opt;
                btn.appendChild(span);

                btn.onclick = function () {
                    // Si ya hemos validado, que no haga nada
                    if (btn.classList.contains('correct') || btn.classList.contains('incorrect')) {
                        return;
                    }

                    if (opt === pattern.answer) {
                        // Correcto
                        btn.classList.add('correct');
                        if (msgDiv) {
                            msgDiv.textContent = '¡Correcto! Buen trabajo.';
                            msgDiv.classList.remove('err');
                            msgDiv.classList.add('ok');
                        }

                        // Parar tiempo y guardar resultado (100 puntos)
                        detenerTemporizador();
                        const tiempoFinal = segundos;
                        const puntuacion = 100;
                        guardarResultadoRazonamiento(puntuacion, tiempoFinal);

                        // Mostrar nuevo ejercicio tras una pequeña pausa
                        setTimeout(() => {
                            iniciarTemporizador();
                            loadReasoningGame(area);
                        }, 1200);

                    } else {
                        // Incorrecto
                        btn.classList.add('incorrect');
                        if (msgDiv) {
                            msgDiv.textContent = 'Incorrecto, inténtalo de nuevo.';
                            msgDiv.classList.remove('ok');
                            msgDiv.classList.add('err');
                        }
                    }
                };

                options.appendChild(btn);
            });

            area.appendChild(options);
        }

        // Iniciar juego al cargar la página
        document.addEventListener("DOMContentLoaded", function () {
            const area = document.getElementById("zona-razonamiento");
            iniciarTemporizador();
            loadReasoningGame(area);
        });
    </script>

</body>

</html>