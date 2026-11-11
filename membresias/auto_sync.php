<?php
/**
 * Script automatizado para sincronización y verificación de pagos y cancelaciones
 * Este script se puede ejecutar manualmente o programar con cron
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Configurar logging
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

function autoLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/auto_sync.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

autoLog('=== SINCRONIZACIÓN AUTOMATIZADA INICIADA ===');

try {
    // Inicializar MercadoPago
    mpInit();
    
    // 1. Procesar cargos por cancelación pendientes automáticamente
    autoLog('1. Procesando cargos por cancelación pendientes...');
    
    $pendingChargesStmt = $pdo->prepare("
        SELECT 
            m.id,
            m.cliente_id,
            m.tipo_membresia_id,
            c.nombre,
            c.apellido_paterno,
            c.mp_card_token,
            tm.monto_cancelacion,
            tm.nombre as tipo_nombre
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
        WHERE m.mp_status = 'cancelled'
        AND tm.monto_cancelacion > 0
        AND m.mp_cancelled_at IS NOT NULL
        AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
        AND m.mp_cancellation_reason NOT LIKE '%Pago procesado%'
        AND c.mp_card_token IS NOT NULL
        ORDER BY m.mp_cancelled_at DESC
        LIMIT 10
    ");
    $pendingChargesStmt->execute();
    $pendingCharges = $pendingChargesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    autoLog('Cargos pendientes encontrados: ' . count($pendingCharges));
    
    $processedCount = 0;
    foreach ($pendingCharges as $charge) {
        try {
            autoLog('Procesando cargo automático', [
                'membresia_id' => $charge['id'],
                'cliente' => $charge['nombre'] . ' ' . $charge['apellido_paterno'],
                'monto' => $charge['monto_cancelacion']
            ]);
            
            $result = mpCreateCancellationPaymentFromClient(
                $charge['cliente_id'],
                $charge['monto_cancelacion'],
                'CANCEL_FEE_MEM_' . $charge['id'] . '_AUTO'
            );
            
            if ($result['success']) {
                // Registrar el pago en la tabla de logs
                $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                    (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([
                    $charge['id'],
                    $result['payment_id'],
                    null,
                    $charge['monto_cancelacion'],
                    $result['status'],
                    'cancellation_fee',
                    date('Y-m-d H:i:s'),
                    json_encode($result)
                ]);
                
                // Actualizar la membresía
                $updateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_cancellation_fee = ?,
                    mp_cancellation_reason = CONCAT(COALESCE(mp_cancellation_reason, ''), ' - Pago procesado automáticamente: ', ?)
                    WHERE id = ?");
                $updateStmt->execute([$charge['monto_cancelacion'], $result['payment_id'], $charge['id']]);
                
                $processedCount++;
                autoLog('Cargo procesado exitosamente', [
                    'membresia_id' => $charge['id'],
                    'payment_id' => $result['payment_id']
                ]);
            } else {
                autoLog('Error procesando cargo', [
                    'membresia_id' => $charge['id'],
                    'error' => $result['error'] ?? 'Error desconocido'
                ]);
            }
            
        } catch (Throwable $e) {
            autoLog('Excepción procesando cargo', [
                'membresia_id' => $charge['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    autoLog("Cargos procesados: $processedCount de " . count($pendingCharges));
    
    // 2. Sincronizar estados de membresías con MercadoPago
    autoLog('\n2. Sincronizando estados de membresías...');
    
    $syncStmt = $pdo->prepare("
        SELECT 
            m.id,
            m.mp_preapproval_id,
            m.mp_status,
            c.nombre,
            c.apellido_paterno
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        WHERE m.mp_preapproval_id IS NOT NULL
        AND m.mp_preapproval_id != ''
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $syncStmt->execute();
    $membresias = $syncStmt->fetchAll(PDO::FETCH_ASSOC);
    
    autoLog('Membresías para sincronizar: ' . count($membresias));
    
    $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
    $syncedCount = 0;
    
    foreach ($membresias as $membresia) {
        try {
            $preapproval = $client->get($membresia['mp_preapproval_id']);
            
            if ($preapproval) {
                $mpStatus = $preapproval->status ?? null;
                
                if ($membresia['mp_status'] !== $mpStatus) {
                    // Actualizar estado
                    $updateStmt = $pdo->prepare("UPDATE membresias SET 
                        mp_status = ?,
                        mp_next_payment_date = ?,
                        mp_last_payment_date = ?
                        WHERE id = ?");
                    $updateStmt->execute([
                        $mpStatus,
                        $preapproval->next_payment_date ?? null,
                        $preapproval->last_payment_date ?? null,
                        $membresia['id']
                    ]);
                    
                    $syncedCount++;
                    autoLog('Estado sincronizado', [
                        'membresia_id' => $membresia['id'],
                        'old_status' => $membresia['mp_status'],
                        'new_status' => $mpStatus
                    ]);
                }
            }
            
        } catch (Throwable $e) {
            autoLog('Error sincronizando membresía', [
                'membresia_id' => $membresia['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    autoLog("Estados sincronizados: $syncedCount");
    
    // 3. Limpiar logs antiguos (mantener solo los últimos 30 días)
    autoLog('\n3. Limpiando logs antiguos...');
    
    $cleanupStmt = $pdo->prepare("
        DELETE FROM mp_payment_logs 
        WHERE payment_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $cleanupStmt->execute();
    $deletedRows = $cleanupStmt->rowCount();
    
    autoLog("Logs antiguos eliminados: $deletedRows registros");
    
    // 4. Verificar inconsistencias y corregirlas
    autoLog('\n4. Verificando y corrigiendo inconsistencias...');
    
    // Corregir membresías canceladas sin cargo cuando deberían tenerlo
    $fixStmt = $pdo->prepare("
        UPDATE membresias m
        LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
        SET m.mp_cancellation_fee = tm.monto_cancelacion
        WHERE m.mp_status = 'cancelled'
        AND m.mp_cancellation_fee = 0
        AND tm.monto_cancelacion > 0
    ");
    $fixStmt->execute();
    $fixedRows = $fixStmt->rowCount();
    
    autoLog("Membresías corregidas: $fixedRows");
    
    // 5. Generar reporte final
    autoLog('\n5. Generando reporte final...');
    
    $reportStmt = $pdo->prepare("
        SELECT 
            mp_status,
            COUNT(*) as count
        FROM membresias 
        WHERE mp_preapproval_id IS NOT NULL
        GROUP BY mp_status
        ORDER BY count DESC
    ");
    $reportStmt->execute();
    $report = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    autoLog('Estado final de membresías:');
    foreach ($report as $stat) {
        autoLog("  - {$stat['mp_status']}: {$stat['count']} membresías");
    }
    
    // Contar cargos pendientes
    $pendingStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM membresias m
        LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
        WHERE m.mp_status = 'cancelled'
        AND tm.monto_cancelacion > 0
        AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
        AND m.mp_cancellation_reason NOT LIKE '%Pago procesado%'
    ");
    $pendingStmt->execute();
    $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    autoLog("Cargos por cancelación pendientes: $pendingCount");
    
    autoLog('\n=== SINCRONIZACIÓN AUTOMATIZADA COMPLETADA ===');
    
    // Retornar estadísticas para uso programático
    return [
        'success' => true,
        'processed_charges' => $processedCount,
        'synced_memberships' => $syncedCount,
        'cleaned_logs' => $deletedRows,
        'fixed_inconsistencies' => $fixedRows,
        'pending_charges' => $pendingCount,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (Throwable $e) {
    autoLog('Error en sincronización automatizada', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
