<?php
// Incluir configuración de base de datos
include '../includes/db.php';
include '../includes/config.php';

initConfig();

// Verificar si el cliente está logueado
if (!isset($_SESSION['cliente'])) {
    header('Location: login.php');
    exit;
}

// Procesar logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $cliente_id = $_SESSION['cliente']['id'] ?? null;
    
    // Registrar el logout en el log si es necesario
    if ($cliente_id) {
        error_log("Cliente ID {$cliente_id} cerró sesión - " . date('Y-m-d H:i:s'));
    }
    
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Si se usan cookies de sesión, también eliminarlas
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Redireccionar al login con mensaje de éxito
    header('Location: login.php?msg=logout_success');
    exit;
} else {
    // Si no es POST o GET, redireccionar al dashboard
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesión cerrada - Portal de Clientes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #f44336;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --white-color: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
            --border-radius: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animación de fondo */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .logout-container {
            background: var(--white-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
            text-align: center;
            padding: 40px 30px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success-color) 0%, #45a049 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 40px;
            color: var(--white-color);
            box-shadow: var(--shadow);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: var(--shadow);
            }
            50% {
                transform: scale(1.05);
                box-shadow: var(--shadow-hover);
            }
            100% {
                transform: scale(1);
                box-shadow: var(--shadow);
            }
        }

        .logout-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .logout-message {
            font-size: 16px;
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white-color);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            color: var(--white-color);
        }

        .security-notice {
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }

        .security-notice i {
            color: var(--success-color);
            font-size: 20px;
            margin-bottom: 10px;
        }

        .security-notice small {
            color: #6c757d;
            font-size: 12px;
            display: block;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .logout-container {
                margin: 10px;
                border-radius: 15px;
                padding: 30px 20px;
            }

            .logout-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }

            .logout-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1 class="logout-title">Sesión cerrada</h1>
        <p class="logout-message">
            Has cerrado sesión exitosamente. Tu información está segura y protegida.
        </p>
        
        <a href="login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i>
            Iniciar sesión nuevamente
        </a>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <small>
                <strong>Sesión terminada de forma segura</strong><br>
                Todos los datos temporales han sido eliminados
            </small>
        </div>
    </div>

    <script>
        // Redirección automática después de 5 segundos
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 5000);

        // Contador regresivo
        let countdown = 5;
        const countdownElement = document.createElement('div');
        countdownElement.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.9);
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-color);
            box-shadow: var(--shadow);
            z-index: 1000;
        `;
        document.body.appendChild(countdownElement);

        const updateCountdown = () => {
            countdownElement.textContent = `Redirigiendo en ${countdown}...`;
            countdown--;
            if (countdown >= 0) {
                setTimeout(updateCountdown, 1000);
            }
        };
        updateCountdown();
    </script>
</body>
</html>