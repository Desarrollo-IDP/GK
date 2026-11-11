<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../log_activity.php';


$json = file_get_contents('php://input');
$data = json_decode($json, true);

$nombre_usuario = $data['nombre_usuario'] ?? '';
$password = $data['contrase�0�9a'] ?? '';
$rol = $data['rol'] ?? '';

if (empty($nombre_usuario) || empty($password) || empty($rol)) {
    echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios']);
    exit;
}

$check_stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
$check_stmt->bind_param("s", $nombre_usuario);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya existe']);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
 
$stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $nombre_usuario, $hashed_password, $rol);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id;

    logActivity(
        "Creaci��n de usuario",
        "usuarios",
        $new_user_id,
        "Se creó el usuario: " . $nombre_usuario . " (ID: " . $new_user_id . ")"
    );
    
    echo json_encode(['success' => true, 'id' => $new_user_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al crear el usuario: ' . $conn->error]);
}
?>