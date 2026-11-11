<?php
// Archivo de prueba para verificar la versión alternativa usando preferencias
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

echo "<h1>Prueba de Activación Alternativa (Preferencias)</h1>";

// Verificar si hay sesión activa
if (!isset($_SESSION['cliente'])) {
    echo "<p style='color: red;'>❌ No hay sesión activa. Por favor, inicia sesión primero.</p>";
    echo "<p><a href='login.php'>Ir al Login</a></p>";
    exit;
}

$cliente_id = $_SESSION['cliente']['id'];
$cliente_nombre = $_SESSION['cliente']['nombre'];

echo "<h2>1. Verificando datos del cliente...</h2>";
echo "<p>Cliente ID: $cliente_id</p>";
echo "<p>Cliente Nombre: $cliente_nombre</p>";

// Verificar membresía del cliente
$stmt = $pdo->prepare("
    SELECT m.*, tm.nombre as plan_nombre, tm.precio, tm.mp_plan_id, tm.empresa_id
    FROM membresias m
    LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
    WHERE m.cliente_id = ?
");
$stmt->execute([$cliente_id]);
$membresia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membresia) {
    echo "<p style='color: red;'>❌ No se encontró membresía para el cliente</p>";
    exit;
}

echo "<h2>2. Datos de la membresía encontrada:</h2>";
echo "<p>Membresía ID: {$membresia['id']}</p>";
echo "<p>Plan: {$membresia['plan_nombre']}</p>";
echo "<p>Precio: \${$membresia['precio']}</p>";
echo "<p>MP Plan ID: {$membresia['mp_plan_id']}</p>";
echo "<p>Estado: {$membresia['mp_status']}</p>";

// Verificar token de tarjeta del cliente
$clienteStmt = $pdo->prepare("SELECT mp_card_token FROM clientes WHERE id = ?");
$clienteStmt->execute([$cliente_id]);
$cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>3. Verificando método de pago...</h2>";
if ($cliente && $cliente['mp_card_token']) {
    echo "<p style='color: green;'>✅ Cliente tiene token de tarjeta configurado</p>";
} else {
    echo "<p style='color: red;'>❌ Cliente no tiene token de tarjeta configurado</p>";
    echo "<p>Debe configurar un método de pago primero.</p>";
    exit;
}

// Simular la activación usando preferencias
echo "<h2>4. Simulando activación usando preferencias...</h2>";

try {
    // Inicializar Mercado Pago
    mpInit();
    
    // Crear preferencia de pago
    $preferenceClient = new \MercadoPago\Client\Preference\PreferenceClient();
    
    // Generar external_reference único con timestamp
    $timestamp = time();
    $external_reference = 'MEM_' . $membresia['id'] . '_' . $cliente_id . '_' . $timestamp;
    
    // Crear item para la preferencia
    $item = [
        'title' => $membresia['plan_nombre'],
        'quantity' => 1,
        'unit_price' => (float)$membresia['precio'],
        'currency_id' => 'MXN'
    ];
    
    // back_url a donde volverá el pagador tras aprobar
    $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $back = $baseUrl . '/membresias/payment_success.php';
    
    $preferenceRequest = [
        'items' => [$item],
        'external_reference' => $external_reference,
        'notification_url' => $back,
        'back_urls' => [
            'success' => $baseUrl . '/membresias/dashboard.php?payment=success',
            'failure' => $baseUrl . '/membresias/dashboard.php?payment=failure',
            'pending' => $baseUrl . '/membresias/dashboard.php?payment=pending'
        ],
        'auto_return' => 'approved',
        'expires' => true,
        'expiration_date_to' => date('Y-m-d\TH:i:s.000-03:00', strtotime('+1 hour')),
        'payer' => [
            'email' => (string)($_SESSION['cliente']['correo'] ?? '')
        ]
    ];
    
    echo "<h3>Request que se enviará a Mercado Pago:</h3>";
    echo "<pre>" . json_encode($preferenceRequest, JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<p>External Reference único: $external_reference</p>";
    echo "<p>Intentando crear preferencia...</p>";
    
    $preference = $preferenceClient->create($preferenceRequest);
    
    if ($preference && $preference->id) {
        echo "<p style='color: green;'>✅ Preferencia creada exitosamente</p>";
        echo "<p>Preference ID: {$preference->id}</p>";
        echo "<p>Init Point: {$preference->init_point}</p>";
        echo "<p>External Reference: {$preference->external_reference}</p>";
        
        // Crear enlace para probar
        echo "<h3>Enlace de prueba:</h3>";
        echo "<p><a href='{$preference->init_point}' target='_blank' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Probar Preferencia</a></p>";
        
        // Simular actualización en base de datos
        echo "<h3>Simulando actualización en base de datos...</h3>";
        echo "<p>Se actualizaría la membresía con:</p>";
        echo "<ul>";
        echo "<li>mp_subscription_id: {$preference->id}</li>";
        echo "<li>mp_status: pending</li>";
        echo "<li>mp_external_reference: $external_reference</li>";
        echo "</ul>";
        
        // Mostrar URL que se generaría desde el dashboard
        echo "<h3>URL que se generaría desde el dashboard:</h3>";
        echo "<p><a href='{$preference->init_point}' target='_blank' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Probar desde Dashboard</a></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Error al crear preferencia</p>";
    }
    
} catch (\MercadoPago\Exceptions\MPApiException $e) {
    echo "<p style='color: red;'>❌ Error de API de Mercado Pago</p>";
    echo "<h3>Detalles del Error:</h3>";
    echo "<p>Status Code: " . $e->getCode() . "</p>";
    echo "<p>Error Message: " . $e->getMessage() . "</p>";
    
    $apiResponse = $e->getApiResponse();
    if (!empty($apiResponse)) {
        echo "<p>API Response:</p>";
        echo "<pre>" . json_encode($apiResponse, JSON_PRETTY_PRINT) . "</pre>";
        
        // Analizar el error específico
        if (isset($apiResponse['error'])) {
            echo "<h3>Análisis del Error:</h3>";
            echo "<p>Tipo de Error: " . ($apiResponse['error']['type'] ?? 'Desconocido') . "</p>";
            echo "<p>Mensaje: " . ($apiResponse['error']['message'] ?? 'Sin mensaje') . "</p>";
            
            if (isset($apiResponse['error']['causes'])) {
                echo "<p>Causas:</p>";
                echo "<ul>";
                foreach ($apiResponse['error']['causes'] as $cause) {
                    echo "<li>" . ($cause['description'] ?? 'Sin descripción') . "</li>";
                }
                echo "</ul>";
            }
        }
    } else {
        echo "<p>API Response: Vacía (posible problema de conectividad)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>";
    echo "<p>Stack Trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>5. Información adicional:</h2>";
echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Timezone: " . date_default_timezone_get() . "</p>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";

echo "<p><a href='dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Volver al Dashboard</a></p>";
?>
