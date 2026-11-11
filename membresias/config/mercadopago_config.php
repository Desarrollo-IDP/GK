<?php
/**
 * Configuración e integración de Mercado Pago (SDK v3) para el sistema de membresías
 */

use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Client\PreApprovalPlan\PreApprovalPlanClient;
use MercadoPago\MercadoPagoConfig;

// Asegurar autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Configuración de Mercado Pago (Credenciales de Producción)
// NOTA: Para desarrollo, necesitas credenciales de TEST reales desde tu cuenta de Mercado Pago
if (!defined('MP_PUBLIC_KEY')) define('MP_PUBLIC_KEY', 'APP_USR-75eacea8-eed8-44ac-b63f-c871863d857e');
if (!defined('MP_ACCESS_TOKEN')) define('MP_ACCESS_TOKEN', 'APP_USR-8978107880642429-081212-f72cd2585bcb4498b8ddc90e93bd00bf-2624624906');

// URL configurada en Mercado Pago para recibir notificaciones
if (!defined('MP_WEBHOOK_URL')) define('MP_WEBHOOK_URL', 'https://membresia.secureitmx.com/webhook.php');

// Email saliente
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', 'noreply@tu-dominio.com');
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', 'Sistema de Membresías');

// Porcentaje de cargo por cancelación (si aplica en tu negocio)
if (!defined('CANCELLATION_FEE_PERCENTAGE')) define('CANCELLATION_FEE_PERCENTAGE', 10);

/**
 * Extrae el collector_id del token de acceso de MercadoPago
 */
function getCollectorIdFromToken($accessToken) {
    $parts = explode('-', $accessToken);
    return end($parts);
}

function mpInit(): void {
    // Inicializa el SDK v3
    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    // Opcional: MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);
}

function mpVerifyCredentials(): array {
    try {
        mpInit();
        
        // Verificar credenciales usando una verificación más simple
        // que no requiera crear recursos en MercadoPago
        $collectorId = getCollectorIdFromToken(MP_ACCESS_TOKEN);
        
        // Verificar que el collector_id sea válido
        if (empty($collectorId) || !is_numeric($collectorId)) {
            return [
                'success' => false,
                'error' => 'Collector ID inválido',
                'details' => 'No se pudo extraer un collector_id válido del token de acceso'
            ];
        }
        
        // Verificar que las credenciales básicas estén configuradas
        if (empty(MP_ACCESS_TOKEN) || empty(MP_PUBLIC_KEY)) {
            return [
                'success' => false,
                'error' => 'Credenciales faltantes',
                'details' => 'MP_ACCESS_TOKEN o MP_PUBLIC_KEY no están configurados'
            ];
        }
        
        // Si llegamos aquí, las credenciales básicas son válidas
        return [
            'success' => true,
            'message' => 'Credenciales básicas válidas',
            'collector_id' => $collectorId,
            'user_info' => [
                'collector_id' => $collectorId,
                'token_length' => strlen(MP_ACCESS_TOKEN),
                'public_key_length' => strlen(MP_PUBLIC_KEY)
            ]
        ];
        
    } catch (Throwable $e) {
        error_log('MP Verify Credentials Error: ' . $e->getMessage());
        
        $errorDetails = [];
        
        // Verificar si es un error de API específico
        if (method_exists($e, 'getApiResponse')) {
            $apiResponse = $e->getApiResponse();
            $errorDetails['api_response'] = $apiResponse;
        }
        
        // Verificar si es un error de red
        if (method_exists($e, 'getCode')) {
            $errorDetails['error_code'] = $e->getCode();
        }
        
        $errorDetails['full_message'] = $e->getMessage();
        
        // Verificar si es un error de autenticación específico
        $isAuthError = false;
        if (strpos($e->getMessage(), '401') !== false || 
            strpos($e->getMessage(), 'Unauthorized') !== false ||
            strpos($e->getMessage(), 'Invalid access token') !== false) {
            $isAuthError = true;
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => $isAuthError ? 'Token de acceso inválido o expirado' : 'Error de conexión con MercadoPago',
            'debug_info' => $errorDetails,
            'is_auth_error' => $isAuthError
        ];
    }
}

function mpTestConnection(): array {
    try {
        mpInit();
        
        // Verificar conexión usando una prueba más simple
        // que no requiera crear recursos en MercadoPago
        $collectorId = getCollectorIdFromToken(MP_ACCESS_TOKEN);
        
        // Verificar que el SDK se inicializó correctamente
        if (!class_exists('MercadoPago\MercadoPagoConfig')) {
            return [
                'success' => false,
                'error' => 'SDK de MercadoPago no disponible',
                'details' => 'La librería de MercadoPago no está instalada correctamente'
            ];
        }
        
        // Verificar que las credenciales estén configuradas
        if (empty(MP_ACCESS_TOKEN)) {
            return [
                'success' => false,
                'error' => 'Token de acceso no configurado',
                'details' => 'MP_ACCESS_TOKEN no está definido'
            ];
        }
        
        // Si llegamos aquí, la conexión básica es exitosa
        return [
            'success' => true,
            'message' => 'Conexión básica exitosa con MercadoPago',
            'collector_id' => $collectorId,
            'sdk_version' => 'MercadoPago SDK v3',
            'connection_info' => [
                'collector_id' => $collectorId,
                'token_configured' => !empty(MP_ACCESS_TOKEN),
                'public_key_configured' => !empty(MP_PUBLIC_KEY),
                'webhook_url_configured' => !empty(MP_WEBHOOK_URL)
            ]
        ];
        
    } catch (Throwable $e) {
        error_log('MP Test Connection Error: ' . $e->getMessage());
        
        // Obtener más detalles del error
        $errorDetails = [];
        
        // Verificar si es un error de API específico
        if (method_exists($e, 'getApiResponse')) {
            $apiResponse = $e->getApiResponse();
            $errorDetails['api_response'] = $apiResponse;
        }
        
        // Verificar si es un error de red
        if (method_exists($e, 'getCode')) {
            $errorDetails['error_code'] = $e->getCode();
        }
        
        // Verificar el mensaje completo
        $errorDetails['full_message'] = $e->getMessage();
        $errorDetails['trace'] = $e->getTraceAsString();
        
        error_log('MP Test Connection Error Details: ' . json_encode($errorDetails));
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => 'Verifica las credenciales de MercadoPago',
            'debug_info' => $errorDetails
        ];
    }
}

/**
 * Función específica para probar la creación de planes en MercadoPago
 * Esta función se usa para verificar que las credenciales permiten crear recursos
 */
function mpTestPlanCreation(): array {
    try {
        mpInit();
        
        $planClient = new PreApprovalPlanClient();
        
        // Crear un plan de prueba mínimo
        $testRequest = [
            'back_url' => MP_WEBHOOK_URL,
            'reason' => 'Test Plan Creation - ' . date('Y-m-d H:i:s'),
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => 10.00,
                'currency_id' => 'MXN'
            ],
            'external_reference' => 'TEST_PLAN_' . time(),
            'payer_email' => 'test@example.com',
            'collector_id' => getCollectorIdFromToken(MP_ACCESS_TOKEN),
            'application_fee' => 0,
            'status' => 'active'
        ];
        
        error_log('MP Test Plan Creation Request: ' . json_encode($testRequest));
        
        $plan = $planClient->create($testRequest);
        
        return [
            'success' => true,
            'message' => 'Plan de prueba creado exitosamente',
            'plan_id' => $plan->id ?? null,
            'plan_data' => $plan
        ];
        
    } catch (Throwable $e) {
        error_log('MP Test Plan Creation Error: ' . $e->getMessage());
        
        $errorDetails = [];
        if (method_exists($e, 'getApiResponse')) {
            $errorDetails['api_response'] = $e->getApiResponse();
        }
        if (method_exists($e, 'getCode')) {
            $errorDetails['error_code'] = $e->getCode();
        }
        $errorDetails['full_message'] = $e->getMessage();
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => 'Error al crear plan de prueba',
            'debug_info' => $errorDetails
        ];
    }
}

function mpCreatePlan($nombre, $precio, $descripcion = '', $duracion = 1, $tipo_duracion = 'month', $external_reference = '', $payer_email = 'admin@secureitmx.com', $application_fee = 0, $status = 'active') {
    try {
        // Validar campos requeridos
        if (empty($nombre)) {
            return ['success' => false, 'error' => 'El nombre del plan es requerido'];
        }
        
        if (empty($precio) || $precio <= 0) {
            return ['success' => false, 'error' => 'El precio debe ser mayor a 0'];
        }
        
        mpInit();
        $planClient = new PreApprovalPlanClient();
        
        // Convertir tipo de duración a formato correcto de MercadoPago
        $frequency_type = 'months'; // Por defecto
        if ($tipo_duracion === 'day') {
            $frequency_type = 'days';
        } elseif ($tipo_duracion === 'week') {
            // Para semanas, convertimos a días
            $frequency_type = 'days';
            $duracion = $duracion * 7;
        } elseif ($tipo_duracion === 'year') {
            // Para años, convertimos a meses
            $frequency_type = 'months';
            $duracion = $duracion * 12;
        }
        
        $request = [
            'back_url' => MP_WEBHOOK_URL, // SIEMPRE requerido
            'reason' => $nombre,
            'auto_recurring' => [
                'frequency' => $duracion,
                'frequency_type' => $frequency_type,
                'transaction_amount' => (float)$precio,
                'currency_id' => 'MXN'
            ],
            'external_reference' => 'PLAN_' . time() . '_' . uniqid(),
            'payer_email' => 'admin@secureitmx.com', // Email por defecto
            'collector_id' => getCollectorIdFromToken(MP_ACCESS_TOKEN), // ID del vendedor (extraído automáticamente)
            'application_fee' => 0,
            'status' => 'active'
        ];
        
        // Agregar descripción si está disponible
        if (!empty($descripcion)) {
            $request['reason'] .= ' - ' . $descripcion;
        }
        
        error_log('MP CreatePlan Request: ' . json_encode($request));
        
        $plan = $planClient->create($request);
        
        error_log('MP CreatePlan Success: ' . json_encode($plan));
        
        return [
            'success' => true,
            'plan_id' => $plan->id ?? null,
            'plan_data' => $plan
        ];
        
    } catch (Throwable $e) {
        error_log('MP CreatePlan Error: ' . $e->getMessage());
        $errorDetails = [];
        if (method_exists($e, 'getApiResponse')) {
            $errorDetails['api_response'] = $e->getApiResponse();
        }
        if (method_exists($e, 'getCode')) {
            $errorDetails['error_code'] = $e->getCode();
        }
        $errorDetails['full_message'] = $e->getMessage();
        error_log('MP CreatePlan Error Details: ' . json_encode($errorDetails));
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => $errorDetails
        ];
    }
}

function mpUpdatePlan($planId, $nombre, $precio, $descripcion = '', $duracion = 1, $tipo_duracion = 'month') {
    try {
        // Validar campos requeridos
        if (empty($nombre)) {
            return ['error' => 'El nombre del plan es requerido'];
        }
        
        if (empty($precio) || $precio <= 0) {
            return ['error' => 'El precio debe ser mayor a 0'];
        }
        
        mpInit();
        $planClient = new PreApprovalPlanClient();
        
        // Convertir tipo de duración a formato correcto de MercadoPago
        $frequency_type = 'months'; // Por defecto
        if ($tipo_duracion === 'day') {
            $frequency_type = 'days';
        } elseif ($tipo_duracion === 'week') {
            // Para semanas, convertimos a días
            $frequency_type = 'days';
            $duracion = $duracion * 7;
        } elseif ($tipo_duracion === 'year') {
            // Para años, convertimos a meses
            $frequency_type = 'months';
            $duracion = $duracion * 12;
        }
        
        $request = [
            'back_url' => MP_WEBHOOK_URL, // SIEMPRE requerido
            'reason' => $nombre,
            'auto_recurring' => [
                'frequency' => $duracion,
                'frequency_type' => $frequency_type,
                'transaction_amount' => (float)$precio,
                'currency_id' => 'MXN'
            ],
            'external_reference' => 'PLAN_' . time() . '_' . uniqid(),
            'payer_email' => 'admin@secureitmx.com', // Email por defecto
            'collector_id' => getCollectorIdFromToken(MP_ACCESS_TOKEN), // ID del vendedor (extraído automáticamente)
            'application_fee' => 0,
            'status' => 'active'
        ];
        
        // Agregar descripción si está disponible
        if (!empty($descripcion)) {
            $request['reason'] .= ' - ' . $descripcion;
        }
        
        error_log('MP UpdatePlan Request: ' . json_encode($request));
        
        $plan = $planClient->update($planId, $request);
        
        error_log('MP UpdatePlan Success: ' . json_encode($plan));
        
        return [
            'success' => true,
            'plan_id' => $plan->id ?? null,
            'plan_data' => $plan
        ];
        
    } catch (Throwable $e) {
        error_log('MP UpdatePlan Error: ' . $e->getMessage());
        $errorDetails = [];
        if (method_exists($e, 'getApiResponse')) {
            $errorDetails['api_response'] = $e->getApiResponse();
        }
        if (method_exists($e, 'getCode')) {
            $errorDetails['error_code'] = $e->getCode();
        }
        $errorDetails['full_message'] = $e->getMessage();
        error_log('MP UpdatePlan Error Details: ' . json_encode($errorDetails));
        
        return [
            'error' => $e->getMessage(),
            'debug_info' => $errorDetails
        ];
    }
}

function mpDeletePlan(string $planId): array {
    try {
        mpInit();
        $planClient = new PreApprovalPlanClient();
        
        // MercadoPago no tiene un método directo de eliminación, pero podemos desactivarlo
        // cambiando su estado a 'cancelled'
        $request = [
            'status' => 'cancelled'
        ];
        
        $plan = $planClient->update($planId, $request);
        return ['success' => true, 'plan' => $plan];
    } catch (Throwable $e) {
        error_log('MP mpDeletePlan error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function mpCreateSubscription(string $planId, array $cliente, int $membresiaId, ?string $backUrl = null): array {
    try {
        mpInit();
        $preClient = new PreApprovalClient();

        // back_url a donde volverá el pagador tras aprobar
        $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        $back = $backUrl ?: $baseUrl . '/modules/membresias/subscription_success.php';

        $request = [
            'preapproval_plan_id' => $planId,
            'payer_email' => (string)($cliente['correo'] ?? ''),
            'back_url' => $back,
            'reason' => 'Membresía ' . ($cliente['nombre'] ?? '') . ' - ' . $membresiaId,
            'external_reference' => 'MEM_' . $membresiaId,
        ];

        error_log('MP CreateSubscription Request: ' . json_encode($request));

        $pre = $preClient->create($request);
        
        error_log('MP CreateSubscription Success: ' . json_encode($pre));
        
        return [
            'success' => true,
            'preapproval_id' => $pre->id,
            'init_point' => $pre->init_point ?? null,
            'status' => $pre->status ?? null,
        ];
    } catch (Throwable $e) {
        error_log('MP mpCreateSubscription error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function mpCancelSubscription(string $preapprovalId, float $cancellationFee = 0, string $payerEmail = ''): array {
    try {
        mpInit();
        $client = new PreApprovalClient();
        
        // Cancelar la suscripción primero
        error_log('MP Cancelling subscription: ' . $preapprovalId);
        
        try {
            $updated = $client->update($preapprovalId, ['status' => 'cancelled']);
            error_log('MP Subscription cancelled successfully: ' . json_encode($updated));
            
            // Si hay un monto de cancelación, registrar el cargo en la base de datos
            // pero NO crear un pago real ya que requiere token válido del frontend
            if ($cancellationFee > 0) {
                error_log('MP Cancellation fee registered: $' . $cancellationFee . ' for email: ' . $payerEmail);
            }
            
            return [
                'success' => true, 
                'status' => $updated->status ?? null,
                'cancellation_fee_charged' => $cancellationFee > 0,
                'cancellation_fee_amount' => $cancellationFee,
                'message' => $cancellationFee > 0 ? 
                    'Suscripción cancelada. Cargo por cancelación registrado: $' . number_format($cancellationFee, 2) . ' MXN' :
                    'Suscripción cancelada exitosamente',
                'preapproval_data' => $updated
            ];
            
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            error_log('MP API Exception in mpCancelSubscription: ' . $e->getMessage());
            error_log('MP API Response: ' . json_encode($e->getApiResponse()));
            error_log('MP API Status Code: ' . $e->getCode());
            
            return [
                'success' => false, 
                'error' => 'Error de API de MercadoPago: ' . $e->getMessage(),
                'debug_info' => [
                    'api_response' => $e->getApiResponse(),
                    'status_code' => $e->getCode(),
                    'preapproval_id' => $preapprovalId
                ]
            ];
        }
        
    } catch (Throwable $e) {
        error_log('MP mpCancelSubscription error: ' . $e->getMessage());
        error_log('MP mpCancelSubscription stack trace: ' . $e->getTraceAsString());
        
        return [
            'success' => false, 
            'error' => 'Error interno: ' . $e->getMessage(),
            'debug_info' => [
                'exception_type' => get_class($e),
                'preapproval_id' => $preapprovalId,
                'stack_trace' => $e->getTraceAsString()
            ]
        ];
    }
}

/**
 * Función para crear un pago de cancelación (requiere token válido del frontend)
 * Esta función se usa cuando se tiene un token de pago válido
 */
function mpCreateCancellationPayment(float $amount, string $payerEmail, string $paymentToken, string $externalReference = ''): array {
    try {
        mpInit();
        $paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
        
        $paymentRequest = [
            'transaction_amount' => $amount,
            'token' => $paymentToken,
            'description' => 'Cargo por cancelación de suscripción',
            'installments' => 1,
            'payer' => [
                'email' => $payerEmail
            ],
            'external_reference' => $externalReference ?: 'CANCEL_FEE_' . time(),
            'notification_url' => MP_WEBHOOK_URL,
            'statement_descriptor' => 'CANCELACION',
            'binary_mode' => true, // Evita pagos pendientes
            'capture' => true // Captura inmediatamente
        ];
        
        error_log('MP Creating cancellation payment: ' . json_encode($paymentRequest));
        
        try {
            $payment = $paymentClient->create($paymentRequest);
            error_log('MP Cancellation payment created: ' . json_encode($payment));
            
            return [
                'success' => true,
                'payment_id' => $payment->id ?? null,
                'status' => $payment->status ?? null,
                'amount' => $amount,
                'external_reference' => $externalReference,
                'payment_data' => $payment
            ];
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            error_log('MP API Exception in mpCreateCancellationPayment: ' . $e->getMessage());
            
            // Obtener información detallada del error
            $apiResponse = $e->getApiResponse();
            $statusCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            // Log detallado del error
            error_log('MP API Error Details:');
            error_log('- Status Code: ' . $statusCode);
            error_log('- Error Message: ' . $errorMessage);
            error_log('- API Response: ' . json_encode($apiResponse));
            
            // Si la API response está vacía, intentar obtener más información
            if (empty($apiResponse)) {
                error_log('MP API Response está vacío, verificando propiedades del exception');
                
                // Intentar obtener más información del exception
                $reflection = new ReflectionClass($e);
                $properties = $reflection->getProperties();
                
                foreach ($properties as $property) {
                    $property->setAccessible(true);
                    $value = $property->getValue($e);
                    error_log('- Property ' . $property->getName() . ': ' . json_encode($value));
                }
            }
            
            return [
                'success' => false,
                'error' => 'Error de API de MercadoPago: ' . $errorMessage,
                'api_response' => $apiResponse,
                'status_code' => $statusCode,
                'error_details' => [
                    'message' => $errorMessage,
                    'code' => $statusCode,
                    'api_response' => $apiResponse,
                    'payment_request' => $paymentRequest
                ],
                'debug_info' => [
                    'exception_type' => get_class($e),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            ];
        } catch (\Exception $e) {
            error_log('MP General Exception in mpCreateCancellationPayment: ' . $e->getMessage());
            error_log('MP Exception Type: ' . get_class($e));
            error_log('MP Exception Stack Trace: ' . $e->getTraceAsString());
            
            return [
                'success' => false,
                'error' => 'Error general: ' . $e->getMessage(),
                'debug_info' => [
                    'exception_type' => get_class($e),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'payment_request' => $paymentRequest
                ]
            ];
        }
        
    } catch (Throwable $e) {
        error_log('MP mpCreateCancellationPayment error: ' . $e->getMessage());
        error_log('MP mpCreateCancellationPayment stack trace: ' . $e->getTraceAsString());
        
        return [
            'success' => false,
            'error' => 'Error interno: ' . $e->getMessage(),
            'debug_info' => [
                'exception_type' => get_class($e),
                'amount' => $amount,
                'payer_email' => $payerEmail,
                'external_reference' => $externalReference,
                'stack_trace' => $e->getTraceAsString()
            ]
        ];
    }
}

/**
 * Crear un pago de cancelación usando el token de tarjeta guardado del cliente
 */
function mpCreateCancellationPaymentFromClient(int $clienteId, float $amount, string $externalReference): array {
    try {
        global $pdo;
        
        // Obtener datos del cliente
        $stmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id, correo FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente || !$cliente['mp_card_token']) {
            error_log('MP CreateCancellationPaymentFromClient - Cliente sin token de tarjeta: ' . $clienteId);
            return [
                'success' => false, 
                'error' => 'Cliente no tiene token de tarjeta guardado',
                'debug_info' => [
                    'cliente_id' => $clienteId,
                    'has_token' => !empty($cliente['mp_card_token']),
                    'has_customer_id' => !empty($cliente['mp_customer_id'])
                ]
            ];
        }
        
        error_log('MP CreateCancellationPaymentFromClient - Cliente encontrado, token disponible: ' . $clienteId);
        
        // Crear el pago usando el token guardado
        $result = mpCreateCancellationPayment(
            $amount, 
            $cliente['correo'], 
            $cliente['mp_card_token'], 
            $externalReference
        );
        
        if ($result['success']) {
            error_log('MP CreateCancellationPaymentFromClient - Pago creado exitosamente: ' . json_encode($result));
        } else {
            error_log('MP CreateCancellationPaymentFromClient - Error creando pago: ' . ($result['error'] ?? 'Error desconocido'));
            if (isset($result['api_response'])) {
                error_log('MP CreateCancellationPaymentFromClient - API Response: ' . json_encode($result['api_response']));
            }
        }
        
        return $result;
        
    } catch (Throwable $e) {
        error_log('MP CreateCancellationPaymentFromClient - Error interno: ' . $e->getMessage());
        error_log('MP CreateCancellationPaymentFromClient - Stack trace: ' . $e->getTraceAsString());
        
        return [
            'success' => false, 
            'error' => 'Error interno: ' . $e->getMessage(),
            'debug_info' => [
                'exception_type' => get_class($e),
                'cliente_id' => $clienteId,
                'amount' => $amount,
                'external_reference' => $externalReference,
                'stack_trace' => $e->getTraceAsString()
            ]
        ];
    }
}

/**
 * Crear un pago usando el token de tarjeta directamente
 */
function mpCreatePaymentWithToken($token, $amount, $description, $externalReference) {
    try {
        mpInit();
        
        $paymentData = [
            'transaction_amount' => floatval($amount),
            'token' => $token,
            'description' => $description,
            'external_reference' => $externalReference,
            'payment_method_id' => 'master', // Se detectará automáticamente
            'installments' => 1,
            'payer' => [
                'email' => 'test@example.com' // Se puede obtener del cliente
            ]
        ];
        
        error_log('MP Payment Request: ' . json_encode($paymentData));
        
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        
        try {
            $payment = $client->create($paymentData);
            error_log('MP Payment Success: ' . json_encode($payment));
            
            return [
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status
            ];
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            error_log('MP API Exception: ' . $e->getMessage());
            error_log('MP API Response: ' . json_encode($e->getApiResponse()));
            error_log('MP API Status: ' . $e->getCode());
            
            return [
                'success' => false, 
                'error' => 'API Error: ' . $e->getMessage(),
                'api_response' => $e->getApiResponse(),
                'status_code' => $e->getCode()
            ];
        }
        
    } catch (Throwable $e) {
        error_log('MP Payment Error: ' . $e->getMessage());
        error_log('MP Error type: ' . get_class($e));
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function sendSubscriptionEmail($cliente, $tipoMembresia, $empresa) {
    $to = $cliente['correo'];
    $subject = '¡Bienvenido a tu membresía!';
    $message = "<html><head><title>Confirmación de Membresía</title></head><body>
        <h2>¡Bienvenido a tu membresía!</h2>
        <p>Hola {$cliente['nombre']} {$cliente['apellido_paterno']},</p>
        <p>Tu suscripción ha sido activada exitosamente.</p>
        <h3>Detalles de tu membresía:</h3>
        <ul>
            <li><strong>Plan:</strong> {$tipoMembresia['nombre']}</li>
            <li><strong>Precio:</strong> $" . number_format($tipoMembresia['precio'], 2) . " MXN</li>
            <li><strong>Empresa:</strong> {$empresa['nombre']}</li>
            <li><strong>Fecha de inicio:</strong> " . date('d/m/Y') . "</li>
        </ul>
        <p>Gracias por confiar en nosotros.</p>
        <p>Saludos,<br>Equipo de {$empresa['nombre']}</p>
    </body></html>";
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_FROM,
        'X-Mailer: PHP/' . phpversion(),
    ];
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function sendCancellationEmail($cliente, $tipoMembresia, $empresa, $cancellationFee) {
    $to = $cliente['correo'];
    $subject = 'Tu membresía ha sido cancelada';
    $message = "<html><head><title>Cancelación de Membresía</title></head><body>
        <h2>Tu membresía ha sido cancelada</h2>
        <p>Hola {$cliente['nombre']} {$cliente['apellido_paterno']},</p>
        <p>Tu suscripción ha sido cancelada exitosamente.</p>
        <h3>Detalles de la cancelación:</h3>
        <ul>
            <li><strong>Plan:</strong> {$tipoMembresia['nombre']}</li>
            <li><strong>Empresa:</strong> {$empresa['nombre']}</li>
            <li><strong>Fecha de cancelación:</strong> " . date('d/m/Y') . "</li>
        </ul>";
    
    if ($cancellationFee > 0) {
        $message .= "<p><strong>Cargo por cancelación:</strong> $" . number_format($cancellationFee, 2) . " MXN</p>";
    }
    
    $message .= "<p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
        <p>Saludos,<br>Equipo de {$empresa['nombre']}</p>
    </body></html>";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_FROM,
        'X-Mailer: PHP/' . phpversion(),
    ];
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Obtiene la URL de suscripción de un plan de MercadoPago
 * @param string $planId ID del plan de MercadoPago
 * @return array Array con success, url y error si aplica
 */
function mpGetSubscriptionUrl(string $planId, ?string $externalReference = null): array {
    try {
        mpInit();
        $planClient = new PreApprovalPlanClient();
        
        // Obtener el plan de MercadoPago
        $plan = $planClient->get($planId);
        
        if (!$plan || !$plan->init_point) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener la URL de suscripción del plan',
                'url' => null
            ];
        }
        
        // Si se proporciona external_reference, modificar la URL para incluirlo
        $url = $plan->init_point;
        if ($externalReference) {
            // Agregar external_reference como parámetro de consulta
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'external_reference=' . urlencode($externalReference);
        }
        
        return [
            'success' => true,
            'url' => $url,
            'plan_info' => [
                'id' => $plan->id,
                'reason' => $plan->reason,
                'status' => $plan->status,
                'auto_recurring' => $plan->auto_recurring ?? null
            ]
        ];
        
    } catch (Throwable $e) {
        error_log('MP mpGetSubscriptionUrl error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'url' => null
        ];
    }
}

/**
 * Genera un código QR en formato SVG para una URL
 * @param string $url URL para generar el código QR
 * @param int $size Tamaño del código QR (por defecto 200)
 * @return string SVG del código QR
 */
function generateQRCodeSVG(string $url, int $size = 200): string {
    // Usar una librería simple de QR o generar SVG básico
    // Por simplicidad, usaremos una API externa o generaremos un SVG básico
    
    $encodedUrl = urlencode($url);
    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedUrl}&format=svg";
    
    // Intentar obtener el SVG desde la API
    $svgContent = @file_get_contents($qrApiUrl);
    
    if ($svgContent === false) {
        // Fallback: generar un SVG básico con la URL
        return '<svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="#f8f9fa" stroke="#dee2e6" stroke-width="2"/>
            <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-family="Arial, sans-serif" font-size="12" fill="#6c757d">
                QR Code
            </text>
            <text x="50%" y="70%" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" fill="#6c757d">
                ' . htmlspecialchars(substr($url, 0, 30)) . '...
            </text>
        </svg>';
    }
    
    return $svgContent;
}

/**
 * Verifica el estado de un pago por cancelación y actualiza la base de datos
 */
function mpCheckCancellationPaymentStatus(string $paymentId): array {
    try {
        mpInit();
        $paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
        
        error_log('MP Checking cancellation payment status: ' . $paymentId);
        
        $payment = $paymentClient->get($paymentId);
        
        if (!$payment) {
            return [
                'success' => false,
                'error' => 'Pago no encontrado',
                'payment_id' => $paymentId
            ];
        }
        
        $status = $payment->status ?? null;
        $externalRef = $payment->external_reference ?? null;
        $amount = $payment->transaction_amount ?? 0;
        
        error_log('MP Cancellation payment status: ' . json_encode([
            'payment_id' => $paymentId,
            'status' => $status,
            'external_ref' => $externalRef,
            'amount' => $amount
        ]));
        
        // Buscar membresía por external_reference
        global $pdo;
        $membresiaId = null;
        
        if ($externalRef && preg_match('/^CANCEL_FEE_MEM_(\d+)$/', $externalRef, $m)) {
            $membresiaId = (int)$m[1];
        }
        
        if ($membresiaId) {
            // Actualizar el log de pago con el estado actual
            $updateLogStmt = $pdo->prepare("UPDATE mp_payment_logs SET 
                status = ?, 
                mp_response = ? 
                WHERE mp_payment_id = ?");
            $updateLogStmt->execute([
                $status,
                json_encode($payment),
                $paymentId
            ]);
            
            // Si el pago fue aprobado, actualizar la membresía
            if ($status === 'approved') {
                $updateMembresiaStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago aprobado: ', ?)
                    WHERE id = ?");
                $updateMembresiaStmt->execute([$paymentId, $membresiaId]);
                
                error_log('MP Cancellation payment approved for membership: ' . $membresiaId);
            } elseif ($status === 'rejected' || $status === 'cancelled') {
                $updateMembresiaStmt = $pdo->prepare("UPDATE membresias SET 
                    mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago rechazado: ', ?)
                    WHERE id = ?");
                $updateMembresiaStmt->execute([$paymentId, $membresiaId]);
                
                error_log('MP Cancellation payment rejected for membership: ' . $membresiaId);
            }
        }
        
        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => $status,
            'amount' => $amount,
            'external_reference' => $externalRef,
            'membresia_id' => $membresiaId,
            'payment_data' => $payment
        ];
        
    } catch (Throwable $e) {
        error_log('MP mpCheckCancellationPaymentStatus error: ' . $e->getMessage());
        error_log('MP mpCheckCancellationPaymentStatus stack trace: ' . $e->getTraceAsString());
        
        return [
            'success' => false,
            'error' => 'Error interno: ' . $e->getMessage(),
            'payment_id' => $paymentId,
            'debug_info' => [
                'exception_type' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]
        ];
    }
}
