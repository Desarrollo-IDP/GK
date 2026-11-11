<?php
/**
 * Funciones Helper para Contadores de Equipos por Empresa
 * Permite que cada empresa tenga su propio contador independiente
 */

/**
 * Obtiene el siguiente número de equipo para una empresa específica
 * @param mysqli $conn Conexión a la base de datos
 * @param int|null $empresa_id ID de la empresa (null para empresa del usuario actual)
 * @return string Número de equipo formateado (ej: "1", "2", "3")
 */
function getNextEquipmentNumber($conn, $empresa_id = null) {
    // Si no se proporciona empresa_id, obtener del usuario actual
    if ($empresa_id === null) {
        require_once __DIR__ . '/../../includes/empresa_helper.php';
        $empresa_id = getCurrentUserEmpresaId();
    }
    
    // Si no hay empresa, usar contador global (0)
    if ($empresa_id === null) {
        $empresa_id = 0;
    }
    
    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();
    
    try {
        // Intentar obtener o crear el contador para esta empresa
        $stmt = $conn->prepare("SELECT current_counter FROM equipment_counters WHERE id_empresa = ? FOR UPDATE");
        if (!$stmt) {
            error_log("ERROR al preparar consulta de contador equipment: " . $conn->error);
            $conn->rollback();
            return (string)time(); // Fallback
        }
        
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // El contador existe, incrementarlo
            $row = $result->fetch_assoc();
            $next_counter = $row['current_counter'] + 1;
            
            // Actualizar el contador
            $update_stmt = $conn->prepare("UPDATE equipment_counters SET current_counter = ? WHERE id_empresa = ?");
            if (!$update_stmt) {
                error_log("ERROR al preparar actualización de contador equipment: " . $conn->error);
                $stmt->close();
                $conn->rollback();
                return (string)time(); // Fallback
            }
            $update_stmt->bind_param("ii", $next_counter, $empresa_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // El contador no existe, crearlo con valor inicial 1
            $next_counter = 1;
            $insert_stmt = $conn->prepare("INSERT INTO equipment_counters (id_empresa, current_counter) VALUES (?, ?)");
            if (!$insert_stmt) {
                error_log("ERROR al preparar inserción de contador equipment: " . $conn->error);
                $stmt->close();
                $conn->rollback();
                return (string)time(); // Fallback
            }
            $insert_stmt->bind_param("ii", $empresa_id, $next_counter);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        $stmt->close();
        
        // Confirmar transacción
        $conn->commit();
        
        // Retornar el número como string
        return (string)$next_counter;
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        error_log("ERROR al obtener siguiente número de equipo para empresa {$empresa_id}: " . $e->getMessage());
        
        // Fallback: usar timestamp como número único
        return (string)time();
    }
}

/**
 * Obtiene el número de equipo formateado con prefijo de empresa (opcional)
 * @param mysqli $conn Conexión a la base de datos
 * @param int|null $empresa_id ID de la empresa
 * @param string $format Formato del número (ej: "{EMP}-{NUM}" o solo "{NUM}")
 * @return string Número de equipo formateado
 */
function getFormattedEquipmentNumber($conn, $empresa_id = null, $format = "{NUM}") {
    $next_number = getNextEquipmentNumber($conn, $empresa_id);
    
    // Si el formato incluye {EMP}, reemplazarlo con el ID de empresa
    if (strpos($format, '{EMP}') !== false) {
        if ($empresa_id === null) {
            require_once __DIR__ . '/../../includes/empresa_helper.php';
            $empresa_id = getCurrentUserEmpresaId() ?? 0;
        }
        $format = str_replace('{EMP}', $empresa_id, $format);
    }
    
    // Reemplazar {NUM} con el número de equipo
    $formatted = str_replace('{NUM}', $next_number, $format);
    
    return $formatted;
}

/**
 * Obtiene el contador actual de una empresa sin incrementarlo
 * @param mysqli $conn Conexión a la base de datos
 * @param int|null $empresa_id ID de la empresa
 * @return int Contador actual
 */
function getCurrentEquipmentCounter($conn, $empresa_id = null) {
    if ($empresa_id === null) {
        require_once __DIR__ . '/../../includes/empresa_helper.php';
        $empresa_id = getCurrentUserEmpresaId();
    }
    
    if ($empresa_id === null) {
        $empresa_id = 0;
    }
    
    $stmt = $conn->prepare("SELECT current_counter FROM equipment_counters WHERE id_empresa = ?");
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $counter = (int)$row['current_counter'];
    } else {
        $counter = 0;
    }
    
    $stmt->close();
    return $counter;
}

