<?php
// Define current page for active menu highlighting
$current_page = 'usuarios_add';

include '../../includes/db.php';
include '../../includes/config.php';

// Obtener roles disponibles
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY nombre ASC")->fetchAll();
    $empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC")->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener roles o empresas: " . $e->getMessage());
}

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $rol_id = $_POST['rol_id'] ?? '';
    
    // Validaciones
    if ($nombre === '') $errores[] = 'El nombre completo es obligatorio.';
    if ($usuario === '') $errores[] = 'El nombre de usuario es obligatorio.';
    if ($password === '') $errores[] = 'La contraseña es obligatoria.';
    if ($password !== $password_confirm) $errores[] = 'Las contraseñas no coinciden.';
    if (strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    
    // Validar que el usuario no exista
    if ($usuario !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'El nombre de usuario ya existe.';
        }
    }
    
    // Validar que el correo no exista (si se proporciona)
    if ($correo !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'El correo electrónico ya existe.';
        }
    }
    
    if (empty($errores)) {
        try {
            // Encriptar contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $empresa_id = $_POST['empresa_id'] ?? '';
            $sql = "INSERT INTO usuarios (nombre, usuario, password, correo, rol_id, empresa_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $usuario, $password_hash, $correo, $rol_id ?: null, $empresa_id ?: null]);
            
            // Redirigir al listado con mensaje de éxito
            header('Location: list.php?success=1');
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>

<style>
.body-bg {
    background: #f8f9fc;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
    padding: 1rem 0;
}

.main-content-boxed {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05);
    margin-top: 1rem;
    margin-bottom: 2rem;
}

.form-section {
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.04);
    padding: 1.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.form-section h5 {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
}

.form-section h5 i {
    margin-right: 0.5rem;
    color: #5e72e4;
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-control, .form-select {
    border-radius: 0.5rem;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    font-size: 0.9375rem;
}

.form-control:focus, .form-select:focus {
    border-color: #5e72e4;
    box-shadow: 0 0 0 0.25rem rgba(94, 114, 228, 0.15);
}

.form-control::placeholder {
    color: #a0aec0;
}

.btn {
    border-radius: 0.5rem;
    padding: 0.6rem 1.25rem;
    font-weight: 500;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn i {
    margin-right: 0.5rem;
}

.btn-primary {
    background-color: #5e72e4;
    border-color: #5e72e4;
}

.btn-primary:hover {
    background-color: #4a5bd9;
    border-color: #4a5bd9;
}

.btn-outline-secondary {
    color: #4a5568;
    border-color: #e2e8f0;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #cbd5e0;
}

.alert {
    border-radius: 0.5rem;
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}

.alert-danger {
    background-color: #fed7d7;
    color: #c53030;
    border-left: 4px solid #e53e3e;
}

.alert-success {
    background-color: #c6f6d5;
    color: #22543d;
    border-left: 4px solid #38a169;
}

.alert ul {
    margin-bottom: 0;
    padding-left: 1.25rem;
}

.alert li {
    margin-bottom: 0.25rem;
}

.alert li:last-child {
    margin-bottom: 0;
}

.password-strength {
    margin-top: 0.5rem;
    font-size: 0.875rem;
}

.password-strength.weak {
    color: #e53e3e;
}

.password-strength.medium {
    color: #d69e2e;
}

.password-strength.strong {
    color: #38a169;
}

.password-requirements {
    background-color: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1rem;
}

.password-requirements h6 {
    color: #4a5568;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.password-requirements ul {
    margin-bottom: 0;
    padding-left: 1.25rem;
    font-size: 0.8125rem;
    color: #718096;
}

.password-requirements li {
    margin-bottom: 0.25rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .main-content-boxed {
        padding: 1.5rem 1rem;
        margin: 0.5rem;
    }
    
    .form-section {
        padding: 1.25rem;
    }
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Agregar Nuevo Usuario</h1>
                    <p class="text-muted mb-0">Crea una nueva cuenta de usuario para el sistema</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="list.php" class="btn btn-outline-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> Por favor corrija los siguientes errores:
                <ul class="mb-0 mt-2">
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>Información Personal</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nombre" 
                                       name="nombre" 
                                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                       placeholder="Ej: Juan Pérez García"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor ingresa el nombre completo del usuario.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="usuario" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="usuario" 
                                       name="usuario" 
                                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                                       placeholder="Ej: juan.perez"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor ingresa un nombre de usuario único.
                                </div>
                                <div class="form-text">
                                    El nombre de usuario debe ser único y no puede contener espacios.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="correo" class="form-label">Correo Electrónico</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="correo" 
                                       name="correo" 
                                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                                       placeholder="Ej: juan.perez@ejemplo.com">
                                <div class="invalid-feedback">
                                    Por favor ingresa un correo electrónico válido.
                                </div>
                                <div class="form-text">
                                    Opcional: Para notificaciones y recuperación de contraseña.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rol_id" class="form-label">Rol de Usuario</label>
                                <select class="form-select" id="rol_id" name="rol_id">
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= $rol['id'] ?>" <?= ($_POST['rol_id'] ?? '') == $rol['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rol['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Opcional: Asigna permisos específicos al usuario.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-building me-2"></i>Cede o Sucursal</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="empresa_id" class="form-label">Selecciona la cede o sucursal <span class="text-danger">*</span></label>
                                <select class="form-select" id="empresa_id" name="empresa_id" required>
                                    <option value="">Seleccione una cede o sucursal</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= $empresa['id'] ?>" <?= (isset($_POST['empresa_id']) && $_POST['empresa_id'] == $empresa['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($empresa['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor selecciona una cede o sucursal.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-lock me-2"></i>Seguridad</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Ingresa una contraseña segura"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor ingresa una contraseña válida.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirm" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       placeholder="Repite la contraseña"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor confirma la contraseña.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <h6><i class="fas fa-shield-alt me-2"></i>Requisitos de Contraseña</h6>
                        <ul>
                            <li>Mínimo 6 caracteres</li>
                            <li>Se recomienda incluir mayúsculas, minúsculas y números</li>
                            <li>Evita usar información personal como contraseña</li>
                        </ul>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="list.php" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-lg btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Password confirmation validation
document.getElementById('password_confirm').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    
    if (password !== confirm) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('password').addEventListener('input', function() {
    const confirm = document.getElementById('password_confirm');
    if (confirm.value) {
        if (this.value !== confirm.value) {
            confirm.setCustomValidity('Las contraseñas no coinciden');
        } else {
            confirm.setCustomValidity('');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 