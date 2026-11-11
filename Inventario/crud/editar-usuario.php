<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../log_activity.php'; 
checkAdmin();
function validatePassword($password) {
    if (!empty($password) && strlen($password) < 8) {
        return false;
    }
    return true;
}
$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    header('Location: gestion-usuarios.php');
    exit;
}
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: gestion-usuarios.php');
    exit;
}

$usuario = $result->fetch_assoc();
$isAdminUser = strtolower($usuario['nombre_usuario']) === 'admin';
$canUpdatePassword = canUpdatePassword();
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $email = $_POST['email'] ?? ''; // Added email field
    
    if (!empty($password) && $password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden. Por favor, inténtelo de nuevo.";
    } elseif (!empty($password) && !validatePassword($password)) {
        $error_message = "La contraseña debe tener al menos 8 caracteres.";
    } else {
        if (!$canUpdatePassword && !empty($password)) {
            $error_message = "Solo el usuario admin puede actualizar contraseñas.";
        } else {
            if ($isAdminUser) {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
                    $update_stmt->bind_param("si", $hashed_password, $id);
                    
                    if ($update_stmt->execute()) {
                        logActivity(
                            "Actualización de contraseña",
                            "usuarios",
                            $id,
                            "Se actualizó la contraseña del usuario admin"
                        );header('Location: gestion-usuarios.php');
                        exit;
                    } else {
                        $error_message = "Error al actualizar la contraseña: " . $conn->error;
                    }
                } else {
                    $error_message = "No se proporcionó una nueva contraseña";
                }
            } else {
                $updates = [];
                $types = "";
                $params = [];
                
                if (!empty($nombre_usuario)) {
                    $check_stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ? AND id_usuario != ?");
                    $check_stmt->bind_param("si", $nombre_usuario, $id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $error_message = "El nombre de usuario ya existe. Por favor, elija otro.";
                    } else {
                        $updates[] = "nombre_usuario = ?";
                        $types .= "s";
                        $params[] = $nombre_usuario;
                    }
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
                if (!empty($password) && $canUpdatePassword) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $updates[] = "contraseña = ?";
                    $types .= "s";
                    $params[] = $hashed_password;
                }
                
                if (!empty($updates) && empty($error_message)) {
                    $query = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id_usuario = ?";
                    $types .= "i";
                    $params[] = $id;
                    
                    $update_stmt = $conn->prepare($query);
                    $update_stmt->bind_param($types, ...$params);
                    
                    if ($update_stmt->execute()) {
                        logActivity(
                            "Actualización de usuario",
                            "usuarios",
                            $id,
                            "Se actualizó el usuario: " . $nombre_usuario
                        );
                        header('Location: gestion-usuarios.php');
                        exit;
                    } else {
                        $error_message = "Error al actualizar el usuario: " . $conn->error;
                    }
                }
            }
        }
    }
}

$pageTitle = 'Editar Usuario';
require_once 'includes/header.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Editar Usuario</h1>
    
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    <form method="post" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="nombre_usuario">
                Nombre de Usuario
            </label>
            <input 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="nombre_usuario" 
                type="text" 
                name="nombre_usuario" 
                value="<?php echo htmlspecialchars($usuario['nombre_usuario'] ?? ''); ?>"
                <?php echo $isAdminUser ? 'readonly' : ''; ?>
            >
            <?php if ($isAdminUser): ?>
            
            <?php endif; ?>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                Correo Electrónico
            </label>
            <input 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="email" 
                type="email" 
                name="email" 
                value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>"
            >
        </div>
        
        <?php if ($canUpdatePassword): ?>
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                Nueva Contraseña (dejar en blanco para mantener la actual)
            </label>
            <input 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="password" 
                type="password" 
                name="password" 
                placeholder="Nueva contraseña"
            >
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                Confirmar Nueva Contraseña
            </label>
            <input 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="confirm_password" 
                type="password" 
                name="confirm_password" 
                placeholder="Confirmar nueva contraseña"
            >
            
        </div>
        <?php else: ?>
        <div class="mb-4">
            
        </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="rol">
                Rol
            </label>
            <select 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="rol" 
                name="rol"
                <?php echo $isAdminUser ? 'disabled' : ''; ?>
            >
                <option value="administrador" <?php echo ($usuario['rol'] === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                <option value="visualizador" <?php echo ($usuario['rol'] === 'visualizador') ? 'selected' : ''; ?>>Visualizador</option>
            </select>
            <?php if ($isAdminUser): ?>
            
            <input type="hidden" name="rol" value="<?php echo htmlspecialchars($usuario['rol']); ?>">
            <?php endif; ?>
        </div>
        
        <div class="flex items-center justify-between">
            <button class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                Guardar Cambios
            </button>
            <a class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800" href="gestion-usuarios.php">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    function showErrorMessage(message) {
        const existingError = document.querySelector('.bg-red-100');
        if (existingError) {
            existingError.remove();
        }
        const errorDiv = document.createElement('div');
        errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
        errorDiv.textContent = message;
        const formElement = document.querySelector('form');
        formElement.parentNode.insertBefore(errorDiv, formElement);
        window.scrollTo(0, 0);
    }
    
    form.addEventListener('submit', function(e) {
        let hasError = false;
        if (passwordField.value.length > 0 && passwordField.value.length < 8) {
            e.preventDefault();
            showErrorMessage('La contraseña debe tener al menos 8 caracteres.');
            hasError = true;
        }
        if (passwordField.value !== confirmPasswordField.value && passwordField.value.length > 0) {
            e.preventDefault();
            showErrorMessage('Las contraseñas no coinciden.');
            hasError = true;
        }
    });
});
</script>