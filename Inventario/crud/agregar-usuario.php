<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../log_activity.php';
checkAdmin();
function validatePassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $email = $_POST['email'] ?? ''; // Added email field
    
    if (!empty($nombre_usuario) && !empty($password) && !empty($rol)) {
        if ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden. Por favor, inténtelo de nuevo.";
        } elseif (!validatePassword($password)) {
            $error = "La contraseña debe tener al menos 8 caracteres.";
        } else {
            $check_stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
            $check_stmt->bind_param("s", $nombre_usuario);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "El nombre de usuario ya existe. Por favor, elija otro.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nombre_usuario, $hashed_password, $rol, $email);
                
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    logActivity(
                        "Creación de usuario",
                        "usuarios",
                        $new_user_id,
                        "Se creó el usuario: " . $nombre_usuario . " (ID: " . $new_user_id . ")"
                    );
                    header('Location: gestion-usuarios.php');
                    exit;
                } else {
                    $error = "Error al crear el usuario: " . $conn->error;
                }
            } 
        }
    } else {
        $error = "Todos los campos son obligatorios.";
    }
}
$pageTitle = 'Agregar Usuario';
require_once 'includes/header.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4" style="text-align: center;">Agregar Usuario</h1>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="max-w-lg mx-auto bg-white p-6 rounded shadow-md">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Nombre de Usuario</label>
            <input type="text" name="nombre_usuario" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Correo Electrónico</label>
            <input type="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
            <input type="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Confirmar Contraseña</label>
            <input type="password" name="confirm_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Rol</label>
            <select name="rol" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <option value="visualizador">Visualizador</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Agregar Usuario
            </button>
            <a href="gestion-usuarios.php" class="text-gray-600 hover:text-gray-800">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    
    form.addEventListener('submit', function(e) {
        let hasError = false;
        if (passwordField.value.length < 8) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 8 caracteres.');
            hasError = true;
        }
        if (passwordField.value !== confirmPasswordField.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden.');
            hasError = true;
        }
    });
});
</script>