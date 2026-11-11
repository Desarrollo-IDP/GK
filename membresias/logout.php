<?php
// Incluir configuración
require_once __DIR__ . '/includes/config.php';

// Inicializar configuración
initConfig();

// Log para debugging
error_log("Logout iniciado desde: " . $_SERVER['SCRIPT_NAME']);

// Verificar si hay una sesión activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log del estado de la sesión antes del logout
error_log("Estado de sesión antes del logout - Session ID: " . session_id());
error_log("Usuario en sesión: " . (isset($_SESSION['user']) ? $_SESSION['user']['usuario'] ?? 'N/A' : 'No hay usuario'));

// Limpiar todas las variables de sesión
$_SESSION = array();

// Si se usa una cookie de sesión, destruirla
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Limpiar cualquier variable de sesión que pueda quedar
unset($_SESSION);

// Asegurar que no haya salida antes de la redirección
if (ob_get_level()) {
    ob_clean();
}

// Log después del logout
error_log("Logout completado - redirigiendo a index.php");

// Redirigir al index.php
header('Location: index.php');
exit();
?> 