<?php
// Verificar estado del método de pago del cliente
include '../includes/db.php';
include '../includes/config.php';

initConfig();

header('Content-Type: application/json');

// Verificar si el cliente está logueado
if (!isset($_SESSION['cliente'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$cliente_id = $_SESSION['cliente']['id'];

try {
    // Verificar si el cliente tiene token de tarjeta configurado
    $stmt = $pdo->prepare("SELECT mp_card_token FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $hasPaymentMethod = !empty($cliente['mp_card_token']);
    
    echo json_encode([
        'success' => true,
        'hasPaymentMethod' => $hasPaymentMethod,
        'message' => $hasPaymentMethod ? 'Método de pago configurado' : 'Sin método de pago'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error general: ' . $e->getMessage()
    ]);
}
?>
