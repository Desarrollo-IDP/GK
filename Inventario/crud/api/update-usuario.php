<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../log_activity.php';
checkAdmin();

$data = json_decode(file_get_contents('php://input'), true);
// If using form data instead of JSON:
// $data = $_POST;

$id = $data['id'] ?? 0;
$nombre = $data['nombre'] ?? '';
$nombre_usuario = $data['nombre_usuario'] ?? '';
$rol = $data['rol'] ?? '';
$password = $data['password'] ?? '';
$email = $data['email'] ?? ''; // Added email field
$stmt_user = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?");
$stmt_user->bind_param("i", $id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

if ($user && strtolower($user['nombre_usuario']) === 'admin') {
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if ($stmt->execute()) {
            logActivity(
                "Actualización de contraseña",
                "usuarios",
                $id,
                "Se actualizó la contraseña del usuario admin"
            );
            echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la contraseña: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'El usuario admin solo puede modificar su contraseña']);
    }
} else {
    $updates = [];
    $types = "";
    $params = [];
    
    if (!empty($nombre)) {
        $updates[] = "nombre = ?";
        $types .= "s";
        $params[] = $nombre;
    }
    
    if (!empty($nombre_usuario)) {
        $updates[] = "nombre_usuario = ?";
        $types .= "s";
        $params[] = $nombre_usuario;
    }
    
    if (!empty($email)) {
        $updates[] = "email = ?";
        $types .= "s";
        $params[] = $email;
    }
    
    if (!empty($rol)) {
        $updates[] = "rol = ?";
        $types .= "s";
        $params[] = $rol;
    }
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $updates[] = "contraseña = ?";
        $types .= "s";
        $params[] = $hashed_password;
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No se proporcionaron datos para actualizar']);
        exit;
    }
    
    $query = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id_usuario = ?";
    $types .= "i";
    $params[] = $id;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logActivity(
            "Actualización de usuario",
            "usuarios",
            $id,
            "Se actualizó el usuario ID: " . $id . " - " . $nombre_usuario
        );
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el usuario: ' . $conn->error]);
    }
}
?>