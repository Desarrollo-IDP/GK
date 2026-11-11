<?php
// Define current page for active menu highlighting
$current_page = 'empresas_add';

include '../../includes/db.php';
include '../../includes/config.php';

// Inicializar variables
$errores = [];
$mensaje = '';
$tipoMensaje = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $razon_social = trim($_POST['razon_social'] ?? '');
    $rfc = trim($_POST['rfc'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $colonia = trim($_POST['colonia'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $pais = trim($_POST['pais'] ?? 'México');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $sitio_web = trim($_POST['sitio_web'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $logo = null;

    // Validaciones
    if (empty($nombre)) {
        $errores[] = 'El nombre de la empresa es obligatorio.';
    }
    
    if (empty($razon_social)) {
        $errores[] = 'La razón social es obligatoria.';
    }
    
    if (empty($rfc)) {
        $errores[] = 'El RFC es obligatorio.';
    } elseif (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
        $errores[] = 'El RFC debe tener un formato válido.';
    }
    
    if (empty($ciudad)) {
        $errores[] = 'La ciudad es obligatoria.';
    }
    
    if (empty($estado)) {
        $errores[] = 'El estado es obligatorio.';
    }
    
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }
    
    if (!empty($sitio_web) && !filter_var($sitio_web, FILTER_VALIDATE_URL)) {
        $errores[] = 'El sitio web no tiene un formato válido.';
    }

    // Manejo del logo (base64)
    if (isset($_POST['logo_data']) && $_POST['logo_data'] !== '') {
        $logoData = $_POST['logo_data'];
        $dirLogos = '../../uploads/logos/';
        
        // Crear directorio si no existe
        if (!is_dir($dirLogos)) {
            if (!mkdir($dirLogos, 0755, true)) {
                $errores[] = 'Error al crear el directorio de logos.';
            }
        }
        
        // Verificar permisos del directorio
        if (!is_writable($dirLogos)) {
            $errores[] = 'El directorio de logos no tiene permisos de escritura.';
        }
        
        if (empty($errores)) {
            $logoNombre = uniqid('logo_') . '.png';
            $logoPath = $dirLogos . $logoNombre;
            
            // Verificar que los datos base64 sean válidos
            if (strpos($logoData, ',') !== false) {
                $logoBase64 = explode(',', $logoData, 2)[1];
                $decodedData = base64_decode($logoBase64);
                
                if ($decodedData !== false) {
                    if (file_put_contents($logoPath, $decodedData)) {
                        // Verificar que el archivo se guardó correctamente
                        if (file_exists($logoPath) && filesize($logoPath) > 0) {
                            $logo = str_replace('../../', '', $logoPath);
                            error_log("Logo guardado exitosamente: $logoPath");
                        } else {
                            $errores[] = 'Error: El archivo de logo no se guardó correctamente.';
                        }
                    } else {
                        $errores[] = 'Error al guardar el logo.';
                    }
                } else {
                    $errores[] = 'Error: Los datos del logo no son válidos.';
                }
            } else {
                $errores[] = 'Error: Formato de datos del logo inválido.';
            }
        }
    }

    // Si no hay errores, guardar la empresa
    if (empty($errores)) {
        try {
            $sql = "INSERT INTO empresas (nombre, razon_social, rfc, direccion, colonia, ciudad, estado, codigo_postal, pais, telefono, correo, sitio_web, logo, descripcion, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre, 
                $razon_social, 
                $rfc, 
                $direccion, 
                $colonia, 
                $ciudad, 
                $estado, 
                $codigo_postal, 
                $pais, 
                $telefono, 
                $correo, 
                $sitio_web, 
                $logo, 
                $descripcion, 
                $activo
            ]);
            
            $empresa_id = $pdo->lastInsertId();
            
            $mensaje = 'Empresa agregada exitosamente.';
            $tipoMensaje = 'success';
            
            // Redirigir a la lista después de un breve delay
            header("Location: list.php?success=" . urlencode($mensaje));
            exit;
            
        } catch (PDOException $e) {
            $errores[] = 'Error al guardar la empresa: ' . $e->getMessage();
            error_log("Error al agregar empresa: " . $e->getMessage());
        }
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-plus me-2"></i>Nueva Empresa
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver
                    </a>
                </div>
            </div>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>Información de la Empresa
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Información Básica -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Información Básica
                                </h6>
                                
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre de la Empresa *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="razon_social" class="form-label">Razón Social *</label>
                                    <input type="text" class="form-control" id="razon_social" name="razon_social" 
                                           value="<?= htmlspecialchars($_POST['razon_social'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="rfc" class="form-label">RFC *</label>
                                    <input type="text" class="form-control" id="rfc" name="rfc" 
                                           value="<?= htmlspecialchars($_POST['rfc'] ?? '') ?>" 
                                           placeholder="Ej: ABC123456789" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                              placeholder="Descripción de la empresa..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Información de Contacto -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-phone me-2"></i>Información de Contacto
                                </h6>
                                
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="correo" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="correo" name="correo" 
                                           value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sitio_web" class="form-label">Sitio Web</label>
                                    <input type="url" class="form-control" id="sitio_web" name="sitio_web" 
                                           value="<?= htmlspecialchars($_POST['sitio_web'] ?? '') ?>"
                                           placeholder="https://www.ejemplo.com">
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                               <?= isset($_POST['activo']) ? 'checked' : 'checked' ?>>
                                        <label class="form-check-label" for="activo">
                                            Empresa Activa
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dirección -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-map-marker-alt me-2"></i>Dirección
                                </h6>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" 
                                           value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="colonia" class="form-label">Colonia</label>
                                    <input type="text" class="form-control" id="colonia" name="colonia" 
                                           value="<?= htmlspecialchars($_POST['colonia'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ciudad" class="form-label">Ciudad *</label>
                                    <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                           value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="estado" class="form-label">Estado *</label>
                                    <input type="text" class="form-control" id="estado" name="estado" 
                                           value="<?= htmlspecialchars($_POST['estado'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="codigo_postal" class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" 
                                           value="<?= htmlspecialchars($_POST['codigo_postal'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="pais" class="form-label">País</label>
                                    <input type="text" class="form-control" id="pais" name="pais" 
                                           value="<?= htmlspecialchars($_POST['pais'] ?? 'México') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logo 
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-image me-2"></i>Logo de la Empresa
                                </h6>
                                
                                <div class="mb-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                        <button type="button" id="btnCamara" class="btn btn-sm btn-outline-primary" onclick="toggleCamara()">
                                            <i class="fas fa-camera me-1"></i>Activar cámara
                                        </button>
                                        <button type="button" onclick="tomarFoto()" id="btnTomarFoto" class="btn btn-sm btn-success" style="display:none;">
                                            <i class="fas fa-camera me-1"></i>Tomar foto
                                        </button>
                                        <!--<button type="button" onclick="probarImagen()" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-1"></i>Probar imagen
                                        </button> -->
                                    <!--</div>
                                    <div class="text-center">
                                        <video id="video" width="320" height="240" autoplay style="display:none; max-width:100%;"></video>
                                        <canvas id="canvas_logo" width="320" height="240" style="display:none;"></canvas>
                                        <img id="preview_logo" src="" alt="Vista previa del logo" style="display:none; max-width:100%; max-height:300px; border-radius:0.5rem;"/>
                                    </div>
                                    <input type="hidden" name="logo_data" id="logo_data">
                                    <div class="form-text mt-2">
                                        Permita el acceso a la cámara y tome una foto del logo de la empresa, o deje vacío para agregar el logo más tarde.
                                    </div>
                                </div>
                            </div>
                        </div>-->
                        
                        <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                            <a href="list.php" class="btn btn-lg btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-lg btn-primary px-4">
                                <i class="fas fa-save me-2"></i>Guardar Empresa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Variables para la cámara
let video = document.getElementById('video');
let canvasLogo = document.getElementById('canvas_logo');
let previewLogo = document.getElementById('preview_logo');
let logoData = document.getElementById('logo_data');
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
                
                // Ocultar preview si existe
                previewLogo.style.display = 'none';
            })
            .catch(function(err) {
                console.error('Error al acceder a la cámara:', err);
                alert('Error al acceder a la cámara. Verifique los permisos.');
            });
        } else {
            alert('Su navegador no soporta el acceso a la cámara.');
        }
    } else {
        // Desactivar cámara
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        video.style.display = 'none';
        btnTomarFoto.style.display = 'none';
        btnCamara.textContent = 'Activar cámara';
        camaraActiva = false;
    }
}

function tomarFoto() {
    if (camaraActiva) {
        const context = canvasLogo.getContext('2d');
        canvasLogo.width = video.videoWidth;
        canvasLogo.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvasLogo.width, canvasLogo.height);
        
        // Convertir a base64
        const imageData = canvasLogo.toDataURL('image/png');
        logoData.value = imageData;
        
        // Mostrar preview
        previewLogo.src = imageData;
        previewLogo.style.display = 'block';
        video.style.display = 'none';
        
        // Desactivar cámara
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        btnTomarFoto.style.display = 'none';
        btnCamara.textContent = 'Activar cámara';
        camaraActiva = false;
        
        console.log('Logo capturado exitosamente');
    }
}

function probarImagen() {
    if (logoData.value) {
        alert('Logo capturado correctamente. Tamaño: ' + Math.round(logoData.value.length / 1024) + ' KB');
    } else {
        alert('No hay logo capturado. Use la cámara para tomar una foto.');
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 