<?php
// Define current page for active menu highlighting
$current_page = 'usuarios_delete';

include '../../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: list.php?error=id_missing');
    exit;
}

// Verificar si el usuario existe y obtener sus datos
try {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id 
            WHERE u.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: list.php?error=not_found');
        exit;
    }
    
    // Verificar si es el último usuario administrador
    $sqlAdmin = "SELECT COUNT(*) as total FROM usuarios u 
                  LEFT JOIN roles r ON u.rol_id = r.id 
                  WHERE r.nombre = 'Administrador' OR r.nombre = 'Admin'";
    $stmtAdmin = $pdo->prepare($sqlAdmin);
    $stmtAdmin->execute();
    $totalAdmins = $stmtAdmin->fetch()['total'];
    
    // Verificar si el usuario actual es administrador
    $esAdmin = false;
    if (!empty($usuario['rol_nombre']) && 
        (strtolower($usuario['rol_nombre']) === 'administrador' || 
         strtolower($usuario['rol_nombre']) === 'admin')) {
        $esAdmin = true;
    }
    
} catch (PDOException $e) {
    header('Location: list.php?error=database');
    exit;
}

// Procesar la eliminación si se confirma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        // Prevenir eliminación del último administrador
        if ($esAdmin && $totalAdmins <= 1) {
            header('Location: list.php?error=last_admin');
            exit;
        }
        
        // Eliminar el usuario
        $sql = "DELETE FROM usuarios WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        header('Location: list.php?success=deleted');
        exit;
        
    } catch (PDOException $e) {
        header('Location: list.php?error=delete_failed');
        exit;
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
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05);
    margin-top: 1rem;
    margin-bottom: 2rem;
}

.card {
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.04);
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #f5365c 0%, #ec0c38 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.card-body {
    padding: 2rem;
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

.btn-danger {
    background-color: #f5365c;
    border-color: #f5365c;
    color: white;
}

.btn-danger:hover {
    background-color: #ec0c38;
    border-color: #ec0c38;
    color: white;
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

.alert-warning {
    background-color: #fef5e7;
    color: #c05621;
    border-left: 4px solid #ed8936;
}

.alert-danger {
    background-color: #fed7d7;
    color: #c53030;
    border-left: 4px solid #e53e3e;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
    color: #4a5568;
}

.info-value {
    color: #2d3748;
    font-weight: 600;
}

.warning-box {
    background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
    border: 1px solid #ed8936;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem 0;
}

.warning-box i {
    color: #c05621;
    margin-right: 0.5rem;
}

.danger-box {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    border: 1px solid #e53e3e;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem 0;
}

.danger-box i {
    color: #c53030;
    margin-right: 0.5rem;
}

.avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background-color: #5e72e4;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
    margin: 0 auto 1rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .main-content-boxed {
        padding: 1.5rem 1rem;
        margin: 0.5rem;
    }
    
    .card-body {
        padding: 1.5rem;
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
                    <h1 class="h2 mb-2">Eliminar Usuario</h1>
                    <p class="text-muted mb-0">Confirma la eliminación del usuario</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="list.php" class="btn btn-outline-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($esAdmin && $totalAdmins <= 1): ?>
                        <div class="danger-box">
                            <i class="fas fa-ban"></i>
                            <strong>No se puede eliminar este usuario</strong>
                            <p class="mb-0 mt-2">
                                Este es el último usuario administrador del sistema. 
                                Debes crear otro usuario administrador antes de poder eliminar este.
                            </p>
                        </div>
                        
                        <div class="text-center mb-4">
                            <div class="avatar">
                                <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                            </div>
                            <h5><?= htmlspecialchars($usuario['nombre']) ?></h5>
                            <p class="text-muted">@<?= htmlspecialchars($usuario['usuario']) ?></p>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">ID del usuario:</span>
                            <span class="info-value">#<?= $usuario['id'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Rol:</span>
                            <span class="info-value text-danger"><?= htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Correo:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['correo'] ?? 'Sin correo') ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>¿Estás seguro de que deseas eliminar este usuario?</strong>
                            <p class="mb-0 mt-2">
                                Esta acción no se puede deshacer. Se eliminará permanentemente el usuario y toda su información.
                            </p>
                        </div>
                        
                        <div class="text-center mb-4">
                            <div class="avatar">
                                <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                            </div>
                            <h5><?= htmlspecialchars($usuario['nombre']) ?></h5>
                            <p class="text-muted">@<?= htmlspecialchars($usuario['usuario']) ?></p>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">ID del usuario:</span>
                            <span class="info-value">#<?= $usuario['id'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre de usuario:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['usuario']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Rol:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Correo:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['correo'] ?? 'Sin correo') ?></span>
                        </div>
                        
                        <form method="post" class="mt-4">
                            <div class="d-flex justify-content-end gap-3">
                                <a href="list.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" name="confirmar" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Eliminar Definitivamente
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?> 