<?php
require_once __DIR__ . '/auth.php';
checkAuth();
if (!(function_exists('canManageUsers') && canManageUsers())) {
    header('Location: ' . appUrl('/direc.php'));
    exit;
}

// Incluir configuración de base de datos
require_once __DIR__ . '/Inventario/crud/includes/db.php';

// Verificar y crear tabla empresas_usuarios si no existe
try {
    if ($conn instanceof mysqli) {
        $table_check = $conn->query("SHOW TABLES LIKE 'empresas_usuarios'");
        if (!$table_check || $table_check->num_rows === 0) {
            // Crear tabla empresas_usuarios
            $create_table_sql = "CREATE TABLE IF NOT EXISTS `empresas_usuarios` (
                `id_empresa` INT(11) NOT NULL AUTO_INCREMENT,
                `nombre` VARCHAR(255) NOT NULL,
                `descripcion` TEXT NULL DEFAULT NULL,
                `direccion` VARCHAR(500) NULL DEFAULT NULL,
                `telefono` VARCHAR(50) NULL DEFAULT NULL,
                `email` VARCHAR(255) NULL DEFAULT NULL,
                `contacto` VARCHAR(255) NULL DEFAULT NULL,
                `activo` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_empresa`),
                INDEX `idx_empresas_usuarios_activo` (`activo`),
                INDEX `idx_empresas_usuarios_nombre` (`nombre`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if (!$conn->query($create_table_sql)) {
                error_log("Error creando tabla empresas_usuarios: " . $conn->error);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error verificando tabla empresas_usuarios: " . $e->getMessage());
}

// Función para registrar acciones en el historial de usuarios
function logUserAction($conn, $action, $target_user, $target_user_id, $performed_by, $performed_by_id, $description = '', $old_values = null, $new_values = null) {
    try {
        // Asegurar que la tabla existe con la nueva estructura
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_history'");
        if ($tableCheck && $tableCheck->num_rows === 0) {
            $createTable = "CREATE TABLE user_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                target_user VARCHAR(100) NOT NULL,
                target_user_id INT,
                nombre_usuario VARCHAR(100) NOT NULL,
                id_usuario INT,
                description TEXT,
                old_values JSON,
                new_values JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
        } else {
            // Verificar si las columnas nuevas existen y agregarlas si es necesario
            $columnCheck1 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'nombre_usuario'");
            $columnCheck2 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'id_usuario'");
            
            if ($columnCheck1 && $columnCheck1->num_rows === 0) {
                $conn->query("ALTER TABLE user_history ADD COLUMN nombre_usuario VARCHAR(100) NOT NULL AFTER target_user_id");
            }
            if ($columnCheck2 && $columnCheck2->num_rows === 0) {
                $conn->query("ALTER TABLE user_history ADD COLUMN id_usuario INT AFTER nombre_usuario");
            }
            
            // Eliminar solo las foreign keys de la tabla (no PRIMARY ni otros índices)
            try {
                // Obtener solo las foreign keys reales de la tabla
                $fkQuery = "SELECT DISTINCT CONSTRAINT_NAME 
                           FROM information_schema.KEY_COLUMN_USAGE 
                           WHERE TABLE_NAME = 'user_history' 
                           AND TABLE_SCHEMA = DATABASE()
                           AND CONSTRAINT_NAME IS NOT NULL
                           AND CONSTRAINT_NAME != 'PRIMARY'
                           AND CONSTRAINT_NAME IN (
                               SELECT CONSTRAINT_NAME 
                               FROM information_schema.REFERENTIAL_CONSTRAINTS 
                               WHERE TABLE_NAME = 'user_history' 
                               AND TABLE_SCHEMA = DATABASE()
                           )";
                $fkResult = $conn->query($fkQuery);
                
                if ($fkResult && $fkResult->num_rows > 0) {
                    while ($fkRow = $fkResult->fetch_assoc()) {
                        $constraintName = $fkRow['CONSTRAINT_NAME'];
                        try {
                            $conn->query("ALTER TABLE user_history DROP FOREIGN KEY `$constraintName`");
                        } catch (Exception $dropError) {
                            error_log("Error eliminando FK $constraintName: " . $dropError->getMessage());
                        }
                    }
                }
            } catch (Exception $fkError) {
                error_log('Error obteniendo foreign keys: ' . $fkError->getMessage());
            }
            
            // Eliminar columnas antiguas si existen
            $oldColumnCheck1 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'performed_by'");
            $oldColumnCheck2 = $conn->query("SHOW COLUMNS FROM user_history LIKE 'performed_by_id'");
            
            if ($oldColumnCheck1 && $oldColumnCheck1->num_rows > 0) {
                $conn->query("ALTER TABLE user_history DROP COLUMN performed_by");
            }
            if ($oldColumnCheck2 && $oldColumnCheck2->num_rows > 0) {
                $conn->query("ALTER TABLE user_history DROP COLUMN performed_by_id");
            }
        }
        
        // Obtener datos del usuario actual de la sesión
        $session_user_name = $_SESSION['user']['username'] ?? '';
        $session_user_id = $_SESSION['user']['id'] ?? 0;
        
        // Log de depuración
        error_log("Historial - Sesión: nombre='$session_user_name', id=$session_user_id");
        error_log("Historial - Sesión completa: " . json_encode($_SESSION['user'] ?? []));
        
        // Validar que tenemos datos de sesión válidos
        if (empty($session_user_name) || $session_user_id <= 0) {
            error_log('Error: No hay datos de sesión válidos para el historial');
            return; // No registrar si no hay sesión válida
        }
        
        // Verificar que el usuario existe en la base de datos
        $userQuery = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id_usuario = ? AND nombre_usuario = ?");
        $userQuery->bind_param("is", $session_user_id, $session_user_name);
        $userQuery->execute();
        $userResult = $userQuery->get_result();
        
        if ($userResult && $userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $performed_by_name = $userData['nombre_usuario'];
            $performed_id_for_store = $session_user_id;
        } else {
            error_log('Error: Usuario de sesión no encontrado en la base de datos. ID: ' . $session_user_id . ', Nombre: ' . $session_user_name);
            // Usar los datos de la sesión directamente si no se encuentra en la consulta
            $performed_by_name = $session_user_name;
            $performed_id_for_store = $session_user_id;
        }

        // Normalizar target_user_id también
        $target_id_for_store = null;
        if (is_int($target_user_id) || ctype_digit((string)$target_user_id)) {
            $target_user_id = (int)$target_user_id;
            if ($target_user_id > 0) {
                $target_id_for_store = $target_user_id;
            }
        }

        $stmt = $conn->prepare("INSERT INTO user_history (action, target_user, target_user_id, nombre_usuario, id_usuario, description, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $old_json = $old_values ? json_encode($old_values) : null;
        $new_json = $new_values ? json_encode($new_values) : null;
        $stmt->bind_param("ssisssss", $action, $target_user, $target_id_for_store, $performed_by_name, $performed_id_for_store, $description, $old_json, $new_json);
        
        if ($stmt->execute()) {
            error_log("Historial guardado exitosamente: acción='$action', usuario='$performed_by_name', id=$performed_id_for_store");
        } else {
            error_log("Error guardando historial: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log('Error registrando acción en historial: ' . $e->getMessage());
    }
}

// Asegurar columnas de permisos por usuario (si no existen)
try {
    $columnsCheck = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_view_inventory_history'");
    if ($columnsCheck && $columnsCheck->num_rows === 0) {
        $conn->query("ALTER TABLE usuarios ADD COLUMN can_view_inventory_history TINYINT(1) NOT NULL DEFAULT 0 AFTER rol");
    }
    $columnsCheck2 = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_view_ticket_history'");
    if ($columnsCheck2 && $columnsCheck2->num_rows === 0) {
        $conn->query("ALTER TABLE usuarios ADD COLUMN can_view_ticket_history TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_inventory_history");
    }
    $columnsCheck3 = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'can_manage_users'");
    if ($columnsCheck3 && $columnsCheck3->num_rows === 0) {
        $conn->query("ALTER TABLE usuarios ADD COLUMN can_manage_users TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_ticket_history");
    }
} catch (Exception $e) {
    error_log('Error verificando/agregando columnas de permisos: ' . $e->getMessage());
}

$current_user = $_SESSION['user']['username'] ?? 'Sistema';
$current_user_id = $_SESSION['user']['id'] ?? 0;
$canViewAdminProfile = canViewAdminProfile();

// Función de validación de contraseña
function validatePassword($password) {
    if (!empty($password) && strlen($password) < 8) {
        return false;
    }
    return true;
}

// Manejo de acciones CRUD
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

// Verificar si se recargó después de agregar usuario exitosamente
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Usuario agregado exitosamente";
    $messageType = "success";
}

// Si es una petición AJAX para eliminar, manejar solo eso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        $nombre_usuario = $data['nombre_usuario'] ?? '';
        
        if (strtolower($nombre_usuario) === 'admin') {
            echo json_encode(['success' => false, 'error' => 'El usuario administrador no puede ser eliminado por seguridad.']);
            exit;
        }
        
        if ($id > 0) {
            // Obtener información del usuario antes de eliminarlo
            $user_info = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
            $user_info->bind_param("i", $id);
            $user_info->execute();
            $user_data = $user_info->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Registrar en historial
                logUserAction($conn, 'deleted', $user_data['nombre_usuario'], $id, $current_user, $current_user_id, 'Usuario eliminado del sistema');
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar el usuario: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de usuario no válido']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
    exit;
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add':
                $nombre_usuario = $_POST['nombre_usuario'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';
                $rol = $_POST['rol'] ?? '';
                $perm_hist_inventario = isset($_POST['perm_hist_inventario']) ? 1 : 0;
                $perm_hist_tickets = isset($_POST['perm_hist_tickets']) ? 1 : 0;
                $perm_manage_users = isset($_POST['perm_manage_users']) ? 1 : 0;
                
                // Validaciones
                if (empty($nombre_usuario) || empty($password) || empty($rol)) {
                    $message = "Todos los campos son obligatorios.";
                    $messageType = "error";
                } elseif ($password !== $password_confirm) {
                    $message = "Las contraseñas no coinciden. Por favor, inténtelo de nuevo.";
                    $messageType = "error";
                } elseif (!validatePassword($password)) {
                    $message = "La contraseña debe tener al menos 8 caracteres.";
                    $messageType = "error";
                } else {
                    // Verificar si el usuario ya existe
                    $check_stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
                    $check_stmt->bind_param("s", $nombre_usuario);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $message = "El nombre de usuario ya existe. Por favor, elija otro.";
                        $messageType = "error";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        // Solo los supervisores pueden tener permisos extras
                        if ($rol !== 'supervisor') {
                            $perm_hist_inventario = 0;
                            $perm_hist_tickets = 0;
                            $perm_manage_users = 0;
                        }
                        $id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
                        $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, email, id_empresa, can_view_inventory_history, can_view_ticket_history, can_manage_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssiiii", $nombre_usuario, $hashed_password, $rol, $email, $id_empresa, $perm_hist_inventario, $perm_hist_tickets, $perm_manage_users);
                        
                        if ($stmt->execute()) {
                            // Registrar en historial
                            $new_user_id = $conn->insert_id;
                            $new_values = [
                                'nombre_usuario' => $nombre_usuario,
                                'email' => $email,
                                'rol' => $rol,
                                'can_view_inventory_history' => $perm_hist_inventario,
                                'can_view_ticket_history' => $perm_hist_tickets,
                                'can_manage_users' => $perm_manage_users
                            ];
                            logUserAction($conn, 'created', $nombre_usuario, $new_user_id, $current_user, $current_user_id, 'Nuevo usuario creado', null, $new_values);
                            
                            // Redirigir para recargar la página y eliminar el mensaje
                            header('Location: gestion-usuarios.php?success=1');
                            exit;
                        } else {
                            $message = "Error al crear el usuario: " . $conn->error;
                            $messageType = "error";
                        }
                    }
                }
                break;
                
            case 'edit':
                $id = $_POST['id_usuario'];
                $nombre_usuario = $_POST['nombre_usuario'];
                $email = $_POST['email'];
                $rol = $_POST['rol'];
                $password = $_POST['password'];
                $password_confirm = $_POST['password_confirm'];
                $isAdminUser = strtolower($nombre_usuario) === 'admin';
                $perm_hist_inventario = isset($_POST['perm_hist_inventario']) ? 1 : 0;
                $perm_hist_tickets = isset($_POST['perm_hist_tickets']) ? 1 : 0;
                $perm_manage_users = isset($_POST['perm_manage_users']) ? 1 : 0;
                
                // Obtener datos anteriores para el historial
                $old_data_stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $old_data_stmt->bind_param("i", $id);
                $old_data_stmt->execute();
                $old_data = $old_data_stmt->get_result()->fetch_assoc();
                
                // Validar contraseñas si se proporcionaron
                if (!empty($password)) {
                    if ($password !== $password_confirm) {
                        $message = "Las contraseñas no coinciden. Por favor, inténtelo de nuevo.";
                        $messageType = "error";
                    } elseif (!validatePassword($password)) {
                        $message = "La contraseña debe tener al menos 8 caracteres.";
                        $messageType = "error";
                    } else {
                        if ($isAdminUser) {
                            // Solo actualizar contraseña para admin
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
                            $update_stmt->bind_param("si", $hashed_password, $id);
                            
                            if ($update_stmt->execute()) {
                                // Registrar en historial
                                logUserAction($conn, 'updated', $nombre_usuario, $id, $current_user, $current_user_id, 'Contraseña actualizada');
                                
                                $message = "Usuario actualizado exitosamente";
                                $messageType = "success";
                            } else {
                                $message = "Error al actualizar la contraseña: " . $conn->error;
                                $messageType = "error";
                            }
                        } else {
                            // Actualizar usuario normal
                            $updates = [];
                            $types = "";
                            $params = [];
                            
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
                            // Solo los supervisores pueden tener permisos extras
                            if ($rol === 'supervisor') {
                                $updates[] = "can_view_inventory_history = ?";
                                $types .= "i";
                                $params[] = $perm_hist_inventario;
                                $updates[] = "can_view_ticket_history = ?";
                                $types .= "i";
                                $params[] = $perm_hist_tickets;
                                $updates[] = "can_manage_users = ?";
                                $types .= "i";
                                $params[] = $perm_manage_users;
                            } else {
                                $updates[] = "can_view_inventory_history = 0";
                                $updates[] = "can_view_ticket_history = 0";
                                $updates[] = "can_manage_users = 0";
                            }
                            
                            if (isset($_POST['id_empresa'])) {
                                $id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
                                $updates[] = "id_empresa = ?";
                                $types .= "i";
                                $params[] = $id_empresa;
                            }
                            
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $updates[] = "contraseña = ?";
                            $types .= "s";
                            $params[] = $hashed_password;
                            
                            $query = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id_usuario = ?";
                            $types .= "i";
                            $params[] = $id;
                            
                            $update_stmt = $conn->prepare($query);
                            $update_stmt->bind_param($types, ...$params);
                            
                            if ($update_stmt->execute()) {
                                // Registrar en historial
                                $old_values = [
                                    'email' => $old_data['email'],
                                    'rol' => $old_data['rol'],
                                    'can_view_inventory_history' => $old_data['can_view_inventory_history'],
                                    'can_view_ticket_history' => $old_data['can_view_ticket_history'],
                                    'can_manage_users' => $old_data['can_manage_users']
                                ];
                                $new_values = [
                                    'email' => $email,
                                    'rol' => $rol,
                                    'can_view_inventory_history' => $perm_hist_inventario,
                                    'can_view_ticket_history' => $perm_hist_tickets,
                                    'can_manage_users' => $perm_manage_users
                                ];
                                logUserAction($conn, 'updated', $nombre_usuario, $id, $current_user, $current_user_id, 'Usuario actualizado con nueva contraseña', $old_values, $new_values);
                                
                                $message = "Usuario actualizado exitosamente";
                                $messageType = "success";
                            } else {
                                $message = "Error al actualizar el usuario: " . $conn->error;
                                $messageType = "error";
                            }
                        }
                    }
                } else {
                    // No cambiar contraseña, solo otros campos
                    if (!$isAdminUser) {
                        $updates = [];
                        $types = "";
                        $params = [];
                        
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
                        // Solo los supervisores pueden tener permisos extras
                        if ($rol === 'supervisor') {
                            $updates[] = "can_view_inventory_history = ?";
                            $types .= "i";
                            $params[] = $perm_hist_inventario;
                            $updates[] = "can_view_ticket_history = ?";
                            $types .= "i";
                            $params[] = $perm_hist_tickets;
                            $updates[] = "can_manage_users = ?";
                            $types .= "i";
                            $params[] = $perm_manage_users;
                        } else {
                            $updates[] = "can_view_inventory_history = 0";
                            $updates[] = "can_view_ticket_history = 0";
                            $updates[] = "can_manage_users = 0";
                        }
                        
                        if (isset($_POST['id_empresa'])) {
                            $id_empresa = !empty($_POST['id_empresa']) ? intval($_POST['id_empresa']) : null;
                            $updates[] = "id_empresa = ?";
                            $types .= "i";
                            $params[] = $id_empresa;
                        }
                        
                        if (!empty($updates)) {
                            $query = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id_usuario = ?";
                            $types .= "i";
                            $params[] = $id;
                            
                            $update_stmt = $conn->prepare($query);
                            $update_stmt->bind_param($types, ...$params);
                            
                            if ($update_stmt->execute()) {
                                // Registrar en historial
                                $old_values = [
                                    'email' => $old_data['email'],
                                    'rol' => $old_data['rol'],
                                    'can_view_inventory_history' => $old_data['can_view_inventory_history'],
                                    'can_view_ticket_history' => $old_data['can_view_ticket_history'],
                                    'can_manage_users' => $old_data['can_manage_users']
                                ];
                                $new_values = [
                                    'email' => $email,
                                    'rol' => $rol,
                                    'can_view_inventory_history' => $perm_hist_inventario,
                                    'can_view_ticket_history' => $perm_hist_tickets,
                                    'can_manage_users' => $perm_manage_users
                                ];
                                logUserAction($conn, 'updated', $nombre_usuario, $id, $current_user, $current_user_id, 'Usuario actualizado sin cambiar contraseña', $old_values, $new_values);
                                
                                $message = "Usuario actualizado exitosamente";
                                $messageType = "success";
                            } else {
                                $message = "Error al actualizar el usuario: " . $conn->error;
                                $messageType = "error";
                            }
                        }
                    }
                }
                break;
        }
    }
}

// Obtener lista de usuarios
if ($canViewAdminProfile) {
    $query = "SELECT u.*, e.nombre as empresa_nombre FROM usuarios u LEFT JOIN empresas_usuarios e ON u.id_empresa = e.id_empresa ORDER BY nombre_usuario";
} else {
    $query = "SELECT u.*, e.nombre as empresa_nombre FROM usuarios u LEFT JOIN empresas_usuarios e ON u.id_empresa = e.id_empresa WHERE LOWER(u.nombre_usuario) != 'admin' ORDER BY nombre_usuario";
}

$result = $conn->query($query);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

// Obtener usuario para editar
$editUser = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_edit = $stmt->get_result();
    $editUser = $result_edit->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1E2A3B;
            --secondary-color: #2C3E50;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            overflow-x: hidden;
        }
        
        .custom-bg {
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color));
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.375rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
        }
        
        /* Responsive para desktop grande */
        @media (min-width: 1441px) {
            .container {
                max-width: 1400px;
            }
            
            .modal-content {
                max-width: 600px;
                padding: 2.5rem;
            }
        }
        
        /* Responsive para tablet */
        @media (max-width: 1024px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .custom-bg {
                padding: 0.75rem 0;
            }
            
            .custom-bg h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Responsive para móvil */
        @media (max-width: 768px) {
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .custom-bg {
                padding: 0.5rem 0;
            }
            
            .custom-bg h1 {
                font-size: 1.25rem;
            }
            
            .custom-bg .container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .custom-bg a {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
            
            .bg-white {
                padding: 1rem !important;
            }
            
            .flex.justify-between {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start !important;
            }
            
            .flex.space-x-2 {
                width: 100%;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .flex.space-x-2 a,
            .flex.space-x-2 button {
                width: 100%;
                text-align: center;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 1.5rem;
                width: 95%;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.5rem !important;
            }
        }
        
        /* Responsive para móvil pequeño */
        @media (max-width: 480px) {
            .custom-bg h1 {
                font-size: 1.1rem;
            }
            
            .bg-white {
                padding: 0.75rem !important;
            }
            
            .modal-content {
                padding: 1rem;
                width: 98%;
            }
            
            table {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 0.25rem !important;
            }
            
            .btn,
            button,
            a.bg-blue-600,
            a.bg-gray-600,
            a.bg-gray-700 {
                font-size: 0.875rem !important;
                padding: 0.5rem 1rem !important;
            }
        }
        
        /* Tabla responsive */
        @media (max-width: 768px) {
            .overflow-x-auto {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="custom-bg text-white py-4 mb-6">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-users mr-2"></i>
                Gestión de Usuarios
            </h1>
            <a href="<?php echo htmlspecialchars(appUrl('/direc.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Volver
            </a>
        </div>
    </div>

    <div class="container mx-auto px-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="messageAlert">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button onclick="closeMessage()" class="ml-4 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Lista de Usuarios</h2>
                <div class="flex space-x-2">
                    <?php if (isCurrentUserAdmin()): ?>
                    <a href="<?php echo htmlspecialchars(appUrl('/gestion-empresas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-building mr-2"></i>
                        Gestionar Sucursales
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(appUrl('/gestionar-rutas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-route mr-2"></i>
                        Gestionar Rutas
                    </a>
                    <?php if (canViewUserHistory()): ?>
                        <a href="<?php echo htmlspecialchars(appUrl('/historial-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            <i class="fas fa-history mr-2"></i>
                            Historial de Usuarios
                        </a>
                    <?php endif; ?>
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>
                        Agregar Usuario
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Correo Electrónico
                            </th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rol
                            </th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Empresa
                            </th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Autenticado
                            </th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($usuario = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <?php echo htmlspecialchars($usuario['nombre_usuario'] ?? ''); ?>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <?php echo htmlspecialchars($usuario['email'] ?? ''); ?>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    $rol = $usuario['rol'] ?? '';
                                    if ($rol === 'administrador') {
                                        echo 'bg-red-100 text-red-800';
                                    } elseif ($rol === 'supervisor') {
                                        echo 'bg-yellow-100 text-yellow-800';
                                    } else {
                                        echo 'bg-blue-100 text-blue-800';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($rol); ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <?php 
                                if ($usuario['id_empresa']): 
                                    echo '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">' . htmlspecialchars($usuario['empresa_nombre'] ?? 'Sin empresa') . '</span>';
                                else:
                                    echo '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Todas</span>';
                                endif; 
                                ?>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <?php echo !empty($usuario['google_auth_secret']) ? 'Sí' : 'No'; ?>
                <?php if (!empty($usuario['can_view_inventory_history']) || !empty($usuario['can_view_ticket_history']) || !empty($usuario['can_manage_users'])): ?>
                                    <span class="ml-2 text-xs text-gray-500">(
                                        <?php echo !empty($usuario['can_view_inventory_history']) ? 'Inv' : ''; ?>
                        <?php echo (!empty($usuario['can_view_inventory_history']) && (!empty($usuario['can_view_ticket_history']) || !empty($usuario['can_manage_users']))) ? ' & ' : ''; ?>
                                        <?php echo !empty($usuario['can_view_ticket_history']) ? 'Tick' : ''; ?>
                        <?php echo (!empty($usuario['can_view_ticket_history']) && !empty($usuario['can_manage_users'])) ? ' & ' : (!empty($usuario['can_view_inventory_history']) && empty($usuario['can_view_ticket_history']) && !empty($usuario['can_manage_users']) ? ' & ' : ''); ?>
                        <?php echo !empty($usuario['can_manage_users']) ? 'Gest' : ''; ?>
                                    )</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap text-sm font-medium">
                                <?php 
                                $isAdminUser = strtolower($usuario['nombre_usuario']) === 'admin';
                                if (!$isAdminUser || $canViewAdminProfile): 
                                ?>
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($usuario)); ?>)" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <?php if (!$isAdminUser): ?>
                                <button onclick="eliminarUsuario(<?php echo $usuario['id_usuario']; ?>, '<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>')" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- Modal para agregar usuario -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Agregar Usuario</h3>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de Usuario</label>
                    <input type="text" name="nombre_usuario" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Correo Electrónico</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar Contraseña</label>
                    <input type="password" name="password_confirm" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rol</label>
                    <select name="rol" id="add_rol" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="toggleAddPermissions()">
                        <option value="">Seleccionar rol</option>
                        <option value="administrador">Administrador</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </div>
                <div id="add_permissions" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Permisos adicionales</label>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="add_perm_hist_inventario" name="perm_hist_inventario" class="mr-2">
                        <label for="add_perm_hist_inventario" class="text-sm">Historial Inventario</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="add_perm_hist_tickets" name="perm_hist_tickets" class="mr-2">
                        <label for="add_perm_hist_tickets" class="text-sm">Historial Tickets</label>
                    </div>
                    <div class="flex items-center mt-2">
                        <input type="checkbox" id="add_perm_manage_users" name="perm_manage_users" class="mr-2">
                        <label for="add_perm_manage_users" class="text-sm">Gestión de Usuarios</label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Empresa</label>
                    <select name="id_empresa" id="add_id_empresa" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Sin empresa (Admin - Ve todas las empresas)</option>
                        <?php
                        $empresas_query = "SELECT id_empresa, nombre FROM empresas_usuarios WHERE activo = 1 ORDER BY nombre";
                        $empresas_result = $conn->query($empresas_query);
                        if ($empresas_result) {
                            while ($empresa = $empresas_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $empresa['id_empresa']; ?>">
                                <?php echo htmlspecialchars($empresa['nombre']); ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                    <small class="text-xs text-gray-500 mt-1">Dejar vacío para acceso a todas las empresas (solo para administradores)</small>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Editar Usuario</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de Usuario</label>
                    <input type="text" name="nombre_usuario" id="edit_nombre_usuario" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" readonly>
                    <p class="text-xs text-gray-500 mt-1">El nombre de usuario no puede ser modificado</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Correo Electrónico</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nueva Contraseña (dejar vacío para mantener actual)</label>
                    <input type="password" name="password" id="edit_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar Nueva Contraseña</label>
                    <input type="password" name="password_confirm" id="edit_password_confirm" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rol</label>
                    <select name="rol" id="edit_rol" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="toggleEditPermissions()">
                        <option value="administrador">Administrador</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </div>
                <div id="edit_permissions" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Permisos adicionales</label>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="edit_perm_hist_inventario" name="perm_hist_inventario" class="mr-2">
                        <label for="edit_perm_hist_inventario" class="text-sm">Historial Inventario</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_perm_hist_tickets" name="perm_hist_tickets" class="mr-2">
                        <label for="edit_perm_hist_tickets" class="text-sm">Historial Tickets</label>
                    </div>
                    <div class="flex items-center mt-2">
                        <input type="checkbox" id="edit_perm_manage_users" name="perm_manage_users" class="mr-2">
                        <label for="edit_perm_manage_users" class="text-sm">Gestión de Usuarios</label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Empresa</label>
                    <select name="id_empresa" id="edit_id_empresa" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Sin empresa (Admin - Ve todas las empresas)</option>
                        <?php
                        $empresas_query = "SELECT id_empresa, nombre FROM empresas_usuarios WHERE activo = 1 ORDER BY nombre";
                        $empresas_result = $conn->query($empresas_query);
                        if ($empresas_result) {
                            while ($empresa = $empresas_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $empresa['id_empresa']; ?>">
                                <?php echo htmlspecialchars($empresa['nombre']); ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                    <small class="text-xs text-gray-500 mt-1">Dejar vacío para acceso a todas las empresas (solo para administradores)</small>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function openEditModal(usuario) {
            document.getElementById('edit_id_usuario').value = usuario.id_usuario;
            document.getElementById('edit_nombre_usuario').value = usuario.nombre_usuario;
            document.getElementById('edit_email').value = usuario.email;
            document.getElementById('edit_rol').value = usuario.rol;
            document.getElementById('edit_password').value = '';
            if (document.getElementById('edit_id_empresa')) {
                document.getElementById('edit_id_empresa').value = usuario.id_empresa || '';
            }
            const inv = Number(usuario.can_view_inventory_history || 0) === 1;
            const tik = Number(usuario.can_view_ticket_history || 0) === 1;
            const mng = Number(usuario.can_manage_users || 0) === 1;
            const invChk = document.getElementById('edit_perm_hist_inventario');
            const tikChk = document.getElementById('edit_perm_hist_tickets');
            const mngChk = document.getElementById('edit_perm_manage_users');
            if (invChk) invChk.checked = inv;
            if (tikChk) tikChk.checked = tik;
            if (mngChk) mngChk.checked = mng;
            toggleEditPermissions();
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function closeMessage() {
            const messageAlert = document.getElementById('messageAlert');
            if (messageAlert) {
                messageAlert.style.transition = 'opacity 0.5s ease';
                messageAlert.style.opacity = '0';
                setTimeout(function() {
                    messageAlert.remove();
                }, 500);
            }
        }

        function eliminarUsuario(id, nombre) {
            if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${nombre}"?`)) {
                fetch('gestion-usuarios.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        nombre_usuario: nombre
                    }),
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Error al eliminar el usuario');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud. Verifique la consola para más detalles.');
                });
            }
        }


        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Validación de contraseñas
        function validatePasswords(form) {
            const passwordField = form.querySelector('input[name="password"]');
            const confirmPasswordField = form.querySelector('input[name="password_confirm"]');
            
            if (passwordField && confirmPasswordField) {
                if (passwordField.value.length === 0 && confirmPasswordField.value.length === 0) {
                    return true;
                }
                if (passwordField.value.length > 0 && passwordField.value.length < 8) {
                    alert('La contraseña debe tener al menos 8 caracteres.');
                    return false;
                }
                if (passwordField.value !== confirmPasswordField.value) {
                    alert('Las contraseñas no coinciden.');
                    return false;
                }
            }
            return true;
        }

        // Agregar validación a los formularios
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!validatePasswords(form)) {
                        e.preventDefault();
                    }
                });
            });

            // Auto-ocultar mensaje de éxito después de 3 segundos
            const successMessage = document.getElementById('messageAlert');
            if (successMessage && successMessage.classList.contains('alert-success')) {
                setTimeout(function() {
                    closeMessage();
                }, 3000);
            }
            toggleAddPermissions();
        });

        function toggleAddPermissions() {
            const roleSelect = document.getElementById('add_rol');
            const container = document.getElementById('add_permissions');
            if (!roleSelect || !container) return;
            const role = roleSelect.value;
            if (role === 'supervisor') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                // Desactivar todos los permisos cuando el rol no es supervisor
                const inv = document.getElementById('add_perm_hist_inventario');
                const tik = document.getElementById('add_perm_hist_tickets');
                const mng = document.getElementById('add_perm_manage_users');
                if (inv) inv.checked = false;
                if (tik) tik.checked = false;
                if (mng) mng.checked = false;
            }
        }

        function toggleEditPermissions() {
            const roleSelect = document.getElementById('edit_rol');
            const container = document.getElementById('edit_permissions');
            if (!roleSelect || !container) return;
            const role = roleSelect.value;
            if (role === 'supervisor') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                // Desactivar todos los permisos cuando el rol no es supervisor
                const inv = document.getElementById('edit_perm_hist_inventario');
                const tik = document.getElementById('edit_perm_hist_tickets');
                const mng = document.getElementById('edit_perm_manage_users');
                if (inv) inv.checked = false;
                if (tik) tik.checked = false;
                if (mng) mng.checked = false;
            }
        }
    </script>
</body>
</html>
