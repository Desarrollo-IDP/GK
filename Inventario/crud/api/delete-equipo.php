<?php
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
    exit;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;

// Validar que el ID sea un entero válido y mayor a 0
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de equipo no válido']);
    exit;
}

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Verificar que el equipo exista y obtener sus datos
$stmt = $conn->prepare("SELECT nombre_equipo, nombre_cliente, id_empresa_usuario FROM equipos WHERE id_equipo = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$equipo = $result->fetch_assoc();
$stmt->close();

if (!$equipo) {
    echo json_encode(['success' => false, 'error' => 'Equipo no encontrado']);
    exit;
}

// Verificar permisos: si el usuario tiene empresa asignada y el campo existe, verificar que coincidan
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    // Si el equipo tiene id_empresa_usuario NULL, no puede ser eliminado por usuarios con empresa asignada
    if ($equipo['id_empresa_usuario'] === null) {
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para eliminar este equipo. El equipo no está asignado a ninguna empresa.']);
        exit;
    }
    
    // Verificar que el equipo pertenezca a la empresa del usuario
    if ($equipo['id_empresa_usuario'] != $user_empresa_id) {
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para eliminar este equipo. El equipo pertenece a otra empresa.']);
        exit;
    }
}

// DELETE con filtro de empresa para mayor seguridad
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    // Incluir filtro de empresa en el DELETE para asegurar que solo se elimine el equipo correcto
    $stmt = $conn->prepare("DELETE FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $id, $user_empresa_id);
} else {
    // Si no existe el campo o es admin, eliminar solo por ID
$stmt = $conn->prepare("DELETE FROM equipos WHERE id_equipo = ?");
$stmt->bind_param("i", $id);
}

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta DELETE: ' . $conn->error]);
    exit;
}

if ($stmt->execute()) {
    // Verificar que realmente se eliminó exactamente 1 registro
    $affected_rows = $stmt->affected_rows;
    
    if ($affected_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el equipo. Verifique que el equipo existe y pertenece a su empresa.']);
        exit;
    }
    
    if ($affected_rows > 1) {
        // Esto no debería pasar nunca, pero por seguridad lo verificamos
        error_log("ADVERTENCIA: Se eliminaron " . $affected_rows . " equipos cuando se esperaba eliminar solo 1. ID: " . $id);
        echo json_encode(['success' => false, 'error' => 'Error: Se eliminaron múltiples equipos. Contacte al administrador.']);
        exit;
    }
    
    logActivity(
        "Eliminación de equipo",
        "equipos",
        $id,
        "Se eliminó el equipo: " . $equipo['nombre_equipo']. " - De la empresa: " .$equipo['nombre_cliente']
    );
    
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al ejecutar DELETE: ' . $stmt->error]);
}
?>