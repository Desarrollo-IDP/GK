<?php
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $file_id = $input['file_id'] ?? null;
    
    if (empty($file_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de archivo no proporcionado'
        ]);
        exit;
    }
    
    try {
        // Determinar si es el archivo principal o un adjunto adicional
        if (strpos($file_id, 'main_') === 0) {
            // Es el archivo principal del costo
            $cost_id = str_replace('main_', '', $file_id);
            
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
                    'message' => 'No tienes acceso a este archivo'
                ]);
                exit;
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare("UPDATE ticket_costs SET file_name = NULL, file_content = NULL, file_type = NULL, file_size = NULL WHERE id = ?");
            $stmt->bind_param("i", $cost_id);
            
        } else if (strpos($file_id, 'attach_') === 0) {
            // Es un archivo adjunto adicional
            $attachment_id = str_replace('attach_', '', $file_id);
            
            // Verificar que el attachment pertenece a un costo de un ticket de la empresa del usuario
            $query = "SELECT tca.id FROM ticket_cost_attachments tca 
                      INNER JOIN ticket_costs tc ON tca.cost_id = tc.id 
                      INNER JOIN tickets t ON tc.ticket_id = t.id 
                      WHERE tca.id = ?";
            $params = [$attachment_id];
            $types = 'i';
            $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
            $check_stmt = $conn->prepare($query);
            $check_stmt->bind_param($types, ...$params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes acceso a este archivo'
                ]);
                exit;
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM ticket_cost_attachments WHERE id = ?");
            $stmt->bind_param("i", $attachment_id);
        } else {
            throw new Exception('ID de archivo inválido');
        }
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Archivo eliminado correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró el archivo especificado'
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

