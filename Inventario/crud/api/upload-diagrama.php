<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../log_activity.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
$sede = isset($_POST['sede']) ? $_POST['sede'] : 'General';
if ($id_empresa <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'ID de empresa no válido']);
    exit;
}

if (!isset($_FILES['diagrama']) || $_FILES['diagrama']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['diagrama']['error'] ?? 'sin archivo';
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al recibir el archivo: ' . $err]);
    exit;
}

$file = $_FILES['diagrama'];
$maxSize = 10 * 1024 * 1024; // 10 MB
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo imágenes (JPG, PNG, GIF, WEBP, SVG).']);
    exit;
}

if ($file['size'] > $maxSize) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'El archivo excede el límite de 10 MB.']);
    exit;
}

// Definir el nombre del archivo y la ruta de destino
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = "diagrama_" . $id_empresa . "_" . str_replace(' ', '_', $sede) . "_" . time() . "." . $extension;
$targetDir = "../uploads/diagramas/";

// Crear el directorio si no existe
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$targetPath = $targetDir . $fileName;

// Mover el archivo al directorio de destino
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo en el servidor']);
    exit;
}

$archivo_url = "uploads/diagramas/" . $fileName;
$archivo_nombre = $file['name'];
$mimeType = $file['type'];
$creado_por = $_SESSION['user']['id'] ?? null;

// Registrar en la base de datos con la URL del archivo
$sql = "INSERT INTO empresa_memoria_tecnica (id_empresa, sede, tipo, titulo, descripcion, archivo_url, archivo_nombre, mime_type, creado_por, actualizado_por, orden) 
        VALUES (?, ?, 'diagrama', 'Actualización de Diagrama de Red', 'Se ha subido una nueva versión del diagrama de red.', ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(orden), 0) + 1 FROM empresa_memoria_tecnica m2 WHERE id_empresa = ?))";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error en la preparación de la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param('issssiii', $id_empresa, $sede, $archivo_url, $archivo_nombre, $mimeType, $creado_por, $creado_por, $id_empresa);

if (!$stmt->execute()) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al registrar en la base de datos: ' . $stmt->error]);
    exit;
}

$newId = $stmt->insert_id;

// Log activity
if (function_exists('logActivity')) {
    logActivity("Subió nuevo diagrama en '$sede'", "empresa_memoria_tecnica", $newId, "URL: $archivo_url", $id_empresa);
}

$output = [
    'success' => true,
    'url' => 'api/get-diagrama.php?id=' . $newId . '&t=' . time(),
    'id' => $newId
];

ob_clean();
echo json_encode($output);
exit;
?>