<?php
require_once __DIR__ . '/../../auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
session_destroy();
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode(['success' => true]);
    exit;
}
header('Location: ' . appUrl('/index.php'));
exit;
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
session_destroy();
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode(['success' => true]);
    exit;
}
header('Location: ../../index.php');
exit;
?>