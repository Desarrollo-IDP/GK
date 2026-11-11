<?php
// Define current page for active menu highlighting
$current_page = 'tipos_membresia_edit';

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

// Obtener datos del tipo de membresía
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
} catch (PDOException $e) {
    header('Location: list.php?error=database');
    exit;
}

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';
$mp_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $duracion_dias = intval($_POST['duracion_dias'] ?? 30);
    $interval = trim($_POST['interval'] ?? 'month');
    $interval_count = intval($_POST['interval_count'] ?? 1);

    // Validaciones
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($precio <= 0) $errores[] = 'El precio debe ser mayor a 0.';
    if ($duracion_dias <= 0) $errores[] = 'La duración debe ser mayor a 0 días.';
    if (!in_array($interval, ['day', 'week', 'month', 'year'])) {
        $errores[] = 'El intervalo debe ser: day, week, month o year.';
    }
    if ($interval_count <= 0) $errores[] = 'El conteo de intervalo debe ser mayor a 0.';
    
    if (empty($errores)) {
        try {
            // Detectar si existe columna mp_plan_id
            $cols = $pdo->query("SHOW COLUMNS FROM tipos_membresia")->fetchAll(PDO::FETCH_COLUMN);
            $hasPlanId = in_array('mp_plan_id', $cols);

            // Buscar plan actual si existe
            $mp_plan_id = null;
            if ($hasPlanId) {
                $stmtPlan = $pdo->prepare("SELECT mp_plan_id FROM tipos_membresia WHERE id=?");
                $stmtPlan->execute([$id]);
                $mp_plan_id = $stmtPlan->fetchColumn();
            }

            // Actualizar/crear plan en MP
            if ($hasPlanId && $mp_plan_id) {
                $mpRes = mpUpdatePlan($mp_plan_id, [
                    'nombre' => $nombre,
                    'precio' => $precio,
                    'interval' => $interval,
                    'interval_count' => $interval_count,
                ]);
                if ($mpRes['success']) {
                    $mp_status = 'Plan actualizado exitosamente en MercadoPago';
                } else {
                    $errores[] = "Error al actualizar plan en MercadoPago: " . ($mpRes['error'] ?? 'Error desconocido');
                    $mp_status = 'Error: ' . ($mpRes['error'] ?? 'Error desconocido');
                }
            } elseif ($hasPlanId) {
                $mpRes = mpCreatePlan([
                    'nombre' => $nombre,
                    'precio' => $precio,
                    'interval' => $interval,
                    'interval_count' => $interval_count,
                ]);
                if ($mpRes['success']) {
                    $mp_plan_id = $mpRes['plan_id'] ?? null;
                    $mp_status = 'Plan creado exitosamente en MercadoPago';
                } else {
                    $errores[] = "Error al crear plan en MercadoPago: " . ($mpRes['error'] ?? 'Error desconocido');
                    $mp_status = 'Error: ' . ($mpRes['error'] ?? 'Error desconocido');
                }
            }

            // Solo actualizar en BD si no hay errores de MP o si no hay integración con MP
            if (empty($errores) || !$hasPlanId) {
                // Actualizar en BD
                $sql = "UPDATE tipos_membresia SET nombre=?, descripcion=?, precio=?, duracion_dias=?, interval=?, interval_count=?" . ($hasPlanId ? ", mp_plan_id=?" : "") . " WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $params = [$nombre, $descripcion, $precio, $duracion_dias, $interval, $interval_count];

                if ($hasPlanId) {
                    $params[] = $mp_plan_id;
                }
                $params[] = $id;
                $stmt->execute($params);
                
                // Redirigir al listado con mensaje de éxito
                header('Location: list.php?success=updated&mp_plan_id=' . $mp_plan_id);
                exit;
            }
        } catch (PDOException $e) {
            $errores[] = "Error al actualizar en la base de datos: " . $e->getMessage();
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

.mp-info {
    background: #f0f8ff;
    border: 1px solid #bee3f8;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
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
                    <h1 class="h2 mb-2">Editar Tipo de Membresía</h1>
                    <p class="text-muted mb-0">Modifica la configuración del tipo de membresía y su plan en MercadoPago</p>
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

            <?php if ($tipo['mp_plan_id']): ?>
            <div class="mp-info">
                <i class="fas fa-link me-2"></i>
                <strong>Plan MercadoPago:</strong> ID <?= htmlspecialchars($tipo['mp_plan_id']) ?>
                <br>
                <small class="text-muted">Este tipo de membresía está vinculado a un plan de suscripción en MercadoPago.</small>
            </div>
            <?php endif; ?>

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
                                       value="<?= htmlspecialchars($tipo['nombre']) ?>"
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
                                           value="<?= htmlspecialchars($tipo['precio']) ?>"
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
                                          placeholder="Describe los beneficios y características de este tipo de membresía..."><?= htmlspecialchars($tipo['descripcion']) ?></textarea>
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
                                <label for="duracion_dias" class="form-label">Duración (días) <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control" 
                                       id="duracion_dias" 
                                       name="duracion_dias" 
                                       value="<?= htmlspecialchars($tipo['duracion_dias']) ?>"
                                       min="1" 
                                       placeholder="30"
                                       required>
                                <div class="form-text">
                                    Duración de la membresía en días.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="interval" class="form-label">Intervalo de Facturación <span class="text-danger">*</span></label>
                                <select class="form-select" id="interval" name="interval" required>
                                    <option value="day" <?= $tipo['interval'] === 'day' ? 'selected' : '' ?>>Diario</option>
                                    <option value="week" <?= $tipo['interval'] === 'week' ? 'selected' : '' ?>>Semanal</option>
                                    <option value="month" <?= $tipo['interval'] === 'month' ? 'selected' : '' ?>>Mensual</option>
                                    <option value="year" <?= $tipo['interval'] === 'year' ? 'selected' : '' ?>>Anual</option>
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
                                       value="<?= htmlspecialchars($tipo['interval_count']) ?>"
                                       min="1" 
                                       placeholder="1"
                                       required>
                                <div class="form-text">
                                    Número de intervalos entre cada facturación (ej: 1 = cada mes, 3 = cada 3 meses).
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
                        <i class="fas fa-save me-2"></i>Actualizar Plan y Guardar
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