<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/task_dates_helper.php';

$message = '';
$messageType = '';
$tableMissing = false;
$hasDateColumns = false;

$check = $conn->query("SHOW TABLES LIKE 'tasks'");
if (!$check || $check->num_rows === 0) {
    $tableMissing = true;
}

$status_id_filter = isset($_GET['status_id']) ? (int) $_GET['status_id'] : 0;
$tasks = null;
$stats = ['total' => 0, 'open' => 0, 'waiting' => 0, 'closed' => 0];

if (!$tableMissing) {
    $params = [];
    $types = '';
    $query = "SELECT tk.*, s.name AS status_name, c.name AS category_name,
              r.name AS requester_name, tech.name AS technician_name,
              (SELECT COUNT(*) FROM task_subtasks st WHERE st.task_id = tk.id) AS subtask_total,
              (SELECT COUNT(*) FROM task_subtasks st WHERE st.task_id = tk.id AND st.status_id = 3) AS subtask_closed
              FROM tasks tk
              LEFT JOIN statuses s ON tk.status_id = s.id
              LEFT JOIN categories c ON tk.category_id = c.id
              LEFT JOIN requesters r ON tk.requester_id = r.id
              LEFT JOIN technicians tech ON tk.technician_id = tech.id
              WHERE tk.deleted = 0 AND tk.closed_at IS NULL";
    $query = addEmpresaFilter($query, 'tk.id_empresa', $params, $types);

    if ($status_id_filter > 0) {
        $query .= ' AND tk.status_id = ?';
        $params[] = $status_id_filter;
        $types .= 'i';
    }
    $query .= ' ORDER BY tk.created_at DESC';

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $tasks = $stmt->get_result();
    $stmt->close();

    $stats_params = [];
    $stats_types = '';
    $stats_q = "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) AS open_cnt,
        SUM(CASE WHEN status_id = 10 THEN 1 ELSE 0 END) AS waiting_cnt,
        SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) AS closed_cnt
        FROM tasks WHERE deleted = 0 AND closed_at IS NULL";
    $stats_q = addEmpresaFilter($stats_q, 'id_empresa', $stats_params, $stats_types);
    $stats_stmt = $conn->prepare($stats_q);
    if ($stats_params) {
        $stats_stmt->bind_param($stats_types, ...$stats_params);
    }
    $stats_stmt->execute();
    $row = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();
    if ($row) {
        $stats = [
            'total' => (int) $row['total'],
            'open' => (int) $row['open_cnt'],
            'waiting' => (int) $row['waiting_cnt'],
            'closed' => (int) $row['closed_cnt'],
        ];
    }
    $hasDateColumns = taskTableHasDateColumns($conn);
}
?>
<?php include '../../includes/modern-header.php'; ?>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-1">Tareas</h1>
                    <p class="text-muted mb-0">Planificación interna con actividades y seguimiento por técnico</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="closed_tasks.php" class="btn btn-secondary"><i class="fas fa-archive me-2"></i>Tareas cerradas</a>
                    <a href="new.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Nueva tarea</a>
                </div>
            </div>

            <?php if ($tableMissing): ?>
            <div class="alert alert-warning">
                <i class="fas fa-database me-2"></i>
                Las tablas del módulo aún no existen. Ejecute el script
                <code>tickets/sql/create_tasks_module.sql</code> en su base de datos.
            </div>
            <?php else: ?>

            <div class="row mb-4">
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Activas</div><div class="h4"><?php echo $stats['total']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Abiertas (2)</div><div class="h4 text-info"><?php echo $stats['open']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">En espera (10)</div><div class="h4 text-warning"><?php echo $stats['waiting']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Con estado cerrado (activas)</div><div class="h4 text-success"><?php echo $stats['closed']; ?></div></div></div></div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="status_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">Todos</option>
                                <option value="2" <?php echo $status_id_filter === 2 ? 'selected' : ''; ?>>Abierto</option>
                                <option value="10" <?php echo $status_id_filter === 10 ? 'selected' : ''; ?>>En atención / Espera</option>
                                <option value="1" <?php echo $status_id_filter === 1 ? 'selected' : ''; ?>>Sin asignar</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 tickets-list-table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Asunto</th>
                                <th>Estado</th>
                                <th>Técnico</th>
                                <th>Actividades</th>
                                <?php if ($hasDateColumns): ?><th>Inicio</th><th>Término</th><?php endif; ?>
                                <th>Creada</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($tasks && $tasks->num_rows): ?>
                            <?php while ($t = $tasks->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['task_number']); ?></strong></td>
                                <td><?php
                                    $preview = $t['subject'] ?: strip_tags((string) ($t['description'] ?? ''));
                                    echo htmlspecialchars(mb_substr($preview, 0, 60));
                                ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['status_name'] ?? ''); ?></span></td>
                                <td><?php echo htmlspecialchars($t['technician_name'] ?? '—'); ?></td>
                                <td><?php echo (int)$t['subtask_closed']; ?> / <?php echo (int)$t['subtask_total']; ?></td>
                                <?php if ($hasDateColumns): ?>
                                <td><?php echo htmlspecialchars(taskFormatDateDisplay($t['start_date'] ?? null)); ?></td>
                                <td><?php echo htmlspecialchars(taskFormatDateDisplay($t['end_date'] ?? null)); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($t['created_at']); ?></td>
                                <td class="text-nowrap">
                                    <a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                                    <a href="edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="eliminarTarea(<?php echo (int)$t['id']; ?>, <?php echo json_encode($t['task_number'], JSON_UNESCAPED_UNICODE); ?>, 'tasks.php')"
                                        title="Eliminar tarea">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo $hasDateColumns ? 9 : 7; ?>" class="text-center text-muted py-4">No hay tareas activas.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
</div>

<?php include __DIR__ . '/includes/task_delete_script.php'; ?>
<?php include '../../includes/footer.php'; ?>
