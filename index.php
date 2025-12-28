<?php
// Splash screen con animación antes de redirigir a la app principal
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GenWeb - Simulador de Genética</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e2228 0%, #181b20 50%, #1e2228 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }
        
        .splash-container {
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1s ease-out 0.3s forwards;
        }
        
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        .logo-container img {
            height: 120px;
            width: auto;
            opacity: 0;
            transform: scale(0.8);
        }
        
        .logo-container img:nth-child(1) {
            animation: popIn 0.6s ease-out 0.8s forwards;
        }
        .logo-container img:nth-child(2) {
            animation: popIn 0.6s ease-out 1.1s forwards;
        }
        .logo-container img:nth-child(3) {
            animation: popIn 0.6s ease-out 1.4s forwards;
        }
        
        .app-title {
            color: #e6edf3;
            font-size: 2.5rem;
            font-weight: 300;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 1.8s forwards;
        }
        
        .app-subtitle {
            color: #5c9eff;
            font-size: 1.1rem;
            font-weight: 400;
            letter-spacing: 0.1em;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 2.1s forwards;
        }
        
        .loading-bar {
            width: 200px;
            height: 3px;
            background: #2d333b;
            border-radius: 3px;
            margin: 2.5rem auto 0;
            overflow: hidden;
            opacity: 0;
            animation: fadeIn 0.5s ease-out 2.5s forwards;
        }
        
        .loading-bar::after {
            content: '';
            display: block;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, #5c9eff, #34d399);
            border-radius: 3px;
            animation: loadProgress 1.5s ease-in-out 2.8s forwards;
        }
        
        .enter-hint {
            color: #768390;
            font-size: 0.85rem;
            margin-top: 1.5rem;
            opacity: 0;
            animation: fadeIn 0.5s ease-out 3s forwards, pulse 2s ease-in-out 3.5s infinite;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        @keyframes popIn {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes loadProgress {
            to { width: 100%; }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: scale(1.05);
            }
        }
        
        .fade-out {
            animation: fadeOut 0.5s ease-in forwards;
        }
    </style>
</head>
<body>
    <div class="splash-container" id="splash">
        <div class="logo-container">
            <img src="public/css/GW200.png" alt="GenWeb Logo">
            <img src="public/css/calc.png" alt="Calculadora">
            <img src="public/css/hist.png" alt="Histograma">
        </div>
        <h1 class="app-title">GenWeb</h1>
        <p class="app-subtitle">Simulador de Cruzamientos Genéticos</p>
        <div class="loading-bar"></div>
        <p class="enter-hint">Cargando aplicación...</p>
    </div>
    
    <script>
        // Redirigir automáticamente después de la animación
        setTimeout(function() {
            document.getElementById('splash').classList.add('fade-out');
            setTimeout(function() {
                window.location.href = 'public/index.php<?php echo isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>';
            }, 500);
        }, 4500);
        
        // También permitir click para entrar más rápido
        document.body.addEventListener('click', function() {
            document.getElementById('splash').classList.add('fade-out');
            setTimeout(function() {
                window.location.href = 'public/index.php<?php echo isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>';
            }, 400);
        });
    </script>
</body>
</html>
