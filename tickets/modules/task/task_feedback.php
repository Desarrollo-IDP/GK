<?php
header('Content-Type: application/json; charset=utf-8');

include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_audit.php';
require_once '../../includes/task_status_tracking.php';

checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$subtask_id = isset($_POST['subtask_id']) ? (int) $_POST['subtask_id'] : 0;
$work_feedback = trim($_POST['work_feedback'] ?? '');
$task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;

if ($subtask_id <= 0 || mb_strlen($work_feedback) < 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'La retroalimentación debe tener al menos 5 caracteres.']);
    exit;
}

$params = [$subtask_id];
$types = 'i';
$q = 'SELECT st.id, st.task_id, st.technician_id, st.title, t.status_id AS task_status_id
      FROM task_subtasks st
      INNER JOIN tasks t ON t.id = st.task_id
      WHERE st.id = ? AND t.deleted = 0';
$q = addEmpresaFilter($q, 't.id_empresa', $params, $types);
$chk = $conn->prepare($q);
$chk->bind_param($types, ...$params);
$chk->execute();
$sub = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$sub) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Actividad no encontrada.']);
    exit;
}

if ((int) ($sub['task_status_id'] ?? 0) === TASK_STATUS_WAITING) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => taskSubtaskEditBlockedMessage()]);
    exit;
}

$audit = getTaskAuditUser();
$technician_id = (int) $sub['technician_id'];
$task_id = $task_id > 0 ? $task_id : (int) $sub['task_id'];

$stmt = $conn->prepare(
    'INSERT INTO task_subtask_feedback (subtask_id, technician_id, work_feedback, created_by, created_by_user_id)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->bind_param('iissi', $subtask_id, $technician_id, $work_feedback, $audit['username'], $audit['user_id']);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al guardar.']);
    exit;
}
$stmt->close();

$summary = sprintf(
    '%s registró retroalimentación en actividad "%s"',
    $audit['username'],
    $sub['title']
);
logTaskActivity($conn, 'feedback', $summary, $task_id, $subtask_id, $work_feedback, $audit);

echo json_encode(['ok' => true, 'message' => 'Retroalimentación guardada.']);
