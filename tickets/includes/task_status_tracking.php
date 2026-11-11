<?php
/**
 * Tracking de tiempo y cambios de estado — módulo tareas
 */

require_once __DIR__ . '/task_audit.php';

const TASK_TRACKED_STATUS_IDS = [2, 10];
const TASK_STATUS_OPEN = 2;
const TASK_STATUS_WAITING = 10;
const TASK_STATUS_CLOSED = 3;
const TASK_MIN_WAITING_REASON_LEN = 10;
const TASK_MIN_CLOSE_FEEDBACK_LEN = 5;

function taskSubtaskEditBlockedMessage(): string
{
    return 'La tarea está en En atención / Espera. El conteo de tiempo de las actividades está en pausa. '
        . 'Cambie el estado de la tarea a Abierto para editar actividades y reanudar sus tiempos.';
}

function taskSubtaskWaitingPanelMessage(): string
{
    return 'La tarea está en En atención / Espera. Puede agregar nuevas actividades; '
        . 'la edición de actividades existentes y su conteo de tiempo permanecen en pausa hasta cambiar la tarea a Abierto.';
}

/**
 * La tarea padre está en espera: bloquear subtareas y pausar su conteo de tiempo.
 */
function taskIsParentWaitingPausingSubtasks(mysqli $conn, int $task_id): bool
{
    if ($task_id <= 0) {
        return false;
    }
    $stmt = $conn->prepare('SELECT status_id FROM tasks WHERE id = ? AND deleted = 0 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && (int) $row['status_id'] === TASK_STATUS_WAITING) {
        return true;
    }
    return taskHasActiveTracking($conn, 'task', $task_id, TASK_STATUS_WAITING);
}

/**
 * Mientras la tarea padre esté en espera, las subtareas no se pueden modificar.
 */
function taskParentBlocksSubtaskEdits(mysqli $conn, int $task_id): bool
{
    return taskIsParentWaitingPausingSubtasks($conn, $task_id);
}

/**
 * Cierra tracking activo huérfano en subtareas si la tarea padre está en espera.
 */
function taskEnsureSubtasksTrackingPausedWhileParentWaiting(mysqli $conn, int $task_id, array $audit): void
{
    if (!taskIsParentWaitingPausingSubtasks($conn, $task_id)) {
        return;
    }
    taskPauseAllSubtasksTracking($conn, $task_id, $audit);
}

function taskValidateWaitingReason(?string $reason, int $new_status_id): ?string
{
    if ($new_status_id !== TASK_STATUS_WAITING) {
        return null;
    }
    $reason = trim((string) $reason);
    if (mb_strlen($reason) < TASK_MIN_WAITING_REASON_LEN) {
        return 'Debe indicar el motivo de espera (mínimo ' . TASK_MIN_WAITING_REASON_LEN . ' caracteres).';
    }
    return null;
}

function taskValidateCloseFeedback(?string $feedback, int $new_status_id): ?string
{
    if ($new_status_id !== TASK_STATUS_CLOSED) {
        return null;
    }
    $feedback = trim((string) $feedback);
    if (mb_strlen($feedback) < TASK_MIN_CLOSE_FEEDBACK_LEN) {
        return 'Debe registrar retroalimentación / lo que hizo antes de cerrar (mínimo '
            . TASK_MIN_CLOSE_FEEDBACK_LEN . ' caracteres).';
    }
    return null;
}

/**
 * Guarda retroalimentación de subtarea y registra actividad.
 */
function taskSaveSubtaskFeedback(
    mysqli $conn,
    int $subtask_id,
    string $work_feedback,
    int $task_id,
    array $audit
): bool {
    $st = $conn->prepare('SELECT technician_id, title FROM task_subtasks WHERE id = ?');
    $st->bind_param('i', $subtask_id);
    $st->execute();
    $sub = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$sub) {
        return false;
    }

    $technician_id = (int) $sub['technician_id'];
    $stmt = $conn->prepare(
        'INSERT INTO task_subtask_feedback (subtask_id, technician_id, work_feedback, created_by, created_by_user_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissi', $subtask_id, $technician_id, $work_feedback, $audit['username'], $audit['user_id']);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    $summary = sprintf(
        '%s registró retroalimentación en actividad "%s"',
        $audit['username'],
        $sub['title']
    );
    logTaskActivity($conn, 'feedback', $summary, $task_id, $subtask_id, $work_feedback, $audit);
    return true;
}

function taskHasActiveTracking(mysqli $conn, string $entity_type, int $entity_id, ?int $status_id = null): bool
{
    $sql = 'SELECT id FROM task_status_tracking WHERE entity_type = ? AND entity_id = ? AND is_active = 1';
    if ($status_id !== null) {
        $sql .= ' AND status_id = ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($status_id !== null) {
        $stmt->bind_param('sii', $entity_type, $entity_id, $status_id);
    } else {
        $stmt->bind_param('si', $entity_type, $entity_id);
    }
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}

function taskEndStatusTracking(mysqli $conn, string $entity_type, int $entity_id, array $audit): void
{
    $stmt = $conn->prepare(
        'SELECT id, started_at FROM task_status_tracking
         WHERE entity_type = ? AND entity_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->bind_param('si', $entity_type, $entity_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }

    $upd = $conn->prepare(
        'UPDATE task_status_tracking SET ended_at = CURRENT_TIMESTAMP, is_active = 0,
         ended_by = ?, ended_by_user_id = ? WHERE id = ?'
    );
    $upd->bind_param('sii', $audit['username'], $audit['user_id'], $row['id']);
    $upd->execute();
    $upd->close();

    $calc = $conn->prepare(
        'UPDATE task_status_tracking SET duration_minutes = TIMESTAMPDIFF(MINUTE, started_at, ended_at) WHERE id = ?'
    );
    $calc->bind_param('i', $row['id']);
    $calc->execute();
    $calc->close();
}

function taskStartStatusTracking(
    mysqli $conn,
    string $entity_type,
    int $entity_id,
    int $status_id,
    array $audit,
    ?string $status_reason = null
): bool {
    $stmt = $conn->prepare(
        'INSERT INTO task_status_tracking (entity_type, entity_id, status_id, status_reason, started_by, started_by_user_id, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param(
        'siissi',
        $entity_type,
        $entity_id,
        $status_id,
        $status_reason,
        $audit['username'],
        $audit['user_id']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function taskRecordStatusChange(
    mysqli $conn,
    string $entity_type,
    int $entity_id,
    ?int $old_status_id,
    int $new_status_id,
    ?string $change_reason,
    array $audit
): bool {
    $stmt = $conn->prepare(
        'INSERT INTO task_status_changes (entity_type, entity_id, old_status_id, new_status_id, change_reason, changed_by, changed_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param(
        'siiissi',
        $entity_type,
        $entity_id,
        $old_status_id,
        $new_status_id,
        $change_reason,
        $audit['username'],
        $audit['user_id']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Cierra todas las actividades abiertas de una tarea (al cerrar la tarea padre).
 *
 * @return int Cantidad de actividades cerradas
 */
function taskCloseAllOpenActivitiesForTask(mysqli $conn, int $task_id, array $audit): int
{
    $closedStatus = TASK_STATUS_CLOSED;
    $stmt = $conn->prepare(
        'SELECT id, status_id, title FROM task_subtasks WHERE task_id = ? AND status_id != ?'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $task_id, $closedStatus);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $closedName = getStatusName($conn, TASK_STATUS_CLOSED);
    foreach ($rows as $row) {
        $subtaskId = (int) $row['id'];
        $oldStatus = (int) $row['status_id'];
        $title = $row['title'] ?? '';

        taskRecordStatusChange($conn, 'subtask', $subtaskId, $oldStatus, $closedStatus, null, $audit);
        taskEndStatusTracking($conn, 'subtask', $subtaskId, $audit);

        $upd = $conn->prepare(
            'UPDATE task_subtasks SET status_id = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ?,
             closed_at = NOW(), closed_by = ?, closed_by_user_id = ? WHERE id = ?'
        );
        if (!$upd) {
            throw new RuntimeException('No se pudo preparar el cierre de actividades.');
        }
        $upd->bind_param(
            'isisii',
            $closedStatus,
            $audit['username'],
            $audit['user_id'],
            $audit['username'],
            $audit['user_id'],
            $subtaskId
        );
        if (!$upd->execute()) {
            $upd->close();
            throw new RuntimeException('No se pudo cerrar una actividad abierta.');
        }
        $upd->close();

        $fromName = getStatusName($conn, $oldStatus);
        logTaskActivity(
            $conn,
            'status_change',
            sprintf(
                '%s cerró actividad "%s" (%s → %s) al cerrar la tarea',
                $audit['username'],
                $title,
                $fromName,
                $closedName
            ),
            $task_id,
            $subtaskId,
            null,
            $audit
        );
    }

    return count($rows);
}

/**
 * @return array{ok:bool, message?:string}
 */
function taskApplyStatusChange(
    mysqli $conn,
    string $entity_type,
    int $entity_id,
    int $new_status_id,
    ?string $change_reason = null,
    ?int $task_id_for_log = null,
    ?int $subtask_id_for_log = null,
    ?string $work_feedback = null
): array {
    $audit = getTaskAuditUser();

    $err = taskValidateWaitingReason($change_reason, $new_status_id);
    if ($err !== null) {
        return ['ok' => false, 'message' => $err];
    }

    $errClose = taskValidateCloseFeedback($work_feedback, $new_status_id);
    if ($errClose !== null) {
        return ['ok' => false, 'message' => $errClose];
    }

    $subtask_title = null;
    if ($entity_type === 'subtask') {
        $stmt = $conn->prepare('SELECT status_id, task_id, title FROM task_subtasks WHERE id = ?');
    } else {
        $stmt = $conn->prepare('SELECT status_id FROM tasks WHERE id = ? AND deleted = 0');
    }
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Error al leer estado actual.'];
    }
    $stmt->bind_param('i', $entity_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        return ['ok' => false, 'message' => 'Registro no encontrado.'];
    }

    $old_status_id = (int) $current['status_id'];
    if ($old_status_id === $new_status_id) {
        return ['ok' => true, 'message' => 'Sin cambios.'];
    }

    if ($entity_type === 'subtask' && $task_id_for_log === null) {
        $task_id_for_log = (int) ($current['task_id'] ?? 0);
    }
    if ($entity_type === 'subtask') {
        $parent_task_id = $task_id_for_log ?: (int) ($current['task_id'] ?? 0);
        if ($parent_task_id > 0 && taskParentBlocksSubtaskEdits($conn, $parent_task_id)) {
            return ['ok' => false, 'message' => taskSubtaskEditBlockedMessage()];
        }
    }
    if ($entity_type === 'task' && $task_id_for_log === null) {
        $task_id_for_log = $entity_id;
    }
    if ($entity_type === 'subtask' && $subtask_id_for_log === null) {
        $subtask_id_for_log = $entity_id;
    }
    if ($entity_type === 'subtask' && !empty($current['title'])) {
        $subtask_title = $current['title'];
    }

    $reasonForDb = ($new_status_id === TASK_STATUS_WAITING) ? trim((string) $change_reason) : null;

    $feedbackForClose = ($new_status_id === TASK_STATUS_CLOSED) ? trim((string) $work_feedback) : '';

    $conn->begin_transaction();
    try {
        if ($new_status_id === TASK_STATUS_CLOSED && $entity_type === 'subtask') {
            $tid = $task_id_for_log ?: (int) ($current['task_id'] ?? 0);
            if (!taskSaveSubtaskFeedback($conn, $entity_id, $feedbackForClose, $tid, $audit)) {
                throw new Exception('No se pudo guardar la retroalimentación.');
            }
        }

        if ($new_status_id === TASK_STATUS_CLOSED && $entity_type === 'task') {
            $resUpd = $conn->prepare(
                'UPDATE tasks SET resolution = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ?'
            );
            $resUpd->bind_param('ssii', $feedbackForClose, $audit['username'], $audit['user_id'], $entity_id);
            if (!$resUpd->execute()) {
                $resUpd->close();
                throw new Exception('No se pudo guardar la retroalimentación de cierre.');
            }
            $resUpd->close();
            logTaskActivity(
                $conn,
                'feedback',
                $audit['username'] . ' registró retroalimentación al cerrar la tarea',
                $entity_id,
                null,
                $feedbackForClose,
                $audit
            );
        }

        taskRecordStatusChange($conn, $entity_type, $entity_id, $old_status_id, $new_status_id, $reasonForDb, $audit);
        taskEndStatusTracking($conn, $entity_type, $entity_id, $audit);

        if ($entity_type === 'task') {
            $sql = 'UPDATE tasks SET status_id = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ?';
            if ($new_status_id === TASK_STATUS_CLOSED) {
                $sql .= ', closed_at = NOW(), closed_by = ?, closed_by_user_id = ?';
            }
            $sql .= ' WHERE id = ?';
            $upd = $conn->prepare($sql);
            if ($new_status_id === TASK_STATUS_CLOSED) {
                $upd->bind_param('isisii', $new_status_id, $audit['username'], $audit['user_id'], $audit['username'], $audit['user_id'], $entity_id);
            } else {
                $upd->bind_param('isii', $new_status_id, $audit['username'], $audit['user_id'], $entity_id);
            }
        } else {
            $sql = 'UPDATE task_subtasks SET status_id = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ?';
            if ($new_status_id === TASK_STATUS_CLOSED) {
                $sql .= ', closed_at = NOW(), closed_by = ?, closed_by_user_id = ?';
            }
            $sql .= ' WHERE id = ?';
            $upd = $conn->prepare($sql);
            if ($new_status_id === TASK_STATUS_CLOSED) {
                $upd->bind_param('isisii', $new_status_id, $audit['username'], $audit['user_id'], $audit['username'], $audit['user_id'], $entity_id);
            } else {
                $upd->bind_param('isii', $new_status_id, $audit['username'], $audit['user_id'], $entity_id);
            }
        }
        if (!$upd->execute()) {
            throw new Exception($upd->error);
        }
        $upd->close();

        $startTracking = in_array($new_status_id, TASK_TRACKED_STATUS_IDS, true);
        // Subtarea en espera: el rollup pausa todo; el conteo reanuda al volver la tarea a Abierto.
        if ($entity_type === 'subtask' && $new_status_id === TASK_STATUS_WAITING) {
            $startTracking = false;
        }

        if ($startTracking) {
            taskStartStatusTracking($conn, $entity_type, $entity_id, $new_status_id, $audit, $reasonForDb);
        }

        if ($entity_type === 'task') {
            if ($new_status_id === TASK_STATUS_WAITING) {
                taskPauseAllSubtasksTracking($conn, $entity_id, $audit);
            } elseif ($old_status_id === TASK_STATUS_WAITING && $new_status_id === TASK_STATUS_OPEN) {
                taskResumeAllSubtasksTracking($conn, $entity_id, $audit);
            } elseif ($new_status_id === TASK_STATUS_CLOSED) {
                taskCloseAllOpenActivitiesForTask($conn, $entity_id, $audit);
            }
        }

        if ($entity_type === 'subtask' && $task_id_for_log) {
            if ($new_status_id === TASK_STATUS_WAITING) {
                taskRollupParentWaitingTracking(
                    $conn,
                    $task_id_for_log,
                    $audit,
                    $reasonForDb,
                    $subtask_title
                );
            } else {
                taskSyncParentWaitingAfterSubtaskChange($conn, $task_id_for_log, $audit);
            }
        }

        $oldName = getStatusName($conn, $old_status_id);
        $newName = getStatusName($conn, $new_status_id);
        if ($entity_type === 'subtask' && $subtask_title !== null) {
            $summary = sprintf(
                '%s cambió estado de %s → %s en actividad "%s"',
                $audit['username'],
                $oldName,
                $newName,
                $subtask_title
            );
        } else {
            $summary = sprintf(
                '%s cambió estado de %s → %s',
                $audit['username'],
                $oldName,
                $newName
            );
        }
        if ($reasonForDb) {
            $summary .= ' (motivo: ' . mb_substr($reasonForDb, 0, 120) . ')';
        }
        logTaskActivity(
            $conn,
            'status_change',
            $summary,
            $task_id_for_log ?: null,
            $subtask_id_for_log ?: null,
            $reasonForDb,
            $audit
        );

        $conn->commit();
        return ['ok' => true];
    } catch (Exception $e) {
        $conn->rollback();
        error_log('taskApplyStatusChange: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'No se pudo actualizar el estado.'];
    }
}

/**
 * Elimina lógicamente una tarea y detiene su tracking (y el de sus actividades).
 *
 * @return array{ok:bool, message?:string}
 */
function taskDeleteTask(mysqli $conn, int $task_id, ?array $audit = null): array
{
    if ($task_id <= 0) {
        return ['ok' => false, 'message' => 'Tarea no indicada.'];
    }

    if ($audit === null) {
        $audit = getTaskAuditUser();
    }

    $params = [$task_id];
    $types = 'i';
    $q = 'SELECT id, task_number FROM tasks WHERE id = ? AND deleted = 0';
    $q = addEmpresaFilter($q, 'id_empresa', $params, $types);
    $stmt = $conn->prepare($q);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Error al verificar la tarea.'];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$task) {
        return ['ok' => false, 'message' => 'Tarea no encontrada o sin permiso.'];
    }

    $conn->begin_transaction();
    try {
        taskEndStatusTracking($conn, 'task', $task_id, $audit);

        $subStmt = $conn->prepare('SELECT id FROM task_subtasks WHERE task_id = ?');
        if ($subStmt) {
            $subStmt->bind_param('i', $task_id);
            $subStmt->execute();
            $subRes = $subStmt->get_result();
            while ($sub = $subRes->fetch_assoc()) {
                taskEndStatusTracking($conn, 'subtask', (int) $sub['id'], $audit);
            }
            $subStmt->close();
        }

        $upd = $conn->prepare(
            'UPDATE tasks SET deleted = 1, updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ? AND deleted = 0'
        );
        if (!$upd) {
            throw new RuntimeException('No se pudo preparar la eliminación.');
        }
        $upd->bind_param('sii', $audit['username'], $audit['user_id'], $task_id);
        if (!$upd->execute() || $upd->affected_rows === 0) {
            $upd->close();
            throw new RuntimeException('No se pudo eliminar la tarea.');
        }
        $upd->close();

        logTaskActivity(
            $conn,
            'deleted',
            sprintf('%s eliminó la tarea %s', $audit['username'], $task['task_number']),
            $task_id,
            null,
            null,
            $audit
        );

        $conn->commit();
        return ['ok' => true, 'message' => 'Tarea eliminada correctamente.'];
    } catch (Exception $e) {
        $conn->rollback();
        error_log('taskDeleteTask: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'No se pudo eliminar la tarea.'];
    }
}

/**
 * Detiene el conteo de tiempo en todas las subtareas (tarea padre en espera).
 */
function taskPauseAllSubtasksTracking(mysqli $conn, int $task_id, array $audit): void
{
    $stmt = $conn->prepare('SELECT id FROM task_subtasks WHERE task_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $stmt->close();

    foreach ($ids as $subtaskId) {
        while (taskHasActiveTracking($conn, 'subtask', $subtaskId)) {
            taskEndStatusTracking($conn, 'subtask', $subtaskId, $audit);
        }
    }
}

/**
 * Reanuda el conteo en subtareas con estado medible cuando la tarea vuelve a Abierto.
 */
function taskResumeAllSubtasksTracking(mysqli $conn, int $task_id, array $audit): void
{
    if (taskIsParentWaitingPausingSubtasks($conn, $task_id)) {
        return;
    }

    $open = TASK_STATUS_OPEN;
    $waiting = TASK_STATUS_WAITING;
    $stmt = $conn->prepare(
        'SELECT id, status_id FROM task_subtasks WHERE task_id = ? AND status_id IN (?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iii', $task_id, $open, $waiting);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $subtaskId = (int) $row['id'];
        $statusId = (int) $row['status_id'];
        if (taskHasActiveTracking($conn, 'subtask', $subtaskId)) {
            continue;
        }
        $reason = null;
        if ($statusId === TASK_STATUS_WAITING) {
            $info = taskGetWaitingReasonInfo($conn, 'subtask', $subtaskId);
            $reason = $info['status_reason'] ?? null;
        }
        taskStartStatusTracking($conn, 'subtask', $subtaskId, $statusId, $audit, $reason);
    }
}

/**
 * Inicia conteo en la tarea padre cuando una subtarea entra en En atención / Espera (10).
 */
function taskRollupParentWaitingTracking(
    mysqli $conn,
    int $task_id,
    array $audit,
    ?string $subtask_reason,
    ?string $subtask_title
): void {
    $rollupReason = null;
    if ($subtask_title) {
        $rollupReason = 'Incluye actividad en espera: ' . $subtask_title;
        if ($subtask_reason) {
            $rollupReason .= ' — ' . $subtask_reason;
        }
    } elseif ($subtask_reason) {
        $rollupReason = $subtask_reason;
    }

    if (taskHasActiveTracking($conn, 'task', $task_id, TASK_STATUS_WAITING)) {
        taskPauseAllSubtasksTracking($conn, $task_id, $audit);
        return;
    }

    $t = $conn->prepare('SELECT status_id FROM tasks WHERE id = ? AND deleted = 0');
    $t->bind_param('i', $task_id);
    $t->execute();
    $taskRow = $t->get_result()->fetch_assoc();
    $t->close();
    $oldParentStatus = $taskRow ? (int) $taskRow['status_id'] : 0;

    taskEndStatusTracking($conn, 'task', $task_id, $audit);

    if ($oldParentStatus !== TASK_STATUS_WAITING) {
        taskRecordStatusChange($conn, 'task', $task_id, $oldParentStatus, TASK_STATUS_WAITING, $rollupReason, $audit);
        $upd = $conn->prepare(
            'UPDATE tasks SET status_id = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ?'
        );
        $waiting = TASK_STATUS_WAITING;
        $upd->bind_param('isii', $waiting, $audit['username'], $audit['user_id'], $task_id);
        $upd->execute();
        $upd->close();
    }

    taskStartStatusTracking($conn, 'task', $task_id, TASK_STATUS_WAITING, $audit, $rollupReason);

    logTaskActivity(
        $conn,
        'status_change',
        $audit['username'] . ' pasó la tarea a En atención / Espera (por actividad en espera)',
        $task_id,
        null,
        $rollupReason,
        $audit
    );

    taskPauseAllSubtasksTracking($conn, $task_id, $audit);
}

/**
 * Cierra tracking de espera en la tarea padre si ya no hay subtareas en estado 10.
 */
function taskSyncParentWaitingAfterSubtaskChange(mysqli $conn, int $task_id, array $audit): void
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM task_subtasks WHERE task_id = ? AND status_id = ?');
    $waiting = TASK_STATUS_WAITING;
    $stmt->bind_param('ii', $task_id, $waiting);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int) ($row['c'] ?? 0) > 0) {
        return;
    }

    $t = $conn->prepare('SELECT status_id FROM tasks WHERE id = ? AND deleted = 0');
    $t->bind_param('i', $task_id);
    $t->execute();
    $task = $t->get_result()->fetch_assoc();
    $t->close();

    if ($task && (int) $task['status_id'] === TASK_STATUS_WAITING) {
        return;
    }

    if (!taskHasActiveTracking($conn, 'task', $task_id, TASK_STATUS_WAITING)) {
        return;
    }

    taskEndStatusTracking($conn, 'task', $task_id, $audit);
    taskResumeAllSubtasksTracking($conn, $task_id, $audit);
}

/**
 * Tiempos de la tarea principal + suma de todas sus subtareas.
 */
function taskGetTaskAggregatedDurations(mysqli $conn, int $task_id): array
{
    $out = taskGetStatusDurations($conn, 'task', $task_id, $task_id);

    $parentPausesSubtasks = taskIsParentWaitingPausingSubtasks($conn, $task_id);
    $minuteExpr = $parentPausesSubtasks
        ? 'COALESCE(tst.duration_minutes, CASE WHEN tst.ended_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, tst.started_at, tst.ended_at) ELSE 0 END)'
        : 'COALESCE(tst.duration_minutes, TIMESTAMPDIFF(MINUTE, tst.started_at, COALESCE(tst.ended_at, NOW())))';

    $sql = "SELECT tst.status_id, SUM({$minuteExpr}) AS total_minutos
         FROM task_status_tracking tst
         INNER JOIN task_subtasks st ON st.id = tst.entity_id
         WHERE tst.entity_type = ? AND st.task_id = ?
         GROUP BY tst.status_id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $etype = 'subtask';
    $stmt->bind_param('si', $etype, $task_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $sid = (int) $r['status_id'];
        $out[$sid] = ($out[$sid] ?? 0) + (int) $r['total_minutos'];
    }
    return $out;
}

function taskGetStatusDurations(mysqli $conn, string $entity_type, int $entity_id, ?int $parent_task_id = null): array
{
    $freezeActive = $entity_type === 'subtask'
        && $parent_task_id !== null
        && taskIsParentWaitingPausingSubtasks($conn, $parent_task_id);

    if ($freezeActive) {
        $sql = 'SELECT status_id,
                SUM(COALESCE(duration_minutes, CASE WHEN ended_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, started_at, ended_at) ELSE 0 END)) AS total_minutos
         FROM task_status_tracking
         WHERE entity_type = ? AND entity_id = ?
         GROUP BY status_id';
    } else {
        $sql = 'SELECT status_id,
                SUM(COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW())))) AS total_minutos
         FROM task_status_tracking
         WHERE entity_type = ? AND entity_id = ?
         GROUP BY status_id';
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $entity_type, $entity_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['status_id']] = (int) $r['total_minutos'];
    }
    return $out;
}

/**
 * Motivo actual de En atención / Espera (período activo o último registro).
 *
 * @return array{status_reason:string,started_at:?string,started_by:?string}|null
 */
function taskGetWaitingReasonInfo(mysqli $conn, string $entity_type, int $entity_id): ?array
{
    $stmt = $conn->prepare(
        'SELECT status_reason, started_at, started_by
         FROM task_status_tracking
         WHERE entity_type = ? AND entity_id = ? AND status_id = ? AND is_active = 1
         ORDER BY started_at DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $waitingId = TASK_STATUS_WAITING;
    $stmt->bind_param('sii', $entity_type, $entity_id, $waitingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && trim((string) ($row['status_reason'] ?? '')) !== '') {
        return [
            'status_reason' => trim($row['status_reason']),
            'started_at'    => $row['started_at'],
            'started_by'    => $row['started_by'],
        ];
    }

    $stmt = $conn->prepare(
        'SELECT change_reason AS status_reason, created_at AS started_at, changed_by AS started_by
         FROM task_status_changes
         WHERE entity_type = ? AND entity_id = ? AND new_status_id = ?
           AND change_reason IS NOT NULL AND TRIM(change_reason) != ""
         ORDER BY created_at DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('sii', $entity_type, $entity_id, $waitingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || trim((string) ($row['status_reason'] ?? '')) === '') {
        return null;
    }

    return [
        'status_reason' => trim($row['status_reason']),
        'started_at'    => $row['started_at'],
        'started_by'    => $row['started_by'],
    ];
}
