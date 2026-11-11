<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

header('Content-Type: application/json');

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT id_empresa, nombre FROM empresas";
$params = [];
$types = '';

if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $query .= " WHERE id_empresa_usuario = ?";
    $params[] = $user_empresa_id;
    $types = 'i';
}

$query .= " ORDER BY nombre";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$empresas = [];
while ($row = $result->fetch_assoc()) {
    $empresas[] = $row;
}

echo json_encode($empresas);
?>