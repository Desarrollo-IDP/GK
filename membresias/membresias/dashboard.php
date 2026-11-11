<?php
// Incluir configuración de base de datos
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

// Verificar si el cliente está logueado
if (!isset($_SESSION['cliente'])) {
    header('Location: login.php');
    exit;
}

$cliente = $_SESSION['cliente'];

try {
    // Primero obtener la información del cliente
    $stmt = $pdo->prepare("
        SELECT 
            c.nombre as cliente_nombre,
            c.apellido_paterno,
            c.apellido_materno,
            c.correo as cliente_email,
            c.telefono as cliente_telefono,
            c.mp_card_token,
            c.foto as cliente_foto
        FROM clientes c
        WHERE c.id = ?
    ");
    $stmt->execute([$cliente['id']]);
    $cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener datos reales de la membresía del cliente
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.cliente_id,
            m.empresa_id,
            m.tipo_membresia_id,
            m.fecha_inicio,
            m.fecha_fin,
            m.metodo_pago,
            m.mp_status as estado,
            m.mp_subscription_id,
            m.mp_payment_method,
            m.mp_last_payment_date,
            m.mp_next_payment_date,
            m.mp_cancellation_fee,
            m.mp_cancelled_at,
            m.mp_cancellation_reason,
            m.created_at,
            tm.nombre as plan_nombre,
            tm.descripcion,
            tm.precio,
            tm.duracion_dias,
            tm.interval,
            tm.interval_count,
            tm.monto_cancelacion,
            tm.mp_plan_id,
            e.nombre as empresa_nombre
        FROM membresias m
        LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
        LEFT JOIN empresas e ON m.empresa_id = e.id
        WHERE m.cliente_id = ? AND m.mp_status IN ('pending', 'authorized', 'paused')
        ORDER BY m.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$cliente['id']]);
    $membresia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Combinar la información del cliente con la membresía
    if ($membresia) {
        $membresia = array_merge($cliente_info, $membresia);
    }
    
    // Verificar si tiene método de pago registrado (token de tarjeta)
    $hasPaymentMethod = !empty($membresia['mp_card_token']);
    
    // Si no tiene membresía, crear una por defecto
    if (!$membresia) {
        // Buscar un plan por defecto (activo)
        $stmt = $pdo->prepare("SELECT id, nombre, precio, duracion_dias, mp_plan_id FROM tipos_membresia WHERE is_active = 1 ORDER BY precio ASC LIMIT 1");
        $stmt->execute();
        $plan_default = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plan_default) {
            $membresia = array_merge($cliente_info, [
                'id' => null, // No hay membresía creada aún
                'plan_nombre' => $plan_default['nombre'],
                'precio' => $plan_default['precio'],
                'estado' => 'sin_membresia',
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'empresa_nombre' => 'Empresa por defecto',
                'duracion_dias' => $plan_default['duracion_dias'],
                'monto_cancelacion' => 0.00,
                'tipo_membresia_id' => $plan_default['id'],
                'mp_plan_id' => $plan_default['mp_plan_id']
            ]);
        } else {
            // Si no hay planes activos, buscar cualquier plan
            $stmt = $pdo->prepare("SELECT id, nombre, precio, duracion_dias, mp_plan_id FROM tipos_membresia ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $plan_default = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($plan_default) {
                                $membresia = array_merge($cliente_info, [
                    'id' => null,
                    'plan_nombre' => $plan_default['nombre'] . ' (Inactivo)',
                    'precio' => $plan_default['precio'],
                    'estado' => 'sin_membresia',
                    'fecha_inicio' => null,
                    'fecha_fin' => null,
                    'empresa_nombre' => 'Empresa por defecto',
                    'duracion_dias' => $plan_default['duracion_dias'],
                    'monto_cancelacion' => 0.00,
                    'tipo_membresia_id' => $plan_default['id']
                ]);
            } else {
                // Si no hay planes en absoluto
                $membresia = array_merge($cliente_info, [
                    'id' => null,
                    'plan_nombre' => 'Sin membresía activa',
                    'precio' => 0.00,
                    'estado' => 'sin_membresia',
                    'fecha_inicio' => null,
                    'fecha_fin' => null,
                    'empresa_nombre' => 'Empresa por defecto',
                    'duracion_dias' => 0,
                    'monto_cancelacion' => 0.00,
                    'tipo_membresia_id' => null,
                    'mp_plan_id' => null
                ]);
            }
        }
    }
    
} catch (PDOException $e) {
    // En caso de error, usar datos por defecto
    $membresia = array_merge($cliente_info, [
        'plan_nombre' => 'Sin membresía activa',
        'precio' => 0.00,
        'estado' => 'sin_membresia',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'empresa_nombre' => 'Empresa por defecto',
        'duracion_dias' => 0,
        'monto_cancelacion' => 0.00,
        'mp_plan_id' => null
    ]);
    $hasPaymentMethod = false;
    error_log("Error en dashboard: " . $e->getMessage());
}

// Procesar cambio de contraseña
if ($_POST['accion'] ?? '' === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password === $confirm_password) {
        try {
            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password_hash FROM clientes WHERE id = ?");
            $stmt->execute([$cliente['id']]);
            $cliente_db = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $cliente_db['password_hash'])) {
                // Actualizar contraseña
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE clientes SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $cliente['id']]);
                
                $mensaje = "Contraseña actualizada correctamente";
            } else {
                $error = "La contraseña actual es incorrecta";
            }
        } catch (PDOException $e) {
            $error = "Error al actualizar la contraseña";
            error_log("Error cambio contraseña: " . $e->getMessage());
        }
    } else {
        $error = "Las contraseñas no coinciden";
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard de Membresías</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/membresias.css" rel="stylesheet">
    <script src="qrcode-local.js"></script>
    <script>
        // Verificar si QRCode se cargó correctamente
        window.addEventListener('load', function() {
            if (typeof QRCode !== 'undefined') {
                console.log('QRCode local cargado correctamente');
            } else {
                console.error('QRCode local no se pudo cargar');
            }
        });
    </script>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        /* Animación de fondo */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23667eea" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="%23764ba2" opacity="0.05"/><circle cx="50" cy="10" r="0.5" fill="%23667eea" opacity="0.03"/><circle cx="10" cy="60" r="0.5" fill="%23764ba2" opacity="0.03"/><circle cx="90" cy="40" r="0.5" fill="%23667eea" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
            z-index: -1;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: var(--border-radius);
            padding: 30px;
            color: var(--white-color);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .dashboard-header::before {
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

        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-section h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-section p {
            font-size: 16px;
            opacity: 0.9;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.5s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #45a049 100%);
            color: var(--white-color);
            border-left: 4px solid #2e7d32;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: var(--white-color);
            border-left: 4px solid #c92a2a;
        }

        /* Grid de tarjetas */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Tarjeta de membresía */
        .membership-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: var(--border-radius);
            padding: 30px;
            color: var(--white-color);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .membership-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-status {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            backdrop-filter: blur(10px);
        }

        .status-pending {
            background: linear-gradient(135deg, var(--warning-color) 0%, #F57C00 100%);
        }

        .status-authorized {
            background: linear-gradient(135deg, var(--success-color) 0%, #45a049 100%);
        }

        .status-cancelled {
            background: linear-gradient(135deg, var(--danger-color) 0%, #d32f2f 100%);
        }

        .status-paused {
            background: linear-gradient(135deg, #9E9E9E 0%, #757575 100%);
        }

        .status-sin_membresia {
            background: linear-gradient(135deg, #607D8B 0%, #455A64 100%);
        }

        .card-details {
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            opacity: 0.8;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            font-weight: 600;
            font-size: 14px;
        }

        .card-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .btn {
            flex: 1;
            min-width: 140px;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        .btn-payment {
            background: rgba(255,255,255,0.2);
            color: var(--white-color);
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }

        .btn-payment:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn-activate {
            background: linear-gradient(45deg, var(--success-color), #45a049);
            color: var(--white-color);
        }

        .btn-activate:hover {
            background: linear-gradient(45deg, #45a049, var(--success-color));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .btn-activate:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #45a049) !important;
            color: var(--white-color) !important;
            cursor: default !important;
        }

        .btn-success:hover {
            background: linear-gradient(45deg, var(--success-color), #45a049) !important;
            transform: none !important;
            box-shadow: none !important;
        }

        /* Tarjeta de QR */
        .qr-card {
            background: var(--white-color);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            display: none;
            animation: slideInUp 0.6s ease-out 0.2s both;
        }
        
        /* QR Section (como en la carpeta de prueba) */
        .qr-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: none;
        }

        .qr-card h3 {
            color: var(--dark-color);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .qr-card p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .qr-container {
            margin: 25px 0;
            display: flex;
            justify-content: center;
        }
        
        .qr-container svg {
            max-width: 200px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .qr-container svg {
            max-width: 200px;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: var(--white-color);
            padding: 20px;
        }

        .subscription-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white-color);
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .subscription-link:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            color: var(--white-color);
            text-decoration: none;
        }

        .subscription-link:active {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Botones flotantes */
        .floating-buttons {
            position: fixed;
            top: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }

        .settings-btn, .logout-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white-color);
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: none;
            font-size: 20px;
        }

        .settings-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .settings-btn:hover {
            transform: rotate(90deg) scale(1.1);
            box-shadow: var(--shadow-hover);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--danger-color), #d32f2f);
        }

        .logout-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-hover);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--white-color);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: var(--shadow-hover);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #f0f0f0;
            color: var(--dark-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--white-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-secondary {
            background: #6c757d;
            color: var(--white-color);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: var(--white-color);
            transform: translateY(-2px);
        }

        /* Modal de Pago */
        .payment-modal {
            max-width: 600px;
        }

        .payment-info {
            text-align: center;
        }

        .plan-details {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }

        .price-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .price {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .period {
            font-size: 14px;
            color: #6c757d;
        }

        .qr-section {
            margin: 25px 0;
        }

        .qr-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            min-height: 220px;
            align-items: center;
        }

        .qr-container img {
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: 3px solid #fff;
            transition: transform 0.3s ease;
        }

        .qr-container img:hover {
            transform: scale(1.05);
        }

        .qr-container-modal {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }

        .qr-code svg {
            display: block;
            border-radius: 8px;
        }

        .payment-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .payment-actions .btn {
            min-width: 150px;
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 20px;
                margin-bottom: 20px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .welcome-section h1 {
                font-size: 24px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .membership-card {
                padding: 20px;
            }

            .card-actions {
                flex-direction: column;
            }

            .btn {
                min-width: auto;
            }

            .floating-buttons {
                top: 20px;
                right: 20px;
                gap: 10px;
            }

            .settings-btn, .logout-btn {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }

            .modal-content {
                margin: 20px;
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }
            
            .payment-actions {
                flex-direction: column;
            }
            
            .payment-actions .btn {
                min-width: auto;
            }
            
            .price {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .dashboard-header {
                border-radius: 15px;
            }

            .membership-card {
                border-radius: 15px;
            }

            .qr-card {
                border-radius: 15px;
            }
        }

        /* Estilos para el avatar del usuario */
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .user-avatar .client-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-avatar i {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <!-- Botones flotantes -->
    <div class="floating-buttons">
        <button class="settings-btn" id="settingsBtn" title="Configuración">
            <i class="fas fa-cog"></i>
        </button>
        <button class="logout-btn" id="logoutBtn" title="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
        </button>
    </div>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1><i class="fas fa-id-card"></i> Dashboard de Membresías</h1>
                    <p>Bienvenido, <?= htmlspecialchars($membresia['cliente_nombre'] . ' ' . $membresia['apellido_paterno'] . ' ' . $membresia['apellido_materno']) ?></p>
                </div>
                <div class="user-avatar">
                    <?php if (!empty($membresia['cliente_foto'])): ?>
                        <img src="../<?= htmlspecialchars($membresia['cliente_foto']) ?>" alt="Foto del cliente" class="client-photo" onerror="console.log('Error cargando imagen:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-user" style="display: none;"></i>
                        <!-- Debug: <?= htmlspecialchars($membresia['cliente_foto']) ?> -->
                        <!-- Ruta completa: ../<?= htmlspecialchars($membresia['cliente_foto']) ?> -->
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                        <!-- Debug: No hay foto - <?= var_export($membresia['cliente_foto'], true) ?> -->
                    <?php endif; ?>
                </div>
                <!-- Debug completo: <?= var_export($membresia, true) ?> -->
            </div>
        </div>

        <!-- Alertas -->
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Grid de tarjetas -->
        <div class="dashboard-grid">
            <!-- Tarjeta de Membresía -->
            <div class="membership-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-id-card"></i>
                        <?= htmlspecialchars($membresia['plan_nombre']) ?>
                    </h3>
                    <div class="card-status status-<?= $membresia['estado'] ?>">
                        <?php 
                        $estado_texto = [
                            'pending' => 'Pendiente',
                            'authorized' => 'Autorizada',
                            'paused' => 'Pausada',
                            'cancelled' => 'Cancelada',
                            'sin_membresia' => 'Sin membresía'
                        ];
                        echo $estado_texto[$membresia['estado']] ?? ucfirst($membresia['estado']);
                        ?>
                    </div>
                </div>

                <div class="card-details">
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-tag"></i>
                            Plan
                        </span>
                        <span class="detail-value"><?= htmlspecialchars($membresia['plan_nombre']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-dollar-sign"></i>
                            Precio
                        </span>
                        <span class="detail-value">$<?= number_format($membresia['precio'], 2) ?> MXN</span>
                    </div>
                    <?php if ($membresia['fecha_inicio']): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-calendar-plus"></i>
                            Inicio
                        </span>
                        <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['fecha_inicio'])) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-calendar-minus"></i>
                            Fin
                        </span>
                        <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['fecha_fin'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-building"></i>
                            Empresa
                        </span>
                        <span class="detail-value"><?= htmlspecialchars($membresia['empresa_nombre']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-clock"></i>
                            Duración
                        </span>
                        <span class="detail-value"><?= $membresia['duracion_dias'] ?> días</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-credit-card"></i>
                            Método de pago
                        </span>
                        <span class="detail-value">
                            <?php if ($hasPaymentMethod): ?>
                                <i class="fas fa-check-circle" style="color: #4CAF50;"></i> Configurado
                            <?php else: ?>
                                <i class="fas fa-times-circle" style="color: #FF9800;"></i> No configurado
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($membresia['monto_cancelacion'] > 0): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-exclamation-triangle"></i>
                            Cargo por cancelación
                        </span>
                        <span class="detail-value">$<?= number_format($membresia['monto_cancelacion'], 2) ?> MXN</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($membresia['mp_cancelled_at']): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-times-circle"></i>
                            Cancelada el
                        </span>
                        <span class="detail-value"><?= date('d/m/Y H:i', strtotime($membresia['mp_cancelled_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <button class="btn btn-payment" id="btnPayment">
                        <i class="fas fa-credit-card"></i>
                        Método de Pago
                    </button>
                    <?php if ($membresia['estado'] !== 'sin_membresia'): ?>
                    <button class="btn btn-activate" id="btnActivate" <?= !$hasPaymentMethod ? 'disabled' : '' ?>>
                        <i class="fas fa-play"></i>
                        <?= $membresia['estado'] === 'authorized' ? 'Tarjeta Activada' : 'Activar Tarjeta' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Section -->
            <!-- <div class="qr-card" id="qrSection" style="display: none;">
                <h3>
                    <i class="fas fa-qrcode"></i>
                    Código QR de Acceso
                </h3>
                <p>Escanea este código para acceder a tu membresía</p>
                
                <div class="qr-container" id="qrContainer"> -->
                    <!-- El QR se generará dinámicamente cuando se active la membresía -->
                <!-- </div>
                
                <a href="#" class="subscription-link" id="subscriptionLink" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    Suscripción Mercado Pago
                </a>
                
                <div class="qr-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #667eea;">
                    <h5 style="margin: 0 0 10px 0; color: #667eea;">
                        <i class="fas fa-info-circle"></i>
                        Información del QR
                    </h5>
                    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px;">
                        <li>Este código QR contiene un enlace único para tu membresía</li>
                        <li>Puedes escanearlo con cualquier aplicación de QR</li>
                        <li>El enlace te llevará directamente a tu página de suscripción</li>
                        <li>Mantén este código seguro y no lo compartas</li>
                    </ul>
                </div>
            </div> -->
        </div>
    </div>

    <!-- Modal de Configuración -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-cog"></i>
                    Configuración
                </h3>
                <button class="modal-close" id="modalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <h5 style="margin-bottom: 20px; color: var(--dark-color);">
                    <i class="fas fa-key"></i>
                    Cambiar Contraseña
                </h5>
                
                <form method="post" id="passwordForm">
                    <input type="hidden" name="accion" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">Contraseña actual</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="btn-group">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i>
                            Actualizar contraseña
                        </button>
                        <button class="btn btn-secondary" type="button" id="cancelBtn">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Pago -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content payment-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-qrcode"></i>
                    Código QR de Acceso
                </h3>
                <button class="modal-close" id="paymentModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="payment-info">
                    <div class="plan-details">
                        <div class="price-info">
                            <div class="price">$<?= number_format($membresia['precio'], 2) ?> MXN</div>
                            <div class="period"><?= htmlspecialchars($membresia['plan_nombre']) ?></div>
                        </div>
                    </div>
                    
                    <p style="color: #666; margin-bottom: 25px;">
                        Escanea este código QR para acceder a tu membresía o haz clic en el enlace de abajo
                    </p>
                    
                    <div class="qr-section">
                        <div class="qr-container" id="modalQrContainer">
                            <!-- El QR se generará dinámicamente -->
                        </div>
                    </div>
                    
                    <div class="payment-actions">
                        <a href="#" class="btn btn-primary" id="modalSubscriptionLink" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                            Ir al Sitio de Pago
                        </a>
                        <button class="btn btn-outline-primary" id="testQRBtn" style="margin: 0 10px;">
                            <i class="fas fa-qrcode"></i>
                            Probar QR
                        </button>
                        <button class="btn btn-secondary" id="closePaymentModal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script>
        // Variables globales
        let isActivated = <?= $membresia['estado'] === 'authorized' ? 'true' : 'false' ?>;
        let hasPaymentMethod = <?= $hasPaymentMethod ? 'true' : 'false' ?>;
        let currentStatus = '<?= $membresia['estado'] ?>';
        
        console.log('Variables globales inicializadas:', {
            isActivated,
            hasPaymentMethod,
            currentStatus
        });
        
        // Función para verificar estado del método de pago
        function checkPaymentMethodStatus() {
            console.log('Verificando estado del método de pago...');
            
            // Verificar si el cliente tiene token de tarjeta
            const clienteId = <?= $cliente['id'] ?>;
            
            fetch('check_payment_method.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        hasPaymentMethod = data.hasPaymentMethod;
                        updatePaymentMethodUI();
                        console.log('Estado del método de pago actualizado:', hasPaymentMethod);
                    } else {
                        console.error('Error en respuesta:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error verificando método de pago:', error);
                });
        }
        
        // Función para actualizar la UI del método de pago
        function updatePaymentMethodUI() {
            const btnActivate = document.getElementById('btnActivate');
            const paymentStatus = document.querySelector('.detail-value:last-child');
            
            console.log('Actualizando UI del método de pago. hasPaymentMethod:', hasPaymentMethod);
            
            if (hasPaymentMethod) {
                if (btnActivate) {
                    btnActivate.disabled = false;
                    btnActivate.title = '';
                    btnActivate.classList.remove('btn-activate:disabled');
                    console.log('Botón activar habilitado');
                }
                if (paymentStatus) {
                    paymentStatus.innerHTML = '<i class="fas fa-check-circle" style="color: #4CAF50;"></i> Configurado';
                }
            } else {
                if (btnActivate) {
                    btnActivate.disabled = true;
                    btnActivate.title = 'Configura un método de pago primero';
                    btnActivate.classList.add('btn-activate:disabled');
                    console.log('Botón activar deshabilitado');
                }
                if (paymentStatus) {
                    paymentStatus.innerHTML = '<i class="fas fa-times-circle" style="color: #FF9800;"></i> No configurado';
                }
            }
            
            // También actualizar el estado visual del botón
            if (btnActivate) {
                if (hasPaymentMethod) {
                    btnActivate.style.opacity = '1';
                    btnActivate.style.cursor = 'pointer';
                } else {
                    btnActivate.style.opacity = '0.6';
                    btnActivate.style.cursor = 'not-allowed';
                }
            }
        }
        
        // Funciones del modal
        function openSettingsModal() {
            const modal = document.getElementById('settingsModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeSettingsModal() {
            const modal = document.getElementById('settingsModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Función para verificar el estado del modal
        function verifyModalState() {
            const modal = document.getElementById('paymentModal');
            const qrContainer = document.getElementById('modalQrContainer');
            const subscriptionLink = document.getElementById('modalSubscriptionLink');
            
            console.log('=== VERIFICANDO ESTADO DEL MODAL ===');
            console.log('Modal:', {
                found: !!modal,
                display: modal ? modal.style.display : 'N/A',
                visible: modal ? modal.offsetWidth > 0 : false
            });
            
            console.log('QR Container:', {
                found: !!qrContainer,
                innerHTML: qrContainer ? qrContainer.innerHTML.substring(0, 100) + '...' : 'N/A',
                dimensions: qrContainer ? `${qrContainer.offsetWidth}x${qrContainer.offsetHeight}` : 'N/A'
            });
            
            console.log('Subscription Link:', {
                found: !!subscriptionLink,
                href: subscriptionLink ? subscriptionLink.href : 'N/A'
            });
            
            return {
                modal: !!modal,
                qrContainer: !!qrContainer,
                subscriptionLink: !!subscriptionLink
            };
        }
        
        // Funciones del modal de pago
        function showPaymentModal(initPoint = null) {
            console.log('=== ABRIENDO MODAL DE PAGO ===');
            console.log('initPoint recibido:', initPoint);
            
            // Verificar estado inicial del modal
            const initialState = verifyModalState();
            console.log('Estado inicial del modal:', initialState);
            
            const modal = document.getElementById('paymentModal');
            const qrContainer = document.getElementById('modalQrContainer');
            const subscriptionLink = document.getElementById('modalSubscriptionLink');
            
            if (!modal) {
                console.error('ERROR: Modal de pago no encontrado');
                return;
            }
            
            if (!qrContainer) {
                console.error('ERROR: Contenedor QR no encontrado en el modal');
                return;
            }
            
            if (!subscriptionLink) {
                console.error('ERROR: Enlace de suscripción no encontrado en el modal');
                return;
            }
            
            // Mostrar el modal
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
            console.log('Modal mostrado correctamente');
            
            // Verificar estado después de mostrar el modal
            setTimeout(() => {
                console.log('Estado del modal después de mostrar:');
                verifyModalState();
                
                console.log('Generando QR en modal después de delay...');
                generateModalQR(initPoint);
            }, 200);
        }
        
        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Función para generar QR en el modal
        function generateModalQR(initPoint = null) {
            const qrContainer = document.getElementById('modalQrContainer');
            const subscriptionLink = document.getElementById('modalSubscriptionLink');
            
            console.log('generateModalQR llamada con initPoint:', initPoint);
            console.log('qrContainer encontrado:', !!qrContainer);
            console.log('subscriptionLink encontrado:', !!subscriptionLink);
            
            if (!qrContainer || !subscriptionLink) {
                console.error('Elementos del modal QR no encontrados');
                return;
            }
            
            // Si tenemos un initPoint real, usarlo directamente
            let subscriptionUrl = initPoint;
            
            // Si no hay initPoint, crear URL única para la suscripción
            if (!subscriptionUrl) {
                subscriptionUrl = createSubscriptionURL();
            }
            
            console.log('URL de suscripción para modal:', subscriptionUrl);
            
            // Verificar si la URL se generó correctamente
            if (!subscriptionUrl) {
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #f44336;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                        <p style="margin-top: 10px;">Error: No se pudo generar la URL de pago</p>
                        <p style="font-size: 12px; margin-top: 5px;">Verifica que tengas un plan de membresía configurado</p>
                    </div>
                `;
                return;
            }
            
            // Actualizar el enlace de suscripción
            subscriptionLink.href = subscriptionUrl;
            
            // Generar QR con método mejorado
            generateModalQRWithFallback(qrContainer, subscriptionUrl);
        }
        
        // Función mejorada para generar QR en el modal con múltiples fallbacks
        function generateModalQRWithFallback(qrContainer, subscriptionUrl) {
            console.log('=== GENERANDO QR EN MODAL CON FALLBACK ===');
            console.log('URL de suscripción:', subscriptionUrl);
            console.log('Contenedor QR:', qrContainer);
            
            if (!qrContainer) {
                console.error('ERROR: Contenedor QR no proporcionado');
                return;
            }
            
            if (!subscriptionUrl) {
                console.error('ERROR: URL de suscripción no proporcionada');
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #f44336;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                        <p style="margin-top: 10px;">Error: URL de suscripción no válida</p>
                    </div>
                `;
                return;
            }
            
            // Mostrar indicador de carga
            qrContainer.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i>
                    <p style="margin-top: 10px; color: #666;">Generando código QR...</p>
                </div>
            `;
            
            console.log('Indicador de carga mostrado, iniciando generación de QR...');
            
            // Método 1: QRServer (más confiable)
            generateQRWithQRServer(qrContainer, subscriptionUrl);
        }
        
        // Función para verificar y corregir el estado del contenedor QR
        function ensureQRContainerVisible(qrContainer) {
            console.log('=== VERIFICANDO VISIBILIDAD DEL CONTENEDOR QR ===');
            
            if (!qrContainer) {
                console.error('Contenedor QR no proporcionado');
                return false;
            }
            
            // Verificar dimensiones
            const width = qrContainer.offsetWidth;
            const height = qrContainer.offsetHeight;
            console.log('Dimensiones del contenedor:', `${width}x${height}`);
            
            // Si el contenedor no tiene dimensiones, forzar el layout
            if (width === 0 || height === 0) {
                console.log('Contenedor sin dimensiones, forzando layout...');
                
                // Forzar el layout
                qrContainer.style.minHeight = '250px';
                qrContainer.style.minWidth = '250px';
                qrContainer.style.display = 'block';
                
                // Trigger reflow
                qrContainer.offsetHeight;
                
                console.log('Dimensiones después de forzar layout:', `${qrContainer.offsetWidth}x${qrContainer.offsetHeight}`);
            }
            
            // Verificar visibilidad
            const computedStyle = window.getComputedStyle(qrContainer);
            const display = computedStyle.display;
            const visibility = computedStyle.visibility;
            const opacity = computedStyle.opacity;
            
            console.log('Estilos del contenedor:', {
                display,
                visibility,
                opacity,
                position: computedStyle.position,
                zIndex: computedStyle.zIndex
            });
            
            return true;
        }
        
        // Método 1: QRServer
        function generateQRWithQRServer(qrContainer, subscriptionUrl) {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(subscriptionUrl)}&format=png&margin=10`;
            
            console.log('Intentando generar QR con QRServer:', qrUrl);
            console.log('Contenedor QR antes de generar:', qrContainer);
            console.log('Contenedor QR innerHTML antes:', qrContainer.innerHTML);
            
            // Verificar y corregir el estado del contenedor
            ensureQRContainerVisible(qrContainer);
            
            const img = document.createElement('img');
            img.alt = 'Código QR de acceso';
            img.style.width = '250px';
            img.style.height = '250px';
            img.style.display = 'block';
            img.style.margin = '0 auto';
            img.style.borderRadius = '12px';
            img.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            img.style.border = '3px solid #fff';
            
            // Agregar estilos adicionales para forzar la visualización
            img.style.minWidth = '250px';
            img.style.minHeight = '250px';
            img.style.maxWidth = '250px';
            img.style.maxHeight = '250px';
            
            img.onload = () => {
                console.log('QR generado exitosamente con QRServer');
                console.log('Imagen cargada:', img);
                console.log('Contenedor antes de limpiar:', qrContainer.innerHTML);
                
                // Limpiar contenedor
                qrContainer.innerHTML = '';
                console.log('Contenedor después de limpiar:', qrContainer.innerHTML);
                
                // Agregar imagen
                qrContainer.appendChild(img);
                console.log('Imagen agregada al contenedor');
                console.log('Contenedor después de agregar imagen:', qrContainer.innerHTML);
                console.log('Hijos del contenedor:', qrContainer.children.length);
                
                // Verificar que la imagen esté visible
                setTimeout(() => {
                    console.log('Verificación final del contenedor:');
                    console.log('innerHTML:', qrContainer.innerHTML);
                    console.log('children:', qrContainer.children);
                    console.log('offsetWidth:', qrContainer.offsetWidth);
                    console.log('offsetHeight:', qrContainer.offsetHeight);
                    
                    // Verificar que la imagen esté realmente visible
                    if (img.offsetWidth > 0 && img.offsetHeight > 0) {
                        console.log('✅ Imagen visible correctamente');
                    } else {
                        console.error('❌ Imagen no visible - dimensiones:', img.offsetWidth, 'x', img.offsetHeight);
                        
                        // Intentar corregir el problema
                        console.log('Intentando corregir imagen no visible...');
                        fixInvisibleImage(img, qrContainer, subscriptionUrl);
                    }
                }, 100);
            };
            
            img.onerror = () => {
                console.log('QRServer falló, intentando Google Charts...');
                generateQRWithGoogleCharts(qrContainer, subscriptionUrl);
            };
            
            // Iniciar carga de imagen
            img.src = qrUrl;
        }
        
        // Función para corregir imagen no visible
        function fixInvisibleImage(img, qrContainer, subscriptionUrl) {
            console.log('=== CORRIGIENDO IMAGEN NO VISIBLE ===');
            
            // Estrategia 1: Forzar dimensiones y estilos
            console.log('Estrategia 1: Forzando dimensiones y estilos...');
            img.style.width = '250px !important';
            img.style.height = '250px !important';
            img.style.minWidth = '250px !important';
            img.style.minHeight = '250px !important';
            img.style.maxWidth = '250px !important';
            img.style.maxHeight = '250px !important';
            img.style.display = 'block !important';
            img.style.visibility = 'visible !important';
            img.style.opacity = '1 !important';
            
            // Forzar reflow
            img.offsetHeight;
            
            setTimeout(() => {
                console.log('Dimensiones después de forzar estilos:', img.offsetWidth, 'x', img.offsetHeight);
                
                if (img.offsetWidth > 0 && img.offsetHeight > 0) {
                    console.log('✅ Imagen corregida exitosamente');
                    return;
                }
                
                // Estrategia 2: Corregir contenedor padre
                console.log('Estrategia 2: Corrigiendo contenedor padre...');
                fixContainerHierarchy(qrContainer);
                
                // Estrategia 3: Recrear la imagen
                console.log('Estrategia 3: Recreando imagen...');
                const newImg = document.createElement('img');
                newImg.src = img.src;
                newImg.alt = 'Código QR de acceso (recreado)';
                newImg.style.cssText = `
                    width: 250px !important;
                    height: 250px !important;
                    min-width: 250px !important;
                    min-height: 250px !important;
                    max-width: 250px !important;
                    max-height: 250px !important;
                    display: block !important;
                    margin: 0 auto !important;
                    border-radius: 12px !important;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
                    border: 3px solid #fff !important;
                `;
                
                newImg.onload = () => {
                    console.log('Nueva imagen cargada, reemplazando...');
                    qrContainer.innerHTML = '';
                    qrContainer.appendChild(newImg);
                    
                    setTimeout(() => {
                        console.log('Verificación de nueva imagen:', newImg.offsetWidth, 'x', newImg.offsetHeight);
                        if (newImg.offsetWidth > 0 && newImg.offsetHeight > 0) {
                            console.log('✅ Nueva imagen visible correctamente');
                        } else {
                            console.error('❌ Nueva imagen tampoco visible, usando fallback...');
                            showModalQRFallback(qrContainer, subscriptionUrl);
                        }
                    }, 100);
                };
                
                newImg.onerror = () => {
                    console.error('Error cargando nueva imagen, usando fallback...');
                    showModalQRFallback(qrContainer, subscriptionUrl);
                };
                
            }, 200);
        }
        
        // Función para corregir la jerarquía del contenedor
        function fixContainerHierarchy(qrContainer) {
            console.log('=== CORRIGIENDO JERARQUÍA DEL CONTENEDOR ===');
            
            if (!qrContainer) return;
            
            // Verificar y corregir el contenedor QR
            console.log('Contenedor QR antes de corrección:', {
                offsetWidth: qrContainer.offsetWidth,
                offsetHeight: qrContainer.offsetHeight,
                clientWidth: qrContainer.clientWidth,
                clientHeight: qrContainer.clientHeight,
                scrollWidth: qrContainer.scrollWidth,
                scrollHeight: qrContainer.scrollHeight
            });
            
            // Aplicar estilos agresivos al contenedor QR
            qrContainer.style.cssText = `
                min-width: 250px !important;
                min-height: 250px !important;
                width: 250px !important;
                height: 250px !important;
                max-width: 250px !important;
                max-height: 250px !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                overflow: visible !important;
                box-sizing: border-box !important;
            `;
            
            // Verificar y corregir contenedores padre
            let parent = qrContainer.parentElement;
            let level = 1;
            
            while (parent && level <= 5) { // Máximo 5 niveles hacia arriba
                console.log(`Nivel ${level} - Contenedor padre:`, {
                    tagName: parent.tagName,
                    className: parent.className,
                    id: parent.id,
                    offsetWidth: parent.offsetWidth,
                    offsetHeight: parent.offsetHeight,
                    display: window.getComputedStyle(parent).display,
                    visibility: window.getComputedStyle(parent).visibility,
                    opacity: window.getComputedStyle(parent).opacity
                });
                
                // Si el padre tiene dimensiones 0, forzar dimensiones
                if (parent.offsetWidth === 0 || parent.offsetHeight === 0) {
                    console.log(`Forzando dimensiones del padre nivel ${level}...`);
                    parent.style.minWidth = '250px';
                    parent.style.minHeight = '250px';
                    parent.style.width = 'auto';
                    parent.style.height = 'auto';
                    parent.style.display = 'block';
                    parent.style.visibility = 'visible';
                    parent.style.overflow = 'visible';
                }
                
                // Trigger reflow
                parent.offsetHeight;
                
                parent = parent.parentElement;
                level++;
            }
            
            // Verificar contenedor QR después de correcciones
            setTimeout(() => {
                console.log('Contenedor QR después de corrección:', {
                    offsetWidth: qrContainer.offsetWidth,
                    offsetHeight: qrContainer.offsetHeight,
                    clientWidth: qrContainer.clientWidth,
                    clientHeight: qrContainer.clientHeight
                });
            }, 100);
        }
        
        // Método 2: Google Charts
        function generateQRWithGoogleCharts(qrContainer, subscriptionUrl) {
            const googleUrl = `https://chart.googleapis.com/chart?cht=qr&chs=250x250&chl=${encodeURIComponent(subscriptionUrl)}&choe=UTF-8&chld=L|0`;
            
            console.log('Intentando generar QR con Google Charts:', googleUrl);
            
            const img = document.createElement('img');
            img.alt = 'Código QR de acceso (Google Charts)';
            img.style.width = '250px';
            img.style.height = '250px';
            img.style.display = 'block';
            img.style.margin = '0 auto';
            img.style.borderRadius = '12px';
            img.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            img.style.border = '3px solid #fff';
            
            img.onload = () => {
                console.log('QR generado exitosamente con Google Charts');
                qrContainer.innerHTML = '';
                qrContainer.appendChild(img);
            };
            
            img.onerror = () => {
                console.log('Google Charts falló, intentando método local...');
                generateQRWithLocal(qrContainer, subscriptionUrl);
            };
            
            img.src = googleUrl;
        }
        
        // Método 3: QR Local (si está disponible)
        function generateQRWithLocal(qrContainer, subscriptionUrl) {
            console.log('Intentando generar QR con método local');
            
            if (typeof QRCode !== 'undefined') {
                try {
                    // Limpiar contenedor
                    qrContainer.innerHTML = '';
                    
                    // Crear un div temporal para el QR
                    const qrDiv = document.createElement('div');
                    qrDiv.id = 'tempQR';
                    qrContainer.appendChild(qrDiv);
                    
                    // Generar QR usando la librería local
                    new QRCode(qrDiv, {
                        text: subscriptionUrl,
                        width: 250,
                        height: 250,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    
                    console.log('QR generado exitosamente con método local');
                } catch (error) {
                    console.error('Error con QR local:', error);
                    showModalQRFallback(qrContainer, subscriptionUrl);
                }
            } else {
                console.log('QR local no disponible, mostrando fallback');
                showModalQRFallback(qrContainer, subscriptionUrl);
            }
        }
        
        // Método de respaldo para el modal
        function showModalQRFallback(qrContainer, subscriptionUrl) {
            console.log('Mostrando fallback del modal para URL:', subscriptionUrl);
            
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px dashed #dee2e6; border-radius: 15px; padding: 30px; margin-bottom: 20px;">
                            <i class="fas fa-qrcode" style="font-size: 48px; color: #667eea; margin-bottom: 15px;"></i>
                            <h4 style="color: #667eea; margin-bottom: 10px;">Código QR</h4>
                            <p style="color: #6c757d; margin-bottom: 15px;">No disponible temporalmente</p>
                        </div>
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                            <p style="margin-bottom: 15px; color: #666; font-size: 14px; font-weight: 600;">
                                <i class="fas fa-link"></i> Enlace directo de acceso:
                            </p>
                            <a href="${subscriptionUrl}" target="_blank" style="color: #667eea; text-decoration: none; word-break: break-all; font-size: 14px; padding: 15px; background: white; border-radius: 8px; display: inline-block; border: 2px solid #e9ecef; max-width: 100%;">
                                <i class="fas fa-external-link-alt"></i> ${subscriptionUrl}
                            </a>
                        </div>
                        <p style="color: #999; font-size: 12px; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> Puedes copiar y pegar este enlace en tu navegador
                        </p>
                    </div>
                `;
        }
        
        // Función simple para generar QR (mantener para compatibilidad)
        function generateSimpleQR(qrContainer, subscriptionUrl) {
            console.log('generateSimpleQR llamada - usando método mejorado');
            generateModalQRWithFallback(qrContainer, subscriptionUrl);
        }
        
        // Función para activar membresía (mejorada con datos reales)
        function activateMembership() {
            console.log('Activando membresía...');
            console.log('hasPaymentMethod:', hasPaymentMethod);
            console.log('currentStatus:', currentStatus);
            
            if (!hasPaymentMethod) {
                alert('Debes configurar un método de pago antes de activar tu tarjeta.');
                console.log('Activación bloqueada - sin método de pago');
                return;
            }
            
            if (currentStatus === 'cancelled') {
                alert('No puedes activar una membresía cancelada.');
                console.log('Activación bloqueada - membresía cancelada');
                return;
            }
            
            const btnActivate = document.getElementById('btnActivate');
            if (!btnActivate) {
                console.error('ERROR: Botón activar no encontrado');
                return;
            }
            
            // Mostrar loading
            btnActivate.classList.add('loading');
            btnActivate.disabled = true;
            
            // Proceso de activación real con datos de la base de datos
            const clienteId = <?= $cliente['id'] ?>;
            const membresiaId = <?= $membresia['id'] ?? 'null' ?>;
            
            console.log('Datos de activación:', { clienteId, membresiaId, hasPaymentMethod });
            
            // Si no hay membresía en la base de datos, crear una
            if (!membresiaId) {
                console.log('Creando nueva membresía...');
                createNewMembership(clienteId, btnActivate);
            } else {
                console.log('Activando membresía existente...');
                activateExistingMembership(membresiaId, btnActivate);
            }
        }
        
        // Función para crear nueva membresía
        function createNewMembership(clienteId, btnActivate) {
            const planId = <?= $membresia['tipo_membresia_id'] ?? 'null' ?>;
            const precio = <?= $membresia['precio'] ?>;
            
            if (!planId) {
                alert('Error: No hay plan de membresía configurado. Contacta al administrador.');
                btnActivate.classList.remove('loading');
                btnActivate.disabled = false;
                return;
            }
            
            // Crear membresía en la base de datos
            fetch('create_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cliente_id: clienteId,
                    plan_id: planId,
                    precio: precio,
                    accion: 'create_membership'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Membresía creada exitosamente:', data);
                    completeActivation(btnActivate, data.membresia_id);
                } else {
                    console.error('Error creando membresía:', data.error);
                    alert('Error al crear la membresía: ' + (data.error || 'Error desconocido'));
                    btnActivate.classList.remove('loading');
                    btnActivate.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error en la petición:', error);
                alert('Error de conexión al crear la membresía');
                btnActivate.classList.remove('loading');
                btnActivate.disabled = false;
            });
        }
        
        // Función para activar membresía existente
        function activateExistingMembership(membresiaId, btnActivate) {
            console.log('Activando membresía existente con ID:', membresiaId);
            
            fetch('create_subscription_alternative.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    membresia_id: membresiaId,
                    accion: 'activate_membership'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Membresía activada exitosamente:', data);
                    
                    // Completar la activación (esto mostrará el modal)
                    completeActivation(btnActivate, membresiaId, data.init_point);
                } else {
                    console.error('Error activando membresía:', data.error);
                    alert('Error al activar la membresía: ' + (data.error || 'Error desconocido'));
                    btnActivate.classList.remove('loading');
                    btnActivate.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error en la petición:', error);
                alert('Error de conexión al activar la membresía');
                btnActivate.classList.remove('loading');
                btnActivate.disabled = false;
            });
        }
        
        // Función para completar la activación
        function completeActivation(btnActivate, membresiaId, initPoint = null) {
            btnActivate.classList.remove('loading');
            btnActivate.disabled = false;
            
            isActivated = true;
            currentStatus = 'authorized';
            
            // Cambiar texto del botón
            btnActivate.innerHTML = '<i class="fas fa-check"></i>Tarjeta Activada';
            btnActivate.classList.remove('btn-activate');
            btnActivate.classList.add('btn-success');
            btnActivate.disabled = true;
            
            // Actualizar estado en la UI
            const statusElement = document.querySelector('.card-status');
            if (statusElement) {
                statusElement.textContent = 'Autorizada';
                statusElement.className = 'card-status status-authorized';
            }
            
            // Mostrar modal con QR
            showPaymentModal(initPoint);
            
            console.log('Membresía activada correctamente con ID:', membresiaId);
        }
        

        
        // Función para generar y mostrar el QR (mejorada como en la carpeta de prueba)
        function generateAndShowQR(initPoint = null) {
            const qrContainer = document.getElementById('qrContainer');
            const subscriptionLink = document.getElementById('subscriptionLink');
            
            if (!qrContainer || !subscriptionLink) {
                console.error('Elementos QR no encontrados');
                return;
            }
            
            // Si tenemos un initPoint real, usarlo directamente
            let subscriptionUrl = initPoint;
            
            // Si no hay initPoint, crear URL única para la suscripción
            if (!subscriptionUrl) {
                subscriptionUrl = createSubscriptionURL();
            }
            
            console.log('URL de suscripción generada:', subscriptionUrl);
            
            // Verificar si la URL se generó correctamente
            if (!subscriptionUrl) {
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #f44336;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                        <p style="margin-top: 10px;">Error: No se pudo generar la URL de pago</p>
                        <p style="font-size: 12px; margin-top: 5px;">Verifica que tengas un plan de membresía configurado</p>
                    </div>
                `;
                return;
            }
            
            // Actualizar el enlace de suscripción
            subscriptionLink.href = subscriptionUrl;
            
            // Mostrar indicador de carga
            qrContainer.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i>
                    <p style="margin-top: 10px; color: #666;">Generando código QR...</p>
                </div>
            `;
            
            // Intentar generar QR usando múltiples métodos (como en la carpeta de prueba)
            generateQRWithFallback(qrContainer, subscriptionUrl);
        }
        
        // Función mejorada para generar QR con múltiples fallbacks
        function generateQRWithFallback(qrContainer, subscriptionUrl) {
            // Método 1: Servicio QRServer (más confiable)
            const qrServerUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(subscriptionUrl);
            
            const img = document.createElement('img');
            img.alt = 'Código QR de acceso';
            img.style.width = '200px';
            img.style.height = '200px';
            img.style.display = 'block';
            img.style.margin = '0 auto';
            img.style.borderRadius = '8px';
            img.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            
            img.onload = () => {
                // Limpiar contenedor y agregar imagen
                qrContainer.innerHTML = '';
                qrContainer.appendChild(img);
                console.log('QR generado exitosamente con QRServer');
            };
            
            img.onerror = () => {
                console.log('QRServer falló, intentando Google Charts...');
                // Método 2: Google Charts
                const googleUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=' + encodeURIComponent(subscriptionUrl);
                img.src = googleUrl;
                
                img.onerror = () => {
                    console.log('Google Charts falló, intentando QR local...');
                    // Método 3: QR Local (si está disponible)
                    if (typeof QRCodeLocal !== 'undefined') {
                        try {
                            QRCodeLocal.toDataURL(subscriptionUrl, { width: 200 }, function(error, dataURL) {
                                if (error) {
                                    console.error('QR local falló:', error);
                                    showQRFallback(qrContainer, subscriptionUrl);
                                } else {
                                    qrContainer.innerHTML = '';
                                    const localImg = document.createElement('img');
                                    localImg.src = dataURL;
                                    localImg.alt = 'Código QR de acceso (local)';
                                    localImg.style.width = '200px';
                                    localImg.style.height = '200px';
                                    localImg.style.display = 'block';
                                    localImg.style.margin = '0 auto';
                                    localImg.style.borderRadius = '8px';
                                    localImg.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                                    qrContainer.appendChild(localImg);
                                    console.log('QR generado exitosamente con método local');
                                }
                            });
                        } catch (error) {
                            console.error('Error con QR local:', error);
                            showQRFallback(qrContainer, subscriptionUrl);
                        }
                    } else {
                        console.log('QR local no disponible, mostrando fallback');
                        showQRFallback(qrContainer, subscriptionUrl);
                    }
                };
            };
            
            // Iniciar con el primer método
            img.src = qrServerUrl;
        }
        

        
        // Función de respaldo: intenta generar el QR con un segundo servicio y muestra el enlace
        function showQRFallback(qrContainer, subscriptionUrl) {
            const subscriptionLink = document.getElementById('subscriptionLink');
            
            // Verificar si la URL es válida
            if (!subscriptionUrl) {
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #f44336;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                        <p style="margin-top: 10px;">Error: URL de pago no válida</p>
                    </div>
                `;
                return;
            }
            
            // Actualizar el enlace de suscripción
            if (subscriptionLink) {
                subscriptionLink.href = subscriptionUrl;
            }
            
            // Intentar un segundo servicio
            const fallbackUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=' + encodeURIComponent(subscriptionUrl);
            const img = document.createElement('img');
            img.src = fallbackUrl;
            img.alt = 'Código QR de acceso (respaldo)';
            img.style.width = '200px';
            img.style.height = '200px';
            img.style.display = 'block';
            img.style.margin = '0 auto';
            img.style.borderRadius = '8px';
            img.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            
            img.onload = () => {
                qrContainer.innerHTML = '';
                qrContainer.appendChild(img);
            };
            
            img.onerror = () => {
                qrContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 30px; margin-bottom: 15px;">
                            <i class="fas fa-qrcode" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                            <p style="color: #6c757d; margin-bottom: 15px;">QR no disponible</p>
                        </div>
                        <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Haz clic en el enlace para acceder:</p>
                        <a href="${subscriptionUrl}" target="_blank" style="color: #667eea; text-decoration: none; word-break: break-all; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 5px; display: inline-block;">
                            ${subscriptionUrl}
                        </a>
                    </div>
                `;
            };
        }
        

        
        // Función para crear URL única de suscripción (mejorada)
        function createSubscriptionURL() {
            const clienteId = <?= $cliente['id'] ?>;
            const membresiaId = <?= $membresia['id'] ?? 'null' ?>;
            const planId = '<?= $membresia['mp_plan_id'] ?? '' ?>';
            const precio = <?= $membresia['precio'] ?>;
            const planNombre = '<?= addslashes($membresia['plan_nombre']) ?>';
            
            console.log('Creando URL de suscripción con datos:', {
                clienteId,
                membresiaId,
                planId,
                precio,
                planNombre
            });
            
            // Si no hay mp_plan_id, mostrar error más descriptivo
            if (!planId || planId.trim() === '') {
                console.error('No hay plan de Mercado Pago configurado');
                console.error('Datos de membresía:', {
                    id: membresiaId,
                    tipo_membresia_id: <?= $membresia['tipo_membresia_id'] ?? 'null' ?>,
                    mp_plan_id: planId,
                    plan_nombre: planNombre
                });
                
                const errorMessage = 'Error: No hay plan de Mercado Pago configurado.\n\n' +
                    'Esto puede deberse a:\n' +
                    '• El plan no tiene un ID de Mercado Pago asignado\n' +
                    '• Problema de configuración en tipos_membresia\n' +
                    '• El plan no está activo\n\n' +
                    'Contacta al administrador para resolver este problema.';
                
                alert(errorMessage);
                return null;
            }
            
            // Crear URL de suscripción directa de Mercado Pago
            let subscriptionUrl;
            
            // Si tenemos un plan de Mercado Pago, usar checkout directo
            if (planId && planId.trim() !== '') {
                subscriptionUrl = `https://www.mercadopago.com.mx/subscriptions/checkout?preapproval_plan_id=${planId}`;
            } else {
                // Fallback: crear URL personalizada
                const token = generateUniqueToken(clienteId, membresiaId);
                subscriptionUrl = `https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=${token}`;
            }
            
            console.log('URL de suscripción generada:', subscriptionUrl);
            return subscriptionUrl;
        }
        
        // Función para generar token único
        function generateUniqueToken(clienteId, membresiaId) {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            const data = `${clienteId}-${membresiaId}-${timestamp}-${random}`;
            
            // Crear hash simple (en producción usarías una función más segura)
            let hash = 0;
            for (let i = 0; i < data.length; i++) {
                const char = data.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convertir a 32-bit integer
            }
            
            return Math.abs(hash).toString(36);
        }
        

        
        // Función para mostrar mensaje de éxito
        function showSuccessMessage(message) {
            const alertContainer = document.createElement('div');
            alertContainer.className = 'alert alert-success';
            alertContainer.innerHTML = `
                <i class="fas fa-check-circle"></i>
                ${message}
            `;
            
            const dashboardContainer = document.querySelector('.dashboard-container');
            if (dashboardContainer) {
                dashboardContainer.insertBefore(alertContainer, dashboardContainer.firstChild);
                
                // Remover el mensaje después de 5 segundos
                setTimeout(() => {
                    if (alertContainer.parentNode) {
                        alertContainer.remove();
                    }
                }, 5000);
            }
        }
        
        // Función para ir a método de pago
        function goToPayment() {
            console.log('Redirigiendo a método de pago...');
            try {
                window.location.href = 'save_card.php';
                console.log('Redirección iniciada exitosamente');
            } catch (error) {
                console.error('Error al redirigir:', error);
                alert('Error al redirigir: ' + error.message);
            }
        }
        
        // Función para cerrar sesión
        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                try {
                    // Crear un formulario temporal para enviar la solicitud POST
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'logout.php';
                    
                    // Agregar un campo oculto para indicar que es una solicitud de logout
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'logout';
                    input.value = '1';
                    form.appendChild(input);
                    
                    // Agregar el formulario al documento y enviarlo
                    document.body.appendChild(form);
                    form.submit();
                } catch (error) {
                    // Si falla el método POST, intentar con GET
                    window.location.href = 'logout.php';
                }
            }
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Settings button click
            const settingsBtn = document.getElementById('settingsBtn');
            if (settingsBtn) {
                settingsBtn.addEventListener('click', openSettingsModal);
            }
            
            // Logout button click
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', logout);
            }
            
            // Modal close buttons
            const modalClose = document.getElementById('modalClose');
            if (modalClose) {
                modalClose.addEventListener('click', closeSettingsModal);
            }
            
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeSettingsModal);
            }
            
            // Payment modal close buttons
            const paymentModalClose = document.getElementById('paymentModalClose');
            if (paymentModalClose) {
                paymentModalClose.addEventListener('click', closePaymentModal);
            }
            
            const closePaymentModalBtn = document.getElementById('closePaymentModal');
            if (closePaymentModalBtn) {
                closePaymentModalBtn.addEventListener('click', closePaymentModal);
            }
            
            // Test QR button click
            const testQRBtn = document.getElementById('testQRBtn');
            if (testQRBtn) {
                testQRBtn.addEventListener('click', function() {
                    console.log('=== BOTÓN DE PRUEBA QR CLICKEADO ===');
                    const qrContainer = document.getElementById('modalQrContainer');
                    if (qrContainer) {
                        console.log('Contenedor QR encontrado, generando QR de prueba...');
                        // Generar QR de prueba con una URL simple
                        generateModalQRWithFallback(qrContainer, 'https://www.google.com');
                    } else {
                        console.error('Contenedor QR no encontrado');
                    }
                });
            }

            
            // Payment button click
            const btnPayment = document.getElementById('btnPayment');
            if (btnPayment) {
                btnPayment.addEventListener('click', goToPayment);
            }
            
            // Activate button click
            const btnActivate = document.getElementById('btnActivate');
            if (btnActivate) {
                btnActivate.addEventListener('click', activateMembership);
                
                // Si ya está activada, cambiar el estilo del botón
                if (isActivated) {
                    btnActivate.innerHTML = '<i class="fas fa-check"></i>Tarjeta Activada';
                    btnActivate.classList.remove('btn-activate');
                    btnActivate.classList.add('btn-success');
                    btnActivate.disabled = true;
                }
            }
            
            // Close modal when clicking outside
            const settingsModal = document.getElementById('settingsModal');
            if (settingsModal) {
                settingsModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeSettingsModal();
                    }
                });
            }
            
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                paymentModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closePaymentModal();
                    }
                });
            }
            

            
            // Password form submit
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Actualizando...';
                        submitBtn.disabled = true;
                    }
                });
            }
            
            // Inicializar UI del método de pago
            updatePaymentMethodUI();
            
            // Verificar estado del método de pago cada 30 segundos
            setInterval(checkPaymentMethodStatus, 30000);
            
            // Verificar estado inicial del método de pago
            checkPaymentMethodStatus();
            
            // Verificar si la membresía ya está activada
            if (isActivated || currentStatus === 'authorized') {
                const qrSection = document.getElementById('qrSection');
                if (qrSection) {
                    qrSection.style.display = 'block';
                    // Generar QR después de un pequeño delay para asegurar que el DOM esté listo
                    setTimeout(() => {
                        generateAndShowQR();
                    }, 500);
                }
            }
            

            
            console.log('Dashboard inicializado correctamente');
        });
        
        // Función de prueba para debugging desde consola
        window.testQRGeneration = function() {
            console.log('=== FUNCIÓN DE PRUEBA QR DESDE CONSOLA ===');
            
            // Verificar estado del modal
            const state = verifyModalState();
            console.log('Estado del modal:', state);
            
            if (!state.modal) {
                console.error('Modal no encontrado');
                return;
            }
            
            // Mostrar modal si no está visible
            const modal = document.getElementById('paymentModal');
            if (modal.style.display !== 'flex') {
                console.log('Mostrando modal...');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            
            // Generar QR de prueba
            setTimeout(() => {
                const qrContainer = document.getElementById('modalQrContainer');
                if (qrContainer) {
                    console.log('Generando QR de prueba...');
                    generateModalQRWithFallback(qrContainer, 'https://www.google.com');
                } else {
                    console.error('Contenedor QR no encontrado');
                }
            }, 300);
        };
        
        // Función adicional para forzar la visualización del QR
        window.forceQRDisplay = function() {
            console.log('=== FORZANDO VISUALIZACIÓN DEL QR ===');
            
            const qrContainer = document.getElementById('modalQrContainer');
            if (!qrContainer) {
                console.error('Contenedor QR no encontrado');
                return;
            }
            
            // Forzar dimensiones y visibilidad
            qrContainer.style.minHeight = '250px';
            qrContainer.style.minWidth = '250px';
            qrContainer.style.display = 'block';
            qrContainer.style.visibility = 'visible';
            qrContainer.style.opacity = '1';
            
            // Trigger reflow
            qrContainer.offsetHeight;
            
            console.log('Dimensiones forzadas:', `${qrContainer.offsetWidth}x${qrContainer.offsetHeight}`);
            console.log('Estilos aplicados:', {
                minHeight: qrContainer.style.minHeight,
                minWidth: qrContainer.style.minWidth,
                display: qrContainer.style.display,
                visibility: qrContainer.style.visibility,
                opacity: qrContainer.style.opacity
            });
            
            // Si hay una imagen en el contenedor, verificar su estado
            const img = qrContainer.querySelector('img');
            if (img) {
                console.log('Imagen encontrada en contenedor:', img);
                console.log('Dimensiones de imagen:', `${img.offsetWidth}x${img.offsetHeight}`);
                console.log('Estilos de imagen:', {
                    width: img.style.width,
                    height: img.style.height,
                    display: img.style.display
                });
            } else {
                console.log('No hay imagen en el contenedor');
            }
        };
    </script>
</body>
</html>
