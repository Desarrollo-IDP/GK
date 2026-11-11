<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function json_error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "Error PHP ($errno): $errstr en $errfile línea $errline"
    ]);
    exit;
}
set_error_handler('json_error_handler');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => "Error fatal: {$error['message']} en {$error['file']} línea {$error['line']}"
        ]);
    }
});
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Solo se permiten solicitudes AJAX']);
    exit;
}

$user_empresa_id = getCurrentUserEmpresaId();

try {
    $id = $_POST['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('ID de equipo no válido');
    }
    
    // Verificar si existe columna id_empresa_usuario
    $check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
    
    // Verificar que el equipo pertenezca a la empresa del usuario
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
        $stmt->bind_param("ii", $id, $user_empresa_id);
    } else {
    $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
    $stmt->bind_param("i", $id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $equipo_original = $result->fetch_assoc();
    
    if (!$equipo_original) {
        throw new Exception('Equipo no encontrado o no tienes permiso para editarlo');
    }
    
    // Liberar resultado y cerrar statement antes de más consultas (evita "Packets out of order")
    $result->free();
    $stmt->close();
    
    // Verificación adicional de permisos
    if ($user_empresa_id !== null && $has_empresa_usuario_column) {
        if ($equipo_original['id_empresa_usuario'] != $user_empresa_id) {
            throw new Exception('No tienes permiso para editar este equipo');
        }
    }
    
    $documentos = $equipo_original['documentos']; 
    
    if(isset($_POST['eliminar_documentos']) && !empty($_POST['eliminar_documentos'])) {
        $documentos_actuales = ($documentos !== null) ? json_decode($documentos, true) : [];
        $indices_eliminar = $_POST['eliminar_documentos'];
        
        foreach($indices_eliminar as $indice) {
            unset($documentos_actuales[$indice]);
        }
        
        $documentos_actuales = array_values($documentos_actuales); 
        $documentos = !empty($documentos_actuales) ? json_encode($documentos_actuales) : null;
    }
    
    if(isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
        $documentos_actuales = ($documentos !== null) ? json_decode($documentos, true) : [];
        $total_files = count($_FILES['documentos']['name']);
        
        for($i = 0; $i < $total_files; $i++) {
            if($_FILES['documentos']['error'][$i] == 0) {
                $file_name = $_FILES['documentos']['name'][$i];
                $file_type = $_FILES['documentos']['type'][$i];
                $file_content = file_get_contents($_FILES['documentos']['tmp_name'][$i]);
                
                $documentos_actuales[] = [
                    'nombre' => $file_name,
                    'tipo' => $file_type,
                    'contenido' => base64_encode($file_content)
                ];
            }
        }
        
        $documentos = json_encode($documentos_actuales);
    }
    
    if (!isset($_POST['nombre_equipo']) || empty($_POST['nombre_equipo'])) {
        throw new Exception('El nombre del equipo es obligatorio');
    }
    
    // Normalizar campos opcionales (ubicacion, activo_equipo) para pasarlos por referencia en bind_param
    $ubicacion = isset($_POST['ubicacion']) ? $_POST['ubicacion'] : '';
    $activo_equipo = isset($_POST['activo_equipo']) ? $_POST['activo_equipo'] : '';

    // Verificar si existe columna id_empresa_usuario para mantenerla en el UPDATE
    $check_column_update = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column_update = $check_column_update && $check_column_update->num_rows > 0;
    
    if ($has_empresa_usuario_column_update) {
        // UPDATE manteniendo id_empresa_usuario (no se puede cambiar)
        $stmt = $conn->prepare("UPDATE equipos SET 
            nombre_equipo = ?,
            responsable = ?,
            sistema_operativo = ?,
            direccion_mac = ?,
            tipo = ?,
            fabricante = ?,
            modelo = ?,
            numero_serie = ?,
            ram = ?,
            disco_duro = ?,
            procesador = ?,
            observaciones = ?,
            ubicacion = ?,
            activo_equipo = ?,
            documentos = ?
            WHERE id_equipo = ? AND id_empresa_usuario = ?");
        
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        
        // Verificar que el equipo pertenezca a la empresa del usuario
        if ($user_empresa_id === null) {
            throw new Exception('Usuario sin empresa asignada');
        }
        
        $stmt->bind_param("sssssssssssssssii", 
            $_POST['nombre_equipo'],
            $_POST['responsable'],
            $_POST['sistema_operativo'],
            $_POST['direccion_mac'],
            $_POST['tipo'],
            $_POST['fabricante'],
            $_POST['modelo'],
            $_POST['numero_serie'],
            $_POST['ram'],
            $_POST['disco_duro'],
            $_POST['procesador'],
            $_POST['observaciones'],
            $ubicacion,
            $activo_equipo,
            $documentos,
            $id,
            $user_empresa_id
        );
    } else {
        // UPDATE sin id_empresa_usuario (campo no existe aún)
    $stmt = $conn->prepare("UPDATE equipos SET 
        nombre_equipo = ?,
        responsable = ?,
        sistema_operativo = ?,
        direccion_mac = ?,
        tipo = ?,
        fabricante = ?,
        modelo = ?,
        numero_serie = ?,
        ram = ?,
        disco_duro = ?,
        procesador = ?,
        observaciones = ?,
        ubicacion = ?,
        activo_equipo = ?,
        documentos = ?
        WHERE id_equipo = ?");
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("sssssssssssssssi", 
        $_POST['nombre_equipo'],
        $_POST['responsable'],
        $_POST['sistema_operativo'],
        $_POST['direccion_mac'],
        $_POST['tipo'],
        $_POST['fabricante'],
        $_POST['modelo'],
        $_POST['numero_serie'],
        $_POST['ram'],
        $_POST['disco_duro'],
        $_POST['procesador'],
        $_POST['observaciones'],
        $ubicacion,
        $activo_equipo,
        $documentos,
        $id
    );
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    $cambios = [];
    if ($equipo_original['nombre_equipo'] != $_POST['nombre_equipo']) {
        $cambios[] = "Equipo: " . $equipo_original['nombre_equipo'] . " → " . $_POST['nombre_equipo'];
    }
    if ($equipo_original['responsable'] != $_POST['responsable']) {
        $cambios[] = "Responsable: " . $equipo_original['responsable'] . " → " . $_POST['responsable'];
    }
    if ($equipo_original['sistema_operativo'] != $_POST['sistema_operativo']) {
        $cambios[] = "Sistema Operativo: " . $equipo_original['sistema_operativo'] . " → " . $_POST['sistema_operativo'];
    }
    if ($equipo_original['tipo'] != $_POST['tipo']) {
        $cambios[] = "Tipo: " . $equipo_original['tipo'] . " → " . $_POST['tipo'];
    }
    if (($equipo_original['ubicacion'] ?? '') != ($_POST['ubicacion'] ?? '')) {
        $cambios[] = "Ubicación: " . ($equipo_original['ubicacion'] ?? '') . " → " . ($_POST['ubicacion'] ?? '');
    }
    if (($equipo_original['activo_equipo'] ?? '') != ($_POST['activo_equipo'] ?? '')) {
        $cambios[] = "Activo del equipo: " . ($equipo_original['activo_equipo'] ?? '') . " → " . ($_POST['activo_equipo'] ?? '');
    }
    
    logActivity(
        "Actualización de equipo",
        "equipos",
        $id,
        "Se actualizó el equipo ID: $id\n" . implode("\n", $cambios)
    );
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'visualizar-equipos.php']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>