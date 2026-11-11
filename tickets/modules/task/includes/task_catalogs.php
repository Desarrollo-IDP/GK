<?php
/**
 * Catálogos compartidos para new.php / edit.php de tareas
 */

if (!function_exists('taskColumnExists')) {
    function taskColumnExists(mysqli $conn, string $table, string $column): bool
    {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('taskLoadCatalog')) {
    function taskLoadCatalog(mysqli $conn, string $table, ?int $empresa_id): mysqli_result
    {
        $hasEmpresa = taskColumnExists($conn, $table, 'id_empresa');
        $sql = "SELECT id, name FROM `$table`";
        $params = [];
        $types = '';
        if ($hasEmpresa && $empresa_id !== null) {
            $sql .= ' WHERE id_empresa = ?';
            $params[] = $empresa_id;
            $types = 'i';
        }
        $sql .= ' ORDER BY name';
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }
        return $conn->query($sql);
    }
}

$user_empresa_id = getCurrentUserEmpresaId();

$categories = taskLoadCatalog($conn, 'categories', $user_empresa_id);
$statuses = $conn->query('SELECT id, name FROM statuses ORDER BY name');
$contact_modes = taskLoadCatalog($conn, 'contact_modes', $user_empresa_id);
$levels = taskLoadCatalog($conn, 'levels', $user_empresa_id);
$impacts = taskLoadCatalog($conn, 'impacts', $user_empresa_id);
$urgencies = taskLoadCatalog($conn, 'urgencies', $user_empresa_id);
$priorities = taskLoadCatalog($conn, 'priorities', $user_empresa_id);
$classifications = taskLoadCatalog($conn, 'classifications', $user_empresa_id);
$requesters = taskLoadCatalog($conn, 'requesters', $user_empresa_id);
$technicians = taskLoadCatalog($conn, 'technicians', $user_empresa_id);
