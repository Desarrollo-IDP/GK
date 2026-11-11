<?php
/**
 * Auditoría del módulo de tareas — usuario de sesión
 */

require_once __DIR__ . '/../../auth.php';

function getTaskAuditUser(): array
{
    return [
        'username' => getUserName(),
        'user_id'  => isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null,
    ];
}

function logTaskActivity(
    mysqli $conn,
    string $action,
    string $summary,
    ?int $task_id = null,
    ?int $subtask_id = null,
    ?string $details = null,
    ?array $audit = null
): bool {
    if ($audit === null) {
        $audit = getTaskAuditUser();
    }

    $stmt = $conn->prepare(
        'INSERT INTO task_activity_log (task_id, subtask_id, action, summary, details, performed_by, performed_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('logTaskActivity prepare: ' . $conn->error);
        return false;
    }

    $stmt->bind_param(
        'iissssi',
        $task_id,
        $subtask_id,
        $action,
        $summary,
        $details,
        $audit['username'],
        $audit['user_id']
    );
    $ok = $stmt->execute();
    if (!$ok) {
        error_log('logTaskActivity execute: ' . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function getStatusName(mysqli $conn, int $status_id): string
{
    static $cache = [];
    if (isset($cache[$status_id])) {
        return $cache[$status_id];
    }
    $stmt = $conn->prepare('SELECT name FROM statuses WHERE id = ?');
    $stmt->bind_param('i', $status_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cache[$status_id] = $row['name'] ?? ('Estado #' . $status_id);
    return $cache[$status_id];
}
