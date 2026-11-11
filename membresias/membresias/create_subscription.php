<?php
// Crear suscripción de Mercado Pago
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

// Manejar peticiones POST (para activación de membresías)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    $accion = $input['accion'] ?? '';
    
    if ($accion === 'activate_membership') {
        try {
            $membresia_id = $input['membresia_id'] ?? null;
            
            if (!$membresia_id) {
                echo json_encode(['success' => false, 'error' => 'ID de membresía requerido']);
                exit;
            }
            
            // Verificar que el cliente esté logueado
            if (!isset($_SESSION['cliente'])) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }
            
            $cliente_id = $_SESSION['cliente']['id'];
            
            // Obtener la membresía del cliente
            $stmt = $pdo->prepare("
                SELECT m.*, tm.nombre as plan_nombre, tm.precio, tm.mp_plan_id, tm.empresa_id
                FROM membresias m
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.id = ? AND m.cliente_id = ?
            ");
            $stmt->execute([$membresia_id, $cliente_id]);
            $membresia = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$membresia) {
                echo json_encode(['success' => false, 'error' => 'Membresía no encontrada']);
                exit;
            }
            
            // Verificar que el plan existe y está activo
            if (!$membresia['mp_plan_id']) {
                echo json_encode(['success' => false, 'error' => 'Plan no encontrado']);
                exit;
            }
            
            // Verificar que el cliente tiene token de tarjeta
            $clienteStmt = $pdo->prepare("SELECT mp_card_token FROM clientes WHERE id = ?");
            $clienteStmt->execute([$cliente_id]);
            $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente || !$cliente['mp_card_token']) {
                echo json_encode(['success' => false, 'error' => 'Debe configurar un método de pago primero']);
                exit;
            }
            
            // Inicializar Mercado Pago
            mpInit();
            
            // Crear suscripción usando el plan existente
            $preClient = new \MercadoPago\Client\PreApproval\PreApprovalClient();
            
            // Generar external_reference único con timestamp
            $timestamp = time();
            $external_reference = 'MEM_' . $membresia_id . '_' . $cliente_id . '_' . $timestamp;
            
            // back_url a donde volverá el pagador tras aprobar
            $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            $back = $baseUrl . '/membresias/payment_success.php';
            
            $request = [
                'preapproval_plan_id' => $membresia['mp_plan_id'],
                'payer_email' => (string)($_SESSION['cliente']['correo'] ?? ''),
                'back_url' => $back,
                'reason' => 'Membresía ' . ($_SESSION['cliente']['nombre'] ?? '') . ' - ' . $membresia['plan_nombre'],
                'external_reference' => $external_reference,
            ];
            
            // Crear la suscripción
            $pre = $preClient->create($request);
            
            if ($pre && $pre->id) {
                // Actualizar la membresía con el ID de suscripción
                $updateStmt = $pdo->prepare("
                    UPDATE membresias 
                    SET mp_subscription_id = ?, 
                        mp_status = 'pending',
                        mp_external_reference = ?,
                        mp_last_sync_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$pre->id, $external_reference, $membresia_id]);
                
                echo json_encode([
                    'success' => true,
                    'subscription_id' => $pre->id,
                    'init_point' => $pre->init_point,
                    'external_reference' => $external_reference,
                    'message' => 'Suscripción creada exitosamente'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear suscripción']);
            }
            
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $errorMessage = 'Error de API de Mercado Pago';
            
            // Analizar el error específico - convertir el objeto a array si es necesario
            $responseData = $apiResponse;
            if (is_object($apiResponse)) {
                $responseData = $apiResponse->getContent();
            }
            
            if (isset($responseData['error'])) {
                if (isset($responseData['error']['causes'])) {
                    foreach ($responseData['error']['causes'] as $cause) {
                        if (strpos($cause['description'] ?? '', 'external_reference') !== false) {
                            $errorMessage = 'Error: La referencia externa ya existe. Intente nuevamente.';
                            break;
                        }
                    }
                }
                $errorMessage .= ': ' . ($responseData['error']['message'] ?? $e->getMessage());
            }
            
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al activar la membresía: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($accion === 'create_membership') {
        try {
            $cliente_id = $input['cliente_id'] ?? null;
            $plan_id = $input['plan_id'] ?? null;
            $precio = $input['precio'] ?? 0;
            
            if (!$cliente_id || !$plan_id) {
                echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
                exit;
            }
            
            // Verificar que el cliente está logueado
            if (!isset($_SESSION['cliente']) || $_SESSION['cliente']['id'] != $cliente_id) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }
            
            // Verificar que el plan existe
            $stmt = $pdo->prepare("SELECT id, nombre, precio, is_active FROM tipos_membresia WHERE id = ?");
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan || !$plan['is_active']) {
                echo json_encode(['success' => false, 'error' => 'Plan no encontrado o inactivo']);
                exit;
            }
            
            // Crear nueva membresía
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime("+{$plan['duracion_dias']} days"));
            
            $stmt = $pdo->prepare("
                INSERT INTO membresias 
                (cliente_id, empresa_id, tipo_membresia_id, fecha_inicio, fecha_fin, metodo_pago, mp_status, created_at) 
                VALUES (?, 3, ?, ?, ?, 'mercadopago', 'pending', NOW())
            ");
            $stmt->execute([$cliente_id, $plan_id, $fecha_inicio, $fecha_fin]);
            
            $membresia_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'membresia_id' => $membresia_id,
                'plan_id' => $plan_id,
                'precio' => $precio
            ]);
            
        } catch (Exception $e) {
            error_log("Error creando membresía: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al crear la membresía: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Si no es una acción válida
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

// Obtener parámetros para peticiones GET
$cliente_id = $_GET['cliente_id'] ?? null;
$membresia_id = $_GET['membresia_id'] ?? null;
$plan_id = $_GET['plan_id'] ?? null;
$precio = $_GET['precio'] ?? 0;
$plan_nombre = $_GET['plan'] ?? 'Membresía';
$token = $_GET['token'] ?? null;

// Verificar si el cliente está logueado O si tiene parámetros válidos
$isLoggedIn = isset($_SESSION['cliente']);
$hasValidParams = $cliente_id && $plan_id && $token;

if (!$isLoggedIn && !$hasValidParams) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado - Se requieren parámetros válidos o sesión activa']);
    exit;
}

// Si está logueado, usar el ID de la sesión
if ($isLoggedIn) {
    $cliente_id = $_SESSION['cliente']['id'];
}

// Validar que el cliente sea el correcto si está logueado
if ($isLoggedIn && $cliente_id != $_SESSION['cliente']['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

try {
    // Verificar que el cliente existe
    $stmt = $pdo->prepare("SELECT id, nombre, correo FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
        exit;
    }
    
    // Verificar que el plan existe
    $stmt = $pdo->prepare("SELECT id, nombre, precio, duracion_dias, is_active FROM tipos_membresia WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        // Log del error para debug
        error_log("Plan no encontrado - Plan ID: $plan_id, Cliente ID: $cliente_id");
        
        // Obtener todos los planes disponibles para debug
        $stmt = $pdo->prepare("SELECT id, nombre, is_active FROM tipos_membresia ORDER BY id");
        $stmt->execute();
        $planes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $planes_info = [];
        foreach ($planes_disponibles as $p) {
            $planes_info[] = "ID: {$p['id']}, Nombre: {$p['nombre']}, Activo: " . ($p['is_active'] ? 'Sí' : 'No');
        }
        
        error_log("Planes disponibles: " . implode(', ', $planes_info));
        
        http_response_code(404);
        echo json_encode([
            'error' => 'Plan no encontrado',
            'plan_id_requested' => $plan_id,
            'planes_disponibles' => $planes_info,
            'debug_info' => [
                'cliente_id' => $cliente_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }
    
    // Verificar que el plan esté activo
    if (!$plan['is_active']) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Plan inactivo',
            'plan_id' => $plan_id,
            'plan_nombre' => $plan['nombre']
        ]);
        exit;
    }
    
    // Si no hay membresía_id, verificar si el cliente ya tiene una membresía activa
    if (!$membresia_id) {
        $stmt = $pdo->prepare("
            SELECT id, mp_status 
            FROM membresias 
            WHERE cliente_id = ? AND mp_status IN ('pending', 'authorized', 'paused')
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$cliente_id]);
        $membresia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membresia_existente) {
            $membresia_id = $membresia_existente['id'];
        } else {
            // Crear una nueva membresía para el cliente
            $stmt = $pdo->prepare("
                INSERT INTO membresias 
                (cliente_id, empresa_id, tipo_membresia_id, fecha_inicio, fecha_fin, metodo_pago, mp_status, created_at) 
                VALUES (?, 1, ?, NULL, NULL, 'mercadopago', 'pending', NOW())
            ");
            $stmt->execute([$cliente_id, $plan_id]);
            $membresia_id = $pdo->lastInsertId();
        }
    } else {
        // Verificar que la membresía pertenece al cliente
        $stmt = $pdo->prepare("SELECT id FROM membresias WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$membresia_id, $cliente_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Membresía no encontrada']);
            exit;
        }
    }
    
    // Crear identificador único para la suscripción
    $timestamp = time();
    $external_reference = "MEM_{$cliente_id}_{$membresia_id}_{$plan_id}_{$timestamp}";
    
    // Si es muy largo, truncar
    if (strlen($external_reference) > 100) {
        $external_reference = substr($external_reference, 0, 100);
    }
    
    // Inicializar Mercado Pago
    mpInit();
    
    // Crear preferencia de pago usando el SDK v3
    $preferenceClient = new \MercadoPago\Client\Preference\PreferenceClient();
    
    // Crear item para la preferencia
    $item = [
        'title' => $plan_nombre,
        'quantity' => 1,
        'unit_price' => floatval($precio),
        'currency_id' => 'MXN'
    ];
    
    // Crear preferencia con expiración más larga (7 días en lugar de 1 hora)
    $preferenceRequest = [
        'items' => [$item],
        'external_reference' => $external_reference,
        'notification_url' => 'https://membresia.secureitmx.com/membresias/payment_success.php',
        'back_urls' => [
            'success' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=success',
            'failure' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=failure',
            'pending' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=pending'
        ],
        'auto_return' => 'approved',
        'expires' => true,
        'expiration_date_to' => date('Y-m-d\TH:i:s.000-03:00', strtotime('+7 days'))
    ];
    
    error_log('MP Create Preference Request: ' . json_encode($preferenceRequest));
    
    $preference = $preferenceClient->create($preferenceRequest);
    
    if (!$preference || !$preference->id) {
        throw new Exception('Error al crear preferencia en Mercado Pago');
    }
    
    error_log('MP Preference created: ' . json_encode($preference));
    
    // Guardar en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO payment_preferences 
        (cliente_id, membresia_id, plan_id, external_reference, preference_id, amount, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $cliente_id,
        $membresia_id,
        $plan_id,
        $external_reference,
        $preference->id,
        $precio
    ]);
    
    // Actualizar la membresía con la referencia externa
    if ($membresia_id) {
        $stmt = $pdo->prepare("UPDATE membresias SET mp_external_reference = ? WHERE id = ?");
        $stmt->execute([$external_reference, $membresia_id]);
    }
    
    // Crear URL de pago real
    $payment_url = $preference->init_point;
    
    if (!$payment_url) {
        throw new Exception('No se pudo obtener la URL de pago de la preferencia');
    }
    
    error_log('MP Payment URL: ' . $payment_url);
    
    // Redirigir al usuario a la página de pago
    header('Location: ' . $payment_url);
    exit;
    
} catch (Exception $e) {
    error_log("Error creando suscripción: " . $e->getMessage());
    
    // Mostrar página de error
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Crear Suscripción</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 48px;
                color: #f44336;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
            }
            .btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 25px;
                text-decoration: none;
                display: inline-block;
                font-weight: bold;
                transition: transform 0.3s;
            }
            .btn:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Error al crear suscripción</h1>
            <p>Hubo un problema al procesar tu solicitud de pago. Por favor, intenta nuevamente.</p>
            <p style="font-size: 12px; color: #999;">Error: <?= htmlspecialchars($e->getMessage()) ?></p>
            <a href="dashboard.php" class="btn">Volver al Dashboard</a>
        </div>
    </body>
    </html>
    <?php
}
?>
