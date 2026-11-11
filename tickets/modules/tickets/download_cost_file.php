<?php
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

if (isset($_GET['id'])) {
    $cost_id = $_GET['id'];
    $file_id = $_GET['file_id'] ?? null;
    
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
        header('HTTP/1.1 403 Forbidden');
        die('No tienes acceso a este archivo');
    }
    $check_stmt->close();
    
    $file_found = false;
    
    // Si file_id está especificado, buscar el archivo específico
    if ($file_id) {
        if (strpos($file_id, 'main_') === 0) {
            // Es el archivo principal del costo
            $actual_cost_id = str_replace('main_', '', $file_id);
            $stmt = $conn->prepare("SELECT file_name, file_content, file_type FROM ticket_costs WHERE id = ?");
            $stmt->bind_param("i", $actual_cost_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                header('Content-Type: ' . ($row['file_type'] ?? 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
                echo $row['file_content'];
                $file_found = true;
            }
            $stmt->close();
            
        } else if (strpos($file_id, 'attach_') === 0) {
            // Es un archivo adjunto adicional
            $attachment_id = str_replace('attach_', '', $file_id);
            
            // Verificar que la tabla exista
            $table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");
            if ($table_check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT file_name, file_content, file_type FROM ticket_cost_attachments WHERE id = ?");
                $stmt->bind_param("i", $attachment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    header('Content-Type: ' . ($row['file_type'] ?? 'application/octet-stream'));
                    header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
                    echo $row['file_content'];
                    $file_found = true;
                }
                $stmt->close();
            }
        }
    } else {
        // Si no se especifica file_id, descargar el archivo principal por compatibilidad
        $stmt = $conn->prepare("SELECT file_name, file_content, file_type FROM ticket_costs WHERE id = ?");
        $stmt->bind_param("i", $cost_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            header('Content-Type: ' . ($row['file_type'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
            echo $row['file_content'];
            $file_found = true;
        }
        $stmt->close();
    }
    
    if (!$file_found) {
        header("HTTP/1.0 404 Not Found");
        echo "Archivo no encontrado";
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    echo "ID de archivo no proporcionado";
}
exit; 