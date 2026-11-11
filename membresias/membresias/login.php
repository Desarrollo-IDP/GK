<?php
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

$errores = [];
$mensaje = '';

// Verificar mensaje de logout exitoso
if (isset($_GET['msg']) && $_GET['msg'] === 'logout_success') {
    $mensaje = 'Sesión cerrada correctamente.';
}

// Si viene con token de primer acceso
$token = $_GET['token'] ?? '';
$mid = isset($_GET['mid']) ? intval($_GET['mid']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    
    if ($accion === 'login') {
        $password = $_POST['password'] ?? '';
        
        try {
            // Verificar que la conexión a la base de datos esté funcionando
            if (!$pdo) {
                throw new Exception('Error de conexión a la base de datos');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE correo = ? LIMIT 1");
            $stmt->execute([$correo]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                $errores[] = 'Credenciales inválidas.';
            } elseif (empty($cliente['password_hash'])) {
                $errores[] = 'Credenciales inválidas.';
            } elseif (!password_verify($password, $cliente['password_hash'])) {
                $errores[] = 'Credenciales inválidas.';
            } else {
                // Crear la sesión del cliente
                $_SESSION['cliente'] = [
                    'id' => $cliente['id'],
                    'correo' => $cliente['correo'],
                    'nombre' => $cliente['nombre']
                ];
                
                // Debug: verificar que la sesión se haya creado
                error_log("DEBUG: Login exitoso para usuario: " . $cliente['correo']);
                error_log("DEBUG: Session data: " . print_r($_SESSION, true));
                
                // Verificar que la sesión se haya creado correctamente
                if (isset($_SESSION['cliente'])) {
                    // Redireccionar al dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $errores[] = 'Error al crear la sesión.';
                }
            }
        } catch (Throwable $e) {
            $errores[] = 'Error al iniciar sesión: ' . $e->getMessage();
            error_log('Error en login: ' . $e->getMessage());
        }
    } elseif ($accion === 'set_password') {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $correo = trim($_POST['correo'] ?? '');
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE correo = ? LIMIT 1");
            $stmt->execute([$correo]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                $errores[] = 'Correo no encontrado.';
            } elseif ($password === '' || $password !== $confirm) {
                $errores[] = 'Las contraseñas no coinciden.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $upd = $pdo->prepare("UPDATE clientes SET password_hash = ?, must_set_password = 0, first_login_token = NULL, first_login_expires_at = NULL WHERE id = ?");
                $upd->execute([$hash, $cliente['id']]);
                
                // Crear la sesión del cliente
                $_SESSION['cliente'] = [
                    'id' => $cliente['id'],
                    'correo' => $cliente['correo'],
                    'nombre' => $cliente['nombre']
                ];
                
                // Debug: verificar que la sesión se haya creado
                error_log("DEBUG: Password set exitoso para usuario: " . $cliente['correo']);
                error_log("DEBUG: Session data: " . print_r($_SESSION, true));
                
                // Verificar que la sesión se haya creado correctamente
                if (isset($_SESSION['cliente'])) {
                    // Redireccionar al dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $errores[] = 'Error al crear la sesión.';
                }
            }
        } catch (Throwable $e) {
            $errores[] = 'Error al establecer contraseña: ' . $e->getMessage();
            error_log('Error en set_password: ' . $e->getMessage());
        }
    }
}

// Si viene mid y el cliente aún no tiene password, mostrar set password guiado por correo
$showSetPassword = false;
$correoPrefill = '';
if (!empty($mid)) {
    try {
        $q = $pdo->prepare("SELECT c.* FROM membresias m JOIN clientes c ON m.cliente_id = c.id WHERE m.id = ? LIMIT 1");
        $q->execute([$mid]);
        if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $correoPrefill = $row['correo'] ?? '';
            if (empty($row['password_hash']) || (int)$row['must_set_password'] === 1) {
                $showSetPassword = true;
            }
        }
    } catch (Throwable $e) {
        $errores[] = 'Error al preparar el acceso: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Clientes - Acceso</title>
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

        .login-container {
            background: var(--white-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
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

        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white-color);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .logo-container {
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .login-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .login-subtitle {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-container {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
            background: #f8f9fa;
            color: var(--dark-color);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--white-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 18px;
            transition: var(--transition);
        }

        .form-control:focus + .input-icon {
            color: var(--primary-color);
        }

        .btn {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: var(--white-color);
            border-left: 4px solid #c92a2a;
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #45a049 100%);
            color: var(--white-color);
            border-left: 4px solid #2e7d32;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert li {
            margin-bottom: 5px;
        }

        .alert li:last-child {
            margin-bottom: 0;
        }

        .welcome-message {
            text-align: center;
            margin-bottom: 30px;
            color: var(--dark-color);
        }

        .welcome-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .welcome-text {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }

        .security-notice {
            text-align: center;
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
            .login-container {
                margin: 10px;
                border-radius: 15px;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-body {
                padding: 30px 20px;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .login-title {
                font-size: 24px;
            }

            .form-control {
                padding: 14px 18px 14px 45px;
                font-size: 16px;
            }

            .input-icon {
                left: 15px;
                font-size: 16px;
            }
        }

        /* Animaciones adicionales */
        .form-group {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Efectos de hover en inputs */
        .input-container:hover .form-control {
            border-color: #adb5bd;
            transform: translateY(-1px);
        }

        /* Loading state */
        .btn.loading {
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <h1 class="login-title">Portal de Clientes</h1>
                <p class="login-subtitle">Acceso seguro a tu membresía</p>
            </div>
        </div>

        <div class="login-body">
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Error:</strong>
                        <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showSetPassword): ?>
                <div class="welcome-message">
                    <div class="welcome-title">
                        <i class="fas fa-user-plus"></i>
                        Configurar tu cuenta
                    </div>
                    <div class="welcome-text">
                        Es la primera vez que accedes. Por favor, crea una contraseña segura para tu cuenta.
                    </div>
                </div>
                
                <form method="post" id="setupForm">
                    <input type="hidden" name="accion" value="set_password">
                    
                    <div class="form-group">
                        <label class="form-label">Correo electrónico</label>
                        <div class="input-container">
                            <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($correoPrefill) ?>" required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Crear contraseña</label>
                        <div class="input-container">
                            <input type="password" name="password" class="form-control" id="password" required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmar contraseña</label>
                        <div class="input-container">
                            <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                            <i class="fas fa-check-circle input-icon"></i>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-save"></i>
                        Guardar y continuar
                    </button>
                </form>
            <?php else: ?>
                <div class="welcome-message">
                    <div class="welcome-title">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar sesión
                    </div>
                    <div class="welcome-text">
                        Ingresa tus credenciales para acceder a tu membresía
                    </div>
                </div>
                
                <form method="post" id="loginForm">
                    <input type="hidden" name="accion" value="login">
                    
                    <div class="form-group">
                        <label class="form-label">Correo electrónico</label>
                        <div class="input-container">
                            <input type="email" name="correo" class="form-control" required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-container">
                            <input type="password" name="password" class="form-control" required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar sesión
                    </button>
                </form>
            <?php endif; ?>

            <div class="security-notice">
                <i class="fas fa-shield-alt"></i>
                <small>
                    <strong>Tu información está protegida</strong><br>
                    Conexión encriptada SSL • Datos seguros
                </small>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Efectos de focus en los inputs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                    this.parentElement.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // Validación en tiempo real para confirmar contraseña
            const passwordInput = document.querySelector('#password');
            const confirmInput = document.querySelector('#confirmPassword');
            
            if (passwordInput && confirmInput) {
                function validatePassword() {
                    if (confirmInput.value && passwordInput.value !== confirmInput.value) {
                        confirmInput.setCustomValidity('Las contraseñas no coinciden');
                        confirmInput.style.borderColor = 'var(--danger-color)';
                    } else {
                        confirmInput.setCustomValidity('');
                        confirmInput.style.borderColor = '';
                    }
                }
                
                passwordInput.addEventListener('input', validatePassword);
                confirmInput.addEventListener('input', validatePassword);
            }

            // Loading state para formularios
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Procesando...';
                    }
                });
            });

            // Animación de entrada para elementos
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.form-group, .alert, .welcome-message').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
