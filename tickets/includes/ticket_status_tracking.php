<?php
/**
 * Funciones para el seguimiento de tiempo en status específicos
 */

/**
 * Inicia el tracking de un status específico para un ticket
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @param int $status_id ID del status
 * @return bool True si se registró correctamente, false en caso contrario
 */
function startStatusTracking($conn, $ticket_id, $status_id) {
    // Verificar conexión
    if (!$conn) {
        error_log("Error: No hay conexión a la base de datos en startStatusTracking");
        return false;
    }
    
    // Verificar si ya hay un tracking activo para este ticket
    $check_stmt = $conn->prepare("SELECT id FROM ticket_status_tracking WHERE ticket_id = ? AND is_active = 1");
    
    if (!$check_stmt) {
        error_log("Error preparando consulta de verificación en startStatusTracking: " . $conn->error);
        return false;
    }
    
    $check_stmt->bind_param("i", $ticket_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Si ya hay un tracking activo, terminarlo primero
        endStatusTracking($conn, $ticket_id);
    }
    $check_stmt->close();
    
    // Crear nuevo tracking
    $stmt = $conn->prepare("INSERT INTO ticket_status_tracking (ticket_id, status_id, started_at, is_active) VALUES (?, ?, NOW(), 1)");
    
    if (!$stmt) {
        error_log("Error preparando consulta de tracking: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ii", $ticket_id, $status_id);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error ejecutando consulta de tracking: " . $stmt->error);
    } else {
        error_log("Tracking iniciado para ticket $ticket_id con status $status_id");
    }
    
    $stmt->close();
    return $result;
}

/**
 * Termina el tracking activo de un ticket y calcula la duración
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function endStatusTracking($conn, $ticket_id) {
    // Verificar conexión
    if (!$conn) {
        error_log("Error: No hay conexión a la base de datos en endStatusTracking");
        return false;
    }
    
    // Buscar el tracking activo
    $stmt = $conn->prepare("SELECT id, started_at FROM ticket_status_tracking WHERE ticket_id = ? AND is_active = 1");
    
    if (!$stmt) {
        error_log("Error preparando consulta en endStatusTracking: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return true; // No hay tracking activo, no es un error
    }
    
    $tracking = $result->fetch_assoc();
    $stmt->close();
    
    // Actualizar el tracking usando CURRENT_TIMESTAMP de MySQL para evitar problemas de zona horaria
    $update_stmt = $conn->prepare("UPDATE ticket_status_tracking SET ended_at = CURRENT_TIMESTAMP, is_active = 0 WHERE id = ?");
    
    if (!$update_stmt) {
        error_log("Error preparando consulta de actualización en endStatusTracking: " . $conn->error);
        return false;
    }
    
    $update_stmt->bind_param("i", $tracking['id']);
    $result = $update_stmt->execute();
    
    if (!$result) {
        error_log("Error actualizando tracking: " . $update_stmt->error);
        $update_stmt->close();
        return false;
    }
    $update_stmt->close();
    
    // Ahora obtener el ended_at que MySQL guardó y calcular la duración
    $get_updated = $conn->prepare("SELECT started_at, ended_at FROM ticket_status_tracking WHERE id = ?");
    $get_updated->bind_param("i", $tracking['id']);
    $get_updated->execute();
    $updated_result = $get_updated->get_result();
    $updated_data = $updated_result->fetch_assoc();
    $get_updated->close();
    
    if (!$updated_data) {
        error_log("Error obteniendo datos actualizados del tracking");
        return false;
    }
    
    // Calcular la duración usando los timestamps de MySQL
    try {
        $started_timestamp = strtotime($updated_data['started_at']);
        $ended_timestamp = strtotime($updated_data['ended_at']);
        
        // Verificar que los timestamps sean válidos
        if ($started_timestamp === false || $ended_timestamp === false) {
            error_log("Error: Timestamps inválidos - started: " . $updated_data['started_at'] . ", ended: " . $updated_data['ended_at']);
            $duration_minutes = 0;
        } else {
            // Calcular la diferencia en minutos
            $duration_minutes = round(($ended_timestamp - $started_timestamp) / 60);
            
            // Si la diferencia es negativa (fecha futura), usar 0
            if ($duration_minutes < 0) {
                $duration_minutes = 0;
            }
        }
        
        // Log para depuración
        error_log("Tracking calculation - Started: " . $updated_data['started_at'] . " (timestamp: $started_timestamp), Ended: " . $updated_data['ended_at'] . " (timestamp: $ended_timestamp), Minutes: " . $duration_minutes);
        
    } catch (Exception $e) {
        error_log("Error calculating duration: " . $e->getMessage());
        $duration_minutes = 0;
    }
    
    // Actualizar solo la duración calculada
    $duration_stmt = $conn->prepare("UPDATE ticket_status_tracking SET duration_minutes = ? WHERE id = ?");
    $duration_stmt->bind_param("ii", $duration_minutes, $tracking['id']);
    $duration_result = $duration_stmt->execute();
    
    if (!$duration_result) {
        error_log("Error actualizando duración: " . $duration_stmt->error);
    }
    $duration_stmt->close();
    
    return $result;
}

/**
 * Obtiene el tiempo total que un ticket ha estado en un status específico
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @param int $status_id ID del status (opcional, si no se especifica suma todos)
 * @return int Tiempo total en minutos
 */
function getTotalTimeInStatus($conn, $ticket_id, $status_id = null) {
    $query = "SELECT SUM(duration_minutes) as total_minutes FROM ticket_status_tracking WHERE ticket_id = ?";
    $params = [$ticket_id];
    $types = "i";
    
    if ($status_id !== null) {
        $query .= " AND status_id = ?";
        $params[] = $status_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total_minutes'] ?? 0;
}

/**
 * Obtiene el tiempo actual que lleva un ticket en un status específico (si está activo)
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @param int $status_id ID del status
 * @return int Tiempo actual en minutos, 0 si no está activo
 */
function getCurrentTimeInStatus($conn, $ticket_id, $status_id) {
    $stmt = $conn->prepare("SELECT started_at FROM ticket_status_tracking WHERE ticket_id = ? AND status_id = ? AND is_active = 1");
    $stmt->bind_param("ii", $ticket_id, $status_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return 0;
    }
    
    $tracking = $result->fetch_assoc();
    $stmt->close();
    
    // Calcular tiempo transcurrido usando timestamps
    try {
        $started_timestamp = strtotime($tracking['started_at']);
        $now_timestamp = time();
        
        $minutes = round(($now_timestamp - $started_timestamp) / 60);
        
        // Si la diferencia es negativa, usar 0
        if ($minutes < 0) {
            return 0;
        }
        
        return $minutes;
        
    } catch (Exception $e) {
        error_log("Error calculating current time: " . $e->getMessage());
        return 0;
    }
}

/**
 * Formatea el tiempo en minutos a un formato legible
 * 
 * @param int $minutes Tiempo en minutos
 * @return string Tiempo formateado
 */
function formatDuration($minutes) {
    if ($minutes <= 0) {
        return "0 min";
    }
    
    if ($minutes < 60) {
        return $minutes . " min";
    } elseif ($minutes < 1440) { // Menos de 24 horas
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        if ($remaining_minutes > 0) {
            return $hours . "h " . $remaining_minutes . "min";
        } else {
            return $hours . "h";
        }
    } else {
        $days = floor($minutes / 1440);
        $remaining_hours = floor(($minutes % 1440) / 60);
        if ($remaining_hours > 0) {
            return $days . "d " . $remaining_hours . "h";
        } else {
            return $days . "d";
        }
    }
}

/**
 * Obtiene el historial completo de tracking de un ticket
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @return array Array con el historial de tracking
 */
function getTicketStatusHistory($conn, $ticket_id) {
    $stmt = $conn->prepare("
        SELECT tst.*, s.name as status_name 
        FROM ticket_status_tracking tst 
        LEFT JOIN statuses s ON tst.status_id = s.id 
        WHERE tst.ticket_id = ? 
        ORDER BY tst.started_at DESC
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        // Agregar información formateada
        $row['duration_formatted'] = formatDuration($row['duration_minutes'] ?? 0);
        $row['started_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['started_at']));
        $row['ended_at_formatted'] = $row['ended_at'] ? date('d/m/Y H:i:s', strtotime($row['ended_at'])) : 'En curso';
        $history[] = $row;
    }
    
    $stmt->close();
    return $history;
}

/**
 * Obtiene información detallada del tracking actual de un ticket
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @return array|null Información del tracking actual o null si no hay tracking activo
 */
function getCurrentTrackingInfo($conn, $ticket_id) {
    $stmt = $conn->prepare("
        SELECT tst.*, s.name as status_name 
        FROM ticket_status_tracking tst 
        LEFT JOIN statuses s ON tst.status_id = s.id 
        WHERE tst.ticket_id = ? AND tst.is_active = 1
        ORDER BY tst.started_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $tracking = $result->fetch_assoc();
    $stmt->close();
    
    // Calcular tiempo actual
    $current_minutes = getCurrentTimeInStatus($conn, $ticket_id, $tracking['status_id']);
    
    $tracking['current_duration_minutes'] = $current_minutes;
    $tracking['current_duration_formatted'] = formatDuration($current_minutes);
    $tracking['started_at_formatted'] = date('d/m/Y H:i:s', strtotime($tracking['started_at']));
    
    return $tracking;
}

/**
 * Corrige los registros existentes que tienen duration_minutes incorrecto
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @return array Array con estadísticas de la corrección
 */
function fixIncorrectDurations($conn) {
    $stats = [
        'total_checked' => 0,
        'fixed' => 0,
        'errors' => 0
    ];
    
    // Verificar conexión
    if (!$conn) {
        $stats['errors']++;
        error_log("Error: No hay conexión a la base de datos");
        return $stats;
    }
    
    // Obtener todos los registros con ended_at no nulo
    $query = "SELECT id, started_at, ended_at, duration_minutes FROM ticket_status_tracking WHERE ended_at IS NOT NULL";
    $result = $conn->query($query);
    
    if (!$result) {
        $stats['errors']++;
        error_log("Error ejecutando consulta: " . $conn->error);
        return $stats;
    }
    
    $stats['total_checked'] = $result->num_rows;
    
    while ($row = $result->fetch_assoc()) {
        try {
            // Recalcular la duración usando timestamps
            $started_timestamp = strtotime($row['started_at']);
            $ended_timestamp = strtotime($row['ended_at']);
            
            // Verificar que los timestamps sean válidos
            if ($started_timestamp === false || $ended_timestamp === false) {
                error_log("Error: Timestamps inválidos para ID " . $row['id'] . " - started: " . $row['started_at'] . ", ended: " . $row['ended_at']);
                $stats['errors']++;
                continue;
            }
            
            $correct_duration = round(($ended_timestamp - $started_timestamp) / 60);
            
            // Si la duración es negativa, usar 0
            if ($correct_duration < 0) {
                $correct_duration = 0;
            }
            
            // Si la duración calculada es diferente a la almacenada, actualizar
            if ($correct_duration != $row['duration_minutes']) {
                $update_stmt = $conn->prepare("UPDATE ticket_status_tracking SET duration_minutes = ? WHERE id = ?");
                
                if (!$update_stmt) {
                    error_log("Error preparando consulta de actualización: " . $conn->error);
                    $stats['errors']++;
                    continue;
                }
                
                $update_stmt->bind_param("ii", $correct_duration, $row['id']);
                
                if ($update_stmt->execute()) {
                    $stats['fixed']++;
                    error_log("Fixed duration for tracking ID " . $row['id'] . ": " . $row['duration_minutes'] . " -> " . $correct_duration . " (started: " . $row['started_at'] . ", ended: " . $row['ended_at'] . ")");
                } else {
                    error_log("Error ejecutando actualización: " . $update_stmt->error);
                    $stats['errors']++;
                }
                $update_stmt->close();
            }
            
        } catch (Exception $e) {
            error_log("Error fixing duration for ID " . $row['id'] . ": " . $e->getMessage());
            $stats['errors']++;
        }
    }
    
    return $stats;
}
?>
