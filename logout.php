<?php
require_once __DIR__ . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
session_destroy();

// Si es una petición AJAX (no la usaremos para timeout en tickets, pero por compatibilidad)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header('Location: ' . appUrl('/index.php'));
exit();