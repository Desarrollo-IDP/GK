<?php
/**
 * Archivo específico para probar el error de pago de MercadoPago
 * Simula exactamente el mismo pago que está fallando en el webhook
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Prueba Específica del Error de Pago</h1>";

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
    
    // 3. Obtener datos del error específico
    echo "<h2>3. Datos del Error Específico</h2>";
    
    // Usar los mismos datos que fallaron en el webhook
    $testData = [
        'amount' => 500.00,
        'payerEmail' => '10@10',
        'paymentToken' => '315fad9778c08061dcb786034f9a8c1a',
        'externalReference' => 'TEST_CANCEL_FEE_MEM_80'
    ];
    
    echo "📋 Datos de prueba:<br>";
    echo "- Monto: \${$testData['amount']}<br>";
    echo "- Email: {$testData['payerEmail']}<br>";
    echo "- Token: {$testData['paymentToken']}<br>";
    echo "- External Reference: {$testData['externalReference']}<br>";
    
    // 4. Probar la función de pago paso a paso
    echo "<h2>4. Prueba Paso a Paso de la Función de Pago</h2>";
    
    // 4.1 Crear cliente
    try {
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        echo "✅ Cliente de Payment creado<br>";
        
        // 4.2 Verificar métodos disponibles
        $methods = get_class_methods($client);
        echo "✅ Métodos disponibles en PaymentClient:<br>";
        foreach ($methods as $method) {
            if (strpos($method, 'create') === 0 || strpos($method, 'get') === 0) {
                echo "- {$method}<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error creando cliente: " . $e->getMessage() . "<br>";
        exit;
    }
    
    // 5. Probar pago con datos mínimos
    echo "<h2>5. Prueba de Pago con Datos Mínimos</h2>";
    
    if (isset($_GET['test_minimal']) && $_GET['test_minimal'] === '1') {
        echo "<strong>⚠️ EJECUTANDO PRUEBA DE PAGO MÍNIMO ⚠️</strong><br>";
        
        try {
            // Pago mínimo para probar la API
            $minimalPayment = [
                'transaction_amount' => 1.00,
                'token' => $testData['paymentToken'],
                'description' => 'Prueba mínima de pago',
                'installments' => 1,
                'payer' => [
                    'email' => $testData['payerEmail']
                ],
                'external_reference' => 'TEST_MIN_' . time()
            ];
            
            echo "📤 Request de pago mínimo:<br>";
            echo "<pre>" . json_encode($minimalPayment, JSON_PRETTY_PRINT) . "</pre>";
            
            $payment = $client->create($minimalPayment);
            
            echo "✅ Pago mínimo creado exitosamente<br>";
            echo "Payment ID: " . $payment->id . "<br>";
            echo "Status: " . $payment->status . "<br>";
            
        } catch (Exception $e) {
            echo "❌ Error en pago mínimo: " . $e->getMessage() . "<br>";
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
        echo "Para ejecutar prueba de pago mínimo, agrega ?test_minimal=1 a la URL<br>";
        echo "<a href='?test_minimal=1' class='btn btn-warning'>Ejecutar Prueba de Pago Mínimo</a><br>";
    }
    
    // 6. Probar pago completo (igual al del webhook)
    echo "<h2>6. Prueba de Pago Completo (Igual al Webhook)</h2>";
    
    if (isset($_GET['test_full']) && $_GET['test_full'] === '1') {
        echo "<strong>⚠️ EJECUTANDO PRUEBA DE PAGO COMPLETO ⚠️</strong><br>";
        
        try {
            // Pago completo igual al que falla en el webhook
            $fullPayment = [
                'transaction_amount' => $testData['amount'],
                'token' => $testData['paymentToken'],
                'description' => 'Cargo por cancelación de suscripción',
                'installments' => 1,
                'payer' => [
                    'email' => $testData['payerEmail']
                ],
                'external_reference' => $testData['externalReference'],
                'notification_url' => MP_WEBHOOK_URL,
                'statement_descriptor' => 'CANCELACION',
                'binary_mode' => true,
                'capture' => true
            ];
            
            echo "📤 Request de pago completo:<br>";
            echo "<pre>" . json_encode($fullPayment, JSON_PRETTY_PRINT) . "</pre>";
            
            $payment = $client->create($fullPayment);
            
            echo "✅ Pago completo creado exitosamente<br>";
            echo "Payment ID: " . $payment->id . "<br>";
            echo "Status: " . $payment->status . "<br>";
            
        } catch (Exception $e) {
            echo "❌ Error en pago completo: " . $e->getMessage() . "<br>";
            echo "Tipo de excepción: " . get_class($e) . "<br>";
            
            if (method_exists($e, 'getApiResponse')) {
                $apiResponse = $e->getApiResponse();
                echo "API Response: " . json_encode($apiResponse) . "<br>";
            }
            
            if (method_exists($e, 'getCode')) {
                echo "HTTP Code: " . $e->getCode() . "<br>";
            }
            
            // Log detallado del error
            error_log('MP Full Payment Test Error: ' . $e->getMessage());
            error_log('MP Full Payment Test Error Type: ' . get_class($e));
            error_log('MP Full Payment Test Error Code: ' . $e->getCode());
            if (method_exists($e, 'getApiResponse')) {
                error_log('MP Full Payment Test API Response: ' . json_encode($e->getApiResponse()));
            }
        }
    } else {
        echo "Para ejecutar prueba de pago completo, agrega ?test_full=1 a la URL<br>";
        echo "<a href='?test_full=1' class='btn btn-warning'>Ejecutar Prueba de Pago Completo</a><br>";
    }
    
    // 7. Verificar token de tarjeta
    echo "<h2>7. Verificación del Token de Tarjeta</h2>";
    
    try {
        // Intentar obtener información del token
        if (method_exists($client, 'getTokenInfo')) {
            $tokenInfo = $client->getTokenInfo($testData['paymentToken']);
            echo "✅ Información del token obtenida<br>";
            echo "Token válido: " . ($tokenInfo->valid ? 'Sí' : 'No') . "<br>";
            echo "Card ID: " . ($tokenInfo->card_id ?? 'N/A') . "<br>";
            echo "Card Type: " . ($tokenInfo->card_type ?? 'N/A') . "<br>";
        } else {
            echo "⚠️ Método getTokenInfo no disponible en esta versión del SDK<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error obteniendo información del token: " . $e->getMessage() . "<br>";
        echo "Tipo de excepción: " . get_class($e) . "<br>";
        
        if (method_exists($e, 'getApiResponse')) {
            $apiResponse = $e->getApiResponse();
            echo "API Response: " . json_encode($apiResponse) . "<br>";
        }
        
        if (method_exists($e, 'getCode')) {
            echo "HTTP Code: " . $e->getCode() . "<br>";
        }
    }
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en la prueba específica</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Tipo: " . get_class($e) . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Prueba específica generada el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
