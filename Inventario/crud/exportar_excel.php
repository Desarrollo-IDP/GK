<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['equipos']) || empty($_POST['equipos'])) {
    header('Location: reporte.php');
    exit;
}

$equipos_ids = $_POST['equipos'];
$id_list = implode(',', array_map('intval', $equipos_ids));
$empresa_nombre = $_POST['empresa_nombre'] ?? 'Todas las empresas';

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT *, id_equipo, equipo_number, COALESCE(equipo_number, id_equipo) as display_id FROM equipos WHERE id_equipo IN ($id_list)";

// Agregar filtro por empresa del usuario para verificar permisos
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $query .= " AND id_empresa_usuario = " . intval($user_empresa_id);
}

$result = $conn->query($query);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

$equipos = [];
while ($row = $result->fetch_assoc()) {
    $equipos[] = $row;
}

// Registrar la actividad
logActivity('Exportación a Excel', 'equipos', null, 'Empresa: ' . $empresa_nombre . ', Equipos: ' . implode(',', $equipos_ids));

// Configurar encabezados para descarga de CSV
$filename = 'reporte_equipos_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_nombre) . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Abrir flujo de salida
$output = fopen('php://output', 'w');

// Agregar BOM UTF-8 para que Excel reconozca los caracteres especiales
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir encabezados (en el orden del INSERT para importación)
$headers = [
    'empresa',
    'nombre del equipo',
    'responsable',
    'sistema operativo',
    'direccion mac',
    'tipo',
    'fabricante',
    'modelo',
    'numero de serie',
    'ram',
    'disco duro',
    'procesador',
    'observaciones'
];
fputcsv($output, $headers);

// Escribir datos de equipos (en el orden del INSERT para importación)
foreach ($equipos as $equipo) {
    $row = [
        $equipo['nombre_cliente'] ?? '',
        $equipo['nombre_equipo'] ?? '',
        $equipo['responsable'] ?? '',
        $equipo['sistema_operativo'] ?? '',
        $equipo['direccion_mac'] ?? '',
        $equipo['tipo'] ?? '',
        $equipo['fabricante'] ?? '',
        $equipo['modelo'] ?? '',
        $equipo['numero_serie'] ?? '',
        $equipo['ram'] ?? '',
        $equipo['disco_duro'] ?? '',
        $equipo['procesador'] ?? '',
        $equipo['observaciones'] ?? ''
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
?>

