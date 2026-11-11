<?php
require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../log_activity.php';
checkAdmin();


$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;
$nombre_usuario = $data['nombre_usuario'] ?? '';

if (empty($nombre_usuario)) {
    $stmt_user = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?");
    $stmt_user->bind_param("i", $id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($user = $result_user->fetch_assoc()) {
        $nombre_usuario = $user['nombre_usuario'];
    }
}

if (strtolower($nombre_usuario) === 'admin') {
    echo json_encode(['success' => false, 'error' => 'El usuario administrador no puede ser eliminado por seguridad.']);
    exit;
}
    
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        logActivity(
            "Eliminaci&oacuten de usuario",
            "usuarios",
            $id,
            "Se elimin&oacute el usuario: " . $nombre_usuario . " (ID: " . $id . ")"
        );
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar el usuario: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID de usuario no válido']);
}
?>