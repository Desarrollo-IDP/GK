<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_audit.php';
require_once '../../includes/task_status_tracking.php';
require_once '../../includes/task_dates_helper.php';
require_once '../../includes/task_rich_text_helper.php';
include 'includes/task_catalogs.php';

$hasDateColumns = taskTableHasDateColumns($conn);

$task_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($task_id <= 0) {
    header('Location: tasks.php');
    exit;
}

$params = [$task_id];
$types = 'i';
$q = 'SELECT tk.*, s.name AS status_name, tech.name AS technician_name FROM tasks tk
      LEFT JOIN statuses s ON tk.status_id = s.id
      LEFT JOIN technicians tech ON tk.technician_id = tech.id
      WHERE tk.id = ? AND tk.deleted = 0';
$q = addEmpresaFilter($q, 'tk.id_empresa', $params, $types);
$stmt = $conn->prepare($q);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    header('Location: tasks.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task'])) {
    $audit = getTaskAuditUser();
    $subject = trim($_POST['subject'] ?? '');
    $description = taskSanitizeRichDescription($_POST['description'] ?? '');
    $technician_id = (int) ($_POST['technician_id'] ?? 0) ?: null;
    $start_date = $hasDateColumns ? taskNormalizeDateInput($_POST['start_date'] ?? '') : null;
    $end_date = $hasDateColumns ? taskNormalizeDateInput($_POST['end_date'] ?? '') : null;

    if (taskRichDescriptionIsEmpty($description)) {
        $message = 'La descripción es obligatoria.';
        $messageType = 'danger';
    } elseif ($start_date === false || $end_date === false) {
        $message = 'Use un formato de fecha válido (aaaa-mm-dd).';
        $messageType = 'danger';
    } elseif ($errDates = taskValidateDateRange($start_date, $end_date)) {
        $message = $errDates;
        $messageType = 'danger';
    } else {
        if ($hasDateColumns) {
            $upd = $conn->prepare(
                'UPDATE tasks SET subject = ?, description = ?, technician_id = ?,
                 start_date = ?, end_date = ?, updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ?'
            );
            $upd->bind_param('ssisssii', $subject, $description, $technician_id, $start_date, $end_date, $audit['username'], $audit['user_id'], $task_id);
        } else {
            $upd = $conn->prepare(
                'UPDATE tasks SET subject = ?, description = ?, technician_id = ?,
                 updated_at = NOW(), updated_by = ?, updated_by_user_id = ? WHERE id = ?'
            );
            $upd->bind_param('ssisii', $subject, $description, $technician_id, $audit['username'], $audit['user_id'], $task_id);
        }
        if ($upd->execute()) {
            logTaskActivity($conn, 'updated', $audit['username'] . ' actualizó la tarea', $task_id, null, null, $audit);
            $message = 'Tarea guardada.';
            $messageType = 'success';
            $task['subject'] = $subject;
            $task['description'] = $description;
            $task['technician_id'] = $technician_id;
            if ($hasDateColumns) {
                $task['start_date'] = $start_date;
                $task['end_date'] = $end_date;
            }
        }
        $upd->close();
    }
}

$subtasks = [];
$st = $conn->prepare(
    'SELECT st.*, s.name AS status_name, tech.name AS technician_name
     FROM task_subtasks st
     LEFT JOIN statuses s ON st.status_id = s.id
     LEFT JOIN technicians tech ON st.technician_id = tech.id
     WHERE st.task_id = ? ORDER BY st.sort_order, st.id'
);
$st->bind_param('i', $task_id);
$st->execute();
$subRes = $st->get_result();
while ($row = $subRes->fetch_assoc()) {
    $subtasks[] = $row;
}
$st->close();

$feedbackBySubtask = [];
$fbStmt = $conn->prepare(
    'SELECT f.*, tech.name AS technician_name FROM task_subtask_feedback f
     LEFT JOIN technicians tech ON f.technician_id = tech.id
     WHERE f.subtask_id = ? ORDER BY f.created_at DESC'
);
foreach ($subtasks as $sub) {
    $sid = (int) $sub['id'];
    $fbStmt->bind_param('i', $sid);
    $fbStmt->execute();
    $feedbackBySubtask[$sid] = $fbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$fbStmt->close();

$activity = [];
$act = $conn->prepare(
    'SELECT a.*, st.title AS subtask_title
     FROM task_activity_log a
     LEFT JOIN task_subtasks st ON st.id = a.subtask_id
     WHERE a.task_id = ?
     ORDER BY a.created_at DESC
     LIMIT 50'
);
$act->bind_param('i', $task_id);
$act->execute();
$activity = $act->get_result()->fetch_all(MYSQLI_ASSOC);
$act->close();

$taskDurations = taskGetTaskAggregatedDurations($conn, $task_id);
$taskDurationsOwn = taskGetStatusDurations($conn, 'task', $task_id, $task_id);
taskEnsureSubtasksTrackingPausedWhileParentWaiting($conn, $task_id, getTaskAuditUser());
$statuses->data_seek(0);

function taskEditFormatMinutes(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
}

$subtaskClosed = 0;
foreach ($subtasks as $sub) {
    if ((int) $sub['status_id'] === 3) {
        $subtaskClosed++;
    }
}
$subtaskTotal = count($subtasks);
$progressPct = $subtaskTotal > 0 ? (int) round(($subtaskClosed / $subtaskTotal) * 100) : 0;
$listUrl = !empty($task['closed_at']) ? 'closed_tasks.php' : 'tasks.php';
$taskStatusId = (int) $task['status_id'];
$subtasksLocked = taskIsParentWaitingPausingSubtasks($conn, $task_id);

$taskWaitingInfo = null;
if ($taskStatusId === TASK_STATUS_WAITING) {
    $taskWaitingInfo = taskGetWaitingReasonInfo($conn, 'task', $task_id);
}

$subtaskWaitingInfo = [];
foreach ($subtasks as $sub) {
    if ((int) $sub['status_id'] === TASK_STATUS_WAITING) {
        $info = taskGetWaitingReasonInfo($conn, 'subtask', (int) $sub['id']);
        if ($info) {
            $subtaskWaitingInfo[(int) $sub['id']] = $info;
        }
    }
}

$openActivities = [];
foreach ($subtasks as $sub) {
    if ((int) $sub['status_id'] !== TASK_STATUS_CLOSED) {
        $openActivities[] = [
            'id' => (int) $sub['id'],
            'title' => $sub['title'],
            'status_name' => $sub['status_name'] ?? '',
        ];
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<link rel="stylesheet" href="css/task-edit.css">

<div class="body-bg">
<div class="container-fluid task-edit-page">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <header class="task-edit-hero">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                    <div>
                        <div class="task-folio">Tarea · Edición</div>
                        <h1><?php echo htmlspecialchars($task['task_number']); ?></h1>
                        <div class="hero-meta">
                            <span class="task-status-badge badge-status-<?php echo $taskStatusId; ?>">
                                <i class="fas fa-circle" style="font-size:0.45rem"></i>
                                <?php echo htmlspecialchars($task['status_name'] ?? ''); ?>
                            </span>
                            <?php if (!empty($task['technician_name'])): ?>
                            <span><i class="fas fa-user-cog"></i> <?php echo htmlspecialchars($task['technician_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($subtaskTotal > 0): ?>
                            <span><i class="fas fa-layer-group"></i> <?php echo $subtaskClosed; ?>/<?php echo $subtaskTotal; ?> actividades</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($taskWaitingInfo): ?>
                        <div class="task-edit-hero-waiting-wrap">
                            <?php $waitingInfo = $taskWaitingInfo; include __DIR__ . '/includes/waiting_reason_display.php'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-actions">
                        <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-hero btn-sm">
                            <i class="fas fa-eye me-1"></i>Ver
                        </a>
                        <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn btn-hero btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Listado
                        </a>
                        <button type="button" class="btn btn-hero btn-sm btn-outline-danger btn-delete-task"
                            data-task-id="<?php echo $task_id; ?>"
                            data-folio="<?php echo htmlspecialchars($task['task_number'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-redirect="<?php echo htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            title="Eliminar tarea">
                            <i class="fas fa-trash-alt me-1"></i>Eliminar
                        </button>
                        <div class="status-control-wrap">
                            <label for="task_status_select">Estado</label>
                            <select id="task_status_select" class="form-select form-select-sm task-status-select"
                                data-entity-type="task" data-entity-id="<?php echo $task_id; ?>" data-task-id="<?php echo $task_id; ?>">
                                <?php $statuses->data_seek(0); while ($s = $statuses->fetch_assoc()): ?>
                                <option value="<?php echo (int)$s['id']; ?>" <?php echo $taskStatusId === (int)$s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <?php if (!$hasDateColumns): ?>
            <div class="alert alert-warning">
                Ejecute <code>tickets/sql/alter_tasks_add_dates.sql</code> para habilitar fechas de inicio y término.
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <form method="post" class="task-panel task-panel--task-data">
                        <div class="task-panel-header">
                            <h2><i class="fas fa-file-alt"></i> Datos de la tarea</h2>
                        </div>
                        <div class="task-panel-body">
                            <div class="task-form-grid task-form-grid--meta">
                                <div class="span-full">
                                    <label class="task-form-label" for="subject">Asunto</label>
                                    <input type="text" name="subject" id="subject" class="form-control"
                                        value="<?php echo htmlspecialchars($task['subject'] ?? ''); ?>"
                                        placeholder="Título breve de la tarea">
                                </div>
                                <div>
                                    <label class="task-form-label" for="technician_id">Técnico responsable</label>
                                    <select name="technician_id" id="technician_id" class="form-select">
                                        <option value="">Sin asignar</option>
                                        <?php $technicians->data_seek(0); while ($t = $technicians->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)($task['technician_id'] ?? 0) === (int)$t['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['name']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <?php if ($hasDateColumns): ?>
                                <div>
                                    <label class="task-form-label" for="start_date">Fecha de inicio</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control"
                                        value="<?php echo htmlspecialchars(taskFormatDateForInput($task['start_date'] ?? null)); ?>">
                                </div>
                                <div>
                                    <label class="task-form-label" for="end_date">Fecha de término</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control"
                                        value="<?php echo htmlspecialchars(taskFormatDateForInput($task['end_date'] ?? null)); ?>">
                                </div>
                                <?php endif; ?>
                                <div>
                                    <label class="task-form-label">Creada</label>
                                    <input type="text" class="form-control bg-light" readonly
                                        value="<?php echo htmlspecialchars($task['created_at'] . ($task['created_by'] ? ' · ' . $task['created_by'] : '')); ?>">
                                </div>
                            </div>
                            <div class="task-description-block">
                                <?php
                                $value = $task['description'] ?? '';
                                $required = true;
                                $wrapperClass = 'task-description-quill';
                                include __DIR__ . '/includes/quill_description_field.php';
                                ?>
                            </div>
                        </div>
                        <div class="task-panel-footer task-panel-footer--save">
                            <button type="submit" name="save_task" value="1" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Guardar cambios
                            </button>
                        </div>
                    </form>

                    <section class="task-panel<?php echo $subtasksLocked ? ' task-subtasks-locked' : ''; ?>">
                        <div class="task-panel-header">
                            <h2><i class="fas fa-tasks"></i> Actividades</h2>
                            <button type="button" class="btn btn-primary btn-sm btn-toggle-subtask-panel" id="btnToggleNewSubtask"
                                data-bs-toggle="collapse" data-bs-target="#newSubtaskPanel" aria-expanded="false" aria-controls="newSubtaskPanel">
                                <i class="fas fa-plus me-1 btn-icon-plus"></i>
                                <i class="fas fa-chevron-down ms-1 btn-icon-chevron" style="font-size:0.7rem"></i>
                                <span class="btn-label">Nueva actividad</span>
                            </button>
                        </div>
                        <?php if ($subtasksLocked): ?>
                        <div class="alert alert-warning mx-3 mt-3 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo htmlspecialchars(taskSubtaskWaitingPanelMessage()); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($subtaskTotal > 0): ?>
                        <div class="subtasks-progress-wrap">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Progreso</span>
                                <span><?php echo $progressPct; ?>% completado</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progressPct; ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="collapse" id="newSubtaskPanel">
                            <div class="subtask-new-panel">
                                <div class="panel-title"><i class="fas fa-plus-circle"></i> Agregar actividad</div>
                                <div class="subtask-new-form">
                                    <div class="row g-3 subtask-new-meta">
                                        <div class="col-md-6">
                                            <label class="task-form-label" for="new_subtask_title">Título *</label>
                                            <input type="text" id="new_subtask_title" class="form-control subtask-new-input"
                                                placeholder="Ej. Configurar firewall">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="task-form-label" for="new_subtask_technician">Técnico *</label>
                                            <select id="new_subtask_technician" class="form-select subtask-new-input">
                                                <option value="">Seleccionar…</option>
                                                <?php $technicians->data_seek(0); while ($t = $technicians->fetch_assoc()): ?>
                                                <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="task-form-label" for="new_subtask_status">Estado inicial</label>
                                            <select id="new_subtask_status" class="form-select subtask-new-input">
                                                <?php $statuses->data_seek(0); while ($s = $statuses->fetch_assoc()): if ((int)$s['id'] === 3) continue; ?>
                                                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="subtask-new-desc-block">
                                        <?php
                                        $fieldId = 'new_subtask_desc';
                                        $fieldName = 'description';
                                        $label = 'Descripción (opcional)';
                                        $value = '';
                                        $required = false;
                                        $wrapperClass = 'subtask-new-desc-quill';
                                        $placeholder = 'Alcance o notas de la actividad…';
                                        $deferQuillInit = true;
                                        include __DIR__ . '/includes/quill_description_field.php';
                                        unset($deferQuillInit);
                                        ?>
                                    </div>
                                    <div class="subtask-new-actions">
                                        <button type="button" class="btn btn-success subtask-new-submit-btn" id="btnCreateSubtask">
                                            <i class="fas fa-check me-1"></i>Crear actividad
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="task-panel-body">
                            <?php if (empty($subtasks)): ?>
                            <div class="task-empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <p class="mb-2">Aún no hay actividades</p>
                                <p class="small mb-2">Use <strong>Nueva actividad</strong> para asignar trabajo a cada técnico.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm btn-toggle-subtask-panel"
                                    data-bs-toggle="collapse" data-bs-target="#newSubtaskPanel" aria-expanded="false">
                                    <i class="fas fa-plus me-1"></i>Abrir nueva actividad
                                </button>
                            </div>
                            <?php else: ?>
                            <?php foreach ($subtasks as $sub):
                                $sid = (int) $sub['id'];
                                $feedbacks = $feedbackBySubtask[$sid] ?? [];
                                $subStatusId = (int) $sub['status_id'];
                            ?>
                            <article class="task-subtask-card" data-subtask-id="<?php echo $sid; ?>">
                                <div class="subtask-card-head">
                                    <div class="subtask-title-wrap">
                                        <div class="subtask-icon"><i class="fas fa-wrench"></i></div>
                                        <div>
                                            <h3 class="subtask-title"><?php echo htmlspecialchars($sub['title']); ?></h3>
                                            <span class="subtask-tech">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($sub['technician_name'] ?? 'Sin técnico'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="subtask-status-wrap">
                                        <label>Estado</label>
                                        <select class="form-select form-select-sm subtask-status-select"
                                            data-entity-type="subtask" data-entity-id="<?php echo $sid; ?>" data-task-id="<?php echo $task_id; ?>"
                                            <?php echo $subtasksLocked ? 'disabled title="Cambie la tarea a Abierto para editar actividades"' : ''; ?>>
                                            <?php $statuses->data_seek(0); while ($s = $statuses->fetch_assoc()): ?>
                                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $subStatusId === (int)$s['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="subtask-card-body">
                                    <div class="subtask-sections">
                                        <?php if ($subStatusId === TASK_STATUS_WAITING && !empty($subtaskWaitingInfo[$sid])): ?>
                                        <?php $waitingInfo = $subtaskWaitingInfo[$sid]; include __DIR__ . '/includes/waiting_reason_display.php'; ?>
                                        <?php endif; ?>

                                        <section class="subtask-section subtask-section-desc">
                                            <div class="subtask-section-label">
                                                <i class="fas fa-align-left"></i>
                                                Descripción / alcance
                                            </div>
                                            <?php if ($subtasksLocked): ?>
                                            <div class="subtask-desc task-rich-content"><?php echo taskRenderRichDescription($sub['description'] ?? ''); ?></div>
                                            <?php else: ?>
                                            <?php
                                            $fieldId = 'subtask_desc_' . $sid;
                                            $fieldName = 'description';
                                            $value = $sub['description'] ?? '';
                                            $required = false;
                                            $showLabel = false;
                                            $deferQuillInit = false;
                                            $wrapperClass = 'subtask-desc-editor';
                                            $placeholder = 'Alcance, entregables o notas de la actividad…';
                                            include __DIR__ . '/includes/quill_description_field.php';
                                            ?>
                                            <div class="task-feedback-actions mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-save-subtask-desc"
                                                    data-subtask-id="<?php echo $sid; ?>"
                                                    data-field-id="<?php echo htmlspecialchars($fieldId); ?>">
                                                    <i class="fas fa-save me-1"></i>Guardar descripción
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </section>

                                        <section class="subtask-section subtask-section-feedback">
                                            <div class="subtask-section-label">
                                                <i class="fas fa-comment-dots"></i>
                                                Retroalimentación / lo que hice
                                                <span class="text-danger" title="Obligatorio al cerrar">*</span>
                                            </div>
                                            <p class="subtask-section-hint">Obligatoria al cambiar el estado a <strong>Cerrado</strong>.</p>
                                            <div class="task-feedback-box">
                                                <textarea class="form-control feedback-input" rows="3"
                                                    placeholder="Describe la solución, avance o hallazgos…"
                                                    <?php echo $subtasksLocked ? 'disabled' : ''; ?>></textarea>
                                                <div class="task-feedback-actions">
                                                    <button type="button" class="btn btn-sm btn-primary btn-save-feedback" data-subtask-id="<?php echo $sid; ?>"
                                                        <?php echo $subtasksLocked ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-paper-plane me-1"></i>Guardar retroalimentación
                                                    </button>
                                                </div>
                                                <?php if ($feedbacks): ?>
                                                <div class="task-feedback-history">
                                                    <div class="task-feedback-history-title">Historial</div>
                                                    <?php foreach ($feedbacks as $f): ?>
                                                    <div class="task-feedback-item">
                                                        <div class="feedback-meta">
                                                            <?php echo htmlspecialchars($f['created_at']); ?>
                                                            · <strong><?php echo htmlspecialchars($f['created_by']); ?></strong>
                                                        </div>
                                                        <?php echo nl2br(htmlspecialchars($f['work_feedback'])); ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="task-sidebar-sticky">
                        <div class="task-stat-card">
                            <h2 class="h6 mb-3 text-muted text-uppercase" style="font-size:0.7rem;letter-spacing:0.05em">Tiempos (tarea + actividades)</h2>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-folder-open text-info"></i> Abierto</span>
                                <span class="stat-value"><?php echo taskEditFormatMinutes((int)($taskDurations[2] ?? 0)); ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-hourglass-half text-warning"></i> En espera</span>
                                <span class="stat-value"><?php echo taskEditFormatMinutes((int)($taskDurations[10] ?? 0)); ?></span>
                            </div>
                            <?php if ($subtasksLocked): ?>
                            <p class="small text-muted mb-0 mt-2" style="font-size:0.72rem">
                                <i class="fas fa-info-circle me-1"></i>
                                Tiempos de actividades en pausa hasta volver la tarea a Abierto.
                            </p>
                            <?php endif; ?>
                            <!--<?php if (($taskDurations[10] ?? 0) > ($taskDurationsOwn[10] ?? 0)): ?>
                            <p class="small text-muted mb-0 mt-2" style="font-size:0.72rem">
                                Incluye tiempo de actividades en espera.
                                Tarea sola: <?php echo taskEditFormatMinutes((int)($taskDurationsOwn[10] ?? 0)); ?>
                            </p>
                            <?php endif; ?>-->
                        </div>
                        <div class="task-panel">
                            <div class="task-panel-header">
                                <h2><i class="fas fa-history"></i> Actividad</h2>
                            </div>
                            <div class="task-panel-body p-0">
                                <?php if (empty($activity)): ?>
                                <p class="text-muted small p-3 mb-0">Sin registros de actividad.</p>
                                <?php else: ?>
                                <ul class="task-activity-timeline p-3 pt-2">
                                    <?php foreach ($activity as $a):
                                        $activityText = $a['summary'];
                                        if (
                                            !empty($a['subtask_id'])
                                            && $a['action'] === 'status_change'
                                            && !empty($a['subtask_title'])
                                            && stripos($activityText, 'subtarea') === false
                                            && stripos($activityText, 'actividad') === false
                                        ) {
                                            $activityText .= ' en actividad "' . $a['subtask_title'] . '"';
                                        }
                                        $actionClass = preg_replace('/[^a-z_]/', '', $a['action'] ?? 'updated');
                                    ?>
                                    <li class="action-<?php echo htmlspecialchars($actionClass); ?>">
                                        <div class="activity-time"><?php echo htmlspecialchars($a['created_at']); ?></div>
                                        <div class="activity-text"><?php echo htmlspecialchars($activityText); ?></div>
                                        <?php if (!empty($a['performed_by'])): ?>
                                        <div class="activity-by">por <?php echo htmlspecialchars($a['performed_by']); ?></div>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<?php include 'includes/modal_status_10.php'; ?>
<?php include 'includes/modal_close_feedback.php'; ?>
<?php include __DIR__ . '/includes/quill_assets.php'; ?>

<script>
const TASK_ID = <?php echo $task_id; ?>;
const SUBTASKS_LOCKED = <?php echo $subtasksLocked ? 'true' : 'false'; ?>;
const SUBTASKS_LOCKED_MSG = <?php echo json_encode(taskSubtaskEditBlockedMessage(), JSON_UNESCAPED_UNICODE); ?>;
const STATUS_WAITING = 10;
const STATUS_CLOSED = 3;
const MIN_CLOSE_FEEDBACK = 5;
const OPEN_ACTIVITIES = <?php echo json_encode($openActivities, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
const CURRENT_USER_NAME = <?php echo json_encode(getUserName(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

let pendingStatusChange = null;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function postStatus(entityType, entityId, newStatusId, changeReason, taskId, subtaskId, workFeedback) {
    const fd = new FormData();
    fd.append('entity_type', entityType);
    fd.append('entity_id', entityId);
    fd.append('new_status_id', newStatusId);
    if (changeReason) fd.append('change_reason', changeReason);
    if (workFeedback) fd.append('work_feedback', workFeedback);
    if (taskId) fd.append('task_id', taskId);
    if (subtaskId) fd.append('subtask_id', subtaskId);
    return fetch('task_change_status.php', { method: 'POST', body: fd }).then(r => r.json());
}

function getSubtaskFeedbackFromSelect(sel) {
    const card = sel.closest('[data-subtask-id]');
    const input = card ? card.querySelector('.feedback-input') : null;
    return input ? input.value.trim() : '';
}

function openCloseFeedbackModal(pending, prefilled) {
    const ctx = document.getElementById('close_feedback_context');
    const warnEl = document.getElementById('close_open_activities_warning');
    const listEl = document.getElementById('close_open_activities_list');
    const modalTitle = document.getElementById('modalCloseFeedbackLabel');
    const confirmBtn = document.getElementById('btnConfirmClose');

    if (pending.entityType === 'subtask') {
        const card = pending.sel.closest('[data-subtask-id]');
        const title = card ? card.querySelector('.subtask-title') : null;
        const name = title ? title.textContent.trim() : 'actividad';
        ctx.innerHTML = 'Cerrando actividad: <strong>' + escapeHtml(name) + '</strong>. Registrado por: <strong>' + escapeHtml(CURRENT_USER_NAME) + '</strong>';
        if (warnEl) warnEl.classList.add('d-none');
        if (listEl) listEl.innerHTML = '';
        if (modalTitle) modalTitle.textContent = 'Cerrar actividad — retroalimentación requerida';
        if (confirmBtn) confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Guardar y cerrar';
    } else {
        ctx.innerHTML = 'Cerrando la tarea principal. Registrado por: <strong>' + escapeHtml(CURRENT_USER_NAME) + '</strong>';
        if (OPEN_ACTIVITIES.length > 0 && warnEl && listEl) {
            listEl.innerHTML = OPEN_ACTIVITIES.map((a) => {
                const status = a.status_name ? ' <span class="text-muted">(' + escapeHtml(a.status_name) + ')</span>' : '';
                return '<li><strong>' + escapeHtml(a.title) + '</strong>' + status + '</li>';
            }).join('');
            warnEl.classList.remove('d-none');
            if (modalTitle) modalTitle.textContent = 'Cerrar tarea — actividades abiertas';
            if (confirmBtn) confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Cerrar tarea y actividades';
        } else {
            if (warnEl) warnEl.classList.add('d-none');
            if (listEl) listEl.innerHTML = '';
            if (modalTitle) modalTitle.textContent = 'Cerrar tarea — retroalimentación requerida';
            if (confirmBtn) confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Guardar y cerrar';
        }
    }
    document.getElementById('close_feedback_input').value = prefilled || '';
    document.getElementById('close_feedback_input').classList.remove('is-invalid');
    document.getElementById('close_feedback_error').textContent = '';
    pendingStatusChange = pending;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCloseFeedback')).show();
}

function bindStatusSelect(sel) {
    const prev = sel.value;
    sel.addEventListener('change', function() {
        const entityType = this.dataset.entityType;
        if (SUBTASKS_LOCKED && entityType === 'subtask') {
            this.value = prev;
            alert(SUBTASKS_LOCKED_MSG);
            return;
        }
        const newVal = parseInt(this.value, 10);
        const entityId = parseInt(this.dataset.entityId, 10);
        const taskId = parseInt(this.dataset.taskId, 10);
        const subtaskId = entityType === 'subtask' ? entityId : null;

        if (newVal === STATUS_WAITING) {
            pendingStatusChange = { sel, prev, entityType, entityId, newVal, taskId, subtaskId };
            document.getElementById('waiting_reason_input').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalStatus10')).show();
            this.value = prev;
            return;
        }

        if (newVal === STATUS_CLOSED) {
            const inlineFeedback = entityType === 'subtask' ? getSubtaskFeedbackFromSelect(this) : '';
            if (inlineFeedback.length >= MIN_CLOSE_FEEDBACK) {
                postStatus(entityType, entityId, newVal, null, taskId, subtaskId, inlineFeedback).then(data => {
                    if (!data.ok) { alert(data.message || 'Error'); this.value = prev; }
                    else location.reload();
                });
                return;
            }
            openCloseFeedbackModal({ sel: this, prev, entityType, entityId, newVal, taskId, subtaskId }, inlineFeedback);
            this.value = prev;
            return;
        }

        postStatus(entityType, entityId, newVal, null, taskId, subtaskId).then(data => {
            if (!data.ok) { alert(data.message || 'Error'); this.value = prev; }
            else location.reload();
        });
    });
}

document.querySelectorAll('.task-status-select, .subtask-status-select').forEach(bindStatusSelect);

document.getElementById('btnConfirmStatus10')?.addEventListener('click', function() {
    const reason = document.getElementById('waiting_reason_input').value.trim();
    if (reason.length < 10) {
        document.getElementById('waiting_reason_error').textContent = 'Mínimo 10 caracteres.';
        document.getElementById('waiting_reason_input').classList.add('is-invalid');
        return;
    }
    document.getElementById('waiting_reason_input').classList.remove('is-invalid');
    if (!pendingStatusChange) return;
    const p = pendingStatusChange;
    postStatus(p.entityType, p.entityId, p.newVal, reason, p.taskId, p.subtaskId).then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalStatus10')).hide();
        if (!data.ok) alert(data.message || 'Error');
        else location.reload();
    });
});

document.getElementById('btnConfirmClose')?.addEventListener('click', function() {
    const feedback = document.getElementById('close_feedback_input').value.trim();
    const errEl = document.getElementById('close_feedback_error');
    const inputEl = document.getElementById('close_feedback_input');
    if (feedback.length < MIN_CLOSE_FEEDBACK) {
        errEl.textContent = 'Mínimo ' + MIN_CLOSE_FEEDBACK + ' caracteres de retroalimentación.';
        inputEl.classList.add('is-invalid');
        return;
    }
    inputEl.classList.remove('is-invalid');
    errEl.textContent = '';
    if (!pendingStatusChange) return;
    const p = pendingStatusChange;
    const btn = this;
    btn.disabled = true;
    postStatus(p.entityType, p.entityId, p.newVal, null, p.taskId, p.subtaskId, feedback).then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalCloseFeedback')).hide();
        if (!data.ok) {
            alert(data.message || 'No se pudo cerrar. Registre la retroalimentación.');
            p.sel.value = p.prev;
        } else {
            location.reload();
        }
    }).finally(() => { btn.disabled = false; });
});

function setBtnLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    btn.classList.toggle('is-loading', loading);
    const icon = btn.querySelector('i');
    if (loading && icon) {
        icon.dataset.prevClass = icon.className;
        icon.className = 'fas fa-spinner fa-spin me-1';
    } else if (icon && icon.dataset.prevClass) {
        icon.className = icon.dataset.prevClass;
    }
}

(function() {
    const panel = document.getElementById('newSubtaskPanel');
    const toggles = document.querySelectorAll('.btn-toggle-subtask-panel[data-bs-target="#newSubtaskPanel"]');
    if (!panel) return;
    panel.addEventListener('show.bs.collapse', function() {
        toggles.forEach(btn => {
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-open');
            const label = btn.querySelector('.btn-label');
            if (label) label.textContent = 'Ocultar panel';
        });
        setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 350);
    });
    panel.addEventListener('shown.bs.collapse', function() {
        if (window.TaskQuill) {
            panel.querySelectorAll('.task-quill-field[data-defer-init]').forEach((wrap) => {
                delete wrap.dataset.deferInit;
            });
            window.TaskQuill.initAll();
        }
    });
    panel.addEventListener('hide.bs.collapse', function() {
        toggles.forEach(btn => {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-open');
            const label = btn.querySelector('.btn-label');
            if (label) label.textContent = 'Nueva actividad';
        });
    });
})();

document.getElementById('btnCreateSubtask')?.addEventListener('click', function() {
    const btn = this;
    const title = document.getElementById('new_subtask_title').value.trim();
    const tech = document.getElementById('new_subtask_technician').value;
    if (!title || !tech) {
        alert('Indique título y técnico de la actividad.');
        return;
    }
    setBtnLoading(btn, true);
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('task_id', TASK_ID);
    fd.append('title', title);
    fd.append('description', window.TaskQuill ? window.TaskQuill.syncById('new_subtask_desc') : document.getElementById('new_subtask_desc').value);
    fd.append('technician_id', tech);
    fd.append('status_id', document.getElementById('new_subtask_status').value);
    fetch('process_subtask.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) alert(data.message || 'Error');
            else location.reload();
        })
        .finally(() => setBtnLoading(btn, false));
});

document.querySelectorAll('.btn-save-subtask-desc').forEach(btn => {
    btn.addEventListener('click', function() {
        if (SUBTASKS_LOCKED) {
            alert(SUBTASKS_LOCKED_MSG);
            return;
        }
        const fieldId = this.dataset.fieldId;
        const subtaskId = this.dataset.subtaskId;
        const description = window.TaskQuill ? window.TaskQuill.syncById(fieldId) : (document.getElementById(fieldId)?.value || '');
        setBtnLoading(this, true);
        const fd = new FormData();
        fd.append('action', 'update_description');
        fd.append('task_id', TASK_ID);
        fd.append('subtask_id', subtaskId);
        fd.append('description', description);
        fetch('process_subtask.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) alert(data.message || 'Error');
                else location.reload();
            })
            .finally(() => setBtnLoading(this, false));
    });
});

document.querySelectorAll('.btn-save-feedback').forEach(btn => {
    btn.addEventListener('click', function() {
        if (SUBTASKS_LOCKED) {
            alert(SUBTASKS_LOCKED_MSG);
            return;
        }
        const wrap = this.closest('[data-subtask-id]');
        const text = wrap.querySelector('.feedback-input').value.trim();
        if (text.length < 5) {
            alert('Escriba al menos 5 caracteres de retroalimentación.');
            return;
        }
        setBtnLoading(this, true);
        const fd = new FormData();
        fd.append('subtask_id', this.dataset.subtaskId);
        fd.append('task_id', TASK_ID);
        fd.append('work_feedback', text);
        fetch('task_feedback.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) alert(data.message || 'Error');
                else location.reload();
            })
            .finally(() => setBtnLoading(this, false));
    });
});
</script>

<?php if ($hasDateColumns): ?>
<script>
(function() {
    const startDateEl = document.getElementById('start_date');
    const endDateEl = document.getElementById('end_date');
    if (!startDateEl || !endDateEl) return;
    startDateEl.addEventListener('change', function() {
        if (this.value) endDateEl.min = this.value;
    });
    if (startDateEl.value) endDateEl.min = startDateEl.value;
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/task_delete_script.php'; ?>
<?php include '../../includes/footer.php'; ?>
