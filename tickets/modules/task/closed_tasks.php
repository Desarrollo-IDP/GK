<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

$tableMissing = false;
$check = $conn->query("SHOW TABLES LIKE 'tasks'");
if (!$check || $check->num_rows === 0) {
    $tableMissing = true;
}

$tasks = null;
$total_closed = 0;

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
              WHERE tk.deleted = 0 AND tk.closed_at IS NOT NULL";
    $query = addEmpresaFilter($query, 'tk.id_empresa', $params, $types);
    $query .= ' ORDER BY tk.closed_at DESC';

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $tasks = $stmt->get_result();
    $total_closed = $tasks ? $tasks->num_rows : 0;
    $stmt->close();
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
                    <h1 class="h2 mb-1">Tareas cerradas</h1>
                    <p class="text-muted mb-0">Historial de tareas finalizadas (estado cerrado)</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tasks.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Tareas activas</a>
                </div>
            </div>

            <?php if ($tableMissing): ?>
            <div class="alert alert-warning">
                Ejecute <code>tickets/sql/create_tasks_module.sql</code> en la base de datos.
            </div>
            <?php else: ?>

            <div class="card mb-4">
                <div class="card-body py-3">
                    <span class="text-muted">Total cerradas:</span>
                    <strong class="ms-2"><?php echo $total_closed; ?></strong>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 tickets-list-table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Asunto</th>
                                <th>Solicitante</th>
                                <th>Técnico</th>
                                <th>Actividades</th>
                                <th>Cerrada por</th>
                                <th>Fecha cierre</th>
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
                                <td><?php echo htmlspecialchars($t['requester_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($t['technician_name'] ?? '—'); ?></td>
                                <td><?php echo (int)$t['subtask_closed']; ?> / <?php echo (int)$t['subtask_total']; ?></td>
                                <td><?php echo htmlspecialchars($t['closed_by'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($t['closed_at'] ?? ''); ?></td>
                                <td class="text-nowrap">
                                    <a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                                    <a href="edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="eliminarTarea(<?php echo (int)$t['id']; ?>, <?php echo json_encode($t['task_number'], JSON_UNESCAPED_UNICODE); ?>, 'closed_tasks.php')"
                                        title="Eliminar tarea">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No hay tareas cerradas. Al cambiar una tarea al estado <strong>Cerrado (3)</strong> aparecerá aquí.
                                </td>
                            </tr>
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
