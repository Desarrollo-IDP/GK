<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

if(!isset($_GET['id']) || !isset($_GET['doc'])) {
    die("Parámetros incorrectos");
}

$id_equipo = (int)$_GET['id'];
$doc_index = (int)$_GET['doc'];

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Verificar que el equipo pertenezca a la empresa del usuario
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $stmt = $conn->prepare("SELECT documentos FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $id_equipo, $user_empresa_id);
} else {
    $stmt = $conn->prepare("SELECT documentos FROM equipos WHERE id_equipo = ?");
    $stmt->bind_param("i", $id_equipo);
}

$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    die("Equipo no encontrado o no tienes permiso para ver este documento");
}

// Verificación adicional de permisos
if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    $verify_stmt = $conn->prepare("SELECT id_equipo FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
    $verify_stmt->bind_param("ii", $id_equipo, $user_empresa_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        die("No tienes permiso para ver este documento");
    }
    $verify_stmt->close();
}

$equipo = $result->fetch_assoc();

if(empty($equipo['documentos'])) {
    die("No hay documentos adjuntos para este equipo");
}

$documentos = json_decode($equipo['documentos'], true);

if(!isset($documentos[$doc_index])) {
    die("Documento no encontrado");
}

$documento = $documentos[$doc_index];
$contenido = base64_decode($documento['contenido']);
$tipo = $documento['tipo'];
$nombre = $documento['nombre'];

header("Content-Type: $tipo");
header("Content-Disposition: inline; filename=\"$nombre\"");
header("Content-Length: " . strlen($contenido));
echo $contenido;
exit;
?>