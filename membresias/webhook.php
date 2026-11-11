<?php
require_once __DIR__ . '/includes/config.php';
//require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/mercadopago_config.php';

http_response_code(200);
header('Content-Type: application/json');

// Función para logging detallado
function webhookLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/mercadopago_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    error_log('Webhook MP: ' . $message);
}

// Función para obtener y procesar datos del webhook
function getWebhookData() {
    $raw = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    webhookLog('Raw webhook data received', [
        'content_type' => $contentType,
        'request_method' => $requestMethod,
        'raw_length' => strlen($raw),
        'raw_preview' => substr($raw, 0, 500),
        'post_data' => $_POST,
        'get_data' => $_GET
    ]);
    
    // Si es una solicitud GET vacía, probablemente es una verificación de salud
    if ($requestMethod === 'GET' && empty($raw) && empty($_POST)) {
        webhookLog('Health check request detected');
        return ['raw' => '', 'event' => ['type' => 'health_check', 'action' => 'ping']];
    }
    
    // Si no hay datos raw, intentar con POST
    if (empty($raw) && !empty($_POST)) {
        webhookLog('Using POST data instead of raw input');
        $raw = json_encode($_POST);
    }
    
    // Si aún no hay datos, intentar con GET (para pruebas)
    if (empty($raw) && !empty($_GET)) {
        webhookLog('Using GET data for testing');
        $raw = json_encode($_GET);
    }
    
    // Si no hay datos en absoluto, retornar null
    if (empty($raw)) {
        webhookLog('No data received in webhook request');
        return ['raw' => '', 'event' => null];
    }
    
    // Intentar decodificar JSON
    $event = json_decode($raw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        webhookLog('JSON decode error', [
            'error_code' => json_last_error(),
            'error_message' => json_last_error_msg(),
            'raw_data' => $raw
        ]);
        
        // Intentar parsear como datos POST form-encoded
        if (!empty($_POST)) {
            webhookLog('Attempting to parse POST data as form-encoded');
            $event = [
                'type' => $_POST['type'] ?? 'unknown',
                'action' => $_POST['action'] ?? 'updated',
                'data' => [
                    'id' => $_POST['id'] ?? $_POST['data_id'] ?? null,
                    'status' => $_POST['status'] ?? null,
                    'payer_email' => $_POST['payer_email'] ?? null,
                    'external_reference' => $_POST['external_reference'] ?? null
                ]
            ];
        } else {
            $event = null;
        }
    }
    
    return ['raw' => $raw, 'event' => $event];
}

// Manejar modo de prueba
if (isset($_POST['test_mode']) && $_POST['test_mode'] == '1') {
    $event = [
        'type' => $_POST['type'] ?? 'preapproval',
        'data' => [
            'id' => $_POST['data']['id'] ?? 'test_preapproval_123',
            'status' => $_POST['data']['status'] ?? 'authorized',
            'payer_email' => $_POST['data']['payer_email'] ?? 'test@example.com',
            'external_reference' => $_POST['data']['external_reference'] ?? 'MEM_1'
        ]
    ];
    $raw = json_encode($event);
} else {
    $webhookData = getWebhookData();
    $raw = $webhookData['raw'];
    $event = $webhookData['event'];
}

try {
    // Log de la notificación recibida
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }
    @file_put_contents(__DIR__ . '/logs/mercadopago_webhook.log', date('c') . ' ' . $raw . PHP_EOL, FILE_APPEND);

    // Manejar health checks y solicitudes vacías
    if ($event && isset($event['type']) && $event['type'] === 'health_check') {
        webhookLog('Health check request processed successfully');
        echo json_encode(['received' => true, 'health_check' => true, 'status' => 'ok']);
        exit;
    }
    
    if (!$event || !is_array($event)) {
        $rawLength = strlen($raw);
        $jsonError = json_last_error();
        $jsonErrorMsg = json_last_error_msg();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        
        // Si es una solicitud GET vacía, responder con éxito (probablemente verificación de URL)
        if ($requestMethod === 'GET' && empty($raw) && empty($_POST)) {
            webhookLog('Empty GET request - likely URL verification');
            echo json_encode(['received' => true, 'verified' => true, 'status' => 'ok']);
            exit;
        }
        
        webhookLog('Invalid webhook data received', [
            'raw_length' => $rawLength,
            'raw_preview' => substr($raw, 0, 200),
            'json_error_code' => $jsonError,
            'json_error_message' => $jsonErrorMsg,
            'post_data' => $_POST,
            'get_data' => $_GET,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not-set',
            'request_method' => $requestMethod
        ]);
        
        // Responder con éxito para evitar reintentos
        echo json_encode([
            'received' => true, 
            'message' => 'Invalid JSON data',
            'error_code' => $jsonError,
            'error_message' => $jsonErrorMsg,
            'processed' => false
        ]);
        exit;
    }

    // Extraer información básica del evento
    $type = $event['type'] ?? '';
    $action = $event['action'] ?? '';
    $dataId = $event['data']['id'] ?? null;
    $entity = $event['entity'] ?? '';
    $date = $event['date'] ?? $event['date_created'] ?? null;

    webhookLog('Processing event', [
        'type' => $type,
        'action' => $action,
        'entity' => $entity,
        'data_id' => $dataId,
        'date' => $date,
        'event_structure' => array_keys($event)
    ]);

    // Inicializar MercadoPago
    mpInit();

    // Procesar automáticamente pagos por cancelación pendientes (cada 3 notificaciones)
    processPendingCancellationPayments();
    
    // Sincronización automática de usuarios y suscripciones (cada 5 notificaciones)
    $syncCounterFile = __DIR__ . '/logs/sync_counter.txt';
    $syncCounter = 1;
    
    if (file_exists($syncCounterFile)) {
        $syncCounter = (int)file_get_contents($syncCounterFile) + 1;
    }
    
    // Solo sincronizar cada 5 notificaciones para mayor eficiencia
    if ($syncCounter % 5 === 0) {
        syncUsersAndSubscriptions();
    }
    
    file_put_contents($syncCounterFile, $syncCounter);

    // Manejar diferentes tipos de eventos
    switch ($type) {
        case 'payment':
            handlePaymentEvent($event, $action, $dataId);
            break;
            
        case 'subscription_preapproval':
        case 'preapproval':
            handleSubscriptionEvent($event, $action, $dataId);
            break;
            
        case 'subscription_authorized_payment':
            handleAuthorizedPaymentEvent($event, $action, $dataId);
            break;
            
        case 'subscription_cancelled':
            handleCancellationEvent($event, $action, $dataId);
            break;
            
        default:
            webhookLog('Unhandled event type', ['type' => $type, 'action' => $action]);
            break;
    }

    echo json_encode(['received' => true, 'processed' => true]);
    exit;

} catch (Throwable $e) {
    webhookLog('Error processing webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo json_encode(['received' => true, 'error' => $e->getMessage()]);
    exit;
}

/**
 * Procesa automáticamente pagos por cancelación pendientes
 */
function processPendingCancellationPayments() {
    global $pdo;
    
    try {
        // Contador para controlar la frecuencia de procesamiento
        $counterFile = __DIR__ . '/logs/cancellation_counter.txt';
        $counter = 1;
        
        if (file_exists($counterFile)) {
            $counter = (int)file_get_contents($counterFile) + 1;
        }
        
        // Solo procesar cada 3 notificaciones para mayor eficiencia
        if ($counter % 3 !== 0) {
            file_put_contents($counterFile, $counter);
            return;
        }
        
        file_put_contents($counterFile, $counter);
        
        webhookLog('Procesando pagos por cancelación pendientes automáticamente');
        
        // Buscar membresías canceladas con cargo por cancelación pero sin pago procesado
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.monto_cancelacion, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.mp_status = 'cancelled' 
                AND tm.monto_cancelacion > 0
                AND m.mp_cancelled_at IS NOT NULL
                AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
                AND m.mp_cancellation_reason NOT LIKE '%Error en cobro%'
                AND m.mp_cancellation_reason NOT LIKE '%Pago procesado%'
                ORDER BY m.mp_cancelled_at DESC
                LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($membresias)) {
            webhookLog('No hay pagos por cancelación pendientes');
            return;
        }
        
        webhookLog('Procesando ' . count($membresias) . ' pagos por cancelación pendientes');
        
        foreach ($membresias as $membresia) {
            try {
                // Verificar si el cliente tiene token de tarjeta
                $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id FROM clientes WHERE id = ?");
                $clienteStmt->execute([$membresia['cliente_id']]);
                $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cliente || !$cliente['mp_card_token']) {
                    webhookLog('Cliente sin token de tarjeta para membresía', ['membresia_id' => $membresia['id']]);
                    continue;
                }
                
                // Usar el monto de cancelación del tipo de membresía
                $cancellationAmount = (float)$membresia['monto_cancelacion'];
                
                if ($cancellationAmount > 0) {
                    webhookLog('Procesando cargo automático con monto de tipos_membresia', [
                        'membresia_id' => $membresia['id'],
                        'monto_cancelacion' => $cancellationAmount
                    ]);
                    
                    // Intentar cobro automático
                    $result = mpCreateCancellationPaymentFromClient(
                        $membresia['cliente_id'],
                        $cancellationAmount,
                        'CANCEL_FEE_MEM_' . $membresia['id']
                    );
                    
                    if ($result['success']) {
                        webhookLog('Pago por cancelación procesado automáticamente', [
                            'membresia_id' => $membresia['id'],
                            'payment_id' => $result['payment_id'],
                            'status' => $result['status']
                        ]);
                        
                        // Registrar el pago en la tabla de logs
                        $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                            (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $logStmt->execute([
                            $membresia['id'],
                            $result['payment_id'],
                            $membresia['mp_preapproval_id'],
                            $cancellationAmount,
                            $result['status'],
                            'cancellation_fee',
                            date('Y-m-d H:i:s'),
                            json_encode($result)
                        ]);
                        
                        // Actualizar la membresía
                        $updateStmt = $pdo->prepare("UPDATE membresias SET 
                            mp_cancellation_fee = ?,
                            mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado automáticamente: ', ?)
                            WHERE id = ?");
                        $updateStmt->execute([$cancellationAmount, $result['payment_id'], $membresia['id']]);
                        
                    } else {
                        webhookLog('Error procesando pago automático', [
                            'membresia_id' => $membresia['id'],
                            'error' => $result['error'] ?? 'Error desconocido'
                        ]);
                    }
                } else {
                    webhookLog('No hay monto de cancelación configurado para procesar automáticamente', [
                        'membresia_id' => $membresia['id']
                    ]);
                }
                
            } catch (Throwable $e) {
                webhookLog('Excepción procesando pago automático', [
                    'membresia_id' => $membresia['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
    } catch (Throwable $e) {
        webhookLog('Error en processPendingCancellationPayments', ['error' => $e->getMessage()]);
    }
}

/**
 * Sincronización automática de usuarios y suscripciones
 * Identifica y actualiza el estado de usuarios que pagaron vs pendientes
 */
function syncUsersAndSubscriptions() {
    global $pdo;
    
    try {
        webhookLog('Iniciando sincronización automática de usuarios y suscripciones');
        
        // 1. Sincronizar usuarios registrados con suscripciones activas
        $syncActiveUsers = syncActiveSubscriptions();
        
        // 2. Identificar usuarios pendientes de pago
        $syncPendingUsers = syncPendingUsers();
        
        // 3. Verificar inconsistencias y corregirlas
        $fixInconsistencies = fixSubscriptionInconsistencies();
        
        // 4. Actualizar estadísticas de sincronización
        updateSyncStatistics($syncActiveUsers, $syncPendingUsers, $fixInconsistencies);
        
        webhookLog('Sincronización automática completada', [
            'active_synced' => $syncActiveUsers,
            'pending_synced' => $syncPendingUsers,
            'inconsistencies_fixed' => $fixInconsistencies
        ]);
        
    } catch (Throwable $e) {
        webhookLog('Error en syncUsersAndSubscriptions', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Sincroniza usuarios con suscripciones activas
 */
function syncActiveSubscriptions() {
    global $pdo;
    
    try {
        webhookLog('Sincronizando usuarios con suscripciones activas');
        
        // Buscar usuarios con suscripciones autorizadas pero sin actualizar en la base de datos
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.mp_status = 'authorized' 
                AND m.mp_preapproval_id IS NOT NULL
                AND (m.mp_last_sync_at IS NULL OR m.mp_last_sync_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                ORDER BY m.created_at DESC";
        
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
                        webhookLog('Email del cliente actualizado', [
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
                        webhookLog('Membresía sincronizada exitosamente', [
                            'membresia_id' => $membresia['id'],
                            'status' => $currentStatus,
                            'payer_email' => $payerEmail
                        ]);
                    }
                }
                
            } catch (Throwable $e) {
                webhookLog('Error sincronizando membresía individual', [
                    'membresia_id' => $membresia['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $syncedCount;
        
    } catch (Throwable $e) {
        webhookLog('Error en syncActiveSubscriptions', ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Sincroniza usuarios pendientes de pago
 */
function syncPendingUsers() {
    global $pdo;
    
    try {
        webhookLog('Sincronizando usuarios pendientes de pago');
        
        // Buscar usuarios registrados pero sin suscripción activa
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE (m.mp_status IS NULL OR m.mp_status = 'pending' OR m.mp_status = '')
                AND m.mp_preapproval_id IS NULL
                AND m.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY m.created_at DESC
                LIMIT 20";
        
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
                $searchParams = [
                    'external_reference' => $externalRef
                ];
                
                $searchResult = $client->search($searchParams);
                
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
                    webhookLog('Usuario pendiente sincronizado con suscripción encontrada', [
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
                    
                    webhookLog('Usuario marcado como pendiente de pago', [
                        'membresia_id' => $membresia['id'],
                        'external_ref' => $externalRef
                    ]);
                }
                
            } catch (Throwable $e) {
                webhookLog('Error sincronizando usuario pendiente', [
                    'membresia_id' => $membresia['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $syncedCount;
        
    } catch (Throwable $e) {
        webhookLog('Error en syncPendingUsers', ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Corrige inconsistencias en las suscripciones
 */
function fixSubscriptionInconsistencies() {
    global $pdo;
    
    try {
        webhookLog('Corrigiendo inconsistencias en suscripciones');
        
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
            webhookLog('Inconsistencias corregidas', ['fixed_count' => $fixedCount]);
        }
        
        return $fixedCount;
        
    } catch (Throwable $e) {
        webhookLog('Error corrigiendo inconsistencias', ['error' => $e->getMessage()]);
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
        
        webhookLog('Estadísticas de sincronización actualizadas', [
            'active_synced' => $activeSynced,
            'pending_synced' => $pendingSynced,
            'inconsistencies_fixed' => $inconsistenciesFixed
        ]);
        
    } catch (Throwable $e) {
        webhookLog('Error actualizando estadísticas', ['error' => $e->getMessage()]);
    }
}

/**
 * Maneja eventos de pagos (payment.created, payment.updated, etc.)
 */
function handlePaymentEvent($event, $action, $paymentId) {
    global $pdo;
    
    try {
        if (!$paymentId) {
            webhookLog('Payment event without payment ID');
            return;
        }

        webhookLog('Procesando pago', ['payment_id' => $paymentId]);

        $paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
        $payment = $paymentClient->get($paymentId);
        
        if (!$payment) {
            webhookLog('Payment not found', ['payment_id' => $paymentId]);
            return;
        }

        $status = $payment->status ?? null;
        $externalRef = $payment->external_reference ?? null;
        $payerEmail = $payment->payer->email ?? null;
        $amount = $payment->transaction_amount ?? 0;
        $preapprovalId = $payment->preapproval_id ?? null;
        $paymentMethodId = $payment->payment_method_id ?? null;
        $paymentDate = $payment->date_approved ?? $payment->date_created ?? date('Y-m-d H:i:s');

        webhookLog('Payment data', [
            'payment_id' => $paymentId,
            'status' => $status,
            'external_ref' => $externalRef,
            'payer_email' => $payerEmail,
            'amount' => $amount,
            'preapproval_id' => $preapprovalId,
            'payment_method' => $paymentMethodId,
            'payment_date' => $paymentDate
        ]);

        // Buscar membresía por external_reference o preapproval_id
        $membresiaId = null;
        
        if ($externalRef && preg_match('/^MEM_(\d+)$/', $externalRef, $m)) {
            $membresiaId = (int)$m[1];
            webhookLog('Membresía encontrada por external_ref', ['membresia_id' => $membresiaId]);
        } elseif ($preapprovalId) {
            $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_preapproval_id = ?");
            $stmt->execute([$preapprovalId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
                webhookLog('Membresía encontrada por preapproval_id', ['membresia_id' => $membresiaId]);
            }
        }

        if ($membresiaId) {
            // Verificar si el pago ya está registrado
            $existingLogStmt = $pdo->prepare("SELECT id FROM mp_payment_logs WHERE mp_payment_id = ?");
            $existingLogStmt->execute([$paymentId]);
            $existingLog = $existingLogStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingLog) {
                // Registrar el pago en la tabla de logs
                try {
                    $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                        (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $logStmt->execute([
                        $membresiaId,
                        $paymentId,
                        $preapprovalId,
                        $amount,
                        $status,
                        $paymentMethodId,
                        $paymentDate,
                        json_encode($payment)
                    ]);
                    
                    webhookLog('Pago registrado en mp_payment_logs', ['payment_id' => $paymentId]);
                } catch (Exception $logError) {
                    webhookLog('Error registrando pago en logs', ['error' => $logError->getMessage()]);
                }
            } else {
                webhookLog('Pago ya registrado en logs', ['payment_id' => $paymentId]);
            }

            // Actualizar la membresía con información del pago
            try {
                $updateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_last_payment_date = ?, 
                    mp_payment_method = ? 
                    WHERE id = ?");
                $updateStmt->execute([
                    $paymentDate,
                    $paymentMethodId,
                    $membresiaId
                ]);
                
                webhookLog('Membresía actualizada con información del pago', ['membresia_id' => $membresiaId]);
            } catch (Exception $updateError) {
                webhookLog('Error actualizando membresía', ['error' => $updateError->getMessage()]);
            }
        } else {
            webhookLog('No se encontró membresía para el pago', [
                'payment_id' => $paymentId,
                'external_ref' => $externalRef,
                'preapproval_id' => $preapprovalId
            ]);
        }

    } catch (Throwable $e) {
        webhookLog('Error handling payment event', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Maneja eventos de suscripciones (created, updated, cancelled)
 */
function handleSubscriptionEvent($event, $action, $preapprovalId) {
    global $pdo;
    
    try {
        if (!$preapprovalId) {
            webhookLog('Subscription event without preapproval ID');
            return;
        }

        $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
        $preapproval = $client->get($preapprovalId);
        
        if (!$preapproval) {
            webhookLog('Preapproval not found', ['preapproval_id' => $preapprovalId]);
            return;
        }

        $status = $preapproval->status ?? null;
        $externalRef = $preapproval->external_reference ?? null;
        $payerEmail = $preapproval->payer_email ?? null;
        $nextPaymentDate = $preapproval->next_payment_date ?? null;
        $lastPaymentDate = $preapproval->last_payment_date ?? null;
        $planId = $preapproval->preapproval_plan_id ?? null;

        webhookLog('Preapproval data', [
            'preapproval_id' => $preapprovalId,
            'status' => $status,
            'external_ref' => $externalRef,
            'payer_email' => $payerEmail,
            'next_payment_date' => $nextPaymentDate,
            'last_payment_date' => $lastPaymentDate,
            'plan_id' => $planId,
            'action' => $action
        ]);

        // Buscar membresía
        $membresiaId = null;
        
        webhookLog('Buscando membresía', [
            'external_ref' => $externalRef,
            'plan_id' => $planId,
            'preapproval_id' => $preapprovalId
        ]);
        
        if ($externalRef && preg_match('/^MEM_(\d+)$/', $externalRef, $m)) {
            $membresiaId = (int)$m[1];
            webhookLog('Membresía encontrada por external_ref MEM_XX', ['membresia_id' => $membresiaId]);
        } elseif ($externalRef && preg_match('/^EMP\d+_CLI_\d+_\d+_\d+$/', $externalRef)) {
            // Buscar por identificador único del cliente
            $stmt = $pdo->prepare("SELECT m.id FROM membresias m 
                                  JOIN clientes c ON m.cliente_id = c.id 
                                  WHERE c.identificador_unico = ? 
                                  ORDER BY m.created_at DESC LIMIT 1");
            $stmt->execute([$externalRef]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
                webhookLog('Membresía encontrada por identificador único del cliente', [
                    'external_ref' => $externalRef,
                    'membresia_id' => $membresiaId
                ]);
            }
        } elseif ($externalRef && preg_match('/^PLAN_(\d+)_/', $externalRef, $m)) {
            // Buscar por plan_id extraído del external_reference
            $planIdFromRef = $m[1];
            webhookLog('Buscando por plan_id extraído del external_ref', ['plan_id_from_ref' => $planIdFromRef]);
            
            // Buscar por plan_id exacto (numérico)
            $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_plan_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$planIdFromRef]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
                webhookLog('Membresía encontrada por plan_id numérico del external_ref', [
                    'plan_id' => $planIdFromRef,
                    'membresia_id' => $membresiaId
                ]);
            } else {
                // Buscar por plan_id del webhook (UUID)
                if ($planId) {
                    $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_plan_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$planId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $membresiaId = (int)$result['id'];
                        webhookLog('Membresía encontrada por plan_id UUID del webhook', [
                            'plan_id' => $planId,
                            'membresia_id' => $membresiaId
                        ]);
                    }
                }
                
                // Si aún no se encuentra, buscar por preapproval_id
                if (!$membresiaId && $preapprovalId) {
                    $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_preapproval_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$preapprovalId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $membresiaId = (int)$result['id'];
                        webhookLog('Membresía encontrada por preapproval_id como fallback', [
                            'preapproval_id' => $preapprovalId,
                            'membresia_id' => $membresiaId
                        ]);
                    }
                }
            }
        } elseif ($planId) {
            $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_plan_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$planId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
                webhookLog('Membresía encontrada por plan_id', [
                    'plan_id' => $planId,
                    'membresia_id' => $membresiaId
                ]);
            }
        } elseif ($preapprovalId) {
            // Buscar por preapproval_id como último recurso
            $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_preapproval_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$preapprovalId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
                webhookLog('Membresía encontrada por preapproval_id', [
                    'preapproval_id' => $preapprovalId,
                    'membresia_id' => $membresiaId
                ]);
            }
        }
        
        if (!$membresiaId) {
            webhookLog('No se encontró membresía', [
                'external_ref' => $externalRef,
                'plan_id' => $planId,
                'preapproval_id' => $preapprovalId
            ]);
        }

        if ($membresiaId) {
            // Obtener datos de la membresía
            $membresiaStmt = $pdo->prepare("SELECT m.*, c.correo, c.nombre, c.apellido_paterno 
                                           FROM membresias m 
                                           LEFT JOIN clientes c ON m.cliente_id = c.id 
                                           WHERE m.id = ?");
            $membresiaStmt->execute([$membresiaId]);
            $membresia = $membresiaStmt->fetch(PDO::FETCH_ASSOC);

            if ($membresia) {
                // Verificar si el estado cambió a cancelled
                $wasCancelled = ($membresia['mp_status'] !== 'cancelled' && $status === 'cancelled');
                
                // Actualizar la membresía
                $updateParts = [];
                $updateParams = [];

                if ($status) {
                    $updateParts[] = 'mp_status = ?';
                    $updateParams[] = $status;
                }
                
                if ($preapprovalId) {
                    $updateParts[] = 'mp_preapproval_id = ?';
                    $updateParams[] = $preapprovalId;
                }
                
                if ($nextPaymentDate) {
                    $updateParts[] = 'mp_next_payment_date = ?';
                    $updateParams[] = $nextPaymentDate;
                }
                
                if ($lastPaymentDate) {
                    $updateParts[] = 'mp_last_payment_date = ?';
                    $updateParams[] = $lastPaymentDate;
                }

                if ($updateParts) {
                    $updateParams[] = $membresiaId;
                    $updateSql = 'UPDATE membresias SET ' . implode(', ', $updateParts) . ' WHERE id = ?';
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute($updateParams);
                }

                // Actualizar email del cliente si es diferente
                if ($payerEmail && $membresia['correo'] !== $payerEmail) {
                    $clienteStmt = $pdo->prepare("UPDATE clientes SET correo = ? WHERE id = ?");
                    $clienteStmt->execute([$payerEmail, $membresia['cliente_id']]);
                }

                // Manejar cancelación con cargo si el estado cambió a cancelled
                if ($wasCancelled) {
                    webhookLog('Estado cambiado a cancelled, procesando cargo por cancelación', ['membresia_id' => $membresiaId]);
                    
                    // Actualizar inmediatamente el estado a cancelled
                    $cancelUpdateStmt = $pdo->prepare("UPDATE membresias SET mp_status = 'cancelled', mp_cancelled_at = NOW() WHERE id = ?");
                    $cancelUpdateStmt->execute([$membresiaId]);
                    
                    // Procesar cargo por cancelación
                    handleCancellationWithFee($membresia, $payerEmail);
                }

                // Enviar email de confirmación si fue autorizada
                if ($status === 'authorized' && $membresia['mp_status'] !== 'authorized') {
                    sendSubscriptionConfirmationEmail($membresia, $payerEmail);
                }

                webhookLog('Subscription updated', [
                    'membresia_id' => $membresiaId,
                    'status' => $status,
                    'was_cancelled' => $wasCancelled
                ]);
            }
        }

    } catch (Throwable $e) {
        webhookLog('Error handling subscription event', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Maneja eventos de pagos autorizados de suscripciones
 */
function handleAuthorizedPaymentEvent($event, $action, $authorizedPaymentId) {
    global $pdo;
    
    try {
        if (!$authorizedPaymentId) {
            webhookLog('Authorized payment event without ID');
            return;
        }

        // Obtener información del pago autorizado
        try {
            $paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
            $payment = $paymentClient->get($authorizedPaymentId);
            
            if (!$payment) {
                webhookLog('Authorized payment not found', ['authorized_payment_id' => $authorizedPaymentId]);
                return;
            }
        } catch (Throwable $e) {
            webhookLog('Error obteniendo authorized payment', [
                'authorized_payment_id' => $authorizedPaymentId,
                'error' => $e->getMessage()
            ]);
            return;
        }

        $preapprovalId = $payment->preapproval_id ?? null;
        $externalRef = $payment->external_reference ?? null;
        $payerEmail = $payment->payer->email ?? null;
        $amount = $payment->transaction_amount ?? 0;
        $status = $payment->status ?? null;

        webhookLog('Authorized payment data', [
            'authorized_payment_id' => $authorizedPaymentId,
            'preapproval_id' => $preapprovalId,
            'external_ref' => $externalRef,
            'payer_email' => $payerEmail,
            'amount' => $amount,
            'status' => $status
        ]);

        // Buscar membresía
        $membresiaId = null;
        
        if ($externalRef && preg_match('/^MEM_(\d+)$/', $externalRef, $m)) {
            $membresiaId = (int)$m[1];
        } elseif ($preapprovalId) {
            $stmt = $pdo->prepare("SELECT id FROM membresias WHERE mp_preapproval_id = ?");
            $stmt->execute([$preapprovalId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $membresiaId = (int)$result['id'];
            }
        }

        if ($membresiaId) {
            // Registrar el pago autorizado
            $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $logStmt->execute([
                $membresiaId,
                $authorizedPaymentId,
                $preapprovalId,
                $amount,
                $status,
                $payment->payment_method_id ?? null,
                $payment->date_approved ?? date('Y-m-d H:i:s'),
                json_encode($payment)
            ]);

            // Actualizar la membresía
            $updateStmt = $pdo->prepare("UPDATE membresias SET 
                mp_last_payment_date = ?, 
                mp_payment_method = ?,
                mp_status = 'authorized'
                WHERE id = ?");
            $updateStmt->execute([
                $payment->date_approved ?? date('Y-m-d H:i:s'),
                $payment->payment_method_id ?? null,
                $membresiaId
            ]);

            webhookLog('Authorized payment processed', ['membresia_id' => $membresiaId]);
        }

    } catch (Throwable $e) {
        webhookLog('Error handling authorized payment event', ['error' => $e->getMessage()]);
    }
}

/**
 * Maneja eventos de cancelación de suscripciones
 */
function handleCancellationEvent($event, $action, $preapprovalId) {
    global $pdo;
    
    try {
        if (!$preapprovalId) {
            webhookLog('Cancellation event without preapproval ID');
            return;
        }

        webhookLog('Processing cancellation event', ['preapproval_id' => $preapprovalId]);

        $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
        $preapproval = $client->get($preapprovalId);
        
        if (!$preapproval) {
            webhookLog('Preapproval not found for cancellation', ['preapproval_id' => $preapprovalId]);
            return;
        }

        $status = $preapproval->status ?? null;
        $externalRef = $preapproval->external_reference ?? null;
        $payerEmail = $preapproval->payer_email ?? null;

        webhookLog('Cancellation preapproval data', [
            'preapproval_id' => $preapprovalId,
            'status' => $status,
            'external_ref' => $externalRef,
            'payer_email' => $payerEmail
        ]);

        // Buscar membresía
        $membresiaId = null;
        
        if ($externalRef && preg_match('/^MEM_(\d+)$/', $externalRef, $m)) {
            $membresiaId = (int)$m[1];
            webhookLog('Membresía encontrada por external_ref para cancelación', ['membresia_id' => $membresiaId]);
        }

        if ($membresiaId) {
            // Obtener datos de la membresía
            $membresiaStmt = $pdo->prepare("SELECT m.*, c.correo, c.nombre, c.apellido_paterno 
                                           FROM membresias m 
                                           LEFT JOIN clientes c ON m.cliente_id = c.id 
                                           WHERE m.id = ?");
            $membresiaStmt->execute([$membresiaId]);
            $membresia = $membresiaStmt->fetch(PDO::FETCH_ASSOC);

            if ($membresia) {
                webhookLog('Procesando cancelación para membresía', ['membresia_id' => $membresiaId]);
                
                // Verificar si ya está cancelada
                $wasAlreadyCancelled = ($membresia['mp_status'] === 'cancelled');
                
                // Actualizar la membresía con el nuevo estado
                $updateStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_status = ?, 
                    mp_preapproval_id = ?,
                    mp_cancelled_at = NOW()
                    WHERE id = ?");
                $updateStmt->execute([$status, $preapprovalId, $membresiaId]);

                // Manejar cancelación con cargo solo si no estaba ya cancelada
                if ($status === 'cancelled' && !$wasAlreadyCancelled) {
                    webhookLog('Procesando cargo por cancelación para membresía recién cancelada', ['membresia_id' => $membresiaId]);
                    handleCancellationWithFee($membresia, $payerEmail);
                } elseif ($status === 'cancelled' && $wasAlreadyCancelled) {
                    webhookLog('Membresía ya estaba cancelada, verificando si necesita cargo', ['membresia_id' => $membresiaId]);
                    
                    // Verificar si necesita cargo por cancelación
                    if (!$membresia['mp_cancellation_fee'] || $membresia['mp_cancellation_fee'] == 0) {
                        webhookLog('Membresía cancelada sin cargo, procesando cargo pendiente', ['membresia_id' => $membresiaId]);
                        handleCancellationWithFee($membresia, $payerEmail);
                    }
                }

                webhookLog('Cancellation processed successfully', ['membresia_id' => $membresiaId]);
            } else {
                webhookLog('Membresía no encontrada para cancelación', ['membresia_id' => $membresiaId]);
            }
        } else {
            webhookLog('No se encontró membresía para la cancelación', ['external_ref' => $externalRef]);
        }

    } catch (Throwable $e) {
        webhookLog('Error handling cancellation event', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Maneja la cancelación con cargo por cancelación
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
            webhookLog('Monto de cancelación obtenido de tipos_membresia', [
                'tipo_membresia_id' => $membresia['tipo_membresia_id'],
                'monto_cancelacion' => $cancellationAmount,
                'tipo_nombre' => $tipo['nombre'],
                'empresa_nombre' => $tipo['empresa_nombre']
            ]);
            
            $charged = false;
            $chargeError = null;
            $chargeDetails = null;
            $paymentId = null;
            $paymentStatus = null;

            webhookLog('Iniciando cargo por cancelación', [
                'amount' => $cancellationAmount,
                'membresia_id' => $membresia['id'],
                'cliente_id' => $membresia['cliente_id'],
                'cliente_nombre' => $membresia['nombre'] . ' ' . $membresia['apellido_paterno']
            ]);

            try {
                // Verificar si el cliente tiene token de tarjeta
                $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id, mp_card_id FROM clientes WHERE id = ?");
                $clienteStmt->execute([$membresia['cliente_id']]);
                $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
                
                webhookLog('Verificando token de tarjeta del cliente', [
                    'cliente_id' => $membresia['cliente_id'],
                    'tiene_token' => !empty($cliente['mp_card_token']),
                    'tiene_customer_id' => !empty($cliente['mp_customer_id']),
                    'tiene_card_id' => !empty($cliente['mp_card_id'])
                ]);
                
                if ($cliente && $cliente['mp_card_token']) {
                    webhookLog('Cliente tiene token de tarjeta, intentando cobro automático', [
                        'cliente_id' => $membresia['cliente_id'],
                        'monto' => $cancellationAmount,
                        'external_reference' => 'CANCEL_FEE_MEM_' . $membresia['id']
                    ]);
                    
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
                        
                        webhookLog('Cargo por cancelación exitoso', [
                            'amount' => $cancellationAmount,
                            'payment_id' => $paymentId,
                            'status' => $paymentStatus,
                            'membresia_id' => $membresia['id']
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
                            webhookLog('Pago por cancelación registrado en logs exitosamente', [
                                'payment_id' => $paymentId,
                                'membresia_id' => $membresia['id']
                            ]);
                        } catch (Exception $logError) {
                            webhookLog('Error registrando pago por cancelación en logs', [
                                'error' => $logError->getMessage(),
                                'payment_id' => $paymentId,
                                'membresia_id' => $membresia['id']
                            ]);
                        }
                    } else {
                        $chargeError = $result['error'] ?? 'Error desconocido en el cobro';
                        $chargeDetails = 'Error en cobro automático: ' . $chargeError;
                        webhookLog('Error en cargo por cancelación', [
                            'error' => $chargeError,
                            'membresia_id' => $membresia['id'],
                            'cliente_id' => $membresia['cliente_id']
                        ]);
                        
                        // Log detalles adicionales del error
                        if (isset($result['api_response'])) {
                            webhookLog('API Response del error', [
                                'api_response' => $result['api_response'],
                                'membresia_id' => $membresia['id']
                            ]);
                        }
                        if (isset($result['status_code'])) {
                            webhookLog('Status Code del error', [
                                'status_code' => $result['status_code'],
                                'membresia_id' => $membresia['id']
                            ]);
                        }
                        if (isset($result['debug_info'])) {
                            webhookLog('Debug Info del error', [
                                'debug_info' => $result['debug_info'],
                                'membresia_id' => $membresia['id']
                            ]);
                        }
                        if (isset($result['error_details'])) {
                            webhookLog('Error Details del error', [
                                'error_details' => $result['error_details'],
                                'membresia_id' => $membresia['id']
                            ]);
                        }
                        
                        // Log completo del resultado para debugging
                        webhookLog('Resultado completo del error', [
                            'result_complete' => $result,
                            'membresia_id' => $membresia['id']
                        ]);
                    }
                } else {
                    $chargeError = 'Cliente no tiene token de tarjeta guardado';
                    $chargeDetails = 'Cargo pendiente - Cliente sin token de tarjeta';
                    webhookLog('Cliente sin token de tarjeta para cargo automático', [
                        'cliente_id' => $membresia['cliente_id'],
                        'membresia_id' => $membresia['id'],
                        'tiene_token' => !empty($cliente['mp_card_token']),
                        'tiene_customer_id' => !empty($cliente['mp_customer_id'])
                    ]);
                }
            } catch (Throwable $e) {
                $chargeError = $e->getMessage();
                $chargeDetails = 'Excepción en cobro automático: ' . $chargeError;
                webhookLog('Excepción en cargo por cancelación', [
                    'error' => $chargeError,
                    'membresia_id' => $membresia['id'],
                    'cliente_id' => $membresia['cliente_id'],
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
                
                webhookLog('Información de cancelación actualizada en base de datos', [
                    'membresia_id' => $membresia['id'],
                    'monto_cancelacion' => $cancellationAmount,
                    'razon_cancelacion' => $chargeDetails
                ]);
                
            } catch (Exception $updateError) {
                webhookLog('Error actualizando información de cancelación', [
                    'error' => $updateError->getMessage(),
                    'membresia_id' => $membresia['id']
                ]);
            }
        } else {
            webhookLog('No hay cargo por cancelación configurado para este tipo de membresía', [
                'tipo_membresia_id' => $membresia['tipo_membresia_id'],
                'monto_cancelacion_configurado' => $tipo ? $tipo['monto_cancelacion'] : 'tipo no encontrado'
            ]);
            
            // Actualizar solo el estado de cancelación sin cargo
            try {
                $updateCancellationSql = "UPDATE membresias SET 
                    mp_cancelled_at = NOW(),
                    mp_cancellation_reason = 'Cancelación sin cargo',
                    mp_status = 'cancelled'
                    WHERE id = ?";
                $cancellationStmt = $pdo->prepare($updateCancellationSql);
                $cancellationStmt->execute([$membresia['id']]);
                
                webhookLog('Cancelación sin cargo registrada en base de datos', [
                    'membresia_id' => $membresia['id']
                ]);
                
            } catch (Exception $updateError) {
                webhookLog('Error actualizando cancelación sin cargo', [
                    'error' => $updateError->getMessage(),
                    'membresia_id' => $membresia['id']
                ]);
            }
        }

        // Enviar email de cancelación
        if ($tipo) {
            try {
                $clienteData = [
                    'nombre' => $membresia['nombre'],
                    'apellido_paterno' => $membresia['apellido_paterno'],
                    'correo' => $payerEmail ?: $membresia['correo']
                ];

                $empresaData = [
                    'nombre' => $tipo['empresa_nombre'] ?: 'Nuestra Empresa'
                ];

                $cancellationFee = ($tipo['monto_cancelacion'] > 0) ? $tipo['monto_cancelacion'] : 0;
                $emailResult = sendCancellationEmail($clienteData, $tipo, $empresaData, $cancellationFee);
                
                if ($emailResult) {
                    webhookLog('Email de cancelación enviado exitosamente', [
                        'email' => $clienteData['correo'],
                        'membresia_id' => $membresia['id']
                    ]);
                } else {
                    webhookLog('Error enviando email de cancelación', [
                        'email' => $clienteData['correo'],
                        'membresia_id' => $membresia['id']
                    ]);
                }
            } catch (Throwable $emailError) {
                webhookLog('Excepción enviando email de cancelación', [
                    'error' => $emailError->getMessage(),
                    'membresia_id' => $membresia['id']
                ]);
            }
        }

    } catch (Throwable $e) {
        webhookLog('Error general en handleCancellationWithFee', [
            'error' => $e->getMessage(),
            'membresia_id' => $membresia['id'] ?? 'no disponible',
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Envía email de confirmación de suscripción
 */
function sendSubscriptionConfirmationEmail($membresia, $payerEmail) {
    try {
        global $pdo;
        
        // Obtener datos del tipo de membresía y empresa
        $tipoStmt = $pdo->prepare("SELECT tm.*, e.nombre as empresa_nombre 
                                  FROM tipos_membresia tm 
                                  LEFT JOIN empresas e ON tm.empresa_id = e.id 
                                  WHERE tm.id = ?");
        $tipoStmt->execute([$membresia['tipo_membresia_id']]);
        $tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC);

        if ($tipo) {
            $clienteData = [
                'nombre' => $membresia['nombre'],
                'apellido_paterno' => $membresia['apellido_paterno'],
                'correo' => $payerEmail ?: $membresia['correo']
            ];

            $empresaData = [
                'nombre' => $tipo['empresa_nombre'] ?: 'Nuestra Empresa'
            ];

            sendSubscriptionEmail($clienteData, $tipo, $empresaData);
            webhookLog('Subscription confirmation email sent', ['email' => $clienteData['correo']]);
        }
    } catch (Throwable $e) {
        webhookLog('Error sending subscription email', ['error' => $e->getMessage()]);
    }
}

