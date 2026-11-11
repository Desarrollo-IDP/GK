<?php
// Define current page for active menu highlighting
$current_page = 'clientes_edit';

include '../../includes/db.php';

// Función para verificar y depurar rutas de imágenes
function debugImagePath($imagePath) {
    if (empty($imagePath)) {
        error_log("Debug imagen: Ruta vacía");
        return false;
    }
    
    $fullPath = '../../' . $imagePath;
    $exists = file_exists($fullPath);
    $readable = is_readable($fullPath);
    $size = $exists ? filesize($fullPath) : 0;
    
    error_log("Debug imagen: Ruta=$imagePath, Ruta completa=$fullPath, Existe=" . ($exists ? 'Sí' : 'No') . ", Legible=" . ($readable ? 'Sí' : 'No') . ", Tamaño=$size bytes");
    
    return $exists && $readable && $size > 0;
}

// Verificar que se proporcione un ID
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php');
    exit;
}

// Obtener datos del cliente
try {
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar el cliente: " . $e->getMessage());
}

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $edad = intval($_POST['edad'] ?? 0);
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $curp = trim($_POST['curp'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $foto = $cliente['foto']; // Mantener la foto actual por defecto

    // Validaciones básicas
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($apellido_paterno === '') $errores[] = 'El apellido paterno es obligatorio.';
    if ($edad <= 0) $errores[] = 'La edad debe ser mayor a 0.';
    if ($fecha_nacimiento === '') $errores[] = 'La fecha de nacimiento es obligatoria.';
    if ($sexo === '') $errores[] = 'El sexo es obligatorio.';
    if ($curp === '') $errores[] = 'El CURP es obligatorio.';
    if ($correo === '') $errores[] = 'El correo es obligatorio.';
    if ($telefono === '') $errores[] = 'El número de teléfono es obligatorio.';

    // Manejo de la nueva foto (base64) - opcional
    if (isset($_POST['foto_data']) && $_POST['foto_data'] !== '') {
        $fotoData = $_POST['foto_data'];
        $dirFotos = '../../uploads/fotos/';
        
        // Crear directorio si no existe
        if (!is_dir($dirFotos)) {
            if (!mkdir($dirFotos, 0755, true)) {
                $errores[] = 'Error al crear el directorio de fotos.';
            }
        }
        
        // Verificar permisos del directorio
        if (!is_writable($dirFotos)) {
            $errores[] = 'El directorio de fotos no tiene permisos de escritura.';
        }
        
        if (empty($errores)) {
            $fotoNombre = uniqid('foto_') . '.png';
            $fotoPath = $dirFotos . $fotoNombre;
            
            // Verificar que los datos base64 sean válidos
            if (strpos($fotoData, ',') !== false) {
                $fotoBase64 = explode(',', $fotoData, 2)[1];
                $decodedData = base64_decode($fotoBase64);
                
                if ($decodedData !== false) {
                    if (file_put_contents($fotoPath, $decodedData)) {
                        // Verificar que el archivo se guardó correctamente
                        if (file_exists($fotoPath) && filesize($fotoPath) > 0) {
                            $foto = str_replace('../../', '', $fotoPath);
                            
                            // Eliminar la foto anterior si existe
                            if (!empty($cliente['foto']) && file_exists('../../' . $cliente['foto'])) {
                                @unlink('../../' . $cliente['foto']);
                            }
                            
                            error_log("Foto guardada exitosamente: $fotoPath");
                        } else {
                            $errores[] = 'Error: El archivo no se guardó correctamente.';
                        }
                    } else {
                        $errores[] = 'Error al escribir el archivo de foto.';
                    }
                } else {
                    $errores[] = 'Error: Datos de imagen inválidos.';
                }
            } else {
                $errores[] = 'Error: Formato de datos de imagen incorrecto.';
            }
        }
    }

    // Si no hay errores, actualizar en la base de datos
    if (empty($errores)) {
        try {
            $sql = "UPDATE clientes SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, edad = ?, fecha_nacimiento = ?, sexo = ?, curp = ?, foto = ?, direccion = ?, correo = ?, telefono = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$nombre, $apellido_paterno, $apellido_materno, $edad, $fecha_nacimiento, $sexo, $curp, $foto, $direccion, $correo, $telefono, $id]);
            
            if ($result) {
                $mensaje = 'Cliente actualizado exitosamente.';
                $tipoMensaje = 'success';
            } else {
                $errores[] = 'Error al actualizar el cliente.';
            }
        } catch (PDOException $e) {
            $errores[] = 'Error al actualizar en la base de datos: ' . $e->getMessage();
        }
    }
}
?>
<?php include '../../includes/header.php'; 
include '../../includes/sidebar.php';?>

<style>
/* Estilos para la cámara */
#camara-container {
    margin: 1rem 0;
}

#video, #canvas_foto, #preview_foto {
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    max-width: 100%;
    height: auto;
}

#btnCamara, #btnTomarFoto {
    margin: 0.5rem 0.5rem 0.5rem 0;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s ease;
}

#btnCamara:hover, #btnTomarFoto:hover {
    background-color: #edf2f7;
    border-color: #cbd5e0;
}

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

/* Mejoras responsivas */
@media (max-width: 768px) {
    .main-content-boxed {
        padding: 1.5rem 1rem;
        margin: 0.5rem;
        width: auto;
    }
    
    .form-section {
        padding: 1.25rem;
    }
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Editar Cliente</h1>
                    <p class="text-muted mb-0">Modifique los datos del cliente</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="list.php" class="btn btn-outline-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= $tipoMensaje === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

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

            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>Datos del Cliente</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre(s) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="apellido_paterno" class="form-label">Apellido Paterno <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($cliente['apellido_paterno'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="apellido_materno" class="form-label">Apellido Materno</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($cliente['apellido_materno'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edad" class="form-label">Edad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edad" name="edad" min="1" value="<?= htmlspecialchars($cliente['edad'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($cliente['fecha_nacimiento'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sexo" class="form-label">Sexo <span class="text-danger">*</span></label>
                                <select name="sexo" id="sexo" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <option value="M" <?= ($cliente['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="F" <?= ($cliente['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Femenino</option>
                                    <option value="Otro" <?= ($cliente['sexo'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="curp" class="form-label">CURP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="curp" name="curp" maxlength="18" value="<?= htmlspecialchars($cliente['curp'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="correo" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="correo" name="correo" value="<?= htmlspecialchars($cliente['correo'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-group">
                                <label for="direccion" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Fotografía</label>
                                
                                <!-- Mostrar foto actual si existe -->
                                <?php if (!empty($cliente['foto'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Foto actual:</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php
                                            // Depurar la ruta de la imagen
                                            $imageExists = debugImagePath($cliente['foto']);
                                            if ($imageExists): 
                                            ?>
                                                <img src="<?= htmlspecialchars(getImageUrl($cliente['foto'])) ?>" alt="Foto actual del cliente" style="max-width:120px; border-radius:0.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" onload="console.log('Imagen cargada exitosamente:', this.src);">
                                                <div class="alert alert-warning" style="display:none;">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Error:</strong> No se pudo cargar la imagen.
                                                </div>
                                                <div>
                                                    <small class="text-muted">Esta es la foto actual del cliente. Tome una nueva foto para reemplazarla.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Advertencia:</strong> La imagen del cliente no se encuentra en el servidor o está corrupta. Tome una nueva foto.
                                                    <br><small class="text-muted">Ruta buscada: <?= htmlspecialchars($cliente['foto']) ?></small>
                                                    <br><small class="text-muted">Solución: Use la cámara para tomar una nueva foto.</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div id="camara-container" class="border rounded p-3 bg-light">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                        <button type="button" id="btnCamara" class="btn btn-sm btn-outline-primary" onclick="toggleCamara()">
                                            <i class="fas fa-camera me-1"></i>Activar cámara
                                        </button>
                                        <button type="button" onclick="tomarFoto()" id="btnTomarFoto" class="btn btn-sm btn-success" style="display:none;">
                                            <i class="fas fa-camera me-1"></i>Tomar foto
                                        </button>
                                        <button type="button" onclick="probarImagen()" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-1"></i>Probar imagen
                                        </button>
                                    </div>
                                    <div class="text-center">
                                        <video id="video" width="320" height="240" autoplay style="display:none; max-width:100%;"></video>
                                        <canvas id="canvas_foto" width="320" height="240" style="display:none;"></canvas>
                                        <img id="preview_foto" src="" alt="Vista previa de la nueva foto" style="display:none; max-width:100%; max-height:300px; border-radius:0.5rem;"/>
                                    </div>
                                    <input type="hidden" name="foto_data" id="foto_data">
                                    <div class="form-text mt-2">
                                        <?php if (!empty($cliente['foto'])): ?>
                                            Permita el acceso a la cámara y tome una nueva foto para reemplazar la actual, o deje vacío para mantener la foto existente.
                                        <?php else: ?>
                                            Permita el acceso a la cámara y tome la foto del cliente.
                                        <?php endif; ?>
                                    </div>
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
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Variables para la cámara
let video = document.getElementById('video');
let canvasFoto = document.getElementById('canvas_foto');
let previewFoto = document.getElementById('preview_foto');
let fotoData = document.getElementById('foto_data');
let btnCamara = document.getElementById('btnCamara');
let btnTomarFoto = document.getElementById('btnTomarFoto');
let stream = null;
let camaraActiva = false;

function toggleCamara() {
    if (!camaraActiva) {
        // Activar cámara
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            })
            .then(function(s) {
                stream = s;
                video.srcObject = stream;
                video.style.display = 'block';
                btnTomarFoto.style.display = 'inline-block';
                btnCamara.textContent = 'Desactivar cámara';
                camaraActiva = true;
                
                // Log de éxito
                console.log('Cámara activada exitosamente');
            })
            .catch(function(err) {
                console.error('Error al acceder a la cámara:', err);
                alert('No se pudo acceder a la cámara: ' + err.message);
            });
        } else {
            alert('Su navegador no soporta el acceso a la cámara.');
        }
    } else {
        // Desactivar cámara
        if (stream) {
            let tracks = stream.getTracks();
            tracks.forEach(track => track.stop());
        }
        video.srcObject = null;
        video.style.display = 'none';
        btnTomarFoto.style.display = 'none';
        btnCamara.textContent = 'Activar cámara';
        camaraActiva = false;
        
        console.log('Cámara desactivada');
    }
}

function tomarFoto() {
    if (!camaraActiva) {
        alert('Primero debe activar la cámara.');
        return;
    }
    
    canvasFoto.style.display = 'block';
    previewFoto.style.display = 'block';
    canvasFoto.getContext('2d').drawImage(video, 0, 0, canvasFoto.width, canvasFoto.height);
    let dataURL = canvasFoto.toDataURL('image/png');
    previewFoto.src = dataURL;
    fotoData.value = dataURL;
    
    // Mostrar mensaje de éxito
    alert('Foto tomada exitosamente. Puede guardar los cambios.');
    
    // Opcional: desactivar cámara después de tomar la foto
    toggleCamara();
}

function probarImagen() {
    // Crear una imagen de prueba
    const canvas = document.createElement('canvas');
    canvas.width = 320;
    canvas.height = 240;
    const ctx = canvas.getContext('2d');
    
    // Dibujar un rectángulo de prueba
    ctx.fillStyle = '#5e72e4';
    ctx.fillRect(0, 0, 320, 240);
    ctx.fillStyle = 'white';
    ctx.font = '20px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Imagen de Prueba', 160, 120);
    ctx.fillText('Fecha: ' + new Date().toLocaleString(), 160, 150);
    
    // Mostrar la imagen de prueba
    const dataURL = canvas.toDataURL('image/png');
    previewFoto.src = dataURL;
    previewFoto.style.display = 'block';
    fotoData.value = dataURL;
    
    alert('Imagen de prueba generada. Puede guardar para probar el sistema.');
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Configuración inicial si es necesaria
});
</script> 