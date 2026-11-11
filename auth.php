<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function appBasePath(): string {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $applicationRoot = realpath(__DIR__);
    if ($documentRoot === false || $applicationRoot === false) {
        return '';
    }

    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $applicationRoot = str_replace('\\', '/', $applicationRoot);
    if ($applicationRoot === $documentRoot) {
        return '';
    }

    if (strpos($applicationRoot, $documentRoot . '/') !== 0) {
        return '';
    }

    return '/' . substr($applicationRoot, strlen($documentRoot) + 1);
}

function appUrl(string $path): string {
    return appBasePath() . '/' . ltrim($path, '/');
}

function appLocalUrl(string $path): string {
    return 'http://localhost:90' . appUrl($path);
}

function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function checkAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('/index.php'));
        exit();
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!$path) {
        $path = '/';
    }

    $basePath = appBasePath();
    if ($basePath !== '' && ($path === $basePath || strpos($path, $basePath . '/') === 0)) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }

    $isInventario = strpos($path, '/Inventario/') === 0 || $path === '/Inventario';
    $isTickets = strpos($path, '/tickets/') === 0 || $path === '/tickets';
    $isGestionUsuarios = ($path === '/gestion-usuarios.php');

    if ($isInventario || $isTickets || $isGestionUsuarios) {
        // Bypass total para admin
        $username = strtolower($_SESSION['user']['username'] ?? '');
        $role = strtolower($_SESSION['user']['rol'] ?? '');
        if ($username === 'admin' && $role === 'administrador') {
            return;
        }
        // Cargar la configuración de permisos por rol
        $permissionsConfigPath = __DIR__ . '/route_permissions.php';
        $roleToAllowedRoutes = [];
        if (file_exists($permissionsConfigPath)) {
            $loaded = include $permissionsConfigPath;
            if (is_array($loaded)) {
                $roleToAllowedRoutes = $loaded;
            }
        }

        $userRole = $_SESSION['user']['rol'] ?? '';
        $userRole = is_string($userRole) ? strtolower($userRole) : '';
        $allowedRoutes = $roleToAllowedRoutes[$userRole] ?? [];

        $isAllowed = false;
        if ($allowedRoutes === '*') {
            $isAllowed = true;
        } elseif (is_array($allowedRoutes)) {
            // comodín total
            if (in_array('*', $allowedRoutes, true)) {
                $isAllowed = true;
            } else {
                // Verificar por prefijos permitidos
                foreach ($allowedRoutes as $allowedPrefix) {
                    if (!is_string($allowedPrefix) || $allowedPrefix === '') {
                        continue;
                    }
                    // Normalizar: asegurar que empiece con '/'
                    if ($allowedPrefix[0] !== '/') {
                        $allowedPrefix = '/' . $allowedPrefix;
                    }
                    if (strpos($path, $allowedPrefix) === 0) {
                        $isAllowed = true;
                        break;
                    }
                }
            }
        }

        // Permisos finos para historial según flags por usuario
        if (!$isAllowed) {
            $canInventory = (int)($_SESSION['user']['can_view_inventory_history'] ?? 0) === 1;
            $canTickets = (int)($_SESSION['user']['can_view_ticket_history'] ?? 0) === 1;
            $canManage = (int)($_SESSION['user']['can_manage_users'] ?? 0) === 1;
            if ($isInventario && $canInventory && (strpos($path, '/Inventario/crud/historial-actividades.php') === 0)) {
                $isAllowed = true;
            }
            if ($isTickets && $canTickets && (strpos($path, '/tickets/modules/tickets/history.php') === 0)) {
                $isAllowed = true;
            }
            if ($isGestionUsuarios && $canManage && $userRole === 'supervisor') {
                $isAllowed = true;
            }
        }

        if (!$isAllowed) {
            // Redirigir a una vista segura si no hay permiso
            // Preferir una ruta conocida del módulo de inventario si la hay
            $fallback = appUrl('/index.php');
            if ($isInventario) {
                $fallback = appUrl('/Inventario/crud/visualizar-empresas.php');
            }
            header('Location: ' . $fallback);
            exit();
        }
    }
}

function checkAdmin() {
    checkAuth(); 
    $role = $_SESSION['user']['rol'] ?? '';
    if ($role !== 'administrador' && $role !== 'supervisor') {
        header('Location: ' . appUrl('/Inventario/crud/visualizar-empresas.php'));
        exit();
    }
}

function isAdmin() {
    return isLoggedIn() && in_array(($_SESSION['user']['rol'] ?? ''), ['administrador', 'supervisor'], true);
}

function isCurrentUserAdmin() {
    return isLoggedIn() && strtolower($_SESSION['user']['username']) === 'admin';
}

function canUpdatePassword() {
    return isLoggedIn() && strtolower($_SESSION['user']['username']) === 'admin';
}

function canViewAdminProfile() {
    return isLoggedIn() && strtolower($_SESSION['user']['username']) === 'admin';
}

function canViewInventoryHistory() {
    if (!isLoggedIn()) return false;
    $username = strtolower($_SESSION['user']['username'] ?? '');
    $role = strtolower($_SESSION['user']['rol'] ?? '');
    if ($username === 'admin' && $role === 'administrador') return true;
    return ((int)($_SESSION['user']['can_view_inventory_history'] ?? 0)) === 1;
}

function canViewTicketHistory() {
    if (!isLoggedIn()) return false;
    $username = strtolower($_SESSION['user']['username'] ?? '');
    $role = strtolower($_SESSION['user']['rol'] ?? '');
    if ($username === 'admin' && $role === 'administrador') return true;
    return ((int)($_SESSION['user']['can_view_ticket_history'] ?? 0)) === 1;
}

function canManageUsers() {
    if (!isLoggedIn()) return false;
    $username = strtolower($_SESSION['user']['username'] ?? '');
    $role = strtolower($_SESSION['user']['rol'] ?? '');
    if ($username === 'admin' && $role === 'administrador') return true;
    return ((int)($_SESSION['user']['can_manage_users'] ?? 0)) === 1;
}

function canViewUserHistory() {
    if (!isLoggedIn()) return false;
    $username = strtolower($_SESSION['user']['username'] ?? '');
    $role = strtolower($_SESSION['user']['rol'] ?? '');
    return $username === 'admin' && $role === 'administrador';
}

function getUserName() {
    if (isLoggedIn()) {
        return $_SESSION['user']['username'] ?? 'Usuario desconocido';
    }
    return 'Usuario no autenticado';
}

function logout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    header('Location: ' . appUrl('/index.php'));
    exit();
}

// Incluir funciones helper para multi-empresa
if (file_exists(__DIR__ . '/includes/empresa_helper.php')) {
    require_once __DIR__ . '/includes/empresa_helper.php';
}
?>