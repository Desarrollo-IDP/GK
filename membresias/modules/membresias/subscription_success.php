<?php
// Define current page for active menu highlighting
$current_page = 'membresias_subscription_success';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../config/mercadopago_config.php';

// Inicializar configuración
initConfig();

// Verificar si el usuario está autenticado
if (!isAuthenticated()) {
    header('Location: ../../index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = '';
$membresia = null;

// Obtener parámetros de la URL
$preapproval_id = $_GET['preapproval_id'] ?? null;
$status = $_GET['status'] ?? null;
$external_reference = $_GET['external_reference'] ?? null;

if ($preapproval_id) {
    try {
        // Obtener información de la suscripción desde MercadoPago
        mpInit();
        $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
        $preapproval = $client->get($preapproval_id);
        
        if ($preapproval) {
            $status = $preapproval->status ?? $status;
            $external_reference = $preapproval->external_reference ?? $external_reference;
            
            // Buscar la membresía por external_reference
            if ($external_reference && preg_match('/^MEM_(\d+)$/', $external_reference, $m)) {
                $membresia_id = (int)$m[1];
                
                $stmt = $pdo->prepare("
                    SELECT m.*, c.nombre, c.apellido_paterno, c.apellido_materno, c.correo, c.telefono,
                           tm.nombre as tipo_nombre, tm.precio, tm.duracion_dias,
                           e.nombre as empresa_nombre
                    FROM membresias m
                    LEFT JOIN clientes c ON m.cliente_id = c.id
                    LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                    LEFT JOIN empresas e ON m.empresa_id = e.id
                    WHERE m.id = ? AND m.empresa_id = ?
                ");
                
                $empresa_id = getCurrentUserEmpresaIdOrDefault();
                $stmt->execute([$membresia_id, $empresa_id]);
                $membresia = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($membresia) {
                    // Actualizar el estado de la membresía
                    $cols = $pdo->query("SHOW COLUMNS FROM membresias")->fetchAll(PDO::FETCH_COLUMN);
                    $hasStatus = in_array('mp_status', $cols);
                    $hasNextPayment = in_array('mp_next_payment_date', $cols);
                    
                    if ($hasStatus || $hasNextPayment) {
                        $updateParts = [];
                        $updateParams = [];
                        
                        if ($hasStatus) {
                            $updateParts[] = 'mp_status = ?';
                            $updateParams[] = $status;
                        }
                        
                        if ($hasNextPayment && isset($preapproval->next_payment_date)) {
                            $updateParts[] = 'mp_next_payment_date = ?';
                            $updateParams[] = $preapproval->next_payment_date;
                        }
                        
                        if ($updateParts) {
                            $updateParams[] = $membresia_id;
                            $updateSql = 'UPDATE membresias SET ' . implode(', ', $updateParts) . ' WHERE id = ?';
                            $updateStmt = $pdo->prepare($updateSql);
                            $updateStmt->execute($updateParams);
                        }
                    }
                    
                    // Determinar el mensaje según el estado
                    switch ($status) {
                        case 'authorized':
                            $mensaje = '¡Felicidades! Tu suscripción ha sido activada exitosamente.';
                            $tipoMensaje = 'success';
                            break;
                        case 'pending':
                            $mensaje = 'Tu suscripción está pendiente de confirmación. Te notificaremos cuando se active.';
                            $tipoMensaje = 'warning';
                            break;
                        case 'cancelled':
                            $mensaje = 'Tu suscripción ha sido cancelada.';
                            $tipoMensaje = 'danger';
                            break;
                        default:
                            $mensaje = 'Estado de suscripción: ' . $status;
                            $tipoMensaje = 'info';
                    }
                } else {
                    $mensaje = 'No se encontró la membresía asociada a esta suscripción.';
                    $tipoMensaje = 'danger';
                }
            } else {
                $mensaje = 'Referencia de membresía no válida.';
                $tipoMensaje = 'danger';
            }
        } else {
            $mensaje = 'No se pudo obtener información de la suscripción desde MercadoPago.';
            $tipoMensaje = 'danger';
        }
    } catch (Exception $e) {
        error_log('Error en subscription_success.php: ' . $e->getMessage());
        $mensaje = 'Error al procesar la confirmación de suscripción.';
        $tipoMensaje = 'danger';
    }
} else {
    $mensaje = 'No se proporcionó información de suscripción.';
    $tipoMensaje = 'warning';
}

// Incluir header
include '../../includes/header.php';
?>

<style>
.subscription-success {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.success-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1);
    overflow: hidden;
    max-width: 800px;
    margin: 0 auto;
}

.success-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.success-header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
}

.success-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
}

.success-body {
    padding: 2rem;
}

.membership-details {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #495057;
}

.detail-value {
    color: #6c757d;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-authorized {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #007bff;
    color: white;
    border: 1px solid #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
    color: white;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
}
</style>

<div class="subscription-success">
    <div class="container">
        <div class="success-card">
            <div class="success-header">
                <h1>
                    <i class="fas fa-check-circle me-2"></i>
                    Confirmación de Suscripción
                </h1>
                <p>Proceso de suscripción completado</p>
            </div>
            
            <div class="success-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($membresia): ?>
                    <div class="membership-details">
                        <h4 class="mb-3">
                            <i class="fas fa-id-card me-2"></i>
                            Detalles de la Membresía
                        </h4>
                        
                        <div class="detail-row">
                            <span class="detail-label">Cliente:</span>
                            <span class="detail-value">
                                <?= htmlspecialchars($membresia['nombre'] . ' ' . $membresia['apellido_paterno'] . ' ' . $membresia['apellido_materno']) ?>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?= htmlspecialchars($membresia['correo']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Teléfono:</span>
                            <span class="detail-value"><?= htmlspecialchars($membresia['telefono']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Tipo de Membresía:</span>
                            <span class="detail-value"><?= htmlspecialchars($membresia['tipo_nombre']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Precio:</span>
                            <span class="detail-value">$<?= number_format($membresia['precio'], 2) ?> MXN</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Duración:</span>
                            <span class="detail-value"><?= $membresia['duracion_dias'] ?> días</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Empresa:</span>
                            <span class="detail-value"><?= htmlspecialchars($membresia['empresa_nombre']) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Estado de Suscripción:</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?= $status ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Fecha de Inicio:</span>
                            <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['fecha_inicio'])) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Fecha de Fin:</span>
                            <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['fecha_fin'])) ?></span>
                        </div>
                        
                        <?php if (isset($membresia['mp_next_payment_date']) && $membresia['mp_next_payment_date']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Próximo Pago:</span>
                                <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['mp_next_payment_date'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="cards.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>
                        Ver Todas las Membresías
                    </a>
                    <a href="add.php" class="btn btn-outline-secondary">
                        <i class="fas fa-plus me-2"></i>
                        Nueva Membresía
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
