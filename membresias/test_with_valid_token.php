<?php
/**
 * Archivo para probar pagos con un token válido
 * Usa la API de MercadoPago para crear un token de prueba
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Prueba con Token Válido</h1>";

try {
    // 1. Verificar configuración
    echo "<h2>1. Verificación de Configuración</h2>";
    
    $credentials = mpVerifyCredentials();
    if ($credentials['success']) {
        echo "✅ Credenciales válidas<br>";
        echo "Collector ID: " . $credentials['collector_id'] . "<br>";
    } else {
        echo "❌ Error en credenciales: " . $credentials['error'] . "<br>";
        exit;
    }
    
    // 2. Inicializar MercadoPago
    echo "<h2>2. Inicialización de MercadoPago</h2>";
    
    mpInit();
    echo "✅ MercadoPago inicializado<br>";
    
    // 3. Crear token de tarjeta de prueba
    echo "<h2>3. Creación de Token de Tarjeta de Prueba</h2>";
    
    if (isset($_GET['create_test_token'])) {
        echo "<strong>⚠️ CREANDO TOKEN DE PRUEBA ⚠️</strong><br>";
        
        // Datos de tarjeta de prueba de MercadoPago
        $cardData = [
            'card_number' => '4509953566233704',
            'security_code' => '123',
            'expiration_month' => 11,
            'expiration_year' => 2025,
            'cardholder' => [
                'name' => 'APRO'
            ]
        ];
        
        echo "📋 Datos de tarjeta de prueba:<br>";
        echo "<pre>" . json_encode($cardData, JSON_PRETTY_PRINT) . "</pre>";
        
        try {
            // Crear token usando cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/card_tokens');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cardData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo "❌ Error de cURL: " . $error . "<br>";
            } else {
                echo "✅ Respuesta de API (HTTP {$httpCode}):<br>";
                echo "<pre>" . $response . "</pre>";
                
                $tokenData = json_decode($response, true);
                
                if (isset($tokenData['id'])) {
                    $newToken = $tokenData['id'];
                    echo "✅ Token creado exitosamente: {$newToken}<br>";
                    
                    // Guardar token en la base de datos para un cliente de prueba
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("
                        UPDATE clientes 
                        SET mp_card_token = ? 
                        WHERE id = 81 
                        LIMIT 1
                    ");
                    $stmt->execute([$newToken]);
                    
                    echo "✅ Token guardado en la base de datos para cliente ID 81<br>";
                    echo "🔗 <a href='?test_payment=1&token={$newToken}'>Probar Pago con Nuevo Token</a><br>";
                    
                } else {
                    echo "❌ Error creando token: " . ($tokenData['message'] ?? 'Error desconocido') . "<br>";
                    if (isset($tokenData['error'])) {
                        echo "Error: " . $tokenData['error'] . "<br>";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Error en la creación del token: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Para crear un token de prueba, haz clic en el botón:<br>";
        echo "<a href='?create_test_token=1' class='btn btn-success'>Crear Token de Prueba</a><br>";
    }
    
    // 4. Probar pago con token válido
    if (isset($_GET['test_payment'])) {
        $token = $_GET['token'] ?? null;
        
        if (!$token) {
            // Obtener token de la base de datos
            $pdo = getConnection();
            $stmt = $pdo->prepare("SELECT mp_card_token, correo FROM clientes WHERE id = 81");
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente && $cliente['mp_card_token']) {
                $token = $cliente['mp_card_token'];
            }
        }
        
        if ($token) {
            echo "<h2>4. Prueba de Pago con Token Válido</h2>";
            echo "📋 Token: " . substr($token, 0, 20) . "...<br>";
            
            try {
                $client = new \MercadoPago\Client\Payment\PaymentClient();
                
                // Pago de prueba igual al del webhook
                $testPayment = [
                    'transaction_amount' => 500.00,
                    'token' => $token,
                    'description' => 'Prueba de cargo por cancelación',
                    'installments' => 1,
                    'payer' => [
                        'email' => 'test@prueba.com'
                    ],
                    'external_reference' => 'TEST_VALID_TOKEN_' . time(),
                    'notification_url' => MP_WEBHOOK_URL,
                    'statement_descriptor' => 'CANCELACION',
                    'binary_mode' => true,
                    'capture' => true
                ];
                
                echo "📤 Request de pago:<br>";
                echo "<pre>" . json_encode($testPayment, JSON_PRETTY_PRINT) . "</pre>";
                
                $payment = $client->create($testPayment);
                
                echo "✅ Pago creado exitosamente con token válido!<br>";
                echo "Payment ID: " . $payment->id . "<br>";
                echo "Status: " . $payment->status . "<br>";
                echo "External Reference: " . $payment->external_reference . "<br>";
                
                // Guardar en logs de pago
                $pdo = getConnection();
                $stmt = $pdo->prepare("
                    INSERT INTO mp_payment_logs 
                    (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
                ");
                $stmt->execute([
                    80, // membresia_id de prueba
                    $payment->id,
                    'TEST_SUBSCRIPTION',
                    500.00,
                    $payment->status,
                    'credit_card',
                    json_encode($payment)
                ]);
                
                echo "✅ Pago registrado en logs<br>";
                
            } catch (Exception $e) {
                echo "❌ Error en pago con token válido: " . $e->getMessage() . "<br>";
                echo "Tipo de excepción: " . get_class($e) . "<br>";
                
                if (method_exists($e, 'getApiResponse')) {
                    $apiResponse = $e->getApiResponse();
                    echo "API Response: " . json_encode($apiResponse) . "<br>";
                }
                
                if (method_exists($e, 'getCode')) {
                    echo "HTTP Code: " . $e->getCode() . "<br>";
                }
            }
        } else {
            echo "❌ No se encontró token válido para probar<br>";
            echo "Primero crea un token de prueba<br>";
        }
    }
    
    // 5. Verificar tokens existentes
    echo "<h2>5. Verificación de Tokens Existentes</h2>";
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.correo, c.mp_card_token, 
               CASE WHEN c.mp_card_token IS NOT NULL THEN 'SÍ' ELSE 'NO' END as tiene_token
        FROM clientes c 
        WHERE c.id IN (81, 80, 79) 
        ORDER BY c.id DESC
    ");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Token</th><th>Estado</th></tr>";
    
    foreach ($clientes as $cliente) {
        $token = $cliente['mp_card_token'];
        $tokenPreview = $token ? substr($token, 0, 20) . "..." : 'NO TIENE';
        $estado = $token ? '✅ CON TOKEN' : '❌ SIN TOKEN';
        
        echo "<tr>";
        echo "<td>{$cliente['id']}</td>";
        echo "<td>{$cliente['nombre']}</td>";
        echo "<td>{$cliente['correo']}</td>";
        echo "<td>{$tokenPreview}</td>";
        echo "<td>{$estado}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. Enlaces de acción
    echo "<h2>6. Acciones Disponibles</h2>";
    echo "<p><a href='?create_test_token=1' class='btn btn-success'>1. Crear Token de Prueba</a></p>";
    echo "<p><a href='?test_payment=1' class='btn btn-primary'>2. Probar Pago con Token Existente</a></p>";
    echo "<p><a href='regenerar_token_tarjeta.php' class='btn btn-warning'>3. Ir a Regeneración de Tokens</a></p>";
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en la prueba con token válido</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Tipo: " . get_class($e) . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Prueba con token válido generada el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
