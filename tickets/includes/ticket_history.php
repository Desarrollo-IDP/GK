<?php
/**
 * Funciones para el manejo del historial de tickets
 */

/**
 * Registra una acción en el historial del ticket
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $ticket_id ID del ticket
 * @param string $action Acción realizada (created, updated, closed, deleted, reopened)
 * @param string $user_name Nombre del usuario que realizó la acción
 * @param string $field_name Nombre del campo modificado (opcional)
 * @param string $old_value Valor anterior (opcional)
 * @param string $new_value Valor nuevo (opcional)
 * @param string $description Descripción adicional (opcional)
 * @return bool True si se registró correctamente, false en caso contrario
 */
function logTicketHistory($conn, $ticket_id, $action, $user_name, $field_name = null, $old_value = null, $new_value = null, $description = null) {
    $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, action, user_name, field_name, old_value, new_value, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Error preparando consulta de historial: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issssss", $ticket_id, $action, $user_name, $field_name, $old_value, $new_value, $description);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error ejecutando consulta de historial: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

/**
 * Registra la creación de un ticket
 */
function logTicketCreated($conn, $ticket_id, $user_name) {
    return logTicketHistory($conn, $ticket_id, 'created', $user_name, null, null, null, 'Ticket creado');
}

/**
 * Registra el cierre de un ticket
 */
function logTicketClosed($conn, $ticket_id, $user_name, $resolution = null, $by_inactivity = false) {
    if ($by_inactivity) {
        return logTicketClosedByInactivity($conn, $ticket_id, $user_name, $resolution);
    }

    $description = 'Ticket cerrado';
    if ($resolution) {
        $description .= ' con resolución: ' . substr($resolution, 0, 100);
    }
    return logTicketHistory($conn, $ticket_id, 'closed', $user_name, null, null, null, $description);
}

/**
 * Registra el cierre de un ticket por inactividad
 */
function logTicketClosedByInactivity($conn, $ticket_id, $user_name, $resolution = null) {
    $description = 'Ticket cerrado por inactividad';
    if ($resolution) {
        $description .= '. Resolución: ' . substr($resolution, 0, 100);
    }
    return logTicketHistory($conn, $ticket_id, 'closed_inactivity', $user_name, null, null, null, $description);
}

/**
 * Registra la eliminación de un ticket
 */
function logTicketDeleted($conn, $ticket_id, $user_name) {
    return logTicketHistory($conn, $ticket_id, 'deleted', $user_name, null, null, null, 'Ticket eliminado');
}

/**
 * Registra la reapertura de un ticket
 */
function logTicketReopened($conn, $ticket_id, $user_name) {
    return logTicketHistory($conn, $ticket_id, 'reopened', $user_name, null, null, null, 'Ticket reabierto');
}

/**
 * Registra la actualización de un campo específico
 */
function logFieldUpdate($conn, $ticket_id, $user_name, $field_name, $old_value, $new_value) {
    $field_labels = [
        'category_id' => 'Categoría',
        'status_id' => 'Estado',
        'contact_mode_id' => 'Modo de Contacto',
        'whatsapp_enabled' => 'Conversación WhatsApp',
        'level_id' => 'Nivel',
        'impact_id' => 'Impacto',
        'urgency_id' => 'Urgencia',
        'priority_id' => 'Prioridad',
        'requester_id' => 'Solicitante',
        'classification_id' => 'Clasificación',
        'technician_id' => 'Técnico Asignado',
        'description' => 'Descripción',
        'resolution' => 'Resolución',
        'impact_details' => 'Detalles del Impacto'
    ];
    
    $field_label = $field_labels[$field_name] ?? $field_name;
    
    // Si es un campo de relación, obtener los nombres
    if (strpos($field_name, '_id') !== false && $field_name !== 'requester_id') {
        $old_name = getFieldName($conn, $field_name, $old_value);
        $new_name = getFieldName($conn, $field_name, $new_value);
        $old_value = $old_name ?: $old_value;
        $new_value = $new_name ?: $new_value;
    } elseif ($field_name === 'requester_id') {
        $old_name = getRequesterName($conn, $old_value);
        $new_name = getRequesterName($conn, $new_value);
        $old_value = $old_name ?: $old_value;
        $new_value = $new_name ?: $new_value;
    }
    
    return logTicketHistory($conn, $ticket_id, 'updated', $user_name, $field_label, $old_value, $new_value, "Campo '{$field_label}' actualizado");
}

/**
 * Obtiene el nombre de un campo de relación
 */
function getFieldName($conn, $field_name, $value) {
    if (empty($value)) return null;
    
    $table_map = [
        'category_id' => 'categories',
        'status_id' => 'statuses',
        'contact_mode_id' => 'contact_modes',
        'level_id' => 'levels',
        'impact_id' => 'impacts',
        'urgency_id' => 'urgencies',
        'priority_id' => 'priorities',
        'classification_id' => 'classifications',
        'technician_id' => 'technicians'
    ];
    
    $table = $table_map[$field_name] ?? null;
    if (!$table) return null;
    
    $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = ?");
    $stmt->bind_param("i", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['name'] : null;
}

/**
 * Obtiene el nombre de un solicitante
 */
function getRequesterName($conn, $value) {
    if (empty($value)) return null;
    
    $stmt = $conn->prepare("SELECT r.name, e.nombre as company_name FROM requesters r LEFT JOIN empresas e ON r.company_id = e.id_empresa WHERE r.id = ?");
    $stmt->bind_param("i", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $name = $row['name'];
        if (!empty($row['company_name'])) {
            $name .= ' (' . $row['company_name'] . ')';
        }
        return $name;
    }
    
    return null;
}
?>
