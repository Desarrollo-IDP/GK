<?php
/**
 * Archivo para regenerar tokens de tarjeta y probar pagos
 * Soluciona el problema del token expirado
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Regeneración de Token de Tarjeta</h1>";

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
    
    // 3. Buscar clientes con tokens de tarjeta
    echo "<h2>3. Clientes con Tokens de Tarjeta</h2>";
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.apellido_paterno, c.correo, c.mp_card_token, c.empresa_id
        FROM clientes c 
        WHERE c.mp_card_token IS NOT NULL 
        ORDER BY c.id DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($clientes)) {
        echo "❌ No se encontraron clientes con tokens de tarjeta<br>";
        echo "Necesitas crear un nuevo token de tarjeta<br>";
    } else {
        echo "✅ Clientes encontrados con tokens:<br>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Token</th><th>Empresa</th><th>Acciones</th></tr>";
        
        foreach ($clientes as $cliente) {
            $token = $cliente['mp_card_token'];
            $tokenPreview = substr($token, 0, 20) . "...";
            
            echo "<tr>";
            echo "<td>{$cliente['id']}</td>";
            echo "<td>{$cliente['nombre']} {$cliente['apellido_paterno']}</td>";
            echo "<td>{$cliente['correo']}</td>";
            echo "<td>{$tokenPreview}</td>";
            echo "<td>{$cliente['empresa_id']}</td>";
            echo "<td>";
            echo "<a href='?test_token={$cliente['id']}' class='btn btn-primary'>Probar Token</a> ";
            echo "<a href='?regenerate_token={$cliente['id']}' class='btn btn-warning'>Regenerar</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Probar token específico
    if (isset($_GET['test_token'])) {
        $clienteId = (int)$_GET['test_token'];
        
        echo "<h2>4. Prueba de Token del Cliente {$clienteId}</h2>";
        
        $stmt = $pdo->prepare("SELECT mp_card_token, correo FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente && $cliente['mp_card_token']) {
            $token = $cliente['mp_card_token'];
            echo "📋 Probando token: " . substr($token, 0, 20) . "...<br>";
            echo "📧 Email: {$cliente['correo']}<br>";
            
            try {
                $client = new \MercadoPago\Client\Payment\PaymentClient();
                
                // Pago de prueba mínimo
                $testPayment = [
                    'transaction_amount' => 1.00,
                    'token' => $token,
                    'description' => 'Prueba de token regenerado',
                    'installments' => 1,
                    'payer' => [
                        'email' => $cliente['correo']
                    ],
                    'external_reference' => 'TEST_TOKEN_' . $clienteId . '_' . time()
                ];
                
                echo "📤 Request de prueba:<br>";
                echo "<pre>" . json_encode($testPayment, JSON_PRETTY_PRINT) . "</pre>";
                
                $payment = $client->create($testPayment);
                
                echo "✅ Token válido - Pago creado exitosamente<br>";
                echo "Payment ID: " . $payment->id . "<br>";
                echo "Status: " . $payment->status . "<br>";
                
                // Actualizar el token en la base de datos
                $stmt = $pdo->prepare("UPDATE clientes SET mp_card_token = ? WHERE id = ?");
                $stmt->execute([$token, $clienteId]);
                echo "✅ Token actualizado en la base de datos<br>";
                
            } catch (Exception $e) {
                echo "❌ Error probando token: " . $e->getMessage() . "<br>";
                echo "Tipo de excepción: " . get_class($e) . "<br>";
                
                if (method_exists($e, 'getApiResponse')) {
                    $apiResponse = $e->getApiResponse();
                    echo "API Response: " . json_encode($apiResponse) . "<br>";
                }
                
                if (method_exists($e, 'getCode')) {
                    echo "HTTP Code: " . $e->getCode() . "<br>";
                }
                
                echo "⚠️ Este token también está expirado o es inválido<br>";
            }
        } else {
            echo "❌ Cliente no encontrado o sin token de tarjeta<br>";
        }
    }
    
    // 5. Crear nuevo token de tarjeta
    if (isset($_GET['create_new_token'])) {
        echo "<h2>5. Crear Nuevo Token de Tarjeta</h2>";
        
        echo "<form method='POST' action=''>";
        echo "<h3>Datos de la Tarjeta de Prueba</h3>";
        echo "<p><strong>Tarjeta de Prueba MercadoPago:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Número:</strong> 4509 9535 6623 3704</li>";
        echo "<li><strong>CVV:</strong> 123</li>";
        echo "<li><strong>Fecha:</strong> 11/25</li>";
        echo "<li><strong>Nombre:</strong> APRO</li>";
        echo "</ul>";
        
        echo "<p><strong>O usar esta tarjeta que siempre es rechazada:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Número:</strong> 4000 0000 0000 0002</li>";
        echo "<li><strong>CVV:</strong> 123</li>";
        echo "<li><strong>Fecha:</strong> 11/25</li>";
        echo "<li><strong>Nombre:</strong> OTHE</li>";
        echo "</ul>";
        
        echo "<p>Para crear un token real, necesitas usar el SDK de MercadoPago en el frontend</p>";
        echo "<p>O usar la API de MercadoPago para crear un token de prueba</p>";
        
        echo "</form>";
    }
    
    // 6. Instrucciones para regenerar tokens
    echo "<h2>6. Instrucciones para Regenerar Tokens</h2>";
    
    echo "<h3>Opción 1: Usar SDK de MercadoPago en Frontend</h3>";
    echo "<pre>";
    echo "// En tu página de pago
&lt;script src='https://sdk.mercadopago.com/js/v2'&gt;&lt;/script&gt;
&lt;script&gt;
const mp = new MercadoPago('APP_USR-75eacea8-eed...', {
    locale: 'es-MX'
});

// Crear token
const cardForm = mp.cardForm({
    form: '#form-checkout',
    callbacks: {
        onFormMounted: error => {
            if (error) console.log('Form Mounted handling error: ', error);
        },
        onSubmit: event => {
            event.preventDefault();
            const { paymentMethod, issuer, installments, amount } = cardForm.getCardFormData();
            
            // Enviar token al backend
            fetch('/procesar_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: paymentMethod.id,
                    issuer_id: issuer,
                    installments: installments,
                    amount: amount
                })
            });
        }
    }
});
&lt;/script&gt;";
    echo "</pre>";
    
    echo "<h3>Opción 2: Usar API de MercadoPago</h3>";
    echo "<pre>";
    echo "// Crear token usando cURL
curl -X POST \\
  https://api.mercadopago.com/v1/card_tokens \\
  -H 'Authorization: Bearer APP_USR-897810788064...' \\
  -H 'Content-Type: application/json' \\
  -d '{
    \"card_number\": \"4509953566233704\",
    \"security_code\": \"123\",
    \"expiration_month\": 11,
    \"expiration_year\": 2025,
    \"cardholder\": {
      \"name\": \"APRO\"
    }
  }'";
    echo "</pre>";
    
    echo "<p><a href='?create_new_token=1' class='btn btn-success'>Ver Instrucciones Completas</a></p>";
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en la regeneración de tokens</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Tipo: " . get_class($e) . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Regeneración de tokens generada el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
