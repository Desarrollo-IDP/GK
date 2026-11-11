<?php
require_once '../../conexion/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

// Habilitar logging de errores
error_log("Iniciando download_attachment.php");

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    error_log("ERROR: ID de archivo no válido o no proporcionado");
    header('HTTP/1.1 400 Bad Request');
    die('ID de archivo no válido');
}

$attachment_id = (int)$_GET['id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'download';
error_log("Solicitud de archivo ID: $attachment_id, acción: $action");

// Obtener la información del archivo verificando que pertenece a un ticket de la empresa del usuario
$query = "SELECT ta.filename, ta.mime_type, ta.file_path, ta.size 
          FROM ticket_attachments ta 
          INNER JOIN tickets t ON ta.ticket_id = t.id 
          WHERE ta.id = ?";
$params = [$attachment_id];
$types = 'i';
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
$stmt = $conn->prepare($query);
if ($stmt === false) {
    error_log("ERROR: No se pudo preparar la consulta: " . $conn->error);
    header('HTTP/1.1 500 Internal Server Error');
    die('Error al preparar la consulta');
}

// Usar los parámetros modificados por addEmpresaFilter
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    error_log("ERROR: No se pudo ejecutar la consulta: " . $stmt->error);
    header('HTTP/1.1 500 Internal Server Error');
    die('Error al ejecutar la consulta');
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("ERROR: Archivo no encontrado con ID: $attachment_id");
    header('HTTP/1.1 404 Not Found');
    die('Archivo no encontrado');
}

$attachment = $result->fetch_assoc();
$stmt->close();

error_log("Archivo encontrado: " . $attachment['filename'] . " (" . $attachment['size'] . " bytes). Ruta en BD: " . $attachment['file_path']);

// ======================================================================
// Construir la ruta física del archivo
// ----------------------------------------------------------------------
// - En BD se guarda algo como: "uploads/tickets/123/archivo.png"
// - Físicamente los archivos están en:  /tickets/uploads/tickets/123/archivo.png
// - Este script está en:                /tickets/modules/tickets/download_attachment.php
//   => Para llegar a /tickets/ desde aquí: dirname(__DIR__, 2)
//   => Ruta absoluta correcta: dirname(__DIR__, 2) . '/' . file_path_bd
// ======================================================================

// Normalizar la ruta que viene de la BD (quitar barras iniciales)
$db_path = ltrim($attachment['file_path'], "/\\");

// Directorio base del módulo de tickets ( .../tickets )
$tickets_base_dir = dirname(__DIR__, 2);

// Ruta absoluta esperada del archivo
$absolute_path = $tickets_base_dir . DIRECTORY_SEPARATOR . $db_path;
$absolute_path = str_replace(['\\', '//'], '/', $absolute_path);

error_log("DEBUG: Intentando leer archivo en: " . $absolute_path);

if (!file_exists($absolute_path)) {
    error_log("ERROR: Archivo físico no encontrado en: " . $absolute_path);
    header('HTTP/1.1 404 Not Found');
    die('Error: Archivo físico no encontrado');
}

// Leer contenido del archivo
$file_content = file_get_contents($absolute_path);

if ($file_content === false) {
    error_log("ERROR: No se pudo leer el contenido del archivo: " . $absolute_path);
    header('HTTP/1.1 500 Internal Server Error');
    die('Error al leer el archivo');
}

// Verificar que el contenido del archivo no esté vacío después de leerlo
if (empty($file_content)) {
    error_log("WARNING: Contenido del archivo vacío para ID: " . $attachment_id . ", ruta: " . $absolute_path);
    // No necesariamente es un error fatal, podría ser un archivo vacío legítimo.
    // Continuamos para servir un archivo vacío o con solo headers.
}

// Configurar headers según la acción
if ($action === 'preview') {
    // Para vista previa, solo permitimos ciertos tipos de archivos
    $previewable_types = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/tiff',
        'image/svg+xml',
        'application/pdf'
    ];
    
    if (!in_array($attachment['mime_type'], $previewable_types)) {
        error_log("ERROR: Tipo de archivo no previsualizable: " . $attachment['mime_type']);
        header('HTTP/1.1 400 Bad Request');
        die('Este tipo de archivo no puede ser previsualizado');
    }
    
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . strlen($file_content));
    header('Cache-Control: public, max-age=3600');
    error_log("Enviando archivo para previsualización: " . $attachment['filename']);
} else {
    // Para descarga
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . strlen($file_content));
    header('Content-Disposition: attachment; filename="' . $attachment['filename'] . '"');
    header('Cache-Control: no-cache, must-revalidate');
    error_log("Enviando archivo para descarga: " . $attachment['filename']);
}

// Enviar el contenido del archivo
echo $file_content;
error_log("Archivo enviado exitosamente");