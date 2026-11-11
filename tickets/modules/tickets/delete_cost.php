<?php
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer el cuerpo de la solicitud JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $cost_id = $input['cost_id'] ?? null;
    
    if (empty($cost_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de costo no proporcionado'
        ]);
        exit;
    }
    
    try {
        // Verificar que el costo pertenece a un ticket de la empresa del usuario
        $query = "SELECT tc.id FROM ticket_costs tc 
                  INNER JOIN tickets t ON tc.ticket_id = t.id 
                  WHERE tc.id = ?";
        $params = [$cost_id];
        $types = 'i';
        $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
        $check_stmt = $conn->prepare($query);
        $check_stmt->bind_param($types, ...$params);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes acceso a este costo'
            ]);
            exit;
        }
        $check_stmt->close();
        
        // Eliminar el costo
        $stmt = $conn->prepare("DELETE FROM ticket_costs WHERE id = ?");
        $stmt->bind_param("i", $cost_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Costo eliminado correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró el costo especificado'
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
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}

$conn->close();
?>

