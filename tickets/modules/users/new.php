<?php
include '../../includes/common.php';
include '../../conexion/db_remot.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $message = "Todos los campos son obligatorios";
        $messageType = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Las contraseñas no coinciden";
        $messageType = "danger";
    } else {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $checkUser = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        $checkUser->store_result();
        
        if ($checkUser->num_rows > 0) {
            $message = "El nombre de usuario ya existe";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password_hashed, $role, $email);
            if ($stmt->execute()) {
                $message = "Usuario creado correctamente";
                $messageType = "success";
                header("Refresh: 2; url=usuarios.php");
            } else {
                $message = "Error al crear el usuario: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        $checkUser->close();
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 600px;
    margin: 0 auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
    margin-top: 2rem;
    margin-bottom: 2rem;
}
@media (max-width: 991.98px) {
    .main-content-boxed {
        padding: 1rem 0.5rem;
        border-radius: 0.75rem;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }
}
@media (max-width: 767.98px) {
    .main-content-boxed {
        padding: 0.5rem 0.2rem;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-6 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Nuevo Usuario</h1>
                    <p class="text-muted mb-0">Completa el formulario para registrar un nuevo usuario</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="users.php" class="btn btn-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <form method="POST" id="userForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Rol</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="administrador">Administrador</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </main>
    </div>
</div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('userForm').addEventListener('submit', function(e) {
        var password = document.getElementById('password').value;
        var confirm = document.getElementById('confirm_password').value;
        if (password !== confirm) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
            document.getElementById('confirm_password').focus();
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>