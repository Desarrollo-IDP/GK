<?php
// Archivo para verificar el estado de la base de datos
include '../includes/db.php';
include '../includes/config.php';

initConfig();

echo "<h1>Verificación de Base de Datos</h1>";

try {
    echo "<h2>1. Verificando tablas principales:</h2>";
    
    // Verificar tabla clientes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clientes");
    $stmt->execute();
    $clientes_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>✅ Tabla clientes: $clientes_count registros</p>";
    
    // Verificar tabla tipos_membresia
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tipos_membresia");
    $stmt->execute();
    $planes_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>✅ Tabla tipos_membresia: $planes_count registros</p>";
    
    // Verificar tabla membresias
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM membresias");
    $stmt->execute();
    $membresias_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>✅ Tabla membresias: $membresias_count registros</p>";
    
    // Verificar tabla payment_preferences
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'payment_preferences'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payment_preferences");
        $stmt->execute();
        $preferences_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>✅ Tabla payment_preferences: $preferences_count registros</p>";
    } else {
        echo "<p>❌ Tabla payment_preferences: No existe</p>";
    }
    
    echo "<h2>2. Verificando sesión actual:</h2>";
    if (isset($_SESSION['cliente'])) {
        $cliente = $_SESSION['cliente'];
        echo "<p>✅ Cliente logueado: " . $cliente['nombre'] . " (ID: " . $cliente['id'] . ")</p>";
        echo "<p>Email: " . $cliente['correo'] . "</p>";
        
        // Verificar si el cliente existe en la base de datos
        $stmt = $pdo->prepare("SELECT id, nombre, correo FROM clientes WHERE id = ?");
        $stmt->execute([$cliente['id']]);
        $cliente_db = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente_db) {
            echo "<p>✅ Cliente encontrado en BD: " . $cliente_db['nombre'] . "</p>";
        } else {
            echo "<p>❌ Cliente NO encontrado en BD</p>";
        }
    } else {
        echo "<p>❌ No hay sesión activa</p>";
    }
    
    echo "<h2>3. Planes disponibles:</h2>";
    $stmt = $pdo->prepare("SELECT id, nombre, precio, duracion_dias, is_active FROM tipos_membresia ORDER BY precio ASC");
    $stmt->execute();
    $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($planes) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Duración</th><th>Activo</th></tr>";
        foreach ($planes as $plan) {
            $activo = $plan['is_active'] ? 'Sí' : 'No';
            echo "<tr>";
            echo "<td>" . $plan['id'] . "</td>";
            echo "<td>" . $plan['nombre'] . "</td>";
            echo "<td>$" . $plan['precio'] . "</td>";
            echo "<td>" . $plan['duracion_dias'] . " días</td>";
            echo "<td>$activo</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No hay planes disponibles</p>";
    }
    
    echo "<h2>4. Membresías del cliente actual:</h2>";
    if (isset($_SESSION['cliente'])) {
        $cliente_id = $_SESSION['cliente']['id'];
        $stmt = $pdo->prepare("
            SELECT m.id, m.mp_status, tm.nombre as plan_nombre, m.created_at
            FROM membresias m
            LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
            WHERE m.cliente_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$cliente_id]);
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($membresias) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Plan</th><th>Estado</th><th>Creada</th></tr>";
            foreach ($membresias as $membresia) {
                echo "<tr>";
                echo "<td>" . $membresia['id'] . "</td>";
                echo "<td>" . ($membresia['plan_nombre'] ?? 'Sin plan') . "</td>";
                echo "<td>" . $membresia['mp_status'] . "</td>";
                echo "<td>" . $membresia['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>ℹ️ No hay membresías para este cliente</p>";
        }
    }
    
    echo "<h2>5. Verificación de configuración:</h2>";
    echo "<p>MP_ACCESS_TOKEN configurado: " . (defined('MP_ACCESS_TOKEN') && !empty(MP_ACCESS_TOKEN) ? 'Sí' : 'No') . "</p>";
    echo "<p>MP_PUBLIC_KEY configurado: " . (defined('MP_PUBLIC_KEY') && !empty(MP_PUBLIC_KEY) ? 'Sí' : 'No') . "</p>";
    
    if (defined('MP_ACCESS_TOKEN')) {
        echo "<p>Token length: " . strlen(MP_ACCESS_TOKEN) . " caracteres</p>";
    }
    
    echo "<h2>6. Información del sistema:</h2>";
    echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>Timezone: " . date_default_timezone_get() . "</p>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h2>7. Enlaces útiles:</h2>";
echo "<p><a href='dashboard.php'>← Volver al Dashboard</a></p>";
echo "<p><a href='test_dashboard_flow.php'>Probar Flujo del Dashboard</a></p>";
echo "<p><a href='test_subscription_flow.php'>Probar Flujo Completo</a></p>";
echo "<p><a href='test_mp_connection.php'>Probar Conexión MP</a></p>";
?>
