<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_counter_helper.php';
require_once '../../includes/task_audit.php';
require_once '../../includes/task_status_tracking.php';
require_once '../../includes/task_dates_helper.php';
require_once '../../includes/task_rich_text_helper.php';
require_once '../../includes/task_mailer_helper.php';
include 'includes/task_catalogs.php';

$message = '';
$messageType = '';
$hasDateColumns = taskTableHasDateColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = taskSanitizeRichDescription($_POST['description'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0) ?: null;
    $status_id = (int) ($_POST['status_id'] ?? 2);
    $contact_mode_id = (int) ($_POST['contact_mode_id'] ?? 0) ?: null;
    $level_id = (int) ($_POST['level_id'] ?? 0) ?: null;
    $impact_id = (int) ($_POST['impact_id'] ?? 0) ?: null;
    $urgency_id = (int) ($_POST['urgency_id'] ?? 0) ?: null;
    $priority_id = (int) ($_POST['priority_id'] ?? 0) ?: null;
    $impact_details = trim($_POST['impact_details'] ?? '');
    $requester_id = (int) ($_POST['requester_id'] ?? 0) ?: null;
    $classification_id = (int) ($_POST['classification_id'] ?? 0) ?: null;
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
        $audit = getTaskAuditUser();
        $user_empresa_id = getCurrentUserEmpresaId();
        $task_number = getNextTaskNumber($conn, $user_empresa_id);

        if ($hasDateColumns) {
            $stmt = $conn->prepare(
                'INSERT INTO tasks (
                    task_number, subject, category_id, status_id, contact_mode_id, level_id,
                    impact_id, urgency_id, priority_id, impact_details, requester_id,
                    classification_id, technician_id, description, start_date, end_date,
                    id_empresa, created_by, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssiiiiiiisiiisssisi',
                $task_number,
                $subject,
                $category_id,
                $status_id,
                $contact_mode_id,
                $level_id,
                $impact_id,
                $urgency_id,
                $priority_id,
                $impact_details,
                $requester_id,
                $classification_id,
                $technician_id,
                $description,
                $start_date,
                $end_date,
                $user_empresa_id,
                $audit['username'],
                $audit['user_id']
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO tasks (
                    task_number, subject, category_id, status_id, contact_mode_id, level_id,
                    impact_id, urgency_id, priority_id, impact_details, requester_id,
                    classification_id, technician_id, description, id_empresa,
                    created_by, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssiiiiiiisiiisisi',
                $task_number,
                $subject,
                $category_id,
                $status_id,
                $contact_mode_id,
                $level_id,
                $impact_id,
                $urgency_id,
                $priority_id,
                $impact_details,
                $requester_id,
                $classification_id,
                $technician_id,
                $description,
                $user_empresa_id,
                $audit['username'],
                $audit['user_id']
            );
        }

        if ($stmt->execute()) {
            $task_id = (int) $conn->insert_id;
            $stmt->close();
            logTaskActivity(
                $conn,
                'created',
                sprintf('%s creó la tarea %s', $audit['username'], $task_number),
                $task_id,
                null,
                null,
                $audit
            );
            if (in_array($status_id, TASK_TRACKED_STATUS_IDS, true)) {
                taskStartStatusTracking($conn, 'task', $task_id, $status_id, $audit, null);
                taskRecordStatusChange($conn, 'task', $task_id, null, $status_id, null, $audit);
            }
            if ($status_id === TASK_STATUS_WAITING) {
                taskPauseAllSubtasksTracking($conn, $task_id, $audit);
            }
            if ($technician_id) {
                $notify = sendTaskAssignedToTechnicianNotification(
                    $conn,
                    $user_empresa_id,
                    $task_id,
                    $task_number,
                    (int) $technician_id,
                    $subject,
                    $description,
                    $hasDateColumns ? $start_date : null,
                    $hasDateColumns ? $end_date : null
                );
                if ($notify['success']) {
                    taskMailLog('new.php — correo de tarea confirmado para #' . $task_number, 'OK');
                } else {
                    taskMailLog(
                        'new.php — fallo correo tarea #' . $task_number . ': ' . ($notify['message'] ?? ''),
                        'WARN'
                    );
                }
            }
            if ($messageType !== 'danger') {
                header('Location: edit.php?id=' . $task_id);
                exit;
            }
        } else {
            $message = 'Error al guardar: ' . $conn->error;
            $messageType = 'danger';
            $stmt->close();
        }
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<link rel="stylesheet" href="css/task-quill.css" />
<link rel="stylesheet" href="css/task-new.css" />

<div class="body-bg">
<div class="container-fluid task-new-page">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-0">Nueva tarea</h1>
                </div>
                <div class="tickets-page-actions">
                    <a href="tasks.php" class="btn btn-secondary">Volver al listado</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!$hasDateColumns): ?>
            <div class="alert alert-warning">
                Ejecute <code>tickets/sql/alter_tasks_add_dates.sql</code> para habilitar fechas de inicio y término.
            </div>
            <?php endif; ?>

            <form method="post" class="card task-new-form">
                <div class="card-body">
                    <div class="row g-3 task-new-form-grid">
                        <div class="col-md-6">
                            <label class="form-label">Asunto</label>
                            <input type="text" name="subject" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado inicial</label>
                            <select name="status_id" id="status_id" class="form-select">
                                <?php $statuses->data_seek(0); while ($s = $statuses->fetch_assoc()): if ((int)$s['id'] === 3) continue; ?>
                                <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)$s['id'] === 2 ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <!--<div class="col-md-3">
                            <label class="form-label">Categoría</label>
                            <select name="category_id" class="form-select">
                                <option value="">—</option>
                                <?php while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>-->
                        <!--<div class="col-md-4">
                            <label class="form-label">Solicitante</label>
                            <select name="requester_id" class="form-select">
                                <option value="">—</option>
                                <?php while ($r = $requesters->fetch_assoc()): ?>
                                <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>-->
                        <div class="col-md-4">
                            <label class="form-label">Técnico responsable</label>
                            <select name="technician_id" class="form-select">
                                <option value="">—</option>
                                <?php while ($t = $technicians->fetch_assoc()): ?>
                                <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <!--<div class="col-md-4">
                            <label class="form-label">Prioridad</label>
                            <select name="priority_id" class="form-select">
                                <option value="">—</option>
                                <?php while ($p = $priorities->fetch_assoc()): ?>
                                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>-->
                        <?php if ($hasDateColumns): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="start_date">Fecha de inicio</label>
                            <input type="date" name="start_date" id="start_date" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_date">Fecha de término</label>
                            <input type="date" name="end_date" id="end_date" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <div id="task_start_date_alert" class="alert alert-warning d-none mb-0" role="alert">
                                <i class="fas fa-clock me-2"></i>
                                <span id="task_start_date_alert_text"></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="task-description-block">
                        <?php
                        $value = $_POST['description'] ?? '';
                        $required = true;
                        $wrapperClass = 'task-new-description-quill';
                        $labelClass = 'form-label';
                        include __DIR__ . '/includes/quill_description_field.php';
                        ?>
                    </div>
                </div>
                <div class="card-footer task-new-form-footer">
                    <button type="submit" class="btn btn-primary task-new-submit-btn">
                        <i class="fas fa-plus me-1"></i>Crear tarea y agregar actividades
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>
</div>
<?php include __DIR__ . '/includes/quill_assets.php'; ?>
<script>
const TASK_STATUS_OPEN = 2;

document.getElementById('status_id').addEventListener('change', function() {
    updateTaskStartDateRecommendation('status');
});

const startDateEl = document.getElementById('start_date');
const endDateEl = document.getElementById('end_date');
if (startDateEl && endDateEl) {
    startDateEl.addEventListener('change', function() {
        if (this.value) endDateEl.min = this.value;
        updateTaskStartDateRecommendation('date');
    });
    if (startDateEl.value) endDateEl.min = startDateEl.value;
    updateTaskStartDateRecommendation('init');
}

function todayYmdLocal() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}

function daysUntilStart(startYmd) {
    const t = new Date(todayYmdLocal() + 'T12:00:00');
    const s = new Date(startYmd + 'T12:00:00');
    return Math.round((s - t) / 86400000);
}

function formatDateEs(ymd) {
    const p = ymd.split('-');
    if (p.length !== 3) return ymd;
    return p[2] + '/' + p[1] + '/' + p[0];
}

function daysLabel(n) {
    return n === 1 ? '1 día' : n + ' días';
}

function updateTaskStartDateRecommendation(trigger) {
    const alertEl = document.getElementById('task_start_date_alert');
    const textEl = document.getElementById('task_start_date_alert_text');
    const statusEl = document.getElementById('status_id');
    if (!alertEl || !textEl || !statusEl || !startDateEl) return;

    const start = startDateEl.value;
    const status = parseInt(statusEl.value, 10);
    const today = todayYmdLocal();

    if (!start || start <= today || status !== TASK_STATUS_OPEN) {
        alertEl.classList.add('d-none');
        return;
    }

    const days = daysUntilStart(start);
    const dateFmt = formatDateEs(start);
    let msg = '';

    if (trigger === 'date') {
        msg = 'Para que el tiempo se cuente desde que realmente inicie el trabajo, cambie el estado a '
            + '<strong>En atención / Espera</strong> y pásela a <strong>Abierto</strong> el día que comience la tarea. '
            + 'La fecha de inicio (' + dateFmt + ') es dentro de ' + daysLabel(days) + '.';
    } else {
        msg = 'Por recomendación, coloque el estado en <strong>En atención / Espera</strong> y cámbielo a '
            + '<strong>Abierto</strong> el día que comience la tarea, para que el tiempo se contabilice correctamente. '
            + 'Aún ' + (days === 1 ? 'falta' : 'faltan') + ' ' + daysLabel(days) + ' para la fecha de inicio (' + dateFmt + ').';
    }

    textEl.innerHTML = msg;
    alertEl.classList.remove('d-none');
}
</script>
<?php include '../../includes/footer.php'; ?>
