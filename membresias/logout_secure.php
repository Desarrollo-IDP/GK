<?php
/**
 * Logout seguro del sistema de membresías
 * Este archivo maneja el cierre de sesión desde cualquier ubicación del sistema
 */

// Incluir configuración
require_once __DIR__ . '/includes/config.php';

// Inicializar configuración
initConfig();

// Log para debugging
error_log("Logout seguro iniciado desde: " . $_SERVER['SCRIPT_NAME']);

// Verificar si hay una sesión activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log del estado de la sesión antes del logout
$session_id = session_id();
$user_info = isset($_SESSION['user']) ? $_SESSION['user']['usuario'] ?? 'N/A' : 'No hay usuario';
error_log("Estado de sesión antes del logout - Session ID: {$session_id}, Usuario: {$user_info}");

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
error_log("Logout seguro completado - redirigiendo a index.php");

// Determinar la ruta correcta para el index.php
$current_file = $_SERVER['SCRIPT_NAME'];
$current_dir = dirname($current_file);

// Determinar cuántos niveles subir para llegar a la raíz del proyecto
$levels_up = 0;
if (strpos($current_dir, '/modules/') !== false) {
    $levels_up = 2; // Desde modules/submodulo/archivo.php
} elseif (strpos($current_dir, '/membresias/') !== false) {
    $levels_up = 1; // Desde membresias/archivo.php
}

$redirect_url = '';
for ($i = 0; $i < $levels_up; $i++) {
    $redirect_url .= '../';
}

// Si estamos en la raíz, usar ruta relativa
if ($levels_up === 0) {
    $redirect_url = './';
}

$redirect_url .= 'index.php';

// Log de la ruta de redirección
error_log("Redirigiendo a: {$redirect_url}");

// Redirigir al index.php
header('Location: ' . $redirect_url);
exit();
?> 