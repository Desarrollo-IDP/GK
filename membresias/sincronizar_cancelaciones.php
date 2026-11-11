<?php
/**
 * Script para sincronizar manualmente las cancelaciones de MercadoPago
 * que no se procesaron correctamente en el webhook
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

// Configurar logging
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

function syncLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/sync_cancellations.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

syncLog('=== SINCRONIZACIÓN DE CANCELACIONES MEJORADA ===');

try {
    // Inicializar MercadoPago
    mpInit();
    
    // 1. Buscar membresías que están en estado 'authorized' pero deberían estar canceladas
    syncLog('1. Buscando membresías que necesitan sincronización...');
    
    $pendingSyncStmt = $pdo->prepare("
        SELECT 
            m.id,
            m.mp_preapproval_id,
            m.mp_status,
            m.mp_cancellation_fee,
            m.mp_cancelled_at,
            m.cliente_id,
            m.tipo_membresia_id,
            c.nombre,
            c.apellido_paterno,
            c.correo,
            tm.monto_cancelacion,
            tm.nombre as tipo_nombre
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
        WHERE m.mp_preapproval_id IS NOT NULL
        AND m.mp_preapproval_id != ''
        ORDER BY m.created_at DESC
        LIMIT 30
    ");
    $pendingSyncStmt->execute();
    $membresias = $pendingSyncStmt->fetchAll(PDO::FETCH_ASSOC);
    
    syncLog('Membresías encontradas para sincronización: ' . count($membresias));
    
    $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
    
    foreach ($membresias as $membresia) {
        try {
            syncLog('Procesando membresía', [
                'id' => $membresia['id'],
                'preapproval_id' => $membresia['mp_preapproval_id'],
                'current_status' => $membresia['mp_status']
            ]);
            
            // Obtener información actual de MercadoPago
            $preapproval = $client->get($membresia['mp_preapproval_id']);
            
            if (!$preapproval) {
                syncLog('Preapproval no encontrado en MercadoPago', [
                    'preapproval_id' => $membresia['mp_preapproval_id']
                ]);
                continue;
            }
            
            $mpStatus = $preapproval->status ?? null;
            $externalRef = $preapproval->external_reference ?? null;
            $payerEmail = $preapproval->payer_email ?? null;
            $nextPaymentDate = $preapproval->next_payment_date ?? null;
            $lastPaymentDate = $preapproval->last_payment_date ?? null;
            
            syncLog('Estado en MercadoPago', [
                'preapproval_id' => $membresia['mp_preapproval_id'],
                'mp_status' => $mpStatus,
                'external_ref' => $externalRef,
                'payer_email' => $payerEmail
            ]);
            
            // Verificar si hay discrepancia de estado
            if ($membresia['mp_status'] !== $mpStatus) {
                syncLog('Discrepancia de estado detectada', [
                    'membresia_id' => $membresia['id'],
                    'local_status' => $membresia['mp_status'],
                    'mp_status' => $mpStatus
                ]);
                
                // Actualizar el estado local
                $updateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_status = ?,
                    mp_next_payment_date = ?,
                    mp_last_payment_date = ?
                    WHERE id = ?");
                $updateStmt->execute([
                    $mpStatus,
                    $nextPaymentDate,
                    $lastPaymentDate,
                    $membresia['id']
                ]);
                
                // Si el estado cambió a cancelled, procesar cargo por cancelación
                if ($mpStatus === 'cancelled' && $membresia['mp_status'] !== 'cancelled') {
                    syncLog('Estado cambiado a cancelled, procesando cargo por cancelación', [
                        'membresia_id' => $membresia['id']
                    ]);
                    
                    // Obtener datos completos de la membresía
                    $membresiaData = [
                        'id' => $membresia['id'],
                        'cliente_id' => $membresia['cliente_id'],
                        'nombre' => $membresia['nombre'],
                        'apellido_paterno' => $membresia['apellido_paterno'],
                        'correo' => $membresia['correo'],
                        'mp_preapproval_id' => $membresia['mp_preapproval_id'],
                        'tipo_membresia_id' => $membresia['tipo_membresia_id']
                    ];
                    
                    handleCancellationWithFee($membresiaData, $payerEmail);
                }
                
                syncLog('Estado actualizado correctamente', [
                    'membresia_id' => $membresia['id'],
                    'new_status' => $mpStatus
                ]);
            } else {
                syncLog('Estado sincronizado correctamente', [
                    'membresia_id' => $membresia['id'],
                    'status' => $mpStatus
                ]);
            }
            
            // Actualizar email del cliente si es diferente
            if ($payerEmail && $membresia['correo'] !== $payerEmail) {
                $clienteStmt = $pdo->prepare("UPDATE clientes SET correo = ? WHERE id = ?");
                $clienteStmt->execute([$payerEmail, $membresia['cliente_id']]);
                syncLog('Email del cliente actualizado', [
                    'cliente_id' => $membresia['cliente_id'],
                    'old_email' => $membresia['correo'],
                    'new_email' => $payerEmail
                ]);
            }
            
        } catch (Throwable $e) {
            syncLog('Error procesando membresía', [
                'membresia_id' => $membresia['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // 2. Procesar cargos por cancelación pendientes usando el monto de tipos_membresia
    syncLog('\n2. Procesando cargos por cancelación pendientes...');
    
    $pendingChargesStmt = $pdo->prepare("
        SELECT 
            m.id,
            m.mp_cancellation_fee,
            m.mp_cancellation_reason,
            m.cliente_id,
            m.tipo_membresia_id,
            c.nombre,
            c.apellido_paterno,
            c.mp_card_token,
            c.mp_customer_id,
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
        ORDER BY m.mp_cancelled_at DESC
        LIMIT 15
    ");
    $pendingChargesStmt->execute();
    $pendingCharges = $pendingChargesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    syncLog('Cargos por cancelación pendientes: ' . count($pendingCharges));
    
    foreach ($pendingCharges as $charge) {
        try {
            syncLog('Procesando cargo pendiente', [
                'membresia_id' => $charge['id'],
                'cliente' => $charge['nombre'] . ' ' . $charge['apellido_paterno'],
                'tipo_membresia' => $charge['tipo_nombre'],
                'monto_configurado' => $charge['monto_cancelacion']
            ]);
            
            if ($charge['mp_card_token']) {
                // Usar el monto de cancelación del tipo de membresía
                $cancellationAmount = (float)$charge['monto_cancelacion'];
                
                syncLog('Procesando cargo pendiente con monto de tipos_membresia', [
                    'membresia_id' => $charge['id'],
                    'monto_cancelacion' => $cancellationAmount
                ]);
                
                $result = mpCreateCancellationPaymentFromClient(
                    $charge['cliente_id'],
                    $cancellationAmount,
                    'CANCEL_FEE_MEM_' . $charge['id'] . '_SYNC'
                );
                
                if ($result['success']) {
                    syncLog('Cargo procesado exitosamente', [
                        'membresia_id' => $charge['id'],
                        'payment_id' => $result['payment_id'],
                        'status' => $result['status']
                    ]);
                    
                    // Registrar el pago en la tabla de logs
                    $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                        (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $logStmt->execute([
                        $charge['id'],
                        $result['payment_id'],
                        null, // subscription_id
                        $cancellationAmount,
                        $result['status'],
                        'cancellation_fee',
                        date('Y-m-d H:i:s'),
                        json_encode($result)
                    ]);
                    
                    // Actualizar la membresía
                    $updateStmt = $pdo->prepare("UPDATE membresias SET 
                        mp_cancellation_fee = ?,
                        mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado por sincronización: ', ?)
                        WHERE id = ?");
                    $updateStmt->execute([$cancellationAmount, $result['payment_id'], $charge['id']]);
                    
                } else {
                    syncLog('Error procesando cargo', [
                        'membresia_id' => $charge['id'],
                        'error' => $result['error'] ?? 'Error desconocido'
                    ]);
                }
            } else {
                syncLog('Cliente sin token de tarjeta', [
                    'membresia_id' => $charge['id'],
                    'cliente_id' => $charge['cliente_id']
                ]);
            }
            
        } catch (Throwable $e) {
            syncLog('Error procesando cargo pendiente', [
                'membresia_id' => $charge['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // 3. Verificar pagos recientes que no se registraron
    syncLog('\n3. Verificando pagos recientes no registrados...');
    
    $recentPaymentsStmt = $pdo->prepare("
        SELECT 
            m.id,
            m.mp_preapproval_id,
            m.mp_last_payment_date,
            c.nombre,
            c.apellido_paterno
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        WHERE m.mp_status = 'authorized'
        AND m.mp_preapproval_id IS NOT NULL
        AND m.mp_preapproval_id != ''
        ORDER BY m.mp_last_payment_date DESC
        LIMIT 10
    ");
    $recentPaymentsStmt->execute();
    $recentMembresias = $recentPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentMembresias as $membresia) {
        try {
            // Buscar pagos recientes para esta suscripción
            $paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
            
            // Obtener pagos de los últimos 30 días
            $fromDate = date('Y-m-d', strtotime('-30 days'));
            $toDate = date('Y-m-d');
            
            // Nota: La API de MercadoPago no tiene un endpoint directo para buscar pagos por preapproval_id
            // Esto es una aproximación. En un entorno real, deberías usar el webhook para esto
            
            syncLog('Verificando pagos recientes', [
                'membresia_id' => $membresia['id'],
                'preapproval_id' => $membresia['mp_preapproval_id'],
                'last_payment_date' => $membresia['mp_last_payment_date']
            ]);
            
        } catch (Throwable $e) {
            syncLog('Error verificando pagos recientes', [
                'membresia_id' => $membresia['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    syncLog('\n=== SINCRONIZACIÓN COMPLETADA ===');
    
} catch (Throwable $e) {
    syncLog('Error general en sincronización', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * Función auxiliar para manejar cancelaciones con cargo
 * (Mejorada para usar el monto de tipos_membresia)
 */
function handleCancellationWithFee($membresia, $payerEmail) {
    global $pdo;
    
    try {
        // Obtener datos del tipo de membresía
        $tipoStmt = $pdo->prepare("SELECT tm.*, e.nombre as empresa_nombre 
                                  FROM tipos_membresia tm 
                                  LEFT JOIN empresas e ON tm.empresa_id = e.id 
                                  WHERE tm.id = ?");
        $tipoStmt->execute([$membresia['tipo_membresia_id']]);
        $tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC);

        if ($tipo && $tipo['monto_cancelacion'] > 0) {
            // Usar el monto de cancelación configurado en tipos_membresia
            $cancellationAmount = (float)$tipo['monto_cancelacion'];
            syncLog('Monto de cancelación obtenido de tipos_membresia', [
                'tipo_membresia_id' => $membresia['tipo_membresia_id'],
                'monto_cancelacion' => $cancellationAmount,
                'tipo_nombre' => $tipo['nombre']
            ]);
            $charged = false;
            $chargeError = null;
            $chargeDetails = null;
            $paymentId = null;
            $paymentStatus = null;

            syncLog('Iniciando cargo por cancelación', [
                'amount' => $cancellationAmount,
                'membresia_id' => $membresia['id']
            ]);

            try {
                // Verificar si el cliente tiene token de tarjeta
                $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id, mp_card_id FROM clientes WHERE id = ?");
                $clienteStmt->execute([$membresia['cliente_id']]);
                $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cliente && $cliente['mp_card_token']) {
                    syncLog('Cliente tiene token de tarjeta, intentando cobro automático');
                    
                    // Intentar cobro automático usando el token de tarjeta guardado
                    $result = mpCreateCancellationPaymentFromClient(
                        $membresia['cliente_id'], 
                        $cancellationAmount, 
                        'CANCEL_FEE_MEM_' . $membresia['id']
                    );
                    
                    if ($result['success']) {
                        $charged = true;
                        $paymentId = $result['payment_id'] ?? null;
                        $paymentStatus = $result['status'] ?? null;
                        $chargeDetails = 'Cargo aplicado automáticamente - Payment ID: ' . ($paymentId ?? 'N/A') . ' - Status: ' . ($paymentStatus ?? 'N/A');
                        syncLog('Cargo por cancelación exitoso', [
                            'amount' => $cancellationAmount,
                            'payment_id' => $paymentId,
                            'status' => $paymentStatus
                        ]);
                        
                        // Registrar el pago en la tabla de logs
                        try {
                            $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                                (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $logStmt->execute([
                                $membresia['id'],
                                $paymentId,
                                $membresia['mp_preapproval_id'],
                                $cancellationAmount,
                                $paymentStatus,
                                'cancellation_fee',
                                date('Y-m-d H:i:s'),
                                json_encode($result)
                            ]);
                            syncLog('Pago por cancelación registrado en logs');
                        } catch (Exception $logError) {
                            syncLog('Error registrando pago por cancelación en logs', ['error' => $logError->getMessage()]);
                        }
                    } else {
                        $chargeError = $result['error'] ?? 'Error desconocido en el cobro';
                        $chargeDetails = 'Error en cobro automático: ' . $chargeError;
                        syncLog('Error en cargo por cancelación', ['error' => $chargeError]);
                    }
                } else {
                    $chargeError = 'Cliente no tiene token de tarjeta guardado';
                    $chargeDetails = 'Cargo pendiente - Cliente sin token de tarjeta';
                    syncLog('Cliente sin token de tarjeta para cargo automático');
                }
            } catch (Throwable $e) {
                $chargeError = $e->getMessage();
                $chargeDetails = 'Excepción en cobro automático: ' . $chargeError;
                syncLog('Excepción en cargo por cancelación', [
                    'error' => $chargeError,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Actualizar la membresía con información del cargo
            try {
                $updateCancellationSql = "UPDATE membresias SET 
                    mp_cancellation_fee = ?, 
                    mp_cancelled_at = NOW(),
                    mp_cancellation_reason = ?,
                    mp_status = 'cancelled'
                    WHERE id = ?";
                $cancellationStmt = $pdo->prepare($updateCancellationSql);
                $cancellationStmt->execute([
                    $cancellationAmount, 
                    $chargeDetails,
                    $membresia['id']
                ]);
                
                syncLog('Información de cancelación actualizada en base de datos');
                
            } catch (Exception $updateError) {
                syncLog('Error actualizando información de cancelación', ['error' => $updateError->getMessage()]);
            }
        } else {
            syncLog('No hay cargo por cancelación configurado para este tipo de membresía');
            
            // Actualizar solo el estado de cancelación sin cargo
            try {
                $updateCancellationSql = "UPDATE membresias SET 
                    mp_cancelled_at = NOW(),
                    mp_cancellation_reason = 'Cancelación sin cargo',
                    mp_status = 'cancelled'
                    WHERE id = ?";
                $cancellationStmt = $pdo->prepare($updateCancellationSql);
                $cancellationStmt->execute([$membresia['id']]);
                
                syncLog('Cancelación sin cargo registrada en base de datos');
                
            } catch (Exception $updateError) {
                syncLog('Error actualizando cancelación sin cargo', ['error' => $updateError->getMessage()]);
            }
        }

    } catch (Throwable $e) {
        syncLog('Error general en handleCancellationWithFee', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
