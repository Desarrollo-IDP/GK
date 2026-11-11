<?php
// Define current page for active menu highlighting
$current_page = 'tipos_membresia_add';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../config/mercadopago_config.php';

// Inicializar configuración y sesión
initConfig();

// Debug: verificar estado de la sesión al inicio
error_log("DEBUG: add.php - Session status: " . session_status());
error_log("DEBUG: add.php - Session ID: " . session_id());
error_log("DEBUG: add.php - Session data: " . print_r($_SESSION, true));
error_log("DEBUG: add.php - Is authenticated: " . (isAuthenticated() ? 'true' : 'false'));

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';
$mp_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $interval = trim($_POST['interval'] ?? 'month');
    $interval_count = intval($_POST['interval_count'] ?? 1);
    $monto_cancelacion = floatval($_POST['monto_cancelacion'] ?? 0);

    // Validaciones
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($precio <= 0) $errores[] = 'El precio debe ser mayor a 0.';
    if (!in_array($interval, ['day', 'week', 'month', 'year'])) {
        $errores[] = 'El intervalo debe ser: day, week, month o year.';
    }
    if ($interval_count <= 0) $errores[] = 'El conteo de intervalo debe ser mayor a 0.';
    
    if (empty($errores)) {
        try {
                // Obtener la empresa del usuario logueado con manejo de errores
    $empresa_id = getCurrentUserEmpresaId();
    
    // Si no se pudo obtener el empresa_id, intentar con valor por defecto
    if (!$empresa_id) {
        $empresa_id = getCurrentUserEmpresaIdOrDefault(1);
        error_log("WARNING: add.php - Usando empresa_id por defecto: " . $empresa_id);
    }
    
    // Verificar que el usuario tenga una empresa válida
    if (!$empresa_id) {
        $errores[] = 'No se pudo determinar la empresa del usuario. Contacte al administrador.';
        error_log("ERROR: add.php - No se pudo obtener empresa_id para el usuario");
        return;
    }
            
            // Asegurar columnas para Mercado Pago si existen en la BD
            $sqlCols = $pdo->query("SHOW COLUMNS FROM tipos_membresia")->fetchAll(PDO::FETCH_COLUMN);
            $hasPlanId = in_array('mp_plan_id', $sqlCols);
            $hasCancelacion = in_array('monto_cancelacion', $sqlCols);

            // Crear plan en Mercado Pago primero
            $mpPlan = mpCreatePlan($nombre, $precio, $descripcion, $interval_count, $interval);
            
            if ($mpPlan['success']) {
                $mp_plan_id = $mpPlan['plan_id'] ?? null;
                $mp_status = 'Plan creado exitosamente en MercadoPago';
                
                // Construir SQL dinámicamente basado en las columnas disponibles
                $columns = ['nombre', 'descripcion', 'empresa_id', 'precio', '`interval`', 'interval_count'];
                $values = [$nombre, $descripcion, $empresa_id, $precio, $interval, $interval_count];
                
                if ($hasPlanId && $mp_plan_id) {
                    $columns[] = 'mp_plan_id';
                    $values[] = $mp_plan_id;
                }
                
                if ($hasCancelacion) {
                    $columns[] = 'monto_cancelacion';
                    $values[] = $monto_cancelacion;
                }
                
                $placeholders = str_repeat('?,', count($values) - 1) . '?';
                $sql = "INSERT INTO tipos_membresia (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                // Redirigir al listado con mensaje de éxito
                header('Location: list.php?success=1&mp_plan_id=' . $mp_plan_id);
                exit;
            } else {
                $error_msg = $mpPlan['error'] ?? 'Error desconocido';
                $errores[] = "Error al crear plan en MercadoPago: " . $error_msg;
                $mp_status = 'Error: ' . $error_msg;
                
                // Log detallado del error
                error_log('MP CreatePlan Error Details: ' . json_encode($mpPlan));
                
                // Si es un error de credenciales, mostrar sugerencia
                if (strpos($error_msg, '401') !== false || strpos($error_msg, 'unauthorized') !== false) {
                    $errores[] = "Sugerencia: Verifica que las credenciales de MercadoPago estén configuradas correctamente.";
                }
                
                // Si es un error de campos requeridos
                if (strpos($error_msg, 'required') !== false || strpos($error_msg, 'missing') !== false) {
                    $errores[] = "Sugerencia: Verifica que todos los campos requeridos estén completos.";
                }
            }
        } catch (PDOException $e) {
            $errores[] = "Error al guardar en la base de datos: " . $e->getMessage();
        } catch (Exception $e) {
            $errores[] = "Error general: " . $e->getMessage();
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

.alert-info {
    background-color: #bee3f8;
    color: #2a4365;
    border-left: 4px solid #3182ce;
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

.mp-status {
    background: #f0f8ff;
    border: 1px solid #bee3f8;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.mp-status.success {
    background: #f0fff4;
    border-color: #9ae6b4;
}

.mp-status.error {
    background: #fff5f5;
    border-color: #feb2b2;
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
                    <h1 class="h2 mb-2">Agregar Tipo de Membresía</h1>
                    <p class="text-muted mb-0">Crea un nuevo tipo de membresía con campos esenciales para MercadoPago</p>
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

            <?php if ($mp_status): ?>
            <div class="mp-status <?= strpos($mp_status, 'Error') !== false ? 'error' : 'success' ?>">
                <i class="fas <?= strpos($mp_status, 'Error') !== false ? 'fa-exclamation-circle' : 'fa-info-circle' ?> me-2"></i>
                <strong>Estado MercadoPago:</strong> <?= htmlspecialchars($mp_status) ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Información:</strong> Solo se incluyen los campos esenciales para MercadoPago. El sistema generará automáticamente un plan de suscripción con los valores por defecto necesarios.
            </div>

            <form method="post" class="needs-validation" novalidate>
                <div class="form-section">
                    <h5><i class="fas fa-tag me-2"></i>Información del Tipo de Membresía</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nombre" 
                                       name="nombre" 
                                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                       placeholder="Ej: Membresía Básica"
                                       required>
                                <div class="invalid-feedback">
                                    Por favor ingresa el nombre del tipo de membresía.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="precio" class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="precio" 
                                           name="precio" 
                                           value="<?= htmlspecialchars($_POST['precio'] ?? '') ?>"
                                           min="0.01" 
                                           step="0.01" 
                                           placeholder="0.00"
                                           required>
                                </div>
                                <div class="invalid-feedback">
                                    Por favor ingresa un precio válido mayor a 0.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-group">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" 
                                          id="descripcion" 
                                          name="descripcion" 
                                          rows="4"
                                          placeholder="Describe los beneficios y características de este tipo de membresía..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                                <div class="form-text">
                                    Opcional: Agrega una descripción detallada de los beneficios incluidos.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Configuración de Suscripción</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="interval" class="form-label">Intervalo de Facturación <span class="text-danger">*</span></label>
                                <select class="form-select" id="interval" name="interval" required>
                                    <option value="day" <?= ($_POST['interval'] ?? 'month') === 'day' ? 'selected' : '' ?>>Diario</option>
                                    <option value="week" <?= ($_POST['interval'] ?? 'month') === 'week' ? 'selected' : '' ?>>Semanal</option>
                                    <option value="month" <?= ($_POST['interval'] ?? 'month') === 'month' ? 'selected' : '' ?>>Mensual</option>
                                    <option value="year" <?= ($_POST['interval'] ?? 'month') === 'year' ? 'selected' : '' ?>>Anual</option>
                                </select>
                                <div class="form-text">
                                    Intervalo de facturación para suscripciones recurrentes en MercadoPago.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="interval_count" class="form-label">Conteo de Intervalo <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control" 
                                       id="interval_count" 
                                       name="interval_count" 
                                       value="<?= htmlspecialchars($_POST['interval_count'] ?? '1') ?>"
                                       min="1" 
                                       placeholder="1"
                                       required>
                                <div class="form-text">
                                    Número de intervalos entre cada facturación (ej: 1 = cada mes, 3 = cada 3 meses).
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="monto_cancelacion" class="form-label">Monto por Cancelación</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="monto_cancelacion" 
                                           name="monto_cancelacion" 
                                           value="<?= htmlspecialchars($_POST['monto_cancelacion'] ?? '0') ?>"
                                           min="0" 
                                           step="0.01" 
                                           placeholder="0.00">
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle text-info me-1"></i>
                                    Monto que se cobrará automáticamente si el usuario cancela la suscripción. 
                                    <strong>0 = sin cargo por cancelación</strong>. 
                                    <br><small class="text-muted">Este cargo se procesará automáticamente cuando se reciba la notificación de cancelación desde MercadoPago.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="list.php" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-lg btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Crear Tipo de Membresía
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
</script>

<?php include '../../includes/footer.php'; ?> 