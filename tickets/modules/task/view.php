<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_audit.php';
require_once '../../includes/task_status_tracking.php';
require_once '../../includes/task_dates_helper.php';
require_once '../../includes/task_rich_text_helper.php';

$task_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$hasDateColumns = taskTableHasDateColumns($conn);
if ($task_id <= 0) {
    header('Location: tasks.php');
    exit;
}

function taskViewFormatMinutes(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
}

function taskViewField(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '—') {
        return '<span class="task-view-field-value muted-empty">—</span>';
    }
    return '<div class="task-view-field-value">' . htmlspecialchars($value) . '</div>';
}

$params = [$task_id];
$types = 'i';
$q = "SELECT tk.*, s.name AS status_name,
      c.name AS category_name, cm.name AS contact_mode_name,
      l.name AS level_name, i.name AS impact_name, u.name AS urgency_name,
      p.name AS priority_name, r.name AS requester_name,
      cl.name AS classification_name, tech.name AS technician_name
      FROM tasks tk
      LEFT JOIN statuses s ON tk.status_id = s.id
      LEFT JOIN categories c ON tk.category_id = c.id
      LEFT JOIN contact_modes cm ON tk.contact_mode_id = cm.id
      LEFT JOIN levels l ON tk.level_id = l.id
      LEFT JOIN impacts i ON tk.impact_id = i.id
      LEFT JOIN urgencies u ON tk.urgency_id = u.id
      LEFT JOIN priorities p ON tk.priority_id = p.id
      LEFT JOIN requesters r ON tk.requester_id = r.id
      LEFT JOIN classifications cl ON tk.classification_id = cl.id
      LEFT JOIN technicians tech ON tk.technician_id = tech.id
      WHERE tk.id = ? AND tk.deleted = 0";
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

$isClosed = !empty($task['closed_at']);
$listUrl = $isClosed ? 'closed_tasks.php' : 'tasks.php';

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
    'SELECT f.* FROM task_subtask_feedback f WHERE f.subtask_id = ? ORDER BY f.created_at DESC'
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

$waitingPeriods = [];
$wp = $conn->prepare(
    "SELECT tst.*, st.title AS subtask_title
     FROM task_status_tracking tst
     LEFT JOIN task_subtasks st ON tst.entity_type = 'subtask' AND tst.entity_id = st.id
     WHERE tst.status_id = 10
       AND ((tst.entity_type = 'task' AND tst.entity_id = ?)
            OR (tst.entity_type = 'subtask' AND st.task_id = ?))
     ORDER BY tst.started_at DESC"
);
$wp->bind_param('ii', $task_id, $task_id);
$wp->execute();
$waitingPeriods = $wp->get_result()->fetch_all(MYSQLI_ASSOC);
$wp->close();

$taskDurations = taskGetTaskAggregatedDurations($conn, $task_id);
taskEnsureSubtasksTrackingPausedWhileParentWaiting($conn, $task_id, getTaskAuditUser());
$subtaskDurations = [];
foreach ($subtasks as $sub) {
    $subtaskDurations[(int) $sub['id']] = taskGetStatusDurations($conn, 'subtask', (int) $sub['id'], $task_id);
}

$subtaskClosed = 0;
foreach ($subtasks as $sub) {
    if ((int) $sub['status_id'] === 3) {
        $subtaskClosed++;
    }
}
$subtaskTotal = count($subtasks);
$progressPct = $subtaskTotal > 0 ? (int) round(($subtaskClosed / $subtaskTotal) * 100) : 0;
$taskStatusId = (int) $task['status_id'];

$subtasksLocked = taskIsParentWaitingPausingSubtasks($conn, $task_id);

$taskWaitingInfo = null;
if ($taskStatusId === TASK_STATUS_WAITING || $subtasksLocked) {
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
?>
<link rel="stylesheet" href="css/task-edit.css">
<link rel="stylesheet" href="css/task-view.css">
<link rel="stylesheet" href="css/task-quill.css">
<?php include '../../includes/modern-header.php'; ?>

<div class="body-bg">
<div class="container-fluid task-view-page">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <header class="task-edit-hero task-view-hero">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                    <div>
                        <div class="task-folio">Tarea · Vista</div>
                        <h1><?php echo htmlspecialchars($task['task_number']); ?></h1>
                        <?php if (!empty($task['subject'])): ?>
                        <p class="task-view-subject mb-2"><?php echo htmlspecialchars($task['subject']); ?></p>
                        <?php endif; ?>
                        <div class="hero-meta">
                            <span class="task-status-badge badge-status-<?php echo $taskStatusId; ?>">
                                <i class="fas fa-circle" style="font-size:0.45rem"></i>
                                <?php echo htmlspecialchars($task['status_name'] ?? ''); ?>
                            </span>
                            <?php if ($isClosed): ?>
                            <span class="task-status-badge badge-status-3">
                                <i class="fas fa-check" style="font-size:0.65rem"></i>
                                Cerrada
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($task['technician_name'])): ?>
                            <span><i class="fas fa-user-cog"></i> <?php echo htmlspecialchars($task['technician_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($subtaskTotal > 0): ?>
                            <span><i class="fas fa-layer-group"></i> <?php echo $subtaskClosed; ?>/<?php echo $subtaskTotal; ?> actividades</span>
                            <?php endif; ?>
                            <?php if ($hasDateColumns && !empty($task['start_date'])): ?>
                            <span><i class="fas fa-calendar-alt"></i> Inicio <?php echo htmlspecialchars(taskFormatDateDisplay($task['start_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($taskWaitingInfo): ?>
                        <div class="task-edit-hero-waiting-wrap">
                            <?php $waitingInfo = $taskWaitingInfo; include __DIR__ . '/includes/waiting_reason_display.php'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-actions">
                        <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn btn-hero btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Listado
                        </a>
                        <a href="edit.php?id=<?php echo $task_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i>Editar
                        </a>
                        <button type="button" class="btn btn-hero btn-sm btn-outline-danger"
                            onclick="eliminarTarea(<?php echo $task_id; ?>, <?php echo json_encode($task['task_number'], JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($listUrl, JSON_UNESCAPED_UNICODE); ?>)"
                            title="Eliminar tarea">
                            <i class="fas fa-trash-alt me-1"></i>Eliminar
                        </button>
                    </div>
                </div>
            </header>

            <div class="row g-4">
                <div class="col-lg-8">
                    <section class="task-panel">
                        <div class="task-panel-header">
                            <h2><i class="fas fa-file-alt"></i> Datos de la tarea</h2>
                        </div>
                        <div class="task-panel-body">
                            <div class="task-view-meta">
                                <div>
                                    <div class="task-view-field-label">Categoría</div>
                                    <?php echo taskViewField($task['category_name'] ?? null); ?>
                                </div>
                                <div>
                                    <div class="task-view-field-label">Solicitante</div>
                                    <?php echo taskViewField($task['requester_name'] ?? null); ?>
                                </div>
                                <div>
                                    <div class="task-view-field-label">Técnico responsable</div>
                                    <?php echo taskViewField($task['technician_name'] ?? null); ?>
                                </div>
                                <div>
                                    <div class="task-view-field-label">Prioridad</div>
                                    <?php echo taskViewField($task['priority_name'] ?? null); ?>
                                </div>
                                <?php if ($hasDateColumns): ?>
                                <div>
                                    <div class="task-view-field-label">Fecha de inicio</div>
                                    <?php echo taskViewField(!empty($task['start_date']) ? taskFormatDateDisplay($task['start_date']) : null); ?>
                                </div>
                                <div>
                                    <div class="task-view-field-label">Fecha de término</div>
                                    <?php echo taskViewField(!empty($task['end_date']) ? taskFormatDateDisplay($task['end_date']) : null); ?>
                                </div>
                                <?php endif; ?>
                                <!--<div class="meta-triple-wrap">
                                    <div>
                                        <div class="task-view-field-label">Nivel</div>
                                        <?php echo taskViewField($task['level_name'] ?? null); ?>
                                    </div>
                                    <div>
                                        <div class="task-view-field-label">Impacto</div>
                                        <?php echo taskViewField($task['impact_name'] ?? null); ?>
                                    </div>
                                    <div>
                                        <div class="task-view-field-label">Urgencia</div>
                                        <?php echo taskViewField($task['urgency_name'] ?? null); ?>
                                    </div>
                                </div>-->
                                <div class="span-full">
                                    <div class="task-view-field-label">Descripción</div>
                                    <div class="task-view-desc-box task-rich-content"><?php echo taskRenderRichDescription($task['description'] ?? ''); ?></div>
                                </div>
                                <?php if (!empty($task['impact_details'])): ?>
                                <div class="span-full">
                                    <div class="task-view-field-label">Detalle de impacto</div>
                                    <div class="task-view-desc-box"><?php echo nl2br(htmlspecialchars($task['impact_details'])); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($task['resolution'])): ?>
                                <div class="span-full">
                                    <div class="task-view-field-label">Resolución</div>
                                    <div class="task-view-desc-box"><?php echo nl2br(htmlspecialchars($task['resolution'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="task-view-audit">
                                <span><i class="fas fa-plus-circle"></i> Creada <?php echo htmlspecialchars($task['created_at']); ?>
                                    <?php if (!empty($task['created_by'])): ?> · <?php echo htmlspecialchars($task['created_by']); ?><?php endif; ?>
                                </span>
                                <?php if ($isClosed): ?>
                                <span><i class="fas fa-check-circle"></i> Cerrada <?php echo htmlspecialchars($task['closed_at']); ?>
                                    <?php if (!empty($task['closed_by'])): ?> · <?php echo htmlspecialchars($task['closed_by']); ?><?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="task-panel">
                        <div class="task-panel-header">
                            <h2><i class="fas fa-tasks"></i> Actividades</h2>
                            <?php if ($subtaskTotal > 0): ?>
                            <span class="small text-muted fw-semibold"><?php echo $progressPct; ?>% completado</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($subtasksLocked): ?>
                        <div class="alert alert-warning mx-3 mt-3 mb-0 small">
                            <i class="fas fa-pause-circle me-1"></i>
                            <?php echo htmlspecialchars(taskSubtaskWaitingPanelMessage()); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($subtaskTotal > 0): ?>
                        <div class="subtasks-progress-wrap">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span><?php echo $subtaskClosed; ?> de <?php echo $subtaskTotal; ?> cerradas</span>
                                <span><?php echo $progressPct; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progressPct; ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="task-panel-body">
                            <?php if (empty($subtasks)): ?>
                            <div class="task-view-empty">
                                <i class="fas fa-clipboard-list"></i>
                                <p class="mb-0">Sin actividades registradas.</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($subtasks as $sub):
                                $sid = (int) $sub['id'];
                                $feedbacks = $feedbackBySubtask[$sid] ?? [];
                                $dur = $subtaskDurations[$sid] ?? [];
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
                                    <div class="subtask-status-readonly">
                                        <span class="status-caption">Estado</span>
                                        <span class="task-status-badge badge-status-<?php echo $subStatusId; ?>">
                                            <?php echo htmlspecialchars($sub['status_name'] ?? ''); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="subtask-card-body">
                                    <div class="subtask-sections">
                                        <?php if ($subStatusId === TASK_STATUS_WAITING && !empty($subtaskWaitingInfo[$sid])): ?>
                                        <?php $waitingInfo = $subtaskWaitingInfo[$sid]; include __DIR__ . '/includes/waiting_reason_display.php'; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($sub['description'])): ?>
                                        <section class="subtask-section subtask-section-desc">
                                            <div class="subtask-section-label">
                                                <i class="fas fa-align-left"></i> Descripción / alcance
                                            </div>
                                            <div class="subtask-desc task-rich-content"><?php echo taskRenderRichDescription($sub['description'] ?? ''); ?></div>
                                        </section>
                                        <?php endif; ?>
                                        <?php if ($feedbacks): ?>
                                        <section class="subtask-section subtask-section-feedback">
                                            <div class="subtask-section-label">
                                                <i class="fas fa-comment-dots"></i> Retroalimentación
                                            </div>
                                            <div class="task-feedback-history" style="margin-top:0;padding-top:0;border-top:none;max-height:none">
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
                                        </section>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="task-view-subtask-times">
                                    <span class="task-time-chip chip-open">
                                        <i class="fas fa-folder-open"></i>
                                        Abierto <?php echo taskViewFormatMinutes((int)($dur[2] ?? 0)); ?>
                                    </span>
                                    <span class="task-time-chip chip-wait">
                                        <i class="fas fa-hourglass-half"></i>
                                        En espera <?php echo taskViewFormatMinutes((int)($dur[10] ?? 0)); ?>
                                    </span>
                                </div>
                            </article>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <?php if (!empty($waitingPeriods)): ?>
                    <section class="task-panel">
                        <div class="task-panel-header">
                            <h2><i class="fas fa-pause-circle"></i> Historial en espera</h2>
                        </div>
                        <ul class="task-waiting-history">
                            <?php foreach ($waitingPeriods as $wp): ?>
                            <li>
                                <div class="wh-title">
                                    <?php if ($wp['entity_type'] === 'subtask' && !empty($wp['subtask_title'])): ?>
                                    <i class="fas fa-wrench me-1 text-muted"></i>
                                    Actividad: <?php echo htmlspecialchars($wp['subtask_title']); ?>
                                    <?php else: ?>
                                    <i class="fas fa-tasks me-1 text-muted"></i>
                                    Tarea principal
                                    <?php endif; ?>
                                    <?php if (empty($wp['ended_at'])): ?>
                                    <span class="wh-active">En curso</span>
                                    <?php endif; ?>
                                </div>
                                <div class="wh-meta">
                                    <?php echo htmlspecialchars($wp['started_at']); ?>
                                    <?php if (!empty($wp['ended_at'])): ?>
                                    → <?php echo htmlspecialchars($wp['ended_at']); ?>
                                    <?php endif; ?>
                                    · <?php echo htmlspecialchars($wp['started_by']); ?>
                                </div>
                                <?php if (!empty($wp['status_reason'])): ?>
                                <div class="wh-reason"><?php echo nl2br(htmlspecialchars($wp['status_reason'])); ?></div>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <aside class="task-sidebar-sticky">
                        <div class="task-stat-card">
                            <h2 class="h6 mb-3 text-muted text-uppercase" style="font-size:0.7rem;letter-spacing:0.05em">Tiempos (tarea + actividades)</h2>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-folder-open text-info"></i> Abierto</span>
                                <span class="stat-value"><?php echo taskViewFormatMinutes((int)($taskDurations[2] ?? 0)); ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-hourglass-half text-warning"></i> En espera</span>
                                <span class="stat-value"><?php echo taskViewFormatMinutes((int)($taskDurations[10] ?? 0)); ?></span>
                            </div>
                        </div>

                        <div class="task-panel">
                            <div class="task-panel-header">
                                <h2><i class="fas fa-history"></i> Actividad reciente</h2>
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
                                        <div class="activity-time">por <?php echo htmlspecialchars($a['performed_by']); ?></div>
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

<?php include __DIR__ . '/includes/task_delete_script.php'; ?>
<?php include '../../includes/footer.php'; ?>
