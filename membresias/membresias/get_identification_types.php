<?php
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

header('Content-Type: application/json');

try {
    // Usar cURL para obtener tipos de identificación
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/identification_types',
        CURLOPT_HTTPHEADER => [
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
    
    if ($httpCode !== 200) {
        throw new Exception('Error HTTP ' . $httpCode . ': ' . $response);
    }
    
    $types = json_decode($response, true);
    
    if (!$types || !is_array($types)) {
        throw new Exception('Respuesta inválida de Mercado Pago');
    }
    
    echo json_encode([
        'success' => true,
        'types' => $types
    ]);
    
} catch (Throwable $e) {
    error_log('get_identification_types error: ' . $e->getMessage());
    
    // En caso de error, devolver tipos básicos
    $fallbackTypes = [
        ['id' => 'DNI', 'name' => 'DNI'],
        ['id' => 'CI', 'name' => 'CI'],
        ['id' => 'CPF', 'name' => 'CPF'],
        ['id' => 'CNPJ', 'name' => 'CNPJ']
    ];
    
    echo json_encode([
        'success' => true,
        'types' => $fallbackTypes,
        'fallback' => true
    ]);
}
?>
