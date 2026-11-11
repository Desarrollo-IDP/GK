<?php
/**
 * Fragmento HTML del tbody de tickets activos (auto-refresh).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/common.php';
require_once dirname(__DIR__, 3) . '/conexion/db.php';
require_once dirname(__DIR__, 4) . '/auth.php';
require_once dirname(__DIR__, 4) . '/includes/empresa_helper.php';
require_once dirname(__DIR__, 3) . '/includes/tickets_active_list.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$filters = [
    'filter' => $_GET['filter'] ?? '',
    'status' => $_GET['status'] ?? '',
    'status_id' => $_GET['status_id'] ?? 0,
    'q' => $_GET['q'] ?? '',
    'id' => $_GET['id'] ?? '',
];

try {
    $result = ticketsActiveListFetch($conn, $filters);
    if ($result === false) {
        throw new RuntimeException('No se pudo consultar los tickets');
    }

    $html = ticketsActiveListRenderTbody($result);
    $count = $result->num_rows;

    echo json_encode([
        'ok' => true,
        'html' => $html,
        'count' => $count,
        'updated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
