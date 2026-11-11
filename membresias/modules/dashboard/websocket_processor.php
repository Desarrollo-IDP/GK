<?php
/**
 * Sistema de WebSockets para procesamiento automático de pagos por cancelación
 * Requiere extensión WebSocket de PHP o librería externa
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../config/mercadopago_config.php';

// Inicializar configuración
initConfig();

class CancellationPaymentProcessor {
    private $pdo;
    private $clients = [];
    private $lastProcessTime = 0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Procesa pagos por cancelación pendientes
     */
    public function processPendingPayments() {
        try {
            // Verificar si ya se procesó recientemente (últimos 2 minutos)
            $currentTime = time();
            if (($currentTime - $this->lastProcessTime) < 120) {
                return ['processed' => 0, 'message' => 'Procesamiento reciente'];
            }
            
            $this->lastProcessTime = $currentTime;
            
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
                    LIMIT 3";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($membresias)) {
                return ['processed' => 0, 'message' => 'No hay pagos pendientes'];
            }
            
            $processed = 0;
            $errors = [];
            
            foreach ($membresias as $membresia) {
                try {
                    // Verificar si el cliente tiene token de tarjeta
                    $clienteStmt = $this->pdo->prepare("SELECT mp_card_token, mp_customer_id FROM clientes WHERE id = ?");
                    $clienteStmt->execute([$membresia['cliente_id']]);
                    $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cliente || !$cliente['mp_card_token']) {
                        $errors[] = 'Cliente sin token: ' . $membresia['nombre'];
                        continue;
                    }
                    
                    // Intentar cobro automático
                    $result = mpCreateCancellationPaymentFromClient(
                        $membresia['cliente_id'],
                        $membresia['mp_cancellation_fee'],
                        'CANCEL_FEE_MEM_' . $membresia['id']
                    );
                    
                    if ($result['success']) {
                        // Registrar el pago
                        $logStmt = $this->pdo->prepare("INSERT INTO mp_payment_logs 
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
                        
                        // Actualizar membresía
                        $updateStmt = $this->pdo->prepare("UPDATE membresias SET 
                            mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado via WebSocket: ', ?)
                            WHERE id = ?");
                        $updateStmt->execute([$result['payment_id'], $membresia['id']]);
                        
                        $processed++;
                        error_log('WebSocket - Pago procesado: ' . $result['payment_id']);
                        
                    } else {
                        $errors[] = 'Error procesando ' . $membresia['nombre'] . ': ' . ($result['error'] ?? 'Error desconocido');
                    }
                    
                } catch (Throwable $e) {
                    $errors[] = 'Excepción procesando ' . $membresia['nombre'] . ': ' . $e->getMessage();
                }
            }
            
            return [
                'processed' => $processed,
                'total_found' => count($membresias),
                'errors' => $errors,
                'message' => "Procesados $processed de " . count($membresias) . " pagos pendientes"
            ];
            
        } catch (Throwable $e) {
            error_log('WebSocket - Error procesando cancelaciones: ' . $e->getMessage());
            return ['processed' => 0, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Notifica a todos los clientes conectados
     */
    public function notifyClients($data) {
        $message = json_encode($data);
        foreach ($this->clients as $client) {
            try {
                $client->send($message);
            } catch (Exception $e) {
                error_log('WebSocket - Error enviando mensaje: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Agrega un cliente a la lista
     */
    public function addClient($client) {
        $this->clients[] = $client;
    }
    
    /**
     * Remueve un cliente de la lista
     */
    public function removeClient($client) {
        $key = array_search($client, $this->clients);
        if ($key !== false) {
            unset($this->clients[$key]);
        }
    }
}

// Ejemplo de uso con Ratchet WebSocket (requiere instalación)
/*
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class CancellationWebSocketHandler implements MessageComponentInterface {
    protected $processor;
    
    public function __construct() {
        global $pdo;
        $this->processor = new CancellationPaymentProcessor($pdo);
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->processor->addClient($conn);
        echo "Nueva conexión: {$conn->resourceId}\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        if ($msg === 'process') {
            $result = $this->processor->processPendingPayments();
            $this->processor->notifyClients($result);
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->processor->removeClient($conn);
        echo "Conexión cerrada: {$conn->resourceId}\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Para iniciar el servidor WebSocket:
// $server = IoServer::factory(
//     new HttpServer(
//         new WsServer(
//             new CancellationWebSocketHandler()
//         )
//     ),
//     8080
// );
// $server->run();
*/
?>
