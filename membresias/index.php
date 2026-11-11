<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

initConfig();
// Asegurar columnas para Mercado Pago si es la primera vez
if (isset($pdo)) {
    ensureMpColumns($pdo);
}

// Headers para prevenir cache en la página de login
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Si ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario && $password) {
        // Buscar usuario en la base de datos
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ? LIMIT 1');
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            // Autenticación exitosa
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'usuario' => $user['usuario'],
                'rol_id' => $user['rol_id'],
                'empresa_id' => $user['empresa_id']
            ];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Por favor, ingresa usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema de Membresías</title>
    <link rel="stylesheet" href="membresias/css/modern-design-system.css">
    
    <!-- Script para prevenir navegación hacia atrás -->
    <script>
    // Prevenir navegación hacia atrás
    window.history.forward();
    
    // Limpiar cualquier dato de sesión en el navegador
    localStorage.clear();
    sessionStorage.clear();
    
    // Prevenir que se pueda navegar hacia atrás
    window.onload = function() {
        if (window.history && window.history.pushState) {
            window.history.pushState('forward', null, './#forward');
            window.onpopstate = function () {
                window.history.pushState('forward', null, './#forward');
            };
        }
    };
    </script>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar sesión</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autofocus>
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
    <style>
        .login-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .login-form label {
            display: block;
            margin-top: 1rem;
        }
        .login-form input {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .login-form button {
            margin-top: 1.5rem;
            width: 100%;
            padding: 0.75rem;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .error-message {
            background: #ffe0e0;
            color: #c00;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</body>
</html> 