<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['attachment_id'])) {
        $attachment_id = intval($data['attachment_id']);
        
        // Verificar que el archivo existe y pertenece a un ticket de la empresa del usuario
        $query = "SELECT ta.id, ta.filename, ta.file_path 
                  FROM ticket_attachments ta 
                  INNER JOIN tickets t ON ta.ticket_id = t.id 
                  WHERE ta.id = ?";
        $params = [$attachment_id];
        $types = 'i';
        $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("ERROR: No se pudo preparar la consulta de verificación: " . $conn->error);
            $response['message'] = 'Error al preparar la consulta';
            echo json_encode($response);
            exit;
        }
        
        // Usar los parámetros modificados por addEmpresaFilter
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("ERROR: No se pudo ejecutar la consulta de verificación: " . $stmt->error);
            $response['message'] = 'Error al ejecutar la consulta';
            echo json_encode($response);
            exit;
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            error_log("ERROR: Archivo no encontrado con ID: $attachment_id");
            $response['message'] = 'Archivo no encontrado';
            echo json_encode($response);
            exit;
        }
        
        $attachment = $result->fetch_assoc();
        $stmt->close();
        
        // Construir la ruta completa del archivo físico
        // file_path en BD puede ser: "uploads/attachments/..." o "uploads/tickets/..."
        $db_path = ltrim($attachment['file_path'], "/\\");
        
        // Directorio base del módulo de tickets ( .../tickets )
        $tickets_base_dir = dirname(__DIR__, 2);
        
        // Ruta absoluta esperada del archivo
        $absolute_path = $tickets_base_dir . DIRECTORY_SEPARATOR . $db_path;
        $absolute_path = str_replace(['\\', '//'], '/', $absolute_path);
        
        error_log("DEBUG: Intentando eliminar archivo en: " . $absolute_path);
        
        // Eliminar el archivo físico si existe
        if (file_exists($absolute_path)) {
            if (!unlink($absolute_path)) {
                error_log("ERROR: No se pudo eliminar el archivo físico: $absolute_path");
                $response['message'] = 'Error al eliminar el archivo físico';
                echo json_encode($response);
                exit;
            }
            error_log("Archivo físico eliminado: $absolute_path");
        } else {
            error_log("ADVERTENCIA: Archivo físico no encontrado: $absolute_path (continuando con eliminación en BD)");
            // Continuar con la eliminación en BD aunque el archivo físico no exista
        }
        
        // Eliminar el archivo de la base de datos
        $delete_stmt = $conn->prepare("DELETE FROM ticket_attachments WHERE id = ?");
        if ($delete_stmt === false) {
            error_log("ERROR: No se pudo preparar la consulta de eliminación: " . $conn->error);
            $response['message'] = 'Error al preparar la consulta de eliminación';
            echo json_encode($response);
            exit;
        }
        
        $delete_stmt->bind_param("i", $attachment_id);
        if (!$delete_stmt->execute()) {
            error_log("ERROR: No se pudo eliminar el archivo de la base de datos: " . $delete_stmt->error);
            $response['message'] = 'Error al eliminar el archivo de la base de datos';
            echo json_encode($response);
            exit;
        }
        
        error_log("Archivo eliminado exitosamente: " . $attachment['filename'] . " (ID: " . $attachment_id . ")");
        $response['success'] = true;
        $response['message'] = 'Archivo eliminado correctamente';
        $delete_stmt->close();
    } else {
        error_log("ERROR: ID de archivo no proporcionado");
        $response['message'] = 'ID de archivo no proporcionado';
    }
} else {
    error_log("ERROR: Método de solicitud no válido");
    $response['message'] = 'Método de solicitud no válido';
}

echo json_encode($response);