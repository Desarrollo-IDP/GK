<?php
// Verificar plan de Mercado Pago
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

echo "<h1>🔍 Verificación de Plan de Mercado Pago</h1>";

// Verificar si hay sesión activa
if (!isset($_SESSION['cliente'])) {
    echo "<p style='color: red;'>❌ No hay sesión activa. Por favor, inicia sesión primero.</p>";
    echo "<p><a href='login.php'>Ir al Login</a></p>";
    exit;
}

$cliente_id = $_SESSION['cliente']['id'];

// Obtener membresía del cliente
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

echo "<h2>Datos de la Membresía:</h2>";
echo "<ul>";
echo "<li><strong>Plan:</strong> {$membresia['plan_nombre']}</li>";
echo "<li><strong>Precio:</strong> \${$membresia['precio']}</li>";
echo "<li><strong>MP Plan ID:</strong> {$membresia['mp_plan_id']}</li>";
echo "</ul>";

echo "<h2>Verificando Plan en Mercado Pago...</h2>";

try {
    // Inicializar Mercado Pago
    mpInit();
    
    // Crear cliente para verificar el plan
    $planClient = new \MercadoPago\Client\PreApprovalPlan\PreApprovalPlanClient();
    
    echo "<p>Intentando obtener información del plan: <strong>{$membresia['mp_plan_id']}</strong></p>";
    
    $plan = $planClient->get($membresia['mp_plan_id']);
    
    if ($plan) {
        echo "<p style='color: green;'>✅ Plan encontrado en Mercado Pago!</p>";
        echo "<h3>Información del Plan:</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$plan->id}</li>";
        echo "<li><strong>Status:</strong> {$plan->status}</li>";
        echo "<li><strong>Reason:</strong> {$plan->reason}</li>";
        echo "<li><strong>Auto Recurring:</strong> " . ($plan->auto_recurring ? 'Sí' : 'No') . "</li>";
        echo "<li><strong>Frequency:</strong> {$plan->frequency}</li>";
        echo "<li><strong>Frequency Type:</strong> {$plan->frequency_type}</li>";
        echo "<li><strong>Transaction Amount:</strong> {$plan->transaction_amount}</li>";
        echo "<li><strong>Currency ID:</strong> {$plan->currency_id}</li>";
        echo "</ul>";
        
        if ($plan->status === 'active') {
            echo "<p style='color: green;'>✅ El plan está activo y puede usarse para suscripciones</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ El plan no está activo. Status: {$plan->status}</p>";
            echo "<p>Esto puede ser la causa del error al crear suscripciones.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Plan no encontrado en Mercado Pago</p>";
    }
    
} catch (\MercadoPago\Exceptions\MPApiException $e) {
    echo "<p style='color: red;'>❌ Error de API de Mercado Pago:</p>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    
    $apiResponse = $e->getApiResponse();
    if (!empty($apiResponse)) {
        echo "<h3>Respuesta de la API:</h3>";
        echo "<pre>" . json_encode($apiResponse, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($apiResponse['error'])) {
            echo "<h3>Análisis del Error:</h3>";
            echo "<ul>";
            echo "<li><strong>Tipo:</strong> " . ($apiResponse['error']['type'] ?? 'Desconocido') . "</li>";
            echo "<li><strong>Mensaje:</strong> " . ($apiResponse['error']['message'] ?? 'Sin mensaje') . "</li>";
            
            if (isset($apiResponse['error']['causes'])) {
                echo "<li><strong>Causas:</strong></li>";
                echo "<ul>";
                foreach ($apiResponse['error']['causes'] as $cause) {
                    echo "<li>" . ($cause['description'] ?? 'Sin descripción') . "</li>";
                }
                echo "</ul>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Respuesta de API vacía</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
}

echo "<h2>Recomendaciones:</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #007bff;'>";
echo "<h3>Si el plan no existe o no está activo:</h3>";
echo "<ol>";
echo "<li><strong>Crear un nuevo plan:</strong> Ve a tu panel de Mercado Pago y crea un nuevo plan de suscripción</li>";
echo "<li><strong>Actualizar la base de datos:</strong> Actualiza el campo mp_plan_id en la tabla tipos_membresia</li>";
echo "<li><strong>Verificar permisos:</strong> Asegúrate de que tu cuenta tenga permisos para crear planes y suscripciones</li>";
echo "<li><strong>Usar preferencias:</strong> Como alternativa, puedes usar preferencias de pago que ya funcionan</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Volver al Dashboard</a></p>";
?>
