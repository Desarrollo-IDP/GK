<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$reason_id = isset($data['reason_id']) ? (int)$data['reason_id'] : 0;

if ($reason_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de motivo inválido'
    ]);
    exit;
}

try {
    // Verificar que el motivo pertenece a un ticket de la empresa del usuario
    $query = "SELECT tur.id FROM ticket_update_reasons tur 
              INNER JOIN tickets t ON tur.ticket_id = t.id 
              WHERE tur.id = ?";
    $params = [$reason_id];
    $types = 'i';
    $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    
    $check_stmt = $conn->prepare($query);
    $check_stmt->bind_param($types, ...$params);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes acceso a este motivo de actualización'
        ]);
        exit;
    }
    $check_stmt->close();
    
    // Eliminar el motivo
    $stmt = $conn->prepare("DELETE FROM ticket_update_reasons WHERE id = ?");
    $stmt->bind_param("i", $reason_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Motivo de actualización eliminado correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el motivo especificado'
            ]);
        }
    } else {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
