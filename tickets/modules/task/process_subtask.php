<?php
header('Content-Type: application/json; charset=utf-8');

include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_audit.php';
require_once '../../includes/task_status_tracking.php';
require_once '../../includes/task_rich_text_helper.php';
require_once '../../includes/task_mailer_helper.php';

checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? 'create';
$task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;

$params = [$task_id];
$types = 'i';
$q = 'SELECT id FROM tasks WHERE id = ? AND deleted = 0';
$q = addEmpresaFilter($q, 'id_empresa', $params, $types);
$chk = $conn->prepare($q);
$chk->bind_param($types, ...$params);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Tarea no encontrada.']);
    exit;
}
$chk->close();

$paramsParent = [$task_id];
$typesParent = 'i';
$parentQ = 'SELECT status_id FROM tasks WHERE id = ? AND deleted = 0';
$parentQ = addEmpresaFilter($parentQ, 'id_empresa', $paramsParent, $typesParent);
$parentStmt = $conn->prepare($parentQ);
$parentStmt->bind_param($typesParent, ...$paramsParent);
$parentStmt->execute();
$parentRow = $parentStmt->get_result()->fetch_assoc();
$parentStmt->close();

if (!$parentRow) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Tarea no encontrada.']);
    exit;
}

if ($action !== 'create' && taskParentBlocksSubtaskEdits($conn, $task_id)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => taskSubtaskEditBlockedMessage()]);
    exit;
}

$audit = getTaskAuditUser();

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $description = taskSanitizeRichDescription($_POST['description'] ?? '');
    if (taskRichDescriptionIsEmpty($description)) {
        $description = '';
    }
    $technician_id = (int) ($_POST['technician_id'] ?? 0);
    $status_id = (int) ($_POST['status_id'] ?? 1);

    if ($title === '' || $technician_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Título y técnico son obligatorios.']);
        exit;
    }

    $stmt = $conn->prepare(
        'INSERT INTO task_subtasks (task_id, title, description, technician_id, status_id, created_by, created_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issiisi',
        $task_id,
        $title,
        $description,
        $technician_id,
        $status_id,
        $audit['username'],
        $audit['user_id']
    );
    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'message' => 'Error al crear actividad.']);
        exit;
    }
    $subtask_id = (int) $conn->insert_id;
    $stmt->close();

    if (in_array($status_id, TASK_TRACKED_STATUS_IDS, true) && !taskParentBlocksSubtaskEdits($conn, $task_id)) {
        $reason = ($status_id === TASK_STATUS_WAITING) ? trim($_POST['change_reason'] ?? '') : null;
        if ($status_id === TASK_STATUS_WAITING) {
            $err = taskValidateWaitingReason($reason, $status_id);
            if ($err) {
                echo json_encode(['ok' => false, 'message' => $err]);
                exit;
            }
        }
        taskRecordStatusChange($conn, 'subtask', $subtask_id, null, $status_id, $reason, $audit);
        if ($status_id !== TASK_STATUS_WAITING) {
            taskStartStatusTracking($conn, 'subtask', $subtask_id, $status_id, $audit, $reason);
        }
        if ($status_id === TASK_STATUS_WAITING) {
            taskRollupParentWaitingTracking($conn, $task_id, $audit, $reason, $title);
        }
    }

    logTaskActivity(
        $conn,
        'created',
        sprintf('%s creó actividad "%s"', $audit['username'], $title),
        $task_id,
        $subtask_id,
        null,
        $audit
    );

    $taskContext = taskFetchTaskEmailContext($conn, $task_id);
    if ($taskContext) {
        $notify = sendSubtaskAssignedToTechnicianNotification(
            $conn,
            isset($taskContext['id_empresa']) ? (int) $taskContext['id_empresa'] : null,
            $task_id,
            (string) $taskContext['task_number'],
            $subtask_id,
            $technician_id,
            $title,
            $description,
            $taskContext['start_date'] ?? null,
            $taskContext['end_date'] ?? null
        );
        if ($notify['success']) {
            taskMailLog(
                'process_subtask.php — correo actividad "' . $title . '" confirmado (subtask ID ' . $subtask_id . ')',
                'OK'
            );
        } else {
            taskMailLog(
                'process_subtask.php — fallo correo actividad "' . $title . '": ' . ($notify['message'] ?? ''),
                'WARN'
            );
        }
    } else {
        taskMailLog('process_subtask.php — no se obtuvo contexto de tarea ID ' . $task_id, 'WARN');
    }

    echo json_encode(['ok' => true, 'subtask_id' => $subtask_id]);
    exit;
}

if ($action === 'update_description') {
    $subtask_id = isset($_POST['subtask_id']) ? (int) $_POST['subtask_id'] : 0;
    $description = taskSanitizeRichDescription($_POST['description'] ?? '');
    if (taskRichDescriptionIsEmpty($description)) {
        $description = '';
    }

    if ($subtask_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Actividad no indicada.']);
        exit;
    }

    $params = [$subtask_id, $task_id];
    $types = 'ii';
    $subQ = 'SELECT st.id, st.title, st.description
             FROM task_subtasks st
             INNER JOIN tasks t ON t.id = st.task_id
             WHERE st.id = ? AND st.task_id = ? AND t.deleted = 0';
    $subQ = addEmpresaFilter($subQ, 't.id_empresa', $params, $types);
    $subStmt = $conn->prepare($subQ);
    $subStmt->bind_param($types, ...$params);
    $subStmt->execute();
    $sub = $subStmt->get_result()->fetch_assoc();
    $subStmt->close();

    if (!$sub) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Actividad no encontrada.']);
        exit;
    }

    $oldDescription = taskSanitizeRichDescription($sub['description'] ?? '');
    if ($oldDescription === $description) {
        echo json_encode(['ok' => true, 'message' => 'Sin cambios.']);
        exit;
    }

    $upd = $conn->prepare(
        'UPDATE task_subtasks SET description = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ?'
    );
    $upd->bind_param('ssii', $description, $audit['username'], $audit['user_id'], $subtask_id);
    if (!$upd->execute()) {
        echo json_encode(['ok' => false, 'message' => 'No se pudo guardar la descripción.']);
        exit;
    }
    $upd->close();

    logTaskActivity(
        $conn,
        'updated',
        sprintf(
            '%s modificó la descripción/alcance de la actividad "%s"',
            $audit['username'],
            $sub['title']
        ),
        $task_id,
        $subtask_id,
        null,
        $audit
    );

    echo json_encode(['ok' => true, 'message' => 'Descripción guardada.']);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Acción no soportada.']);
