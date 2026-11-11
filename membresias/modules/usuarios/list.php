<?php
// Define current page for active menu highlighting
$current_page = 'usuarios_list';

include '../../includes/db.php';

// Obtener usuarios con información de roles
try {
    $sql = "SELECT u.*, r.nombre as rol_nombre 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id 
            ORDER BY u.nombre ASC";
    $usuarios = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener usuarios: " . $e->getMessage());
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
    max-width: 1200px;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.table {
    margin: 0;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f3f4;
    color: #495057;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease;
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
    text-decoration: none;
}

.btn i {
    margin-right: 0.5rem;
}

.btn-primary {
    background-color: #5e72e4;
    border-color: #5e72e4;
    color: white;
}

.btn-primary:hover {
    background-color: #4a5bd9;
    border-color: #4a5bd9;
    color: white;
}

.btn-success {
    background-color: #2dce89;
    border-color: #2dce89;
    color: white;
}

.btn-success:hover {
    background-color: #24a46b;
    border-color: #24a46b;
    color: white;
}

.btn-warning {
    background-color: #fb6340;
    border-color: #fb6340;
    color: white;
}

.btn-warning:hover {
    background-color: #f5365c;
    border-color: #f5365c;
    color: white;
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

.badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

.badge-primary {
    background-color: #5e72e4;
    color: white;
}

.badge-success {
    background-color: #2dce89;
    color: white;
}

.badge-warning {
    background-color: #fb6340;
    color: white;
}

.badge-danger {
    background-color: #f5365c;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: #495057;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 1.5rem;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #5e72e4;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 0.875rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
}

.status-active {
    background-color: #2dce89;
}

.status-inactive {
    background-color: #f5365c;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .main-content-boxed {
        padding: 1.5rem 1rem;
        margin: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .btn i {
        margin-right: 0.25rem;
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
                    <h1 class="h2 mb-2">Gestión de Usuarios</h1>
                    <p class="text-muted mb-0">Administra los usuarios del sistema</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="add.php" class="btn btn-primary w-100 w-md-auto">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php if ($_GET['success'] === '1'): ?>
                        <strong>¡Éxito!</strong> Usuario creado correctamente.
                    <?php elseif ($_GET['success'] === 'updated'): ?>
                        <strong>¡Éxito!</strong> Usuario actualizado correctamente.
                    <?php elseif ($_GET['success'] === 'deleted'): ?>
                        <strong>¡Éxito!</strong> Usuario eliminado correctamente.
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php if ($_GET['error'] === 'id_missing'): ?>
                        <strong>Error:</strong> ID no especificado.
                    <?php elseif ($_GET['error'] === 'not_found'): ?>
                        <strong>Error:</strong> Usuario no encontrado.
                    <?php elseif ($_GET['error'] === 'database'): ?>
                        <strong>Error:</strong> Error de base de datos.
                    <?php elseif ($_GET['error'] === 'username_exists'): ?>
                        <strong>Error:</strong> El nombre de usuario ya existe.
                    <?php elseif ($_GET['error'] === 'email_exists'): ?>
                        <strong>Error:</strong> El correo electrónico ya existe.
                    <?php elseif ($_GET['error'] === 'last_admin'): ?>
                        <strong>Error:</strong> No se puede eliminar el último usuario administrador.
                    <?php elseif ($_GET['error'] === 'delete_failed'): ?>
                        <strong>Error:</strong> No se pudo eliminar el usuario.
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Lista de Usuarios del Sistema
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($usuarios)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h5>No hay usuarios registrados</h5>
                            <p>Comienza agregando el primer usuario para tu sistema.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Agregar Primer Usuario
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Correo</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-3">
                                                        <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold"><?= htmlspecialchars($usuario['usuario']) ?></div>
                                                        <small class="text-muted">ID: #<?= $usuario['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="font-weight-bold"><?= htmlspecialchars($usuario['nombre']) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($usuario['correo'])): ?>
                                                    <span class="text-muted"><?= htmlspecialchars($usuario['correo']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">Sin correo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($usuario['rol_nombre'])): ?>
                                                    <span class="badge badge-primary"><?= htmlspecialchars($usuario['rol_nombre']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Sin rol</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-indicator status-active"></span>
                                                <span class="text-success">Activo</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="edit.php?id=<?= $usuario['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?= $usuario['id'] ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       title="Eliminar"
                                                       onclick="return confirm('¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?> 