<?php
// Cargar autoload de Composer si existe (opcional)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once(__DIR__ . '/../../vendor/autoload.php');
}

require_once(__DIR__ . '/conexion/db.php');

// Verificar la conexión a la base de datos
if (!$conn) {
    error_log("ERROR: No se pudo establecer la conexión con la base de datos");
    die('Error de conexión a la base de datos');
}

// Obtener el ID del adjunto y la acción
$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'download';

if (!$attachment_id) {
    die('ID de adjunto no válido');
}

// Obtener la información del adjunto
$stmt = $conn->prepare("
    SELECT filename, file_path, mime_type 
    FROM ticket_attachments 
    WHERE id = ?
");

if (!$stmt) {
    error_log("ERROR al preparar la consulta: " . $conn->error);
    die('Error al preparar la consulta');
}

$stmt->bind_param("i", $attachment_id);
if (!$stmt->execute()) {
    error_log("ERROR al ejecutar la consulta: " . $stmt->error);
    $stmt->close();
    die('Error al ejecutar la consulta');
}

$result = $stmt->get_result();
$attachment = $result->fetch_assoc();
$stmt->close();

if (!$attachment) {
    die('Adjunto no encontrado');
}

// Construir la ruta completa del archivo
// file_path en BD es relativa desde tickets/ (ej: "uploads/attachments/2024/01/archivo.pdf" o "uploads/tickets/123/archivo.pdf")
// __DIR__ es tickets/, así que solo necesitamos concatenar directamente
$db_path = ltrim($attachment['file_path'], "/\\");
$file_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $db_path);

// Normalizar la ruta (resolver .. y .)
$file_path = realpath($file_path);

// Verificar que el archivo existe
if (!$file_path || !file_exists($file_path)) {
    // Intentar con rutas alternativas para debugging
    $alt_path1 = __DIR__ . '/' . $db_path;
    $alt_path2 = dirname(__DIR__) . '/' . $db_path;
    
    error_log("ERROR: Archivo no encontrado en el sistema.");
    error_log("  - Ruta intentada: " . ($file_path ?: 'null'));
    error_log("  - Ruta alternativa 1: " . $alt_path1 . " (existe: " . (file_exists($alt_path1) ? 'sí' : 'no') . ")");
    error_log("  - Ruta alternativa 2: " . $alt_path2 . " (existe: " . (file_exists($alt_path2) ? 'sí' : 'no') . ")");
    error_log("  - file_path en BD: " . $attachment['file_path']);
    error_log("  - __DIR__: " . __DIR__);
    die('Archivo no encontrado');
}

// Leer el contenido del archivo
$content = file_get_contents($file_path);
if ($content === false) {
    error_log("ERROR: No se pudo leer el archivo: " . $file_path);
    die('Error al leer el archivo');
}

// Configurar las cabeceras HTTP según la acción
if ($action === 'preview') {
    // Para vista previa, solo permitir ciertos tipos MIME
    $allowed_preview_types = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/tiff',
        'image/svg+xml',
        'application/pdf'
    ];
    
    if (!in_array($attachment['mime_type'], $allowed_preview_types)) {
        die('Tipo de archivo no permitido para vista previa');
    }
    
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
} else {
    // Para descarga
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Disposition: attachment; filename="' . $attachment['filename'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
}

// Enviar el contenido
echo $content;
?> 