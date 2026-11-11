<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../includes/empresa_helper.php';
require_once __DIR__ . '/../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$id = $_POST['id'] ?? 0;

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Verificar que el equipo pertenezca a la empresa del usuario
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $id, $user_empresa_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
    $stmt->bind_param("i", $id);
}

$stmt->execute();
$result = $stmt->get_result();
$equipo_original = $result->fetch_assoc();

if (!$equipo_original) {
    die("Equipo no encontrado o no tienes permiso para editarlo");
}

// Verificación adicional de permisos
if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    if ($equipo_original['id_empresa_usuario'] != $user_empresa_id) {
        die("No tienes permiso para editar este equipo");
    }
}

// Construir UPDATE según si existe el campo id_empresa_usuario
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    // UPDATE manteniendo id_empresa_usuario y verificando permisos
    $stmt = $conn->prepare("UPDATE equipos SET 
        nombre_equipo = ?,
        responsable = ?,
        sistema_operativo = ?,
        direccion_mac = ?,
        tipo = ?,
        fabricante = ?,
        modelo = ?,
        numero_serie = ?,
        ram = ?,
        disco_duro = ?,
        procesador = ?,
        observaciones = ?
        WHERE id_equipo = ? AND id_empresa_usuario = ?");

    $stmt->bind_param("ssssssssssssii", 
        $_POST['equipo'],
        $_POST['responsable'],
        $_POST['sistema_operativo'],
        $_POST['mac'],
        $_POST['tipo'],
        $_POST['fabricante'],
        $_POST['modelo'],
        $_POST['numero_serie'],
        $_POST['ram'],
        $_POST['disco_duro'],
        $_POST['procesador'],
        $_POST['observaciones'],
        $id,
        $user_empresa_id
    );
} else {
    // UPDATE sin id_empresa_usuario (campo no existe aún)
    $stmt = $conn->prepare("UPDATE equipos SET 
        nombre_equipo = ?,
        responsable = ?,
        sistema_operativo = ?,
        direccion_mac = ?,
        tipo = ?,
        fabricante = ?,
        modelo = ?,
        numero_serie = ?,
        ram = ?,
        disco_duro = ?,
        procesador = ?,
        observaciones = ?
        WHERE id_equipo = ?");

    $stmt->bind_param("ssssssssssssi", 
        $_POST['equipo'],
        $_POST['responsable'],
        $_POST['sistema_operativo'],
        $_POST['mac'],
        $_POST['tipo'],
        $_POST['fabricante'],
        $_POST['modelo'],
        $_POST['numero_serie'],
        $_POST['ram'],
        $_POST['disco_duro'],
        $_POST['procesador'],
        $_POST['observaciones'],
        $id
    );
}

if ($stmt->execute()) {
    $cambios = [];
    if ($equipo_original['nombre_equipo'] != $_POST['equipo']) {
        $cambios[] = "Nombre equipo: " . $equipo_original['nombre_equipo'] . " → " . $_POST['equipo'];
    }
    if ($equipo_original['responsable'] != $_POST['responsable']) {
        $cambios[] = "Responsable: " . $equipo_original['responsable'] . " → " . $_POST['responsable'];
    }
    logActivity(
        "Actualización de equipo",
        "equipos",
        $id,
        "Se actualizó el equipo: " . $_POST['equipo'] . "\n" . implode("\n", $cambios)
    );
    
    header("Location: ../visualizar-equipos.php");
    exit;
} else {
 
    echo "Error: " . $stmt->error;
}
?>