<?php
/**
 * Endpoint AJAX para procesar automáticamente pagos por cancelación
 * Se puede llamar desde JavaScript para procesamiento automático
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../config/mercadopago_config.php';

// Inicializar configuración
initConfig();

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar método de petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar si ya se procesó recientemente
$lastProcessFile = __DIR__ . '/../../logs/last_ajax_cancellation_process.txt';
$currentTime = time();

if (file_exists($lastProcessFile)) {
    $lastProcess = (int)file_get_contents($lastProcessFile);
    if (($currentTime - $lastProcess) < 60) { // 1 minuto
        echo json_encode([
            'success' => true,
            'message' => 'Procesamiento reciente, esperando...',
            'processed' => 0
        ]);
        exit;
    }
}

// Actualizar timestamp
file_put_contents($lastProcessFile, $currentTime);

try {
    // Buscar membresías canceladas con cargo pendiente
    $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.monto_cancelacion, tm.nombre as tipo_nombre
            FROM membresias m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
            WHERE m.mp_status = 'cancelled' 
            AND m.mp_cancellation_fee > 0
            AND m.mp_cancelled_at IS NOT NULL
            AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
            AND m.mp_cancellation_reason NOT LIKE '%Error en cobro%'
            AND m.mp_cancellation_reason NOT LIKE '%Pago procesado%'
            ORDER BY m.mp_cancelled_at DESC
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $errors = [];
    
    foreach ($membresias as $membresia) {
        try {
            // Verificar si el cliente tiene token de tarjeta
            $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id FROM clientes WHERE id = ?");
            $clienteStmt->execute([$membresia['cliente_id']]);
            $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente || !$cliente['mp_card_token']) {
                $errors[] = 'Cliente sin token de tarjeta: ' . $membresia['nombre'] . ' ' . $membresia['apellido_paterno'];
                continue;
            }
            
            // Intentar cobro automático
            $result = mpCreateCancellationPaymentFromClient(
                $membresia['cliente_id'],
                $membresia['mp_cancellation_fee'],
                'CANCEL_FEE_MEM_' . $membresia['id']
            );
            
            if ($result['success']) {
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
                    mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado via AJAX: ', ?)
                    WHERE id = ?");
                $updateStmt->execute([$result['payment_id'], $membresia['id']]);
                
                $processed++;
                error_log('AJAX - Pago por cancelación procesado: ' . $result['payment_id']);
                
            } else {
                $errors[] = 'Error procesando pago para ' . $membresia['nombre'] . ': ' . ($result['error'] ?? 'Error desconocido');
            }
            
        } catch (Throwable $e) {
            $errors[] = 'Excepción procesando ' . $membresia['nombre'] . ': ' . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'total_found' => count($membresias),
        'errors' => $errors,
        'message' => "Procesados $processed de " . count($membresias) . " pagos pendientes"
    ]);
    
} catch (Throwable $e) {
    error_log('AJAX - Error procesando cancelaciones: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
