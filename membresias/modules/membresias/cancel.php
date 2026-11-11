<?php
// Define current page for active menu highlighting
$current_page = 'membresias_cancel';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../config/mercadopago_config.php';

// Inicializar configuración y sesión
initConfig();

// Verificar autenticación
if (!isAuthenticated()) {
    header('Location: ../../login.php');
    exit;
}

$errores = [];
$mensaje = '';
$tipoMensaje = '';
$membresia = null;

// Obtener ID de la membresía
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php');
    exit;
}

try {
    // Obtener datos de la membresía con información del cliente y tipo
    $sql = "SELECT m.*, 
                   CONCAT(c.nombre, ' ', c.apellido_paterno, ' ', COALESCE(c.apellido_materno, '')) as nombre_completo,
                   c.telefono, c.correo, c.direccion,
                   tm.nombre as tipo_nombre, tm.precio, tm.monto_cancelacion,
                   e.nombre as empresa_nombre
            FROM membresias m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
            LEFT JOIN empresas e ON tm.empresa_id = e.id
            WHERE m.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $membresia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membresia) {
        header('Location: list.php');
        exit;
    }

    // Verificar que la membresía esté activa
    if ($membresia['mp_status'] !== 'authorized') {
        $errores[] = 'Solo se pueden cancelar suscripciones activas.';
    }

} catch (PDOException $e) {
    $errores[] = "Error al obtener los datos de la membresía: " . $e->getMessage();
}

// Procesar cancelación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_cancelacion'])) {
    try {
        // Verificar que la membresía tenga un preapproval_id
        if (!$membresia['mp_preapproval_id']) {
            $errores[] = 'Esta membresía no tiene una suscripción activa en MercadoPago.';
        } else {
            // Aplicar cargo por cancelación si está configurado
            $cancellationFee = $membresia['monto_cancelacion'] ?? 0;
            
            error_log('Cancelación manual - Membresía ID: ' . $id . ', PreApproval ID: ' . $membresia['mp_preapproval_id'] . ', Cargo: $' . $cancellationFee);
            
            // Cancelar suscripción en MercadoPago
            $result = mpCancelSubscription($membresia['mp_preapproval_id'], $cancellationFee, $membresia['correo']);
            
            error_log('Cancelación manual - Resultado: ' . json_encode($result));
            
            if ($result['success']) {
                // Actualizar estado en la base de datos
                $updateSql = "UPDATE membresias SET 
                             mp_status = 'cancelled',
                             mp_cancellation_fee = ?,
                             mp_cancelled_at = NOW(),
                             mp_cancellation_reason = 'Cancelación manual desde panel administrativo'
                             WHERE id = ?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([$cancellationFee, $id]);
                
                // Intentar cobro automático usando el token de tarjeta guardado
                if ($cancellationFee > 0) {
                    try {
                        error_log('Cancelación manual - Intentando cobro automático de $' . $cancellationFee);
                        
                        $paymentResult = mpCreateCancellationPaymentFromClient(
                            $membresia['cliente_id'], 
                            $cancellationFee, 
                            'CANCEL_FEE_MEM_' . $id
                        );
                        
                        if ($paymentResult['success']) {
                            error_log('Cancelación manual - Pago por cancelación creado: ' . json_encode([ 
                                'id' => $paymentResult['payment_id'], 
                                'status' => $paymentResult['status'] 
                            ]));
                            
                            // Registrar el pago en la tabla de logs
                            try {
                                $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                                    (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $logStmt->execute([
                                    $id,
                                    $paymentResult['payment_id'],
                                    $membresia['mp_preapproval_id'],
                                    $cancellationFee,
                                    $paymentResult['status'],
                                    'cancellation_fee',
                                    date('Y-m-d H:i:s'),
                                    json_encode($paymentResult)
                                ]);
                                error_log('Cancelación manual - Pago por cancelación registrado en logs');
                            } catch (Exception $logError) {
                                error_log('Cancelación manual - Error registrando pago en logs: ' . $logError->getMessage());
                            }
                        } else {
                            error_log('Cancelación manual - Error al cobrar cargo por cancelación: ' . $paymentResult['error']);
                            
                            // Actualizar la membresía con información del error de cobro
                            $errorUpdateSql = "UPDATE membresias SET 
                                             mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Error en cobro: ', ?)
                                             WHERE id = ?";
                            $errorStmt = $pdo->prepare($errorUpdateSql);
                            $errorStmt->execute([$paymentResult['error'], $id]);
                        }
                    } catch (Throwable $pe) {
                        error_log('Cancelación manual - Error al cobrar cargo por cancelación: ' . $pe->getMessage());
                        
                        // Actualizar la membresía con información del error
                        $errorUpdateSql = "UPDATE membresias SET 
                                         mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Excepción en cobro: ', ?)
                                         WHERE id = ?";
                        $errorStmt = $pdo->prepare($errorUpdateSql);
                        $errorStmt->execute([$pe->getMessage(), $id]);
                    }
                }

                // Enviar email de cancelación
                try {
                    $clienteData = [
                        'nombre' => $membresia['nombre'] ?? '',
                        'apellido_paterno' => $membresia['apellido_paterno'] ?? '',
                        'correo' => $membresia['correo']
                    ];

                    $tipoData = [
                        'nombre' => $membresia['tipo_nombre'],
                        'precio' => $membresia['precio']
                    ];

                    $empresaData = [
                        'nombre' => $membresia['empresa_nombre'] ?: 'Nuestra Empresa'
                    ];

                    $emailResult = sendCancellationEmail($clienteData, $tipoData, $empresaData, $cancellationFee);
                    
                    if ($emailResult) {
                        error_log('Cancelación manual - Email de cancelación enviado exitosamente');
                    } else {
                        error_log('Cancelación manual - Error enviando email de cancelación');
                    }
                } catch (Throwable $emailError) {
                    error_log('Cancelación manual - Error enviando email: ' . $emailError->getMessage());
                }
                
                $mensaje = 'Suscripción cancelada exitosamente.';
                $tipoMensaje = 'success';
                
                if ($cancellationFee > 0) {
                    $mensaje .= ' Se aplicó un cargo por cancelación de $' . number_format($cancellationFee, 2) . ' MXN.';
                }
                
                // Redirigir después de 3 segundos
                header('Refresh: 3; URL=list.php');
            } else {
                $errores[] = 'Error al cancelar la suscripción en MercadoPago: ' . ($result['error'] ?? 'Error desconocido');
                
                // Log detallado del error
                if (isset($result['debug_info'])) {
                    error_log('Cancelación manual - Debug info: ' . json_encode($result['debug_info']));
                }
            }
        }
    } catch (Exception $e) {
        $errores[] = "Error al procesar la cancelación: " . $e->getMessage();
        error_log('Cancelación manual - Excepción general: ' . $e->getMessage());
        error_log('Cancelación manual - Stack trace: ' . $e->getTraceAsString());
    }
}

include '../../includes/header.php';
?>

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

.alert-warning {
    background-color: #fef5e7;
    color: #c05621;
    border-left: 4px solid #ed8936;
}

.membresia-details {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.membresia-details h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.detail-label {
    font-weight: 500;
    color: #4a5568;
}

.detail-value {
    color: #2d3748;
    text-align: right;
}

.cancellation-fee {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem 0;
    color: #856404;
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
    background-color: #e53e3e;
    border-color: #e53e3e;
}

.btn-danger:hover {
    background-color: #c53030;
    border-color: #c53030;
}

.btn-outline-secondary {
    color: #4a5568;
    border-color: #e2e8f0;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #cbd5e0;
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Cancelar Suscripción</h1>
                    <p class="text-muted mb-0">Confirma la cancelación de la suscripción</p>
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

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $tipoMensaje === 'success' ? 'check-circle' : 'info-circle' ?> me-2"></i>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if ($membresia): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Advertencia:</strong> Esta acción cancelará permanentemente la suscripción del cliente. 
                <?php if ($membresia['monto_cancelacion'] > 0): ?>
                    Se aplicará un cargo por cancelación de $<?= number_format($membresia['monto_cancelacion'], 2) ?> MXN.
                <?php endif; ?>
            </div>

            <div class="membresia-details">
                <h5><i class="fas fa-user me-2"></i>Información del Cliente</h5>
                
                <div class="detail-row">
                    <span class="detail-label">Cliente:</span>
                    <span class="detail-value"><?= htmlspecialchars($membresia['nombre_completo']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($membresia['correo']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Teléfono:</span>
                    <span class="detail-value"><?= htmlspecialchars($membresia['telefono'] ?: 'No especificado') ?></span>
                </div>
            </div>

            <div class="membresia-details">
                <h5><i class="fas fa-tag me-2"></i>Información de la Membresía</h5>
                
                <div class="detail-row">
                    <span class="detail-label">Tipo de Membresía:</span>
                    <span class="detail-value"><?= htmlspecialchars($membresia['tipo_nombre']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Precio Mensual:</span>
                    <span class="detail-value">$<?= number_format($membresia['precio'], 2) ?> MXN</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Estado Actual:</span>
                    <span class="detail-value">
                        <span class="badge bg-success">Activa</span>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Fecha de Inicio:</span>
                    <span class="detail-value"><?= date('d/m/Y', strtotime($membresia['fecha_inicio'])) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">PreApproval ID:</span>
                    <span class="detail-value"><?= htmlspecialchars($membresia['mp_preapproval_id']) ?></span>
                </div>
            </div>

            <?php if ($membresia['monto_cancelacion'] > 0): ?>
            <div class="cancellation-fee">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Cargo por Cancelación:</strong> $<?= number_format($membresia['monto_cancelacion'], 2) ?> MXN
                <br>
                <small>Este monto se cobrará automáticamente al cancelar la suscripción.</small>
            </div>
            <?php endif; ?>

            <form method="post" class="mt-4">
                <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                    <a href="list.php" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" name="confirmar_cancelacion" class="btn btn-lg btn-danger px-4" 
                            onclick="return confirm('¿Estás seguro de que deseas cancelar esta suscripción? Esta acción no se puede deshacer.')">
                        <i class="fas fa-ban me-2"></i>Confirmar Cancelación
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
