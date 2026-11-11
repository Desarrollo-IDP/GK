<?php
/**
 * Archivo de diagnóstico para errores de la API de MercadoPago
 * Este archivo ayuda a identificar problemas específicos con la API
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Diagnóstico de Error de API de MercadoPago</h1>";

try {
    // 1. Verificar credenciales
    echo "<h2>1. Verificación de Credenciales</h2>";
    
    $credentials = mpVerifyCredentials();
    if ($credentials['success']) {
        echo "✅ Credenciales válidas<br>";
        echo "Collector ID: " . $credentials['collector_id'] . "<br>";
    } else {
        echo "❌ Error en credenciales: " . $credentials['error'] . "<br>";
    }
    
    // 2. Verificar configuración del SDK
    echo "<h2>2. Configuración del SDK</h2>";
    
    if (defined('MP_ACCESS_TOKEN')) {
        echo "✅ MP_ACCESS_TOKEN definido: " . substr(MP_ACCESS_TOKEN, 0, 20) . "...<br>";
    } else {
        echo "❌ MP_ACCESS_TOKEN NO definido<br>";
    }
    
    if (defined('MP_PUBLIC_KEY')) {
        echo "✅ MP_PUBLIC_KEY definido: " . substr(MP_PUBLIC_KEY, 0, 20) . "...<br>";
    } else {
        echo "❌ MP_PUBLIC_KEY NO definido<br>";
    }
    
    // 3. Verificar cliente de MercadoPago
    echo "<h2>3. Prueba de Cliente de MercadoPago</h2>";
    
    try {
        mpInit();
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        echo "✅ Cliente de Payment creado exitosamente<br>";
        
        // Intentar obtener información básica del usuario usando métodos compatibles
        try {
            // Verificar qué métodos están disponibles
            $methods = get_class_methods($client);
            echo "✅ Métodos disponibles en PaymentClient:<br>";
            foreach ($methods as $method) {
                if (strpos($method, 'get') === 0) {
                    echo "- {$method}<br>";
                }
            }
            
            // Intentar obtener información del usuario usando el método correcto
            if (method_exists($client, 'getUserInfo')) {
                $userInfo = $client->getUserInfo();
                echo "✅ Información del usuario obtenida<br>";
                echo "User ID: " . ($userInfo->id ?? 'N/A') . "<br>";
            } elseif (method_exists($client, 'getUser')) {
                $userInfo = $client->getUser();
                echo "✅ Información del usuario obtenida (método getUser)<br>";
                echo "User ID: " . ($userInfo->id ?? 'N/A') . "<br>";
            } else {
                echo "⚠️ No se encontró método para obtener información del usuario<br>";
                echo "Métodos disponibles: " . implode(', ', array_filter($methods, function($m) { return strpos($m, 'get') === 0; })) . "<br>";
            }
            
        } catch (Exception $e) {
            echo "⚠️ Error obteniendo información del usuario: " . $e->getMessage() . "<br>";
            echo "Tipo de excepción: " . get_class($e) . "<br>";
            
            if (method_exists($e, 'getApiResponse')) {
                $apiResponse = $e->getApiResponse();
                echo "API Response: " . json_encode($apiResponse) . "<br>";
            }
            
            if (method_exists($e, 'getCode')) {
                echo "HTTP Code: " . $e->getCode() . "<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error creando cliente: " . $e->getMessage() . "<br>";
        echo "Tipo de excepción: " . get_class($e) . "<br>";
    }
    
    // 4. Verificar token de tarjeta específico del error
    echo "<h2>4. Verificación del Token de Tarjeta</h2>";
    
    $token = "315fad9778c08061dcb786034f9a8c1a"; // Token del error en los logs
    
    try {
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        
        // Intentar obtener información del token
        $tokenInfo = $client->getTokenInfo($token);
        echo "✅ Información del token obtenida<br>";
        echo "Token válido: " . ($tokenInfo->valid ? 'Sí' : 'No') . "<br>";
        echo "Card ID: " . ($tokenInfo->card_id ?? 'N/A') . "<br>";
        echo "Card Type: " . ($tokenInfo->card_type ?? 'N/A') . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Error obteniendo información del token: " . $e->getMessage() . "<br>";
        echo "Tipo de excepción: " . get_class($e) . "<br>";
        
        if (method_exists($e, 'getApiResponse')) {
            $apiResponse = $e->getApiResponse();
            echo "API Response: " . json_encode($apiResponse) . "<br>";
        }
        
        if (method_exists($e, 'getCode')) {
            echo "Código de error: " . $e->getCode() . "<br>";
        }
    }
    
    // 5. Prueba de pago de prueba (solo para diagnóstico)
    echo "<h2>5. Prueba de Pago de Diagnóstico</h2>";
    
    if (isset($_GET['test_payment']) && $_GET['test_payment'] === '1') {
        echo "<strong>⚠️ EJECUTANDO PRUEBA DE PAGO DE DIAGNÓSTICO ⚠️</strong><br>";
        
        try {
            $testPayment = [
                'transaction_amount' => 1.00, // Monto mínimo para prueba
                'token' => $token,
                'description' => 'Prueba de diagnóstico - Pago mínimo',
                'installments' => 1,
                'payer' => [
                    'email' => 'test@diagnostico.com'
                ],
                'external_reference' => 'TEST_DIAG_' . time()
            ];
            
            echo "Intentando crear pago de prueba...<br>";
            echo "Request: " . json_encode($testPayment) . "<br>";
            
            $client = new \MercadoPago\Client\Payment\PaymentClient();
            $payment = $client->create($testPayment);
            
            echo "✅ Pago de prueba creado exitosamente<br>";
            echo "Payment ID: " . $payment->id . "<br>";
            echo "Status: " . $payment->status . "<br>";
            
        } catch (Exception $e) {
            echo "❌ Error en pago de prueba: " . $e->getMessage() . "<br>";
            echo "Tipo de excepción: " . get_class($e) . "<br>";
            
            if (method_exists($e, 'getApiResponse')) {
                $apiResponse = $e->getApiResponse();
                echo "API Response: " . json_encode($apiResponse) . "<br>";
            }
            
            if (method_exists($e, 'getCode')) {
                echo "Código de error: " . $e->getCode() . "<br>";
            }
            
            // Log detallado del error
            error_log('MP Test Payment Error: ' . $e->getMessage());
            error_log('MP Test Payment Error Type: ' . get_class($e));
            error_log('MP Test Payment Error Code: ' . $e->getCode());
            if (method_exists($e, 'getApiResponse')) {
                error_log('MP Test Payment API Response: ' . json_encode($e->getApiResponse()));
            }
        }
    } else {
        echo "Para ejecutar prueba de pago de diagnóstico, agrega ?test_payment=1 a la URL<br>";
        echo "<a href='?test_payment=1' class='btn btn-warning'>Ejecutar Prueba de Pago</a><br>";
    }
    
    // 6. Verificar configuración del entorno
    echo "<h2>6. Configuración del Entorno</h2>";
    
    echo "PHP Version: " . PHP_VERSION . "<br>";
    echo "MercadoPago SDK Version: ";
    
    try {
        $composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
        if (isset($composerJson['require']['mercadopago/dx-php'])) {
            echo $composerJson['require']['mercadopago/dx-php'] . "<br>";
        } else {
            echo "No encontrado en composer.json<br>";
        }
    } catch (Exception $e) {
        echo "Error leyendo composer.json: " . $e->getMessage() . "<br>";
    }
    
    // 7. Verificar permisos y configuración de red
    echo "<h2>7. Verificación de Red y Permisos</h2>";
    
    $mpUrl = 'https://api.mercadopago.com';
    $ch = curl_init($mpUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Error de cURL: " . $error . "<br>";
    } else {
        echo "✅ Conexión a MercadoPago exitosa (HTTP Code: {$httpCode})<br>";
    }
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en el diagnóstico</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Tipo: " . get_class($e) . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Archivo de diagnóstico generado el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
