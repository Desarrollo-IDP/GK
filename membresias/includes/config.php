<?php
/**
 * Configuración general del sistema de membresías
 * Este archivo contiene configuraciones globales y funciones de utilidad
 */

 
// Evitar que se incluya múltiples veces
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// Configuración de la aplicación
if (!defined('APP_NAME')) define('APP_NAME', 'Sistema de Membresías');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost:90/GK/membresias/');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'America/Mexico_City');

// Configuración de la base de datos
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'secureitmxcom_membrecia');
if (!defined('DB_USER')) define('DB_USER', 'secureitmxcom_membrecia');
if (!defined('DB_PASS')) define('DB_PASS', 'Becarios.2025');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Configuración de sesión
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'membresias_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 hora
if (!defined('SESSION_PATH')) define('SESSION_PATH', '/');

// Configuración de seguridad
if (!defined('HASH_COST')) define('HASH_COST', 12);
if (!defined('TOKEN_LIFETIME')) define('TOKEN_LIFETIME', 3600); // 1 hora

// Configuración de archivos
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', 'uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configuración de paginación
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 10);
if (!defined('MAX_PAGES_SHOWN')) define('MAX_PAGES_SHOWN', 5);

// Configuración de moneda
if (!defined('CURRENCY')) define('CURRENCY', 'MXN');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '$');
if (!defined('DECIMAL_PLACES')) define('DECIMAL_PLACES', 2);

// Configuración de fechas
if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'Y-m-d');
if (!defined('DATETIME_FORMAT')) define('DATETIME_FORMAT', 'Y-m-d H:i:s');
if (!defined('DISPLAY_DATE_FORMAT')) define('DISPLAY_DATE_FORMAT', 'd/m/Y');
if (!defined('DISPLAY_DATETIME_FORMAT')) define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// Configuración de email
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'tu-email@gmail.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'tu-password');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');

// Configuración de logging
if (!defined('LOG_PATH')) define('LOG_PATH', 'logs/');
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

/**
 * Inicializar configuración
 */
function initConfig() {
    // Configurar zona horaria
    date_default_timezone_set(APP_TIMEZONE);
    
    // Debug: verificar estado de la sesión antes de iniciarla
    error_log("DEBUG: initConfig() - Session status before: " . session_status());
    
    // Configurar sesión solo si no está activa
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar sesión antes de iniciarla
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_name(SESSION_NAME);
        
        // Iniciar sesión
        session_start();
        error_log("DEBUG: initConfig() - Session started with ID: " . session_id());
    } else {
        error_log("DEBUG: initConfig() - Session already active with ID: " . session_id());
    }
    
    // Debug: verificar contenido de la sesión después de iniciarla
    // error_log("DEBUG: initConfig() - Session data after init: " . print_r($_SESSION, true));
    
    // Configurar manejo de errores
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'error.log');
    
    // Crear directorios necesarios
    createDirectories();

    // Asegurar columnas del portal de clientes si hay conexión PDO disponible
    try {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            ensureClientePortalColumns($GLOBALS['pdo']);
        }
    } catch (Throwable $e) {
        error_log('initConfig ensureClientePortalColumns error: ' . $e->getMessage());
    }
}

/**
 * Crear directorios necesarios
 */
function createDirectories() {
    $directories = [
        LOG_PATH,
        UPLOAD_PATH,
        'temp/',
        'cache/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Asegurar columnas para portal de clientes
 */
function ensureClientePortalColumns(PDO $pdo): void {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('password_hash', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN password_hash VARCHAR(255) NULL AFTER telefono");
        }
        if (!in_array('must_set_password', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN must_set_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash");
        }
        if (!in_array('first_login_token', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN first_login_token VARCHAR(64) NULL AFTER must_set_password");
        }
        if (!in_array('first_login_expires_at', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN first_login_expires_at DATETIME NULL AFTER first_login_token");
        }
        if (!in_array('mp_customer_id', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN mp_customer_id VARCHAR(255) NULL AFTER first_login_expires_at");
        }
        if (!in_array('mp_card_id', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN mp_card_id VARCHAR(255) NULL AFTER mp_customer_id");
        }
        if (!in_array('mp_card_token', $columns)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN mp_card_token VARCHAR(255) NULL AFTER mp_card_id");
        }
    } catch (Throwable $e) {
        error_log('ensureClientePortalColumns error: ' . $e->getMessage());
    }
}

/**
 * Obtener la URL base
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    return $protocol . '://' . $host . $path;
}

/**
 * Obtener la ruta actual
 */
function getCurrentPath() {
    return $_SERVER['REQUEST_URI'];
}

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated() {
    $has_session = isset($_SESSION['user']);
    $has_id = $has_session && !empty($_SESSION['user']['id']);
    
    //error_log("DEBUG: isAuthenticated() - has_session: " . ($has_session ? 'true' : 'false'));
    //error_log("DEBUG: isAuthenticated() - has_id: " . ($has_id ? 'true' : 'false'));
    //error_log("DEBUG: isAuthenticated() - session data: " . print_r($_SESSION, true));
    
    return $has_id;
}

/**
 * Verificar permisos del usuario
 */
function hasPermission($permission) {
    if (!isAuthenticated()) {
        return false;
    }
    
    $user = $_SESSION['user'];
    $permissions = $user['permissions'] ?? [];
    
    return in_array($permission, $permissions) || in_array('admin', $permissions);
}

/**
 * Obtener el ID de la empresa del usuario autenticado
 */
function getCurrentUserEmpresaId() {
    if (!isAuthenticated()) {
        error_log("DEBUG: getCurrentUserEmpresaId() - User not authenticated");
        return null;
    }
    
    $user = $_SESSION['user'];
    
    // Debug: mostrar información de la sesión
    error_log("DEBUG: getCurrentUserEmpresaId() - Session user: " . print_r($user, true));
    
    // Verificar si empresa_id existe y no es null en la sesión
    if (isset($user['empresa_id']) && $user['empresa_id'] !== null && $user['empresa_id'] !== '') {
        $empresa_id = (int)$user['empresa_id'];
        error_log("DEBUG: getCurrentUserEmpresaId() - Returning empresa_id from session: " . $empresa_id);
        return $empresa_id;
    }
    
    // Si no hay empresa_id en la sesión, intentar obtenerlo de la base de datos
    if (isset($user['id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
            $stmt->execute([$user['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['empresa_id']) && $result['empresa_id'] !== null) {
                $empresa_id = (int)$result['empresa_id'];
                // Actualizar la sesión con el empresa_id
                $_SESSION['user']['empresa_id'] = $empresa_id;
                error_log("DEBUG: getCurrentUserEmpresaId() - Retrieved empresa_id from DB: " . $empresa_id);
                return $empresa_id;
            }
        } catch (Exception $e) {
            error_log("ERROR: getCurrentUserEmpresaId() - Error querying DB: " . $e->getMessage());
        }
    }
    
    // Si aún no se encuentra, intentar obtener el empresa_id del usuario actual
    if (isset($user['usuario'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$user['usuario']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['empresa_id']) && $result['empresa_id'] !== null) {
                $empresa_id = (int)$result['empresa_id'];
                // Actualizar la sesión con el empresa_id
                $_SESSION['user']['empresa_id'] = $empresa_id;
                error_log("DEBUG: getCurrentUserEmpresaId() - Retrieved empresa_id from DB by usuario: " . $empresa_id);
                return $empresa_id;
            }
        } catch (Exception $e) {
            error_log("ERROR: getCurrentUserEmpresaId() - Error querying DB by usuario: " . $e->getMessage());
        }
    }
    
    error_log("DEBUG: getCurrentUserEmpresaId() - No valid empresa_id found, returning null");
    return null;
}

/**
 * Obtener el ID de la empresa del usuario autenticado con valor por defecto
 */
function getCurrentUserEmpresaIdOrDefault($default = 1) {
    $empresa_id = getCurrentUserEmpresaId();
    
    // Si no se pudo obtener el empresa_id, intentar obtenerlo directamente de la BD
    if ($empresa_id === null && isAuthenticated()) {
        global $pdo;
        
        // Intentar por ID del usuario
        if (isset($_SESSION['user']['id'])) {
            try {
                $stmt = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['user']['id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['empresa_id']) && $result['empresa_id'] !== null) {
                    $empresa_id = (int)$result['empresa_id'];
                    // Actualizar la sesión con el empresa_id
                    $_SESSION['user']['empresa_id'] = $empresa_id;
                    error_log("DEBUG: getCurrentUserEmpresaIdOrDefault() - Retrieved empresa_id from DB by ID: " . $empresa_id);
                    return $empresa_id;
                }
            } catch (Exception $e) {
                error_log("ERROR: getCurrentUserEmpresaIdOrDefault() - Error querying DB by ID: " . $e->getMessage());
            }
        }
        
        // Intentar por nombre de usuario
        if (isset($_SESSION['user']['usuario'])) {
            try {
                $stmt = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE usuario = ?");
                $stmt->execute([$_SESSION['user']['usuario']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['empresa_id']) && $result['empresa_id'] !== null) {
                    $empresa_id = (int)$result['empresa_id'];
                    // Actualizar la sesión con el empresa_id
                    $_SESSION['user']['empresa_id'] = $empresa_id;
                    error_log("DEBUG: getCurrentUserEmpresaIdOrDefault() - Retrieved empresa_id from DB by usuario: " . $empresa_id);
                    return $empresa_id;
                }
            } catch (Exception $e) {
                error_log("ERROR: getCurrentUserEmpresaIdOrDefault() - Error querying DB by usuario: " . $e->getMessage());
            }
        }
    }
    
    // Si aún no se encuentra, usar el valor por defecto
    if ($empresa_id === null) {
        error_log("WARNING: getCurrentUserEmpresaIdOrDefault() - Using default empresa_id: " . $default);
    }
    
    return $empresa_id !== null ? $empresa_id : $default;
}

/**
 * Función de utilidad para obtener el empresa_id del usuario actual
 * Esta función es más simple y directa para usar en formularios
 */
function getEmpresaId() {
    return getCurrentUserEmpresaIdOrDefault();
}

/**
 * Verificar si el usuario tiene acceso a una empresa específica
 */
function canAccessEmpresa($empresa_id) {
    $user_empresa_id = getCurrentUserEmpresaId();
    
    // Si el usuario no tiene empresa_id, no puede acceder
    if ($user_empresa_id === null) {
        return false;
    }
    
    // Si el usuario es administrador (rol_id = 1), puede acceder a todas las empresas
    if (isAuthenticated() && isset($_SESSION['user']['rol_id']) && $_SESSION['user']['rol_id'] == 1) {
        return true;
    }
    
    // Verificar que el usuario pertenezca a la empresa
    return $user_empresa_id == $empresa_id;
}

/**
 * Verificar si el usuario es administrador
 */
function isAdmin() {
    return isAuthenticated() && isset($_SESSION['user']['rol_id']) && $_SESSION['user']['rol_id'] == 1;
}

/**
 * Obtener la consulta SQL filtrada por empresa según el rol del usuario
 */
function getEmpresaFilteredQuery($baseQuery, $empresaColumn = 'empresa_id') {
    if (isAdmin()) {
        // Administradores ven todos los datos
        return $baseQuery;
    } else {
        // Otros usuarios solo ven datos de su empresa
        $empresa_id = getCurrentUserEmpresaIdOrDefault();
        if (strpos($baseQuery, 'WHERE') !== false) {
            return str_replace('WHERE', "WHERE $empresaColumn = ? AND", $baseQuery);
        } else {
            return $baseQuery . " WHERE $empresaColumn = ?";
        }
    }
}

/**
 * Redirigir a una página
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Redirigir con mensaje
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    redirect($url);
}

/**
 * Mostrar mensaje de sesión
 */
function showSessionMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    }
    
    return '';
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date)) return '';
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formatear moneda
 */
function formatCurrency($amount, $currency = CURRENCY) {
    return CURRENCY_SYMBOL . number_format($amount, DECIMAL_PLACES);
}

/**
 * Validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validar teléfono
 */
function validatePhone($phone) {
    // Eliminar caracteres no numéricos
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Validar longitud (mínimo 10 dígitos para México)
    return strlen($phone) >= 10;
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitizar entrada
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generar contraseña segura
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Hashear contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generar slug
 */
function generateSlug($text) {
    // Convertir a minúsculas
    $text = strtolower($text);
    
    // Reemplazar caracteres especiales
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    
    // Reemplazar espacios con guiones
    $text = preg_replace('/[\s-]+/', '-', $text);
    
    // Eliminar guiones al inicio y final
    return trim($text, '-');
}

/**
 * Obtener extensión de archivo
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validar archivo
 */
function validateFile($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $extension = getFileExtension($file['name']);
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    return true;
}

/**
 * Subir archivo
 */
function uploadFile($file, $destination) {
    if (!validateFile($file)) {
        return false;
    }
    
    $extension = getFileExtension($file['name']);
    $filename = uniqid() . '.' . $extension;
    $filepath = UPLOAD_PATH . $destination . '/' . $filename;
    
    if (!is_dir(dirname($filepath))) {
        mkdir(dirname($filepath), 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Eliminar archivo
 */
function deleteFile($filename) {
    $filepath = UPLOAD_PATH . $filename;
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}

/**
 * Log de eventos
 */
function logEvent($message, $level = 'INFO') {
    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Obtener IP del cliente
 */
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Verificar si es una petición AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Respuesta JSON
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Respuesta de error JSON
 */
function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

/**
 * Respuesta de éxito JSON
 */
function jsonSuccess($data = null, $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Obtener parámetros de paginación
 */
function getPaginationParams($page = 1, $perPage = ITEMS_PER_PAGE) {
    $page = max(1, intval($page));
    $perPage = max(1, intval($perPage));
    $offset = ($page - 1) * $perPage;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset
    ];
}

/**
 * Obtener ruta correcta para mostrar imágenes
 * Esta función maneja las rutas relativas desde cualquier módulo
 */
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '';
    }
    
    // Si la ruta ya incluye http/https, devolverla tal como está
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // Determinar cuántos niveles subir basado en la ubicación actual
    $current_file = $_SERVER['SCRIPT_NAME'];
    $current_dir = dirname($current_file);
    
    $levels_up = 0;
    
    // Detectar si estamos en un módulo (modules/submodulo/archivo.php)
    if (strpos($current_dir, '/modules/') !== false) {
        $levels_up = 2; // Desde modules/submodulo/archivo.php
    } 
    // Detectar si estamos en la raíz del proyecto
    elseif (strpos($current_dir, '/cache') !== false || $current_dir === '/') {
        $levels_up = 0; // Desde la raíz del proyecto
    }
    // Para otros casos, asumir que estamos en la raíz
    else {
        $levels_up = 0;
    }
    
    // Construir la ruta correcta
    $prefix = '';
    for ($i = 0; $i < $levels_up; $i++) {
        $prefix .= '../';
    }
    
    return $prefix . $imagePath;
}

/**
 * Verificar si una imagen existe y es legible
 */
function imageExists($imagePath) {
    if (empty($imagePath)) {
        return false;
    }
    
    $fullPath = getImageUrl($imagePath);
    $fullPath = str_replace('../', '', $fullPath); // Para verificación de archivo
    
    // Determinar la ruta absoluta para verificación
    $current_file = $_SERVER['SCRIPT_NAME'];
    $current_dir = dirname($current_file);
    
    $levels_up = 0;
    if (strpos($current_dir, '/modules/') !== false) {
        $levels_up = 2;
    } elseif (strpos($current_dir, '/cache') !== false || $current_dir === '/') {
        $levels_up = 0;
    } else {
        $levels_up = 0;
    }
    
    $prefix = '';
    for ($i = 0; $i < $levels_up; $i++) {
        $prefix .= '../';
    }
    
    $absolutePath = $prefix . $imagePath;
    
    return file_exists($absolutePath) && is_readable($absolutePath);
}

/**
 * Generar enlaces de paginación
 */
function generatePagination($totalItems, $currentPage, $perPage, $url) {
    $totalPages = ceil($totalItems / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $pagination = '<nav aria-label="Paginación"><ul class="pagination justify-content-center">';
    
    // Botón anterior
    if ($currentPage > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage - 1) . '">Anterior</a></li>';
    }
    
    // Páginas
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? ' active' : '';
        $pagination .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Botón siguiente
    if ($currentPage < $totalPages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage + 1) . '">Siguiente</a></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}

// Inicializar configuración solo si no se ha hecho antes
 if (!function_exists('initConfig')) {
     initConfig();
 }

 /**
  * Helper para ejecutar SQLs de alter en caliente sin romper si ya existen
  */
 function ensureMpColumns(PDO $pdo): void {
     try {
         $cols = $pdo->query("SHOW COLUMNS FROM tipos_membresia")->fetchAll(PDO::FETCH_COLUMN);
         if (!in_array('mp_plan_id', $cols)) {
             $pdo->exec("ALTER TABLE tipos_membresia ADD COLUMN mp_plan_id VARCHAR(255) NULL COMMENT 'ID del plan de preaprobación en Mercado Pago'");
         }
     } catch (Throwable $e) { /* ignore */ }
     try {
         $cols = $pdo->query("SHOW COLUMNS FROM membresias")->fetchAll(PDO::FETCH_COLUMN);
         if (!in_array('mp_preapproval_id', $cols)) {
             $pdo->exec("ALTER TABLE membresias ADD COLUMN mp_preapproval_id VARCHAR(255) NULL COMMENT 'ID de la suscripción en Mercado Pago'");
         }
         if (!in_array('mp_plan_id', $cols)) {
             $pdo->exec("ALTER TABLE membresias ADD COLUMN mp_plan_id VARCHAR(255) NULL COMMENT 'Plan usado en Mercado Pago'");
         }
         if (!in_array('mp_status', $cols)) {
             $pdo->exec("ALTER TABLE membresias ADD COLUMN mp_status VARCHAR(50) NULL COMMENT 'Estado de la suscripción en MP'");
         }
         if (!in_array('mp_next_payment_date', $cols)) {
             $pdo->exec("ALTER TABLE membresias ADD COLUMN mp_next_payment_date DATETIME NULL COMMENT 'Próxima fecha de cobro en MP'");
         }
     } catch (Throwable $e) { /* ignore */ }
 }

/**
 * Generar identificador único para cliente
 */
function generateClienteIdentificador($empresa_id, $cliente_id = null) {
    $timestamp = time();
    $random = rand(1000, 9999);
    
    if ($cliente_id) {
        return 'EMP' . $empresa_id . '_CLI' . $cliente_id . '_' . $timestamp . '_' . $random;
    } else {
        return 'EMP' . $empresa_id . '_CLI_' . $timestamp . '_' . $random;
    }
}

/**
 * Buscar cliente por identificador único
 */
function findClienteByIdentificador($identificador) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE identificador_unico = ? LIMIT 1");
        $stmt->execute([$identificador]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error buscando cliente por identificador: ' . $e->getMessage());
        return null;
    }
}

/**
 * Buscar membresía por identificador único del cliente
 */
function findMembresiaByClienteIdentificador($identificador) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT m.* FROM membresias m 
                              JOIN clientes c ON m.cliente_id = c.id 
                              WHERE c.identificador_unico = ? 
                              ORDER BY m.created_at DESC LIMIT 1");
        $stmt->execute([$identificador]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error buscando membresía por identificador de cliente: ' . $e->getMessage());
        return null;
    }
}
?> 