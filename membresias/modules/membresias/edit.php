<?php
// Include database connection
require_once '../../includes/db.php';

// Obtener tipos de membresía
try {
    $stmt = $pdo->query("SELECT id, nombre FROM tipos_membresia ORDER BY nombre");
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener los tipos de membresía: " . $e->getMessage());
}

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';

// Obtener datos actuales
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php');
    exit;
}

try {
    $sql = "SELECT m.*, 
                   CONCAT(c.nombre, ' ', c.apellido_paterno, ' ', COALESCE(c.apellido_materno, '')) as nombre_completo,
                   c.telefono, c.correo, c.direccion
            FROM membresias m
            JOIN clientes c ON m.cliente_id = c.id
            WHERE m.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $membresia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membresia) {
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error al obtener los datos de la membresía: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = trim($_POST['cliente'] ?? '');
    $tipo_membresia_id = $_POST['tipo_membresia_id'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $firma = $membresia['firma_digital'];

    if ($cliente === '') $errores[] = 'El nombre del cliente es obligatorio.';
    if ($tipo_membresia_id === '') $errores[] = 'El tipo de membresía es obligatorio.';
    if ($fecha_inicio === '') $errores[] = 'La fecha de inicio es obligatoria.';
    if ($fecha_fin === '') $errores[] = 'La fecha de fin es obligatoria.';
    if ($metodo_pago === '') $errores[] = 'El método de pago es obligatorio.';

    // Manejo de la firma (canvas)
    if (isset($_POST['firma_data']) && $_POST['firma_data'] !== '') {
        $firmaData = $_POST['firma_data'];
        $dirFirmas = '../../uploads/firmas/';
        if (!is_dir($dirFirmas)) {
            mkdir($dirFirmas, 0777, true);
        }
        $firmaNombre = uniqid('firma_') . '.png';
        $firmaPath = $dirFirmas . $firmaNombre;
        $firmaBase64 = explode(',', $firmaData, 2)[1];
        if (file_put_contents($firmaPath, base64_decode($firmaBase64))) {
            $firma = str_replace('../../', '', $firmaPath);
        } else {
            $errores[] = 'Error al guardar la firma digital.';
        }
    }

    if (empty($errores)) {
    try {
        $sql = "UPDATE membresias SET nombre_cliente_manual=:cliente, tipo_membresia_id=:tipo_membresia_id, 
                fecha_inicio=:fecha_inicio, fecha_fin=:fecha_fin, metodo_pago=:metodo_pago, 
                firma_digital=:firma WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cliente' => $cliente,
            ':tipo_membresia_id' => $tipo_membresia_id,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin,
            ':metodo_pago' => $metodo_pago,
            ':firma' => $firma,
            ':id' => $id
        ]);
        
        // Redirigir después de actualizar
        header('Location: list.php');
        exit;
    } catch (PDOException $e) {
        $errores[] = "Error al actualizar la membresía: " . $e->getMessage();
    }
}
}
?>

<?php 
// Include header with proper path
include '../../includes/header.php'; 

// Include CSS files
$base_url = isset($base_url) ? $base_url : '';
echo '<link rel="stylesheet" href="' . $base_url . 'css/modern-design-system.css">';
echo '<link rel="stylesheet" href="' . $base_url . 'css/components.css">';
?>

<style>
/* Custom styles specific to this page */
.body-bg {
    background: var(--gray-50);
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
    padding: 1rem;
}

.main-content {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 24px 0 rgba(32, 38, 84, 0.07);
    padding: 2rem;
    margin-top: 1rem;
    margin-bottom: 2rem;
}

.form-section {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-100);
}

.form-section h5 {
    color: var(--primary-800);
    font-weight: 600;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.signature-pad {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    margin: 1rem 0;
    background: white;
    padding: 1rem;
}

.signature-pad--body {
    border: 1px dashed var(--gray-300);
    border-radius: 0.375rem;
    background: var(--gray-50);
}

.signature-pad--footer {
    margin-top: 1rem;
    color: var(--gray-500);
    font-size: 0.875rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-700) 0%, var(--primary-800) 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(32, 38, 84, 0.1);
}

.btn-outline-secondary {
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
}

.btn-outline-secondary:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.form-control, .form-select {
    border: 1px solid var(--gray-300);
    border-radius: 0.5rem;
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px rgba(32, 38, 84, 0.1);
}

.alert {
    border-radius: 0.5rem;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid transparent;
}

.alert-danger {
    background-color: #FEF2F2;
    border-color: #FECACA;
    color: #B91C1C;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .body-bg {
        padding: 0.5rem;
    }
    
    .main-content {
        padding: 1rem;
        margin-top: 0.5rem;
    }
    
    .form-section {
        padding: 1rem;
    }
}
</style>

<div class="body-bg">
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h3 mb-1">Editar Membresía</h1>
                        <p class="text-muted mb-0">Actualice los datos de la membresía</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
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
                    <h5><i class="fas fa-user me-2"></i>Datos del Cliente</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($membresia['nombre_completo'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($membresia['telefono'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($membresia['correo'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" rows="2" disabled><?= htmlspecialchars($membresia['direccion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-id-card me-2"></i>Datos de la Membresía</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Membresía <span class="text-danger">*</span></label>
                            <select name="tipo_membresia_id" class="form-select" required>
                                <option value="">Seleccione un tipo</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>" <?= ($membresia['tipo_membresia_id'] ?? '') == $tipo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                            <select name="metodo_pago" class="form-select" required>
                                <option value="">Seleccione un método</option>
                                <option value="Efectivo" <?= ($membresia['metodo_pago'] ?? '') == 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="Tarjeta" <?= ($membresia['metodo_pago'] ?? '') == 'Tarjeta' ? 'selected' : '' ?>>Tarjeta de Crédito/Débito</option>
                                <option value="Transferencia" <?= ($membresia['metodo_pago'] ?? '') == 'Transferencia' ? 'selected' : '' ?>>Transferencia Bancaria</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($membresia['fecha_inicio'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Fin <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($membresia['fecha_fin'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-signature me-2"></i>Firma Digital</h5>
                    <div class="mb-3">
                        <div id="signature-pad" class="signature-pad">
                            <div class="signature-pad--body">
                                <canvas id="firma" width="100%" height="280"></canvas>
                            </div>
                            <div class="signature-pad--footer">
                                <div class="description">Firme arriba en el área designada</div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarFirma()">
                                        <i class="fas fa-eraser me-1"></i> Limpiar
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="guardarFirma()">
                                        <i class="fas fa-save me-1"></i> Guardar Firma
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="firma_data" id="firma_data" value="<?= htmlspecialchars($membresia['firma_digital'] ?? '') ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top">
                <a href="list.php" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
            </div>
            </form>
            </main>
        </div>
    </div>
</div>

<!-- Include Footer -->
<?php include '../../includes/footer.php'; ?>

<!-- Custom Scripts -->
<script>
// Enable form validation
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()

// Signature Pad Functionality
let canvas = document.getElementById('firma');
let ctx = canvas.getContext('2d');
let drawing = false;
let lastX = 0;
let lastY = 0;

// Set canvas size
function resizeCanvas() {
    const container = document.querySelector('.signature-pad--body');
    const rect = container.getBoundingClientRect();
    
    // Set canvas size to match container
    canvas.width = rect.width;
    canvas.height = 200; // Fixed height for signature
    
    // Set line styles
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
}

// Initial resize
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

// Drawing functions
function startDrawing(e) {
    drawing = true;
    [lastX, lastY] = [e.offsetX, e.offsetY];
}

function draw(e) {
    if (!drawing) return;
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(e.offsetX, e.offsetY);
    ctx.stroke();
    
    [lastX, lastY] = [e.offsetX, e.offsetY];
}

function stopDrawing() {
    drawing = false;
}

// Touch support
function handleStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousedown', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

function handleMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const rect = canvas.getBoundingClientRect();
    const mouseEvent = new MouseEvent('mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY - rect.top
    });
    canvas.dispatchEvent(mouseEvent);
}

function handleEnd() {
    const mouseEvent = new MouseEvent('mouseup');
    canvas.dispatchEvent(mouseEvent);
}

// Event Listeners
canvas.addEventListener('mousedown', startDrawing);
canvas.addEventListener('mousemove', draw);
canvas.addEventListener('mouseup', stopDrawing);
canvas.addEventListener('mouseout', stopDrawing);

// Touch events
canvas.addEventListener('touchstart', handleStart);
canvas.addEventListener('touchmove', handleMove);
canvas.addEventListener('touchend', handleEnd);

// Clear signature
function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('firma_data').value = '';
}

// Save signature
function guardarFirma() {
    if (isCanvasBlank(canvas)) {
        // Show error if canvas is empty
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning mt-3';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Por favor, realice una firma antes de guardar.';
        
        // Remove any existing alerts
        const existingAlert = document.querySelector('.alert-warning');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Add the new alert
        const form = document.querySelector('form');
        form.insertBefore(alertDiv, form.firstChild);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
        
        return false;
    }
    
    // Save the signature
    const dataURL = canvas.toDataURL('image/png');
    document.getElementById('firma_data').value = dataURL;
    
    // Show success message
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success mt-3';
    successDiv.role = 'alert';
    successDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i> Firma guardada correctamente.';
    
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert-success');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Add the new alert
    const form = document.querySelector('form');
    form.insertBefore(successDiv, form.firstChild);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        successDiv.remove();
    }, 3000);
    
    return true;
}

// Check if canvas is blank
function isCanvasBlank(canvas) {
    const blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;
    return canvas.toDataURL() === blank.toDataURL();
}

// Load existing signature if it exists
window.addEventListener('load', function() {
    const existingSignature = document.getElementById('firma_data').value;
    if (existingSignature) {
        const img = new Image();
        img.onload = function() {
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        };
        img.src = existingSignature;
    }
});
</script>

<script>
let canvas = document.getElementById('firma');
let ctx = canvas.getContext('2d');
let dibujando = false;

canvas.addEventListener('mousedown', e => { dibujando = true; ctx.beginPath(); });
canvas.addEventListener('mouseup', e => { dibujando = false; });
canvas.addEventListener('mouseout', e => { dibujando = false; });
canvas.addEventListener('mousemove', dibujar);

canvas.addEventListener('touchstart', e => { dibujando = true; ctx.beginPath(); });
canvas.addEventListener('touchend', e => { dibujando = false; });
canvas.addEventListener('touchcancel', e => { dibujando = false; });
canvas.addEventListener('touchmove', dibujarTouch);

function dibujar(e) {
    if (!dibujando) return;
    let rect = canvas.getBoundingClientRect();
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}
function dibujarTouch(e) {
    if (!dibujando) return;
    let rect = canvas.getBoundingClientRect();
    let touch = e.touches[0];
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
    e.preventDefault();
}
function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}
function guardarFirma() {
    let dataURL = canvas.toDataURL('image/png');
    document.getElementById('firma_data').value = dataURL;
    // Solo validar si el usuario dibujó algo
    let blanco = document.createElement('canvas');
    blanco.width = canvas.width; blanco.height = canvas.height;
    if (canvas.toDataURL() === blanco.toDataURL()) {
        // No se dibujó nada, no se actualiza la firma
        document.getElementById('firma_data').value = '';
    }
    return true;
}
</script>
