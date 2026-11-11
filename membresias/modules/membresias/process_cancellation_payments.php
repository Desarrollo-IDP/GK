<?php
/**
 * Script para procesar pagos por cancelación pendientes
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

echo "Iniciando procesamiento de pagos por cancelación pendientes...\n";

try {
    // Buscar membresías canceladas con cargo por cancelación pero sin pago procesado
    $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.monto_cancelacion, tm.nombre as tipo_nombre
            FROM membresias m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
            WHERE m.mp_status = 'cancelled' 
            AND m.mp_cancellation_fee > 0
            AND m.mp_cancelled_at IS NOT NULL
            AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
            AND m.mp_cancellation_reason NOT LIKE '%Error en cobro%'
            ORDER BY m.mp_cancelled_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($membresias) . " membresías con cargo por cancelación pendiente.\n";
    
    foreach ($membresias as $membresia) {
        echo "Procesando membresía ID: " . $membresia['id'] . " - Cliente: " . $membresia['nombre'] . " " . $membresia['apellido_paterno'] . "\n";
        
        try {
            // Verificar si el cliente tiene token de tarjeta
            $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id FROM clientes WHERE id = ?");
            $clienteStmt->execute([$membresia['cliente_id']]);
            $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente || !$cliente['mp_card_token']) {
                echo "  - Cliente sin token de tarjeta, saltando...\n";
                continue;
            }
            
            // Intentar cobro automático
            $result = mpCreateCancellationPaymentFromClient(
                $membresia['cliente_id'],
                $membresia['mp_cancellation_fee'],
                'CANCEL_FEE_MEM_' . $membresia['id']
            );
            
            if ($result['success']) {
                echo "  - Pago por cancelación creado exitosamente: " . $result['payment_id'] . "\n";
                
                // Registrar el pago en la tabla de logs
                $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                    (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([
                    $membresia['id'],
                    $result['payment_id'],
                    $membresia['mp_preapproval_id'],
                    $membresia['mp_cancellation_fee'],
                    $result['status'],
                    'cancellation_fee',
                    date('Y-m-d H:i:s'),
                    json_encode($result)
                ]);
                
                // Actualizar la membresía
                $updateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado: ', ?)
                    WHERE id = ?");
                $updateStmt->execute([$result['payment_id'], $membresia['id']]);
                
                // Enviar email de confirmación
                $clienteData = [
                    'nombre' => $membresia['nombre'],
                    'apellido_paterno' => $membresia['apellido_paterno'],
                    'correo' => $membresia['correo']
                ];
                
                $tipoData = [
                    'nombre' => $membresia['tipo_nombre'],
                    'precio' => $membresia['mp_cancellation_fee']
                ];
                
                $empresaData = [
                    'nombre' => 'Nuestra Empresa'
                ];
                
                sendCancellationEmail($clienteData, $tipoData, $empresaData, $membresia['mp_cancellation_fee']);
                echo "  - Email de confirmación enviado\n";
                
            } else {
                echo "  - Error al crear pago: " . ($result['error'] ?? 'Error desconocido') . "\n";
                
                // Actualizar la membresía con información del error
                $errorUpdateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Error en procesamiento: ', ?)
                    WHERE id = ?");
                $errorUpdateStmt->execute([$result['error'], $membresia['id']]);
            }
            
        } catch (Throwable $e) {
            echo "  - Excepción procesando membresía: " . $e->getMessage() . "\n";
            
            // Actualizar la membresía con información del error
            $errorUpdateStmt = $pdo->prepare("UPDATE membresias SET 
                mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Excepción: ', ?)
                WHERE id = ?");
            $errorUpdateStmt->execute([$e->getMessage(), $membresia['id']]);
        }
        
    }
    
    echo "Procesamiento completado.\n";
    
} catch (Throwable $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    error_log('Process cancellation payments error: ' . $e->getMessage());
    error_log('Process cancellation payments stack trace: ' . $e->getTraceAsString());
}

echo "Script finalizado.\n";
?>
