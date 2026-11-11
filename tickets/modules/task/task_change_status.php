<?php
header('Content-Type: application/json; charset=utf-8');

include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_status_tracking.php';

checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$entity_type = $_POST['entity_type'] ?? '';
$entity_id = isset($_POST['entity_id']) ? (int) $_POST['entity_id'] : 0;
$new_status_id = isset($_POST['new_status_id']) ? (int) $_POST['new_status_id'] : 0;
$change_reason = $_POST['change_reason'] ?? null;
$work_feedback = isset($_POST['work_feedback']) ? trim((string) $_POST['work_feedback']) : null;
$task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : null;
$subtask_id = isset($_POST['subtask_id']) ? (int) $_POST['subtask_id'] : null;

if (!in_array($entity_type, ['task', 'subtask'], true) || $entity_id <= 0 || $new_status_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

// Verificar acceso por empresa
$params = [$entity_id];
$types = 'i';
if ($entity_type === 'task') {
    $q = 'SELECT id FROM tasks WHERE id = ? AND deleted = 0';
    $q = addEmpresaFilter($q, 'id_empresa', $params, $types);
} else {
    $q = 'SELECT st.id FROM task_subtasks st INNER JOIN tasks t ON t.id = st.task_id WHERE st.id = ? AND t.deleted = 0';
    $q = addEmpresaFilter($q, 't.id_empresa', $params, $types);
}
$chk = $conn->prepare($q);
$chk->bind_param($types, ...$params);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sin permiso o registro no encontrado']);
    exit;
}
$chk->close();

$result = taskApplyStatusChange(
    $conn,
    $entity_type,
    $entity_id,
    $new_status_id,
    $change_reason,
    $task_id,
    $subtask_id,
    $work_feedback
);

if (!$result['ok']) {
    http_response_code(422);
}
echo json_encode($result);
