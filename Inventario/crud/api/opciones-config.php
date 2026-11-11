<?php
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$is_admin = isAdmin();

// Empresa para la cual devolver opciones: por defecto la del usuario
$id_empresa_usuario = $user_empresa_id;

if ($is_admin && isset($_GET['empresa']) && (int)$_GET['empresa'] > 0) {
    $id_empresa_inventario = (int) $_GET['empresa'];
    $stmt = $conn->prepare("SELECT id_empresa_usuario FROM empresas WHERE id_empresa = ?");
    $stmt->bind_param("i", $id_empresa_inventario);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    if ($row && $row['id_empresa_usuario'] !== null) {
        $id_empresa_usuario = (int) $row['id_empresa_usuario'];
    }
}

$ubicaciones = [];
$activos = [];

if ($id_empresa_usuario !== null) {
    $tables = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
    if ($tables && $tables->num_rows > 0) {
        $s = $conn->prepare("SELECT valor FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? ORDER BY valor");
        $s->bind_param("i", $id_empresa_usuario);
        $s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) {
            $ubicaciones[] = $f['valor'];
        }
        $r->free();
        $s->close();
    }
    $tables2 = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
    if ($tables2 && $tables2->num_rows > 0) {
        $s = $conn->prepare("SELECT valor FROM inventario_config_activos WHERE id_empresa_usuario = ? ORDER BY valor");
        $s->bind_param("i", $id_empresa_usuario);
        $s->execute();
        $r = $s->get_result();
        while ($f = $r->fetch_assoc()) {
            $activos[] = $f['valor'];
        }
        $r->free();
        $s->close();
    }
}

echo json_encode(['ubicaciones' => $ubicaciones, 'activos' => $activos]);
