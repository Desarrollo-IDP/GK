<?php

function logActivity($accion, $tabla_afectada, $id_registro = null, $detalles = null, $id_empresa_override = null) {
    global $conn;

    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        error_log("User not authenticated for activity logging");
        return false;
    }
    
    $id_usuario = $_SESSION['user']['id'];
    $nombre_usuario = $_SESSION['user']['nombre'] ?? $_SESSION['user']['username'] ?? 'Unknown';
    
    // Obtener id_empresa del usuario si existe la función helper
    $id_empresa = null;
    if ($id_empresa_override !== null) {
        $id_empresa = $id_empresa_override;
    } elseif (function_exists('getCurrentUserEmpresaId')) {
        $id_empresa = getCurrentUserEmpresaId();
    } elseif (isset($_SESSION['user']['id_empresa'])) {
        $id_empresa = $_SESSION['user']['id_empresa'];
    }
    
    // Verificar si existe columna id_empresa en historial_actividades
    $check_column = $conn->query("SHOW COLUMNS FROM historial_actividades LIKE 'id_empresa'");
    $has_empresa_column = $check_column && $check_column->num_rows > 0;
    
    if ($has_empresa_column) {
        $query = "INSERT INTO historial_actividades 
                  (id_usuario, nombre_usuario, accion, tabla_afectada, id_registro, detalles, id_empresa) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparing activity log query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isssssi", $id_usuario, $nombre_usuario, $accion, $tabla_afectada, $id_registro, $detalles, $id_empresa);
    } else {
        $query = "INSERT INTO historial_actividades 
                  (id_usuario, nombre_usuario, accion, tabla_afectada, id_registro, detalles) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparing activity log query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isssss", $id_usuario, $nombre_usuario, $accion, $tabla_afectada, $id_registro, $detalles);
    }
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Error logging activity: " . $stmt->error);
        return false;
    }
    
    $stmt->close();
    return true;
}
?>