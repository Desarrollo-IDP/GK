<?php
// Define current page for active menu highlighting
$current_page = 'tipos_membresia_delete';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../config/mercadopago_config.php';

// Inicializar configuración y sesión
initConfig();

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: list.php?error=id_missing');
    exit;
}

// Verificar si el tipo de membresía existe y obtener sus datos
try {
    // Obtener la empresa del usuario logueado
    $empresa_id = getCurrentUserEmpresaId();
    
    // Verificar que el usuario tenga una empresa válida
    if (!$empresa_id) {
        header('Location: list.php?error=no_empresa');
        exit;
    }
    
    $sql = "SELECT * FROM tipos_membresia WHERE id = ? AND empresa_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $empresa_id]);
    $tipo = $stmt->fetch();
    
    if (!$tipo) {
        header('Location: list.php?error=not_found');
        exit;
    }
    
    // Verificar si hay membresías asociadas a este tipo
    $sqlMembresias = "SELECT COUNT(*) as total FROM membresias WHERE tipo_membresia_id = ?";
    $stmtMembresias = $pdo->prepare($sqlMembresias);
    $stmtMembresias->execute([$id]);
    $membresiasAsociadas = $stmtMembresias->fetch()['total'];
    
} catch (PDOException $e) {
    header('Location: list.php?error=database');
    exit;
}

// Procesar la eliminación si se confirma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        // Si hay membresías asociadas, no permitir eliminar
        if ($membresiasAsociadas > 0) {
            header('Location: list.php?error=has_memberships');
            exit;
        }
        
        // Verificar si existe plan en MercadoPago y eliminarlo
        $mp_plan_deleted = false;
        if (!empty($tipo['mp_plan_id'])) {
            try {
                // Intentar eliminar el plan en MercadoPago
                $mpRes = mpDeletePlan($tipo['mp_plan_id']);
                if ($mpRes['success']) {
                    $mp_plan_deleted = true;
                } else {
                    // Log del error pero continuar con la eliminación local
                    error_log('Error al eliminar plan MP: ' . ($mpRes['error'] ?? 'Error desconocido'));
                }
            } catch (Exception $e) {
                error_log('Excepción al eliminar plan MP: ' . $e->getMessage());
            }
        }
        
        // Eliminar el tipo de membresía de la base de datos
        $sql = "DELETE FROM tipos_membresia WHERE id = ? AND empresa_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $empresa_id]);
        
        // Redirigir con mensaje apropiado
        $redirect_params = 'success=deleted';
        if ($mp_plan_deleted) {
            $redirect_params .= '&mp_plan_deleted=1';
        }
        
        header('Location: list.php?' . $redirect_params);
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

.alert {
    border-radius: 0.5rem;
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}

.alert-warning {
    background-color: #fef5e7;
    color: #744210;
    border-left: 4px solid #ed8936;
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

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #4a5568;
}

.info-value {
    color: #2d3748;
}

.mp-plan-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.mp-plan-info i {
    margin-right: 0.5rem;
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
                    <h1 class="h2 mb-2">Eliminar Tipo de Membresía</h1>
                    <p class="text-muted mb-0">Confirma la eliminación del tipo de membresía</p>
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
                    <?php if ($membresiasAsociadas > 0): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-ban me-2"></i>
                            <strong>No se puede eliminar:</strong> Este tipo de membresía tiene <?= $membresiasAsociadas ?> membresía(s) asociada(s). 
                            Debes eliminar todas las membresías asociadas antes de poder eliminar este tipo.
                        </div>
                        
                        <div class="text-center">
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡Atención!</strong> Estás a punto de eliminar permanentemente el tipo de membresía "<strong><?= htmlspecialchars($tipo['nombre']) ?></strong>". 
                            Esta acción no se puede deshacer.
                        </div>

                        <?php if (!empty($tipo['mp_plan_id'])): ?>
                        <div class="mp-plan-info">
                            <i class="fas fa-link"></i>
                            <strong>Plan de MercadoPago:</strong> También se eliminará el plan de suscripción asociado (ID: <?= htmlspecialchars($tipo['mp_plan_id']) ?>)
                        </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h6 class="mb-3">Información del tipo de membresía:</h6>
                            <div class="info-item">
                                <span class="info-label">Nombre:</span>
                                <span class="info-value"><?= htmlspecialchars($tipo['nombre']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Precio:</span>
                                <span class="info-value">$<?= number_format($tipo['precio'], 2) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Duración:</span>
                                <span class="info-value"><?= $tipo['duracion_dias'] ?> días</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Intervalo de facturación:</span>
                                <span class="info-value"><?= ucfirst($tipo['interval']) ?> (<?= $tipo['interval_count'] ?>)</span>
                            </div>
                            <?php if (!empty($tipo['descripcion'])): ?>
                            <div class="info-item">
                                <span class="info-label">Descripción:</span>
                                <span class="info-value"><?= htmlspecialchars($tipo['descripcion']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <form method="post" class="text-center">
                            <div class="d-flex justify-content-center gap-3">
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