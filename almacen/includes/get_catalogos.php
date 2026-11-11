<?php
require_once 'db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

header('Content-Type: application/json');

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

$modulo = $_GET['modulo'] ?? '';
$campo = $_GET['campo'] ?? '';

if (empty($modulo) || empty($campo)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT * FROM catalogos WHERE modulo = ? AND campo = ?";
if ($has_empresa_column && $user_empresa_id !== null) {
    $sql .= " AND id_empresa = ?";
    $sql .= " ORDER BY orden ASC, valor ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $modulo, $campo, $user_empresa_id);
} else {
    $sql .= " ORDER BY orden ASC, valor ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $modulo, $campo);
}
$stmt->execute();
$result = $stmt->get_result();

$catalogos = [];
while ($row = $result->fetch_assoc()) {
    $catalogos[] = $row;
}

echo json_encode($catalogos);
?>

