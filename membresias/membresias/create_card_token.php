<?php
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

header('Content-Type: application/json');

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de tarjeta no proporcionados');
    }
    
    // Validar datos requeridos
    $required = ['card_number', 'expiration_month', 'expiration_year', 'security_code', 'cardholder'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    if (empty($input['cardholder']['name'])) {
        throw new Exception('Nombre del titular es requerido');
    }
    
    // Crear token usando la API de Mercado Pago directamente
    $tokenData = [
        'card_number' => $input['card_number'],
        'expiration_month' => intval($input['expiration_month']),
        'expiration_year' => intval($input['expiration_year']),
        'security_code' => $input['security_code'],
        'cardholder' => [
            'name' => $input['cardholder']['name']
        ]
    ];
    
    // Agregar identificación solo si se proporciona
    if (!empty($input['cardholder']['identification']['type']) && !empty($input['cardholder']['identification']['number'])) {
        $tokenData['cardholder']['identification'] = [
            'type' => $input['cardholder']['identification']['type'],
            'number' => $input['cardholder']['identification']['number']
        ];
    }
    
    // Log de los datos que se envían (sin el número de tarjeta completo por seguridad)
    $logData = $tokenData;
    $logData['card_number'] = substr($input['card_number'], 0, 4) . '****' . substr($input['card_number'], -4);
    error_log('MP Token Request Data: ' . json_encode($logData));
    
    // Usar cURL con mejor manejo de errores
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/card_tokens',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($tokenData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Error de conexión: ' . $curlError);
    }
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = 'Error HTTP ' . $httpCode;
        
        // Log detallado del error
        error_log('MP API Error Response: ' . $response);
        error_log('MP API Error Data: ' . print_r($errorData, true));
        
        if ($errorData && isset($errorData['message'])) {
            $errorMessage .= ': ' . $errorData['message'];
        }
        if ($errorData && isset($errorData['error'])) {
            $errorMessage .= ' - ' . $errorData['error'];
        }
        if ($errorData && isset($errorData['causes']) && is_array($errorData['causes'])) {
            foreach ($errorData['causes'] as $cause) {
                if (isset($cause['description'])) {
                    $errorMessage .= ' | ' . $cause['description'];
                }
            }
        }
        throw new Exception($errorMessage);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['id'])) {
        throw new Exception('Respuesta inválida de Mercado Pago');
    }
    
    echo json_encode([
        'success' => true,
        'token_id' => $result['id'],
        'last_four_digits' => $result['last_four_digits'] ?? ''
    ]);
    
} catch (Throwable $e) {
    error_log('create_card_token error: ' . $e->getMessage());
    error_log('create_card_token trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
