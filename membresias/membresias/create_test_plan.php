<?php
// Archivo para crear un plan de prueba si no existe ninguno
include '../includes/db.php';
include '../includes/config.php';

try {
    // Verificar si ya existen planes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tipos_membresia");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] == 0) {
        // No hay planes, crear uno de prueba
        $stmt = $pdo->prepare("
            INSERT INTO tipos_membresia 
            (nombre, descripcion, precio, duracion_dias, interval, interval_count, monto_cancelacion, is_active, created_at) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'Plan Premium de Prueba',
            'Plan de prueba para testing del sistema de QR',
            299.00,
            30,
            'month',
            1,
            0.00,
            1
        ]);
        
        $plan_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Plan de prueba creado exitosamente',
            'plan_id' => $plan_id,
            'plan_nombre' => 'Plan Premium de Prueba',
            'precio' => 299.00
        ]);
    } else {
        // Ya existen planes, mostrar información
        $stmt = $pdo->prepare("SELECT id, nombre, precio, is_active FROM tipos_membresia ORDER BY id");
        $stmt->execute();
        $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Ya existen planes en la base de datos',
            'planes' => $planes
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
