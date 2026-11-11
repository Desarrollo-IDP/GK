<?php
// Limpiar cualquier salida previa
ob_start();

include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

// Limpiar el buffer de salida antes de enviar JSON
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = $_POST['ticket_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $amount = $_POST['amount'] ?? null;
    
    if (empty($ticket_id) || empty($description) || empty($amount)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos requeridos'
        ]);
        exit;
    }
    
    try {
        // Verificar que el ticket pertenece a la empresa del usuario
        $query = "SELECT id FROM tickets WHERE id = ?";
        $params = [$ticket_id];
        $types = 'i';
        $query = addEmpresaFilter($query, 'id_empresa', $params, $types);
        $check_stmt = $conn->prepare($query);
        $check_stmt->bind_param($types, ...$params);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes acceso a este ticket'
            ]);
            exit;
        }
        $check_stmt->close();
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        // Verificar/crear la tabla ticket_cost_attachments si no existe
        $table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");
        if ($table_check->num_rows === 0) {
            $create_table = "CREATE TABLE `ticket_cost_attachments` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `cost_id` bigint(20) UNSIGNED NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `file_content` longblob NOT NULL,
                `file_type` varchar(100) DEFAULT NULL,
                `file_size` int(11) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `cost_id` (`cost_id`),
                CONSTRAINT `fk_cost_attachments` FOREIGN KEY (`cost_id`) REFERENCES `ticket_costs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            if (!$conn->query($create_table)) {
                throw new Exception('Error al crear la tabla ticket_cost_attachments: ' . $conn->error);
            }
        }
        
        // Insertar el costo sin archivos primero
        $query = "INSERT INTO ticket_costs (ticket_id, description, amount) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isd", $ticket_id, $description, $amount);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al crear el costo: ' . $stmt->error);
        }
        
        $cost_id = $conn->insert_id;
        $stmt->close();
        
        // Procesar múltiples archivos si existen
        $files_uploaded = 0;
        if (isset($_FILES['cost_files']) && is_array($_FILES['cost_files']['name'])) {
            $file_count = count($_FILES['cost_files']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                // Verificar que el archivo se subió correctamente
                if ($_FILES['cost_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['cost_files']['name'][$i];
                    $file_type = $_FILES['cost_files']['type'][$i];
                    $file_size = $_FILES['cost_files']['size'][$i];
                    $file_tmp = $_FILES['cost_files']['tmp_name'][$i];
                    $file_content = file_get_contents($file_tmp);
                    
                    // Insertar archivo en ticket_cost_attachments
                    $file_stmt = $conn->prepare("INSERT INTO ticket_cost_attachments (cost_id, file_name, file_content, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                    $file_stmt->bind_param("isssi", $cost_id, $file_name, $file_content, $file_type, $file_size);
                    
                    if ($file_stmt->execute()) {
                        $files_uploaded++;
                    } else {
                        error_log("Error al guardar archivo '$file_name': " . $file_stmt->error);
                    }
                    
                    $file_stmt->close();
                }
            }
        }
        
        $conn->commit();
        
        $message = 'Costo agregado correctamente';
        if ($files_uploaded > 0) {
            $message .= " con $files_uploaded archivo(s) adjunto(s)";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'cost_id' => $cost_id,
            'files_uploaded' => $files_uploaded
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
exit; 