<?php
// Procesar pagos exitosos de Mercado Pago
include '../includes/db.php';
include '../includes/config.php';

initConfig();

// Obtener parámetros de la URL
$payment_id = $_GET['payment_id'] ?? null;
$external_reference = $_GET['external_reference'] ?? null;
$status = $_GET['status'] ?? null;

// Log del callback
error_log("Payment Success Callback - Payment ID: $payment_id, External Ref: $external_reference, Status: $status");

try {
    if ($external_reference) {
        // Buscar la preferencia de pago
        $stmt = $pdo->prepare("
            SELECT pp.*, m.id as membresia_id, m.cliente_id 
            FROM payment_preferences pp
            LEFT JOIN membresias m ON pp.membresia_id = m.id
            WHERE pp.external_reference = ?
        ");
        $stmt->execute([$external_reference]);
        $preference = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($preference) {
            // Actualizar el estado del pago
            $stmt = $pdo->prepare("
                UPDATE payment_preferences 
                SET status = ?, payment_id = ?, updated_at = NOW()
                WHERE external_reference = ?
            ");
            $stmt->execute([$status, $payment_id, $external_reference]);
            
            // Si el pago fue aprobado, actualizar la membresía
            if ($status === 'approved' && $preference['membresia_id']) {
                $stmt = $pdo->prepare("
                    UPDATE membresias 
                    SET mp_status = 'authorized', 
                        mp_payment_id = ?, 
                        mp_payment_date = NOW(),
                        mp_external_reference = ?
                    WHERE id = ?
                ");
                $stmt->execute([$payment_id, $external_reference, $preference['membresia_id']]);
                
                error_log("Membresía actualizada - ID: {$preference['membresia_id']}, Cliente: {$preference['cliente_id']}");
            }
        }
    }
    
    // Responder con éxito
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Pago procesado correctamente']);
    
} catch (PDOException $e) {
    error_log("Error procesando pago: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
?>
