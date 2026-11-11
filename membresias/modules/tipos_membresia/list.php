<?php
// Define current page for active menu highlighting
$current_page = 'tipos_membresia_list';

include '../../includes/db.php';
include '../../includes/config.php';

// Inicializar configuración ANTES de cualquier otra operación
initConfig();

// Obtener la empresa del usuario logueado
$empresa_id = getCurrentUserEmpresaId();

// Verificar que el usuario tenga una empresa válida
if (!$empresa_id) {
    die("Error: No se pudo determinar la empresa del usuario. Contacte al administrador.");
}

// DEBUG TEMPORAL - Eliminar después de la prueba
if (isset($_GET['debug'])) {
    echo "<h2>DEBUG - List.php</h2>";
    echo "<p><strong>Usuario ID:</strong> " . ($_SESSION['user']['id'] ?? 'No definido') . "</p>";
    echo "<p><strong>Empresa ID en sesión:</strong> " . ($_SESSION['user']['empresa_id'] ?? 'No definido') . "</p>";
    echo "<p><strong>getCurrentUserEmpresaIdOrDefault():</strong> " . $empresa_id . "</p>";
    echo "<hr>";
}

// Obtener tipos de membresía de la empresa del usuario
try {
    $sql = "SELECT * FROM tipos_membresia WHERE empresa_id = ? ORDER BY nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    $tipos = $stmt->fetchAll();
    
    // DEBUG TEMPORAL - Eliminar después de la prueba
    if (isset($_GET['debug'])) {
        echo "<p><strong>SQL ejecutada:</strong> " . $sql . "</p>";
        echo "<p><strong>Total de resultados:</strong> " . count($tipos) . "</p>";
        echo "<hr>";
    }
} catch (PDOException $e) {
    die("Error al obtener tipos de membresía: " . $e->getMessage());
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
    background: linear-gradient(135deg, #5e72e4 0%, #825ee4 100%);
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
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.avatar-sm {
    width: 2rem;
    height: 2rem;
    font-size: 0.75rem;
}

.avatar.bg-primary {
    background-color: #5e72e4 !important;
}

.avatar.bg-success {
    background-color: #2dce89 !important;
}

.avatar.bg-warning {
    background-color: #fb6340 !important;
}

.avatar.bg-info {
    background-color: #11cdef !important;
}

.badge {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.375rem;
}

.badge-success {
    background-color: #2dce89;
    color: white;
}

.badge-warning {
    background-color: #fb6340;
    color: white;
}

.badge-info {
    background-color: #11cdef;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.btn {
    border-radius: 0.375rem;
    padding: 0.5rem 1rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn i {
    margin-right: 0.25rem;
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

.btn-outline-secondary {
    color: #4a5568;
    border-color: #e2e8f0;
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #cbd5e0;
    color: #4a5568;
}

.alert {
    border-radius: 0.5rem;
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: #c6f6d5;
    color: #22543d;
    border-left: 4px solid #38a169;
}

.alert-danger {
    background-color: #fed7d7;
    color: #c53030;
    border-left: 4px solid #e53e3e;
}

.alert-info {
    background-color: #bee3f8;
    color: #2a4365;
    border-left: 4px solid #3182ce;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
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

.mp-plan-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 0.25rem;
}

.mp-plan-badge i {
    margin-right: 0.25rem;
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
                    <h1 class="h2 mb-2">Tipos de Membresía</h1>
                    <p class="text-muted mb-0">Gestiona los diferentes tipos de membresías con suscripciones automáticas en MercadoPago</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="add.php" class="btn btn-primary w-100 w-md-auto">
                        <i class="fas fa-plus me-2"></i>Nuevo Tipo
                    </a>
                </div>
            </div>

            <!--<div class="alert alert-info">
                <i class="fas fa-building me-2"></i>
                <strong>Empresa:</strong> Solo se muestran los tipos de membresía de tu empresa (ID: <?= htmlspecialchars($empresa_id) ?>).
            </div>-->

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php if ($_GET['success'] === '1'): ?>
                        <strong>¡Éxito!</strong> Tipo de membresía creado correctamente.
                        <?php if (isset($_GET['mp_plan_id']) && $_GET['mp_plan_id']): ?>
                            <br><small>Plan de MercadoPago creado con ID: <?= htmlspecialchars($_GET['mp_plan_id']) ?></small>
                        <?php endif; ?>
                    <?php elseif ($_GET['success'] === 'updated'): ?>
                        <strong>¡Éxito!</strong> Tipo de membresía actualizado correctamente.
                        <?php if (isset($_GET['mp_plan_id']) && $_GET['mp_plan_id']): ?>
                            <br><small>Plan de MercadoPago actualizado con ID: <?= htmlspecialchars($_GET['mp_plan_id']) ?></small>
                        <?php endif; ?>
                    <?php elseif ($_GET['success'] === 'deleted'): ?>
                        <strong>¡Éxito!</strong> Tipo de membresía eliminado correctamente.
                        <?php if (isset($_GET['mp_plan_deleted']) && $_GET['mp_plan_deleted']): ?>
                            <br><small>Plan de MercadoPago también fue desactivado.</small>
                        <?php endif; ?>
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
                        <strong>Error:</strong> Tipo de membresía no encontrado.
                    <?php elseif ($_GET['error'] === 'database'): ?>
                        <strong>Error:</strong> Error de base de datos.
                    <?php elseif ($_GET['error'] === 'has_memberships'): ?>
                        <strong>Error:</strong> No se puede eliminar un tipo de membresía que tiene membresías asociadas.
                    <?php elseif ($_GET['error'] === 'delete_failed'): ?>
                        <strong>Error:</strong> No se pudo eliminar el tipo de membresía.
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!--<div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Información:</strong> Los tipos de membresía se sincronizan automáticamente con MercadoPago para crear planes de suscripción recurrentes.
            </div>-->

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>Lista de Tipos de Membresía
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tipos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h5>No hay tipos de membresía</h5>
                            <p>Comienza agregando el primer tipo de membresía para tu sistema.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Agregar Primer Tipo
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Configuración</th>
                                        <th>MercadoPago</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm bg-primary me-3">
                                                        <i class="fas fa-tag"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold"><?= htmlspecialchars($tipo['nombre']) ?></div>
                                                        <small class="text-muted">ID: <?= $tipo['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($tipo['descripcion'])): ?>
                                                    <span class="text-muted"><?= htmlspecialchars($tipo['descripcion']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">Sin descripción</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">
                                                    $<?= number_format($tipo['precio'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <small class="text-muted">
                                                        <strong>Duración:</strong> <?= $tipo['duracion_dias'] ?> días<br>
                                                        <strong>Intervalo:</strong> <?= ucfirst($tipo['interval']) ?> (<?= $tipo['interval_count'] ?>)
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($tipo['mp_plan_id'])): ?>
                                                    <div class="mp-plan-badge">
                                                        <i class="fas fa-link"></i>
                                                        Plan: <?= htmlspecialchars($tipo['mp_plan_id']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-unlink"></i>
                                                        Sin plan
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="edit.php?id=<?= $tipo['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?= $tipo['id'] ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       title="Eliminar"
                                                       onclick="return confirm('¿Estás seguro de que deseas eliminar este tipo de membresía? Esta acción no se puede deshacer.')">
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