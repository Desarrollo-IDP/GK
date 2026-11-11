<?php
require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

header('Content-Type: application/json');

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Verificar que la empresa pertenezca a la empresa del usuario
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $stmt = $conn->prepare("SELECT nombre FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $data['id'], $user_empresa_id);
} else {
    $stmt = $conn->prepare("SELECT nombre FROM empresas_equipos WHERE id_empresa = ?");
    $stmt->bind_param("i", $data['id']);
}

$stmt->execute();
$result = $stmt->get_result();
$empresa = $result->fetch_assoc();

if (!$empresa) {
    echo json_encode(['success' => false, 'error' => 'Empresa no encontrada o no tienes permiso para eliminarla']);
    exit;
}

// Verificación adicional de permisos
if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    $verify_stmt = $conn->prepare("SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?");
    $verify_stmt->bind_param("ii", $data['id'], $user_empresa_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para eliminar esta empresa']);
        exit;
    }
    $verify_stmt->close();
}

// Restricción: no eliminar si hay equipos con nombre_cliente igual al nombre de la empresa
$check_equipos = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE nombre_cliente = ?");
$check_equipos->bind_param("s", $empresa['nombre']);
$check_equipos->execute();
$row = $check_equipos->get_result()->fetch_assoc();
$check_equipos->close();
if ($row && (int)$row['total'] > 0) {
    echo json_encode([
        'success' => false,
        'error' => 'No se puede eliminar la empresa porque existen ' . $row['total'] . ' equipo(s) con esta empresa asignada. Elimine los equipos primero.'
    ]);
    exit;
}

$conn->begin_transaction();

try {
    deleteContactosEmpresaSafe($conn, $data['id'], CONTACTO_ORIGEN_EMPRESAS_EQUIPOS, 'empresas');
    $stmt = $conn->prepare("DELETE FROM empresas_equipos WHERE id_empresa = ?");
    $stmt->bind_param("i", $data['id']);

    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar empresa: " . $stmt->error);
    }
    logActivity(
        "Eliminación de empresa (equipos)",
        "empresas_equipos",
        $data['id'],
        "Se eliminó la empresa: " . $empresa['nombre']
    );

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
