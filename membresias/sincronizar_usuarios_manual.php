<?php
/**
 * Script para ejecutar sincronización manual de usuarios y suscripciones
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

echo "=== SINCRONIZACIÓN MANUAL DE USUARIOS Y SUSCRIPCIONES ===\n\n";

// Función para logging
function syncLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= PHP_EOL;
    echo $logEntry;
    
    // También guardar en archivo de log
    $logFile = __DIR__ . '/logs/sync_manual.log';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

try {
    // Inicializar MercadoPago
    mpInit();
    syncLog('MercadoPago inicializado');
    
    // 1. Sincronizar usuarios con suscripciones activas
    echo "🔄 Sincronizando usuarios con suscripciones activas...\n";
    $activeSynced = syncActiveSubscriptions();
    echo "✅ Usuarios activos sincronizados: {$activeSynced}\n\n";
    
    // 2. Sincronizar usuarios pendientes
    echo "🔄 Sincronizando usuarios pendientes de pago...\n";
    $pendingSynced = syncPendingUsers();
    echo "✅ Usuarios pendientes sincronizados: {$pendingSynced}\n\n";
    
    // 3. Corregir inconsistencias
    echo "🔄 Corrigiendo inconsistencias...\n";
    $inconsistenciesFixed = fixSubscriptionInconsistencies();
    echo "✅ Inconsistencias corregidas: {$inconsistenciesFixed}\n\n";
    
    // 4. Actualizar estadísticas
    updateSyncStatistics($activeSynced, $pendingSynced, $inconsistenciesFixed);
    
    // 5. Mostrar resumen
    echo "📊 RESUMEN DE SINCRONIZACIÓN\n";
    echo "============================\n";
    echo "• Usuarios activos sincronizados: {$activeSynced}\n";
    echo "• Usuarios pendientes sincronizados: {$pendingSynced}\n";
    echo "• Inconsistencias corregidas: {$inconsistenciesFixed}\n";
    echo "• Total de operaciones: " . ($activeSynced + $pendingSynced + $inconsistenciesFixed) . "\n";
    
    if (($activeSynced + $pendingSynced + $inconsistenciesFixed) > 0) {
        echo "\n🎉 Sincronización completada exitosamente\n";
    } else {
        echo "\nℹ️  No se requirieron actualizaciones\n";
    }
    
    echo "\n=== SINCRONIZACIÓN COMPLETADA ===\n";
    
} catch (Throwable $e) {
    syncLog('Error en sincronización manual', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "❌ Error: " . $e->getMessage() . "\n";
}

/**
 * Sincroniza usuarios con suscripciones activas
 */
function syncActiveSubscriptions() {
    global $pdo;
    
    try {
        syncLog('Sincronizando usuarios con suscripciones activas');
        
        // Buscar usuarios con suscripciones autorizadas pero sin actualizar en la base de datos
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.mp_status = 'authorized' 
                AND m.mp_preapproval_id IS NOT NULL
                AND (m.mp_last_sync_at IS NULL OR m.mp_last_sync_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                ORDER BY m.created_at DESC
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $syncedCount = 0;
        
        foreach ($membresias as $membresia) {
            try {
                // Verificar estado actual en MercadoPago
                $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
                $preapproval = $client->get($membresia['mp_preapproval_id']);
                
                if ($preapproval) {
                    $currentStatus = $preapproval->status ?? null;
                    $payerEmail = $preapproval->payer_email ?? null;
                    $nextPaymentDate = $preapproval->next_payment_date ?? null;
                    $lastPaymentDate = $preapproval->last_payment_date ?? null;
                    
                    // Actualizar información del usuario
                    $updateParts = [];
                    $updateParams = [];
                    
                    if ($currentStatus && $currentStatus !== $membresia['mp_status']) {
                        $updateParts[] = 'mp_status = ?';
                        $updateParams[] = $currentStatus;
                    }
                    
                    if ($payerEmail && $payerEmail !== $membresia['correo']) {
                        // Actualizar email del cliente
                        $clienteStmt = $pdo->prepare("UPDATE clientes SET correo = ? WHERE id = ?");
                        $clienteStmt->execute([$payerEmail, $membresia['cliente_id']]);
                        syncLog('Email del cliente actualizado', [
                            'cliente_id' => $membresia['cliente_id'],
                            'old_email' => $membresia['correo'],
                            'new_email' => $payerEmail
                        ]);
                    }
                    
                    if ($nextPaymentDate) {
                        $updateParts[] = 'mp_next_payment_date = ?';
                        $updateParams[] = $nextPaymentDate;
                    }
                    
                    if ($lastPaymentDate) {
                        $updateParts[] = 'mp_last_payment_date = ?';
                        $updateParams[] = $lastPaymentDate;
                    }
                    
                    // Marcar como sincronizado
                    $updateParts[] = 'mp_last_sync_at = NOW()';
                    
                    if ($updateParts) {
                        $updateParams[] = $membresia['id'];
                        $updateSql = 'UPDATE membresias SET ' . implode(', ', $updateParts) . ' WHERE id = ?';
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute($updateParams);
                        
                        $syncedCount++;
                        syncLog('Membresía sincronizada exitosamente', [
                            'membresia_id' => $membresia['id'],
                            'status' => $currentStatus,
                            'payer_email' => $payerEmail
                        ]);
                    }
                }
                
            } catch (Throwable $e) {
                syncLog('Error sincronizando membresía individual', [
                    'membresia_id' => $membresia['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $syncedCount;
        
    } catch (Throwable $e) {
        syncLog('Error en syncActiveSubscriptions', ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Sincroniza usuarios pendientes de pago
 */
function syncPendingUsers() {
    global $pdo;
    
    try {
        syncLog('Sincronizando usuarios pendientes de pago');
        
        // Buscar usuarios registrados pero sin suscripción activa
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE (m.mp_status IS NULL OR m.mp_status = 'pending' OR m.mp_status = '')
                AND m.mp_preapproval_id IS NULL
                AND m.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY m.created_at DESC
                LIMIT 30";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $syncedCount = 0;
        
        foreach ($membresias as $membresia) {
            try {
                // Verificar si existe una suscripción en MercadoPago por external_reference
                $externalRef = 'MEM_' . $membresia['id'];
                
                // Buscar en MercadoPago por external_reference
                $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
                $searchRequest = new \MercadoPago\Net\MPSearchRequest(
                    10, // limit
                    0,  // offset
                    ['external_reference' => $externalRef] // filters
                );
                
                $searchResult = $client->search($searchRequest);
                
                if ($searchResult && $searchResult->results && count($searchResult->results) > 0) {
                    $preapproval = $searchResult->results[0];
                    
                    // Actualizar la membresía con la información encontrada
                    $updateStmt = $pdo->prepare("UPDATE membresias SET 
                        mp_status = ?,
                        mp_preapproval_id = ?,
                        mp_next_payment_date = ?,
                        mp_last_payment_date = ?,
                        mp_last_sync_at = NOW()
                        WHERE id = ?");
                    
                    $updateStmt->execute([
                        $preapproval->status ?? 'pending',
                        $preapproval->id,
                        $preapproval->next_payment_date ?? null,
                        $preapproval->last_payment_date ?? null,
                        $membresia['id']
                    ]);
                    
                    $syncedCount++;
                    syncLog('Usuario pendiente sincronizado con suscripción encontrada', [
                        'membresia_id' => $membresia['id'],
                        'preapproval_id' => $preapproval->id,
                        'status' => $preapproval->status
                    ]);
                } else {
                    // Marcar como pendiente de pago
                    $updateStmt = $pdo->prepare("UPDATE membresias SET 
                        mp_status = 'pending',
                        mp_last_sync_at = NOW()
                        WHERE id = ?");
                    $updateStmt->execute([$membresia['id']]);
                    
                    syncLog('Usuario marcado como pendiente de pago', [
                        'membresia_id' => $membresia['id'],
                        'external_ref' => $externalRef
                    ]);
                }
                
            } catch (Throwable $e) {
                syncLog('Error sincronizando usuario pendiente', [
                    'membresia_id' => $membresia['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $syncedCount;
        
    } catch (Throwable $e) {
        syncLog('Error en syncPendingUsers', ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Corrige inconsistencias en las suscripciones
 */
function fixSubscriptionInconsistencies() {
    global $pdo;
    
    try {
        syncLog('Corrigiendo inconsistencias en suscripciones');
        
        $fixedCount = 0;
        
        // 1. Corregir membresías con preapproval_id pero sin status
        $sql = "UPDATE membresias SET mp_status = 'pending' 
                WHERE mp_preapproval_id IS NOT NULL 
                AND (mp_status IS NULL OR mp_status = '')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $fixedCount += $stmt->rowCount();
        
        // 2. Corregir membresías con status pero sin preapproval_id
        $sql = "UPDATE membresias SET mp_status = 'pending' 
                WHERE mp_preapproval_id IS NULL 
                AND mp_status IN ('authorized', 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $fixedCount += $stmt->rowCount();
        
        // 3. Corregir fechas de sincronización faltantes
        $sql = "UPDATE membresias SET mp_last_sync_at = NOW() 
                WHERE mp_last_sync_at IS NULL 
                AND mp_preapproval_id IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $fixedCount += $stmt->rowCount();
        
        if ($fixedCount > 0) {
            syncLog('Inconsistencias corregidas', ['fixed_count' => $fixedCount]);
        }
        
        return $fixedCount;
        
    } catch (Throwable $e) {
        syncLog('Error corrigiendo inconsistencias', ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Actualiza estadísticas de sincronización
 */
function updateSyncStatistics($activeSynced, $pendingSynced, $inconsistenciesFixed) {
    global $pdo;
    
    try {
        // Insertar registro de estadísticas
        $sql = "INSERT INTO mp_sync_statistics 
                (active_users_synced, pending_users_synced, inconsistencies_fixed, sync_date) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$activeSynced, $pendingSynced, $inconsistenciesFixed]);
        
        syncLog('Estadísticas de sincronización actualizadas', [
            'active_synced' => $activeSynced,
            'pending_synced' => $pendingSynced,
            'inconsistencies_fixed' => $inconsistenciesFixed
        ]);
        
    } catch (Throwable $e) {
        syncLog('Error actualizando estadísticas', ['error' => $e->getMessage()]);
    }
}
?>
