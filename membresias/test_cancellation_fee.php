<?php
/**
 * Archivo de prueba para verificar el sistema de cobro por cancelación
 * Este archivo simula una cancelación y verifica que se procese correctamente
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Inicializar configuración
initConfig();

echo "<h1>Prueba del Sistema de Cobro por Cancelación</h1>";

try {
    // 1. Verificar que las funciones de MercadoPago estén disponibles
    echo "<h2>1. Verificación de Funciones</h2>";
    
    if (function_exists('mpCreateCancellationPaymentFromClient')) {
        echo "✅ Función mpCreateCancellationPaymentFromClient disponible<br>";
    } else {
        echo "❌ Función mpCreateCancellationPaymentFromClient NO disponible<br>";
    }
    
    if (function_exists('mpCreateCancellationPayment')) {
        echo "✅ Función mpCreateCancellationPayment disponible<br>";
    } else {
        echo "❌ Función mpCreateCancellationPayment NO disponible<br>";
    }
    
    // 2. Verificar configuración de MercadoPago
    echo "<h2>2. Verificación de Configuración</h2>";
    
    $credentials = mpVerifyCredentials();
    if ($credentials['success']) {
        echo "✅ Credenciales de MercadoPago válidas<br>";
        echo "Collector ID: " . $credentials['collector_id'] . "<br>";
    } else {
        echo "❌ Error en credenciales: " . $credentials['error'] . "<br>";
    }
    
    // 3. Verificar tipos de membresía con monto de cancelación
    echo "<h2>3. Tipos de Membresía con Cargo por Cancelación</h2>";
    
    $stmt = $pdo->prepare("SELECT tm.id, tm.nombre, tm.precio, tm.monto_cancelacion, e.nombre as empresa_nombre
                           FROM tipos_membresia tm 
                           LEFT JOIN empresas e ON tm.empresa_id = e.id 
                           WHERE tm.monto_cancelacion > 0 
                           ORDER BY tm.monto_cancelacion DESC");
    $stmt->execute();
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tipos)) {
        echo "⚠️ No hay tipos de membresía con cargo por cancelación configurado<br>";
    } else {
        echo "✅ Tipos de membresía con cargo por cancelación encontrados:<br>";
        foreach ($tipos as $tipo) {
            echo "- <strong>{$tipo['nombre']}</strong> (Empresa: {$tipo['empresa_nombre']}) - Cargo: \${$tipo['monto_cancelacion']}<br>";
        }
    }
    
    // 4. Verificar membresías canceladas
    echo "<h2>4. Membresías Canceladas</h2>";
    
    $stmt = $pdo->prepare("SELECT m.id, m.mp_status, m.mp_cancellation_fee, m.mp_cancelled_at, 
                                  c.nombre, c.apellido_paterno, c.mp_card_token,
                                  tm.nombre as tipo_nombre, tm.monto_cancelacion
                           FROM membresias m 
                           LEFT JOIN clientes c ON m.cliente_id = c.id 
                           LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                           WHERE m.mp_status = 'cancelled' 
                           ORDER BY m.mp_cancelled_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($membresias)) {
        echo "⚠️ No hay membresías canceladas<br>";
    } else {
        echo "✅ Membresías canceladas encontradas:<br>";
        foreach ($membresias as $mem) {
            $tieneToken = !empty($mem['mp_card_token']) ? '✅' : '❌';
            $cargoAplicado = $mem['mp_cancellation_fee'] > 0 ? '✅' : '❌';
            echo "- <strong>{$mem['nombre']} {$mem['apellido_paterno']}</strong> ({$mem['tipo_nombre']}) - Token: {$tieneToken} - Cargo: {$cargoAplicado} \${$mem['mp_cancellation_fee']}<br>";
        }
    }
    
    // 5. Verificar logs de pagos por cancelación
    echo "<h2>5. Logs de Pagos por Cancelación</h2>";
    
    $stmt = $pdo->prepare("SELECT * FROM mp_payment_logs 
                           WHERE payment_method = 'cancellation_fee' 
                           ORDER BY created_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "⚠️ No hay logs de pagos por cancelación<br>";
    } else {
        echo "✅ Logs de pagos por cancelación encontrados:<br>";
        foreach ($logs as $log) {
            echo "- Payment ID: {$log['mp_payment_id']} - Monto: \${$log['amount']} - Status: {$log['status']} - Fecha: {$log['created_at']}<br>";
        }
    }
    
    // 6. Prueba de función de cobro (solo si hay datos válidos)
    echo "<h2>6. Prueba de Función de Cobro</h2>";
    
    if (!empty($tipos) && !empty($membresias)) {
        $membresia = $membresias[0];
        $tipo = $tipos[0];
        
        if (!empty($membresia['mp_card_token']) && $tipo['monto_cancelacion'] > 0) {
            echo "🔧 Probando función de cobro con membresía ID: {$membresia['id']}<br>";
            echo "Cliente: {$membresia['nombre']} {$membresia['apellido_paterno']}<br>";
            echo "Monto de cancelación: \${$tipo['monto_cancelacion']}<br>";
            
            // NOTA: No ejecutar en producción, solo para pruebas
            if (isset($_GET['test_payment']) && $_GET['test_payment'] === '1') {
                echo "<strong>⚠️ EJECUTANDO PRUEBA REAL DE COBRO ⚠️</strong><br>";
                
                $result = mpCreateCancellationPaymentFromClient(
                    $membresia['cliente_id'],
                    $tipo['monto_cancelacion'],
                    'TEST_CANCEL_FEE_MEM_' . $membresia['id']
                );
                
                if ($result['success']) {
                    echo "✅ Prueba de cobro exitosa:<br>";
                    echo "- Payment ID: {$result['payment_id']}<br>";
                    echo "- Status: {$result['status']}<br>";
                } else {
                    echo "❌ Error en prueba de cobro: {$result['error']}<br>";
                    if (isset($result['debug_info'])) {
                        echo "Debug info: " . json_encode($result['debug_info']) . "<br>";
                    }
                }
            } else {
                echo "Para ejecutar prueba real de cobro, agrega ?test_payment=1 a la URL<br>";
                echo "<a href='?test_payment=1' class='btn btn-warning'>Ejecutar Prueba de Cobro</a><br>";
            }
        } else {
            echo "⚠️ No se puede probar: membresía sin token de tarjeta o tipo sin monto de cancelación<br>";
        }
    }
    
    // 7. Resumen del sistema
    echo "<h2>7. Resumen del Sistema</h2>";
    
    $totalTipos = $pdo->query("SELECT COUNT(*) FROM tipos_membresia WHERE monto_cancelacion > 0")->fetchColumn();
    $totalMembresias = $pdo->query("SELECT COUNT(*) FROM membresias WHERE mp_status = 'cancelled'")->fetchColumn();
    $totalPagosCancelacion = $pdo->query("SELECT COUNT(*) FROM mp_payment_logs WHERE payment_method = 'cancellation_fee'")->fetchColumn();
    
    echo "📊 Estadísticas del sistema:<br>";
    echo "- Tipos de membresía con cargo por cancelación: {$totalTipos}<br>";
    echo "- Membresías canceladas: {$totalMembresias}<br>";
    echo "- Pagos por cancelación procesados: {$totalPagosCancelacion}<br>";
    
    if ($totalTipos > 0 && $totalMembresias > 0) {
        echo "✅ Sistema de cobro por cancelación configurado y funcionando<br>";
    } else {
        echo "⚠️ Sistema de cobro por cancelación necesita configuración<br>";
    }
    
} catch (Throwable $e) {
    echo "<h2>❌ Error en la prueba</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><small>Archivo de prueba generado el: " . date('Y-m-d H:i:s') . "</small></p>";
?>
