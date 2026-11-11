<?php
ob_start();
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

$task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
$result = taskDeleteTask($conn, $task_id);

if (!$result['ok']) {
    http_response_code($result['message'] === 'Tarea no encontrada o sin permiso.' ? 403 : 422);
}

ob_end_clean();
echo json_encode($result);
