<?php
/**
 * Script para verificar el estado de los pagos por cancelación
 * Este script se puede ejecutar manualmente o mediante cron job
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../config/mercadopago_config.php';

// Inicializar configuración
initConfig();

// Verificar autenticación (opcional para scripts automáticos)
if (php_sapi_name() !== 'cli') {
    if (!isAuthenticated()) {
        header('Location: ../../login.php');
        exit;
    }
}

echo "Iniciando verificación de pagos por cancelación...\n";

try {
    // Buscar pagos por cancelación en estado pendiente
    $sql = "SELECT mpl.*, m.id as membresia_id, m.mp_cancellation_reason, c.nombre, c.apellido_paterno
            FROM mp_payment_logs mpl
            LEFT JOIN membresias m ON mpl.membresia_id = m.id
            LEFT JOIN clientes c ON m.cliente_id = c.id
            WHERE mpl.payment_method = 'cancellation_fee'
            AND mpl.status IN ('pending', 'in_process')
            ORDER BY mpl.payment_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontrados " . count($payments) . " pagos por cancelación pendientes de verificación.\n";
    
    foreach ($payments as $payment) {
        echo "Verificando pago ID: " . $payment['mp_payment_id'] . " - Cliente: " . $payment['nombre'] . " " . $payment['apellido_paterno'] . "\n";
        
        try {
            // Verificar estado del pago en MercadoPago
            $result = mpCheckCancellationPaymentStatus($payment['mp_payment_id']);
            
            if ($result['success']) {
                $status = $result['status'];
                echo "  - Estado actual: " . $status . "\n";
                
                if ($status === 'approved') {
                    echo "  - Pago aprobado exitosamente\n";
                } elseif ($status === 'rejected' || $status === 'cancelled') {
                    echo "  - Pago rechazado o cancelado\n";
                } elseif ($status === 'pending' || $status === 'in_process') {
                    echo "  - Pago aún pendiente\n";
                }
                
            } else {
                echo "  - Error verificando pago: " . ($result['error'] ?? 'Error desconocido') . "\n";
            }
            
        } catch (Throwable $e) {
            echo "  - Excepción verificando pago: " . $e->getMessage() . "\n";
        }
        
    }
    
    echo "Verificación completada.\n";
    
} catch (Throwable $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    error_log('Check cancellation payments error: ' . $e->getMessage());
    error_log('Check cancellation payments stack trace: ' . $e->getTraceAsString());
}

echo "Script finalizado.\n";
?>
