<?php
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cost_id = $_POST['cost_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $amount = $_POST['amount'] ?? null;
    
    if (empty($cost_id) || empty($description) || empty($amount)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos requeridos'
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
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        // Preparar la consulta base para actualizar descripción y monto
        $update_fields = ['description = ?', 'amount = ?'];
        $params = [$description, $amount];
        $types = 'sd';
        
        // Agregar el ID al final
        $params[] = $cost_id;
        $types .= 'i';
        
        // Construir y ejecutar la consulta de actualización
        $query = "UPDATE ticket_costs SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el costo: ' . $stmt->error);
        }
        $stmt->close();
        
        // Manejar múltiples archivos nuevos
        if (isset($_FILES['cost_files']) && !empty($_FILES['cost_files']['name'][0])) {
            // Verificar si la tabla ticket_cost_attachments existe, si no, crearla
            $table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");
            if ($table_check->num_rows == 0) {
                // Crear la tabla si no existe
                $create_table = "CREATE TABLE `ticket_cost_attachments` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `cost_id` bigint(20) UNSIGNED NOT NULL,
                  `file_name` varchar(255) NOT NULL,
                  `file_content` longblob NOT NULL,
                  `file_type` varchar(100) DEFAULT NULL,
                  `file_size` int(11) DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `cost_id` (`cost_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                if (!$conn->query($create_table)) {
                    throw new Exception('Error al crear tabla de adjuntos: ' . $conn->error);
                }
            }
            
            // Insertar cada archivo nuevo
            $file_count = count($_FILES['cost_files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['cost_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['cost_files']['name'][$i];
                    $file_type = $_FILES['cost_files']['type'][$i];
                    $file_size = $_FILES['cost_files']['size'][$i];
                    $file_content = file_get_contents($_FILES['cost_files']['tmp_name'][$i]);
                    
                    $stmt = $conn->prepare("INSERT INTO ticket_cost_attachments (cost_id, file_name, file_content, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $cost_id, $file_name, $file_content, $file_type, $file_size);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Error al guardar archivo: ' . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Costo actualizado correctamente'
        ]);
        
        
    } catch (Exception $e) {
        $conn->rollback();
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

