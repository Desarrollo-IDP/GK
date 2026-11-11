<?php
/**
 * Folios de tareas por empresa (TAR-YYYY-NNNN)
 */

function getNextTaskNumber(mysqli $conn, ?int $empresa_id = null): string
{
    if ($empresa_id === null) {
        require_once __DIR__ . '/../../includes/empresa_helper.php';
        $empresa_id = getCurrentUserEmpresaId();
    }
    if ($empresa_id === null) {
        $empresa_id = 0;
    }

    $year = date('Y');
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            'SELECT current_counter, prefix FROM task_counters WHERE id_empresa = ? FOR UPDATE'
        );
        $stmt->bind_param('i', $empresa_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $next = (int) $row['current_counter'] + 1;
            $prefix = $row['prefix'] ?: 'TAR';
            $upd = $conn->prepare('UPDATE task_counters SET current_counter = ? WHERE id_empresa = ?');
            $upd->bind_param('ii', $next, $empresa_id);
            $upd->execute();
            $upd->close();
        } else {
            $next = 1;
            $prefix = 'TAR';
            $ins = $conn->prepare(
                'INSERT INTO task_counters (id_empresa, current_counter, prefix) VALUES (?, ?, ?)'
            );
            $ins->bind_param('iis', $empresa_id, $next, $prefix);
            $ins->execute();
            $ins->close();
        }
        $stmt->close();
        $conn->commit();

        return sprintf('%s-%s-%04d', $prefix, $year, $next);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('getNextTaskNumber: ' . $e->getMessage());
        return 'TAR-' . $year . '-' . time();
    }
}
