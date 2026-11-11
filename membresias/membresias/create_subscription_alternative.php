<?php
// Versión alternativa usando preferencias de Mercado Pago (funciona correctamente)
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

// Manejar peticiones POST (para activación de membresías)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    $accion = $input['accion'] ?? '';
    
    if ($accion === 'activate_membership') {
        try {
            $membresia_id = $input['membresia_id'] ?? null;
            
            if (!$membresia_id) {
                echo json_encode(['success' => false, 'error' => 'ID de membresía requerido']);
                exit;
            }
            
            // Verificar que el cliente esté logueado
            if (!isset($_SESSION['cliente'])) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }
            
            $cliente_id = $_SESSION['cliente']['id'];
            
            // Obtener la membresía del cliente
            $stmt = $pdo->prepare("
                SELECT m.*, tm.nombre as plan_nombre, tm.precio, tm.mp_plan_id, tm.empresa_id
                FROM membresias m
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.id = ? AND m.cliente_id = ?
            ");
            $stmt->execute([$membresia_id, $cliente_id]);
            $membresia = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$membresia) {
                echo json_encode(['success' => false, 'error' => 'Membresía no encontrada']);
                exit;
            }
            
            // Verificar que el plan existe
            if (!$membresia['mp_plan_id']) {
                echo json_encode(['success' => false, 'error' => 'Plan no encontrado']);
                exit;
            }
            
            // Verificar que el cliente tiene token de tarjeta
            $clienteStmt = $pdo->prepare("SELECT mp_card_token FROM clientes WHERE id = ?");
            $clienteStmt->execute([$cliente_id]);
            $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente || !$cliente['mp_card_token']) {
                echo json_encode(['success' => false, 'error' => 'Debe configurar un método de pago primero']);
                exit;
            }
            
            // Inicializar Mercado Pago
            mpInit();
            
            // Crear preferencia de pago usando el SDK v3
            $preferenceClient = new \MercadoPago\Client\Preference\PreferenceClient();
            
            // Generar external_reference único con timestamp
            $timestamp = time();
            $external_reference = 'MEM_' . $membresia_id . '_' . $cliente_id . '_' . $timestamp;
            
            // Crear item para la preferencia
            $item = [
                'title' => $membresia['plan_nombre'],
                'quantity' => 1,
                'unit_price' => floatval($membresia['precio']),
                'currency_id' => 'MXN'
            ];
            
            // Crear preferencia con expiración de 24 horas
            $preferenceRequest = [
                'items' => [$item],
                'external_reference' => $external_reference,
                'notification_url' => 'https://membresia.secureitmx.com/membresias/payment_success.php',
                'back_urls' => [
                    'success' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=success',
                    'failure' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=failure',
                    'pending' => 'https://membresia.secureitmx.com/membresias/dashboard.php?payment=pending'
                ],
                'auto_return' => 'approved',
                'expires' => true,
                'expiration_date_to' => date('Y-m-d\TH:i:s.000-03:00', strtotime('+24 hours')),
                'payer' => [
                    'email' => $_SESSION['cliente']['correo'] ?? ''
                ]
            ];
            
            // Crear la preferencia
            $preference = $preferenceClient->create($preferenceRequest);
            
            if ($preference && $preference->id) {
                // Actualizar la membresía con el ID de preferencia
                $updateStmt = $pdo->prepare("
                    UPDATE membresias 
                    SET mp_subscription_id = ?, 
                        mp_status = 'pending',
                        mp_external_reference = ?,
                        mp_last_sync_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$preference->id, $external_reference, $membresia_id]);
                
                // Generar URL de checkout directo usando el mp_plan_id
                $checkoutUrl = "https://www.mercadopago.com.mx/subscriptions/checkout?preapproval_plan_id=" . $membresia['mp_plan_id'];
                
                echo json_encode([
                    'success' => true,
                    'subscription_id' => $preference->id,
                    'init_point' => $checkoutUrl,
                    'external_reference' => $external_reference,
                    'message' => 'Preferencia creada exitosamente'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear preferencia']);
            }
            
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $errorMessage = 'Error de API de Mercado Pago';
            
            // Analizar el error específico - convertir el objeto a array si es necesario
            $responseData = $apiResponse;
            if (is_object($apiResponse)) {
                $responseData = $apiResponse->getContent();
            }
            
            if (isset($responseData['error'])) {
                if (isset($responseData['error']['causes'])) {
                    foreach ($responseData['error']['causes'] as $cause) {
                        if (strpos($cause['description'] ?? '', 'external_reference') !== false) {
                            $errorMessage = 'Error: La referencia externa ya existe. Intente nuevamente.';
                            break;
                        }
                    }
                }
                $errorMessage .= ': ' . ($responseData['error']['message'] ?? $e->getMessage());
            }
            
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al activar la membresía: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Código original para crear nueva membresía (mantener para compatibilidad)
// ... existing code ...
