<?php
require_once __DIR__ . '/auth.php';
checkAuth();

require_once __DIR__ . '/Inventario/crud/includes/db.php';

$message = '';
$error = '';

// Procesar el formulario de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } elseif (strlen($new_password) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        // Verificar la contraseña actual
        $user_id = $_SESSION['user']['id'];
        $stmt = $conn->prepare("SELECT contraseña FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($current_password, $user['contraseña'])) {
            // Actualizar la contraseña
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $message = 'Contraseña actualizada correctamente.';
            } else {
                $error = 'Error al actualizar la contraseña.';
            }
            $stmt->close();
        } else {
            $error = 'La contraseña actual es incorrecta.';
        }
    }
}

// Obtener datos del usuario actual
$user_id = $_SESSION['user']['id'];
$stmt = $conn->prepare("SELECT nombre_usuario, rol, email, fecha_creacion FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #1E2A3B;
            --secondary-color: #2C3E50;
            --white: #ffffff;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            overflow-x: hidden;
        }

        .container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2rem;
            width: 100%;
            max-width: 600px;
        }
        
        .header h1 {
            font-size: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .header h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .header .user-info {
            color: #666;
            font-size: 0.9rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
            margin-left: 1rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .user-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--primary-color);
        }

        .detail-value {
            color: #666;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .back-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .back-btn i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        /* Responsive para desktop grande */
        @media (min-width: 1441px) {
            .container {
                max-width: 700px;
                padding: 2.5rem;
            }
            
            .header h1 {
                font-size: 2.25rem;
            }
            
            .form-section h2 {
                font-size: 1.4rem;
            }
            
            .form-group input {
                padding: 0.875rem;
                font-size: 1.05rem;
            }
            
            .btn {
                padding: 0.875rem 2.25rem;
                font-size: 1.05rem;
            }
        }
        
        /* Responsive para tablet */
        @media (max-width: 1024px) {
            body {
                padding: 1.5rem;
            }
            
            .container {
                max-width: 550px;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
        }
        
        /* Responsive para tablet pequeña */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .container {
                padding: 1.5rem;
                max-width: 100%;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .form-section h2 {
                font-size: 1.1rem;
            }

            .back-btn {
                top: 15px;
                left: 15px;
                width: 50px;
                height: 50px;
            }

            .back-btn i {
                font-size: 1.2rem;
            }

            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .form-group input {
                padding: 0.65rem;
                font-size: 0.95rem;
            }
            
            .btn {
                padding: 0.65rem 1.5rem;
                font-size: 0.95rem;
            }
        }
        
        /* Responsive para móvil */
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }
            
            .container {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .header {
                margin-bottom: 1.5rem;
                padding-bottom: 0.75rem;
            }
            
            .header h1 {
                font-size: 1.25rem;
            }
            
            .header .user-info {
                font-size: 0.8rem;
            }
            
            .form-section h2 {
                font-size: 1rem;
            }
            
            .form-group {
                margin-bottom: 0.75rem;
            }
            
            .form-group label {
                font-size: 0.875rem;
            }
            
            .form-group input {
                padding: 0.6rem;
                font-size: 0.875rem;
            }
            
            .btn {
                padding: 0.6rem 1.25rem;
                font-size: 0.875rem;
                width: 100%;
                margin: 0.5rem 0;
            }
            
            .btn-secondary {
                margin-left: 0;
            }
            
            .back-btn {
                top: 10px;
                left: 10px;
                width: 45px;
                height: 45px;
            }
            
            .back-btn i {
                font-size: 1rem;
            }
            
            .user-details {
                padding: 0.75rem;
            }
            
            .detail-row {
                padding: 0.4rem 0;
                font-size: 0.875rem;
            }
        }
        
        /* Responsive para móvil muy pequeño */
        @media (max-width: 320px) {
            body {
                padding: 0.25rem;
            }
            
            .container {
                padding: 0.75rem;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
            
            .form-section h2 {
                font-size: 0.95rem;
            }
            
            .form-group input {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .back-btn {
                width: 40px;
                height: 40px;
            }
            
            .back-btn i {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="window.location.href='<?php echo htmlspecialchars(appUrl('/direc.php'), ENT_QUOTES, 'UTF-8'); ?>'" title="Volver">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user"></i> Mi Perfil</h1>
            <div class="user-info">Información de tu cuenta</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="user-details">
            <div class="detail-row">
                <span class="detail-label">Nombre de usuario:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user_data['nombre_usuario']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Rol:</span>
                <span class="detail-value"><?php echo htmlspecialchars(ucfirst($user_data['rol'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user_data['email'] ?: 'No especificado'); ?></span>
            </div>
            
        </div>

        <div class="form-section">
            <h2><i class="fas fa-lock"></i> Cambiar Contraseña</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Contraseña actual:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva contraseña:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>

                <button type="submit" name="change_password" class="btn">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
                
                <a href="<?php echo htmlspecialchars(appUrl('/direc.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </form>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas nuevas no coinciden.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('La nueva contraseña debe tener al menos 6 caracteres.');
                return false;
            }
        });

        // Limpiar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
