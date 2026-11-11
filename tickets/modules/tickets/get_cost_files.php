<?php
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

if (!isset($_GET['cost_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de costo no proporcionado'
    ]);
    exit;
}

$cost_id = $_GET['cost_id'];

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
    $files = [];
    
    // Obtener el archivo principal del costo (si existe)
    $stmt = $conn->prepare("SELECT id, file_name, file_size FROM ticket_costs WHERE id = ? AND file_name IS NOT NULL");
    $stmt->bind_param("i", $cost_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['file_name'])) {
            $files[] = [
                'id' => 'main_' . $row['id'],
                'file_name' => $row['file_name'],
                'file_size' => $row['file_size'] ?? 0,
                'is_main' => true
            ];
        }
    }
    $stmt->close();
    
    // Obtener archivos adicionales de la tabla ticket_cost_attachments (si existe)
    $table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, file_name, file_size FROM ticket_cost_attachments WHERE cost_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $cost_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $files[] = [
                'id' => 'attach_' . $row['id'],
                'file_name' => $row['file_name'],
                'file_size' => $row['file_size'] ?? 0,
                'is_main' => false
            ];
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'files' => $files
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

