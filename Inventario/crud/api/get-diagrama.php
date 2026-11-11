<?php
require_once '../includes/db.php';
require_once '../../../auth.php';
checkAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT archivo_url, mime_type FROM empresa_memoria_tecnica WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        // 1. Prioridad: Archivo físico en el servidor
        if (!empty($row['archivo_url'])) {
            $filePath = "../" . $row['archivo_url'];
            if (file_exists($filePath)) {
                $mime = $row['mime_type'] ?: mime_content_type($filePath) ?: "image/png";
                header("Content-Type: " . $mime);
                readfile($filePath);
                exit;
            }
        }
    }
}

// Fallback o error
header("HTTP/1.0 404 Not Found");
?>