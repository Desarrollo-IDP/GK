<?php
/**
 * Archivo para probar la configuración de MercadoPago paso a paso
 * Ayuda a identificar problemas específicos de configuración
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Prueba de Configuración de MercadoPago</h1>";

try {
    // 1. Verificar constantes
    echo "<h2>1. Verificación de Constantes</h2>";
    
    $constants = [
        'MP_ACCESS_TOKEN' => defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : 'NO DEFINIDA',
        'MP_PUBLIC_KEY' => defined('MP_PUBLIC_KEY') ? MP_PUBLIC_KEY : 'NO DEFINIDA',
        'MP_WEBHOOK_URL' => defined('MP_WEBHOOK_URL') ? MP_WEBHOOK_URL : 'NO DEFINIDA'
    ];
    
    foreach ($constants as $name => $value) {
        if ($value !== 'NO DEFINIDA') {
            echo "✅ {$name}: " . substr($value, 0, 20) . "...<br>";
        } else {
            echo "❌ {$name}: NO DEFINIDA<br>";
        }
    }
    
    // 2. Verificar autoload
    echo "<h2>2. Verificación de Autoload</h2>";
    
    $autoloadFile = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoloadFile)) {
        echo "✅ Autoload encontrado: {$autoloadFile}<br>";
        require_once $autoloadFile;
    } else {
        echo "❌ Autoload NO encontrado<br>";
        exit;
    }
    
    // 3. Verificar clases disponibles
    echo "<h2>3. Verificación de Clases</h2>";
    
    $classes = [
        'MercadoPago\MercadoPagoConfig',
        'MercadoPago\Client\Payment\PaymentClient',
        'MercadoPago\Exceptions\MPApiException'
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✅ Clase disponible: {$class}<br>";
        } else {
            echo "❌ Clase NO disponible: {$class}<br>";
        }
    }
    
    // 4. Inicializar MercadoPago
    echo "<h2>4. Inicialización de MercadoPago</h2>";
    
    try {
        mpInit();
        echo "✅ MercadoPago inicializado<br>";
        
        // Verificar que se configuró el token de manera más segura
        try {
            $reflection = new ReflectionClass('MercadoPago\MercadoPagoConfig');
            $properties = $reflection->getProperties();
            
            echo "✅ Propiedades disponibles en MercadoPagoConfig:<br>";
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue();
                echo "- {$property->getName()}: " . (is_string($value) ? substr($value, 0, 20) . "..." : json_encode($value)) . "<br>";
            }
            
            // Buscar la propiedad del token de acceso
            $accessTokenFound = false;
            foreach ($properties as $property) {
                if (strpos(strtolower($property->getName()), 'token') !== false || 
                    strpos(strtolower($property->getName()), 'access') !== false) {
                    $property->setAccessible(true);
                    $value = $property->getValue();
                    if ($value && is_string($value) && strpos($value, 'APP_USR-') === 0) {
                        echo "✅ Token configurado en propiedad: {$property->getName()}<br>";
                        $accessTokenFound = true;
                        break;
                    }
                }
            }
            
            if (!$accessTokenFound) {
                echo "⚠️ No se pudo verificar la configuración del token<br>";
            }
            
        } catch (Exception $reflectionError) {
            echo "⚠️ Error en reflection: " . $reflectionError->getMessage() . "<br>";
            echo "✅ MercadoPago inicializado correctamente (verificación de reflection falló)<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error inicializando MercadoPago: " . $e->getMessage() . "<br>";
    }
    
    // 5. Prueba de cliente básico
    echo "<h2>5. Prueba de Cliente Básico</h2>";
    
    try {
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        echo "✅ Cliente de Payment creado<br>";
        
        // Verificar qué métodos están disponibles
        $methods = get_class_methods($client);
        echo "✅ Métodos disponibles en PaymentClient:<br>";
        foreach ($methods as $method) {
            if (strpos($method, 'get') === 0) {
                echo "- {$method}<br>";
            }
        }
        
        // Intentar obtener información del usuario usando el método correcto
        try {
            if (method_exists($client, 'getUserInfo')) {
                $userInfo = $client->getUserInfo();
                echo "✅ Información del usuario obtenida<br>";
                echo "User ID: " . ($userInfo->id ?? 'N/A') . "<br>";
                echo "Nickname: " . ($userInfo->nickname ?? 'N/A') . "<br>";
                echo "Email: " . ($userInfo->email ?? 'N/A') . "<br>";
            } elseif (method_exists($client, 'getUser')) {
                $userInfo = $client->getUser();
                echo "✅ Información del usuario obtenida (método getUser)<br>";
                echo "User ID: " . ($userInfo->id ?? 'N/A') . "<br>";
                echo "Nickname: " . ($userInfo->nickname ?? 'N/A') . "<br>";
                echo "Email: " . ($userInfo->email ?? 'N/A') . "<br>";
            } else {
                echo "⚠️ No se encontró método para obtener información del usuario<br>";
                echo "Métodos disponibles: " . implode(', ', array_filter($methods, function($m) { return strpos($m, 'get') === 0; })) . "<br>";
            }
            
        } catch (Exception $e) {
            echo "⚠️ Error obteniendo información del usuario: " . $e->getMessage() . "<br>";
            
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
        echo "Tipo: " . get_class($e) . "<br>";
    }
    
    // 6. Verificar permisos de la cuenta
    echo "<h2>6. Verificación de Permisos</h2>";
    
    try {
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        
        // Intentar crear un pago de prueba mínimo
        $testPayment = [
            'transaction_amount' => 1.00,
            'token' => 'test_token', // Token de prueba
            'description' => 'Prueba de permisos',
            'installments' => 1,
            'payer' => [
                'email' => 'test@test.com'
            ],
            'external_reference' => 'TEST_PERM_' . time()
        ];
        
        echo "Intentando crear pago de prueba para verificar permisos...<br>";
        
        try {
            $payment = $client->create($testPayment);
            echo "✅ Permisos verificados - Pago creado exitosamente<br>";
            
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $statusCode = $e->getCode();
            
            echo "⚠️ Error de API (esperado para token inválido):<br>";
            echo "- Status Code: {$statusCode}<br>";
            echo "- Message: " . $e->getMessage() . "<br>";
            
            if ($apiResponse) {
                // Verificar si apiResponse es un objeto MPResponse
                if (is_object($apiResponse) && method_exists($apiResponse, 'getContent')) {
                    $content = $apiResponse->getContent();
                    if (is_array($content)) {
                        echo "- API Response: " . json_encode($content) . "<br>";
                        
                        // Verificar si es un error de permisos
                        if (isset($content['error']) && $content['error'] === 'unauthorized') {
                            echo "❌ Error de permisos - Token no autorizado<br>";
                        } elseif (isset($content['error']) && $content['error'] === 'forbidden') {
                            echo "❌ Error de permisos - Acceso denegado<br>";
                        } else {
                            echo "✅ Permisos OK - Error esperado para token inválido<br>";
                        }
                    } else {
                        echo "- API Response (raw): " . json_encode($apiResponse) . "<br>";
                    }
                } else {
                    echo "- API Response: " . json_encode($apiResponse) . "<br>";
                    
                    // Verificar si es un error de permisos
                    if (isset($apiResponse['error']) && $apiResponse['error'] === 'unauthorized') {
                        echo "❌ Error de permisos - Token no autorizado<br>";
                    } elseif (isset($apiResponse['error']) && $apiResponse['error'] === 'forbidden') {
                        echo "❌ Error de permisos - Acceso denegado<br>";
                    } else {
                        echo "✅ Permisos OK - Error esperado para token inválido<br>";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Error inesperado: " . $e->getMessage() . "<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error en verificación de permisos: " . $e->getMessage() . "<br>";
    }
    
    // 7. Resumen de configuración
    echo "<h2>7. Resumen de Configuración</h2>";
    
    $issues = [];
    
    if (!defined('MP_ACCESS_TOKEN') || empty(MP_ACCESS_TOKEN)) {
        $issues[] = 'MP_ACCESS_TOKEN no está configurado';
    }
    
    if (!defined('MP_PUBLIC_KEY') || empty(MP_PUBLIC_KEY)) {
        $issues[] = 'MP_PUBLIC_KEY no está configurado';
    }
    
    if (!class_exists('MercadoPago\Client\Payment\PaymentClient')) {
        $issues[] = 'Clases de MercadoPago no están disponibles';
    }
    
    if (empty($issues)) {
        echo "✅ Configuración básica correcta<br>";
        echo "El sistema debería funcionar correctamente<br>";
    } else {
        echo "❌ Problemas de configuración detectados:<br>";
        foreach ($issues as $issue) {
            echo "- {$issue}<br>";
        }
    }
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en la prueba de configuración</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Tipo: " . get_class($e) . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Prueba de configuración generada el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
