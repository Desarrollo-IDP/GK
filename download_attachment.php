<?php
require_once __DIR__ . '/tickets/conexion/db.php';

if (!$conn) {
    error_log("ERROR: No se pudo establecer la conexion con la base de datos");
    die('Error de conexion a la base de datos');
}

$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'download';

if (!$attachment_id) {
    die('ID de adjunto no valido');
}

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

$file_path = __DIR__ . '/tickets/' . ltrim($attachment['file_path'], "/\\");

if (!file_exists($file_path)) {
    error_log("ERROR: Archivo no encontrado en el sistema: " . $file_path);
    die('Archivo no encontrado');
}

$content = file_get_contents($file_path);
if ($content === false) {
    error_log("ERROR: No se pudo leer el archivo: " . $file_path);
    die('Error al leer el archivo');
}

if ($action === 'preview') {
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
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Disposition: attachment; filename="' . $attachment['filename'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
}

echo $content;
?>
