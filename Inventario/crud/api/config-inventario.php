<?php
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$is_admin = isAdmin();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$accion = isset($data['accion']) ? trim($data['accion']) : '';
$tipo = isset($data['tipo']) ? trim($data['tipo']) : '';
$id = isset($data['id']) ? (int) $data['id'] : 0;
$valor = isset($data['valor']) ? trim($data['valor']) : '';

// id_empresa_usuario con el que trabajar: si admin puede enviar uno, si no usamos el del usuario
$config_empresa_id = $user_empresa_id;
if ($is_admin && isset($data['id_empresa_usuario']) && (int)$data['id_empresa_usuario'] > 0) {
    $config_empresa_id = (int) $data['id_empresa_usuario'];
}
if ($config_empresa_id === null) {
    echo json_encode(['ok' => false, 'error' => 'No hay empresa asignada para esta configuración']);
    exit;
}

$tabla = $tipo === 'ubicacion' ? 'inventario_config_ubicaciones' : ($tipo === 'activo' ? 'inventario_config_activos' : null);
if ($tabla === null || !in_array($accion, ['agregar', 'editar', 'eliminar'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Acción o tipo no válido']);
    exit;
}

// Verificar que la tabla exista
$exists = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'");
if (!$exists || $exists->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Tabla de configuración no existe']);
    exit;
}

if ($accion === 'agregar') {
    if ($valor === '') {
        echo json_encode(['ok' => false, 'error' => 'El valor no puede estar vacío']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO `$tabla` (id_empresa_usuario, valor) VALUES (?, ?)");
    $stmt->bind_param("is", $config_empresa_id, $valor);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'id' => (int) $conn->insert_id]);
    } else {
        echo json_encode(['ok' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'editar') {
    if ($id <= 0 || $valor === '') {
        echo json_encode(['ok' => false, 'error' => 'ID o valor inválido']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE `$tabla` SET valor = ? WHERE id = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("sii", $valor, $id, $config_empresa_id);
    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => $stmt->error ?: 'No se pudo actualizar']);
    }
    $stmt->close();
    exit;
}

if ($accion === 'eliminar') {
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM `$tabla` WHERE id = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $id, $config_empresa_id);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción no soportada']);
