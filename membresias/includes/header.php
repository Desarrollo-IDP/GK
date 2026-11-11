<?php
// Incluir configuración
require_once __DIR__ . '/config.php';

// Inicializar configuración
initConfig();

// Verificar autenticación
// Obtener el nombre del archivo actual
$current_page = basename($_SERVER['SCRIPT_NAME'], '.php');

// Páginas públicas que no requieren autenticación
$public_pages = ['index', 'login', 'register', 'forgot-password', 'setup_user'];

// Si no es una página pública y el usuario no está autenticado, redirigir al login
if (!in_array($current_page, $public_pages) && !isAuthenticated()) {
    // Construir la ruta correcta para el index.php
    $redirect_url = $base_url . 'index.php';
    
    // Si estamos en la raíz, usar ruta relativa
    if ($levels_up === 0) {
        $redirect_url = 'index.php';
    }
    
    // Log para depuración (opcional)
    error_log("Redirigiendo usuario no autenticado desde {$current_page} a {$redirect_url}");
    
    redirect($redirect_url);
}

// Calcular la ruta base correcta
$current_file = $_SERVER['SCRIPT_NAME'];
$current_dir = dirname($current_file);

// Determinar cuántos niveles subir para llegar a la raíz del proyecto
$levels_up = 0;
if (strpos($current_dir, '/modules/') !== false) {
    $levels_up = 2; // Desde modules/submodulo/archivo.php
} elseif (strpos($current_dir, '/membresias/') !== false) {
    $levels_up = 1; // Desde membresias/archivo.php
}

$base_url = '';
for ($i = 0; $i < $levels_up; $i++) {
    $base_url .= '../';
}

// Si estamos en la raíz, no necesitamos subir
if ($levels_up === 0) {
    $base_url = './';
}

// Procesar automáticamente pagos por cancelación pendientes (solo para usuarios autenticados)
if (isAuthenticated() && !isset($_SESSION['cancellation_processed'])) {
    processCancellationPaymentsOnAccess();
    $_SESSION['cancellation_processed'] = true; // Evitar procesamiento múltiple en la misma sesión
}

/**
 * Procesa pagos por cancelación pendientes cuando se accede al panel administrativo
 */
function processCancellationPaymentsOnAccess() {
    global $pdo;
    
    try {
        // Verificar si ya se procesó recientemente (últimos 5 minutos)
        $lastProcessFile = __DIR__ . '/../logs/last_cancellation_process.txt';
        $currentTime = time();
        
        if (file_exists($lastProcessFile)) {
            $lastProcess = (int)file_get_contents($lastProcessFile);
            if (($currentTime - $lastProcess) < 300) { // 5 minutos
                return;
            }
        }
        
        // Actualizar timestamp
        file_put_contents($lastProcessFile, $currentTime);
        
        // Buscar membresías canceladas con cargo pendiente
        $sql = "SELECT m.*, c.correo, c.nombre, c.apellido_paterno, tm.monto_cancelacion, tm.nombre as tipo_nombre
                FROM membresias m
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id
                WHERE m.mp_status = 'cancelled' 
                AND m.mp_cancellation_fee > 0
                AND m.mp_cancelled_at IS NOT NULL
                AND m.mp_cancellation_reason NOT LIKE '%Pago aprobado%'
                AND m.mp_cancellation_reason NOT LIKE '%Error en cobro%'
                AND m.mp_cancellation_reason NOT LIKE '%Pago procesado%'
                ORDER BY m.mp_cancelled_at DESC
                LIMIT 3"; // Limitar a 3 por acceso
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($membresias)) {
            error_log('Panel Admin - Procesando ' . count($membresias) . ' pagos por cancelación pendientes');
            
            foreach ($membresias as $membresia) {
                try {
                    // Verificar si el cliente tiene token de tarjeta
                    $clienteStmt = $pdo->prepare("SELECT mp_card_token, mp_customer_id FROM clientes WHERE id = ?");
                    $clienteStmt->execute([$membresia['cliente_id']]);
                    $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cliente || !$cliente['mp_card_token']) {
                        continue;
                    }
                    
                    // Intentar cobro automático
                    $result = mpCreateCancellationPaymentFromClient(
                        $membresia['cliente_id'],
                        $membresia['mp_cancellation_fee'],
                        'CANCEL_FEE_MEM_' . $membresia['id']
                    );
                    
                    if ($result['success']) {
                        // Registrar el pago en la tabla de logs
                        $logStmt = $pdo->prepare("INSERT INTO mp_payment_logs 
                            (membresia_id, mp_payment_id, mp_subscription_id, amount, status, payment_method, payment_date, mp_response) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $logStmt->execute([
                            $membresia['id'],
                            $result['payment_id'],
                            $membresia['mp_preapproval_id'],
                            $membresia['mp_cancellation_fee'],
                            $result['status'],
                            'cancellation_fee',
                            date('Y-m-d H:i:s'),
                            json_encode($result)
                        ]);
                        
                        // Actualizar la membresía
                        $updateStmt = $pdo->prepare("UPDATE membresias SET 
                            mp_cancellation_reason = CONCAT(mp_cancellation_reason, ' - Pago procesado desde panel: ', ?)
                            WHERE id = ?");
                        $updateStmt->execute([$result['payment_id'], $membresia['id']]);
                        
                        error_log('Panel Admin - Pago por cancelación procesado: ' . $result['payment_id']);
                    }
                    
                } catch (Throwable $e) {
                    error_log('Panel Admin - Error procesando pago: ' . $e->getMessage());
                }
            }
        }
        
    } catch (Throwable $e) {
        error_log('Panel Admin - Error en processCancellationPaymentsOnAccess: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Membresías</title>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Sistema de diseño moderno -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/modern-design-system.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/responsive.css">
    
    <style>
    :root {
        --primary-color: #202654;
        --secondary-color: #1a1f45;
        --accent-color: #3a86ff;
        --text-light: #f8f9fa;
        --text-muted: #adb5bd;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 70px;
        --header-height: 60px;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f7fb;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
    }
    
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 20px;
        margin-top: var(--header-height);
        flex: 1;
        transition: margin 0.3s ease;
        min-height: calc(100vh - var(--header-height));
    }
    
    .sidebar-collapsed + .main-content {
        margin-left: var(--sidebar-collapsed-width);
    }
    
    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .sidebar-collapsed + .main-content {
            margin-left: 0;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
    }
    
    /* Estilos para el header */
    .navbar {
        height: var(--header-height);
        padding: 0 1rem;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1030;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }
    
    .navbar-brand {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--text-light) !important;
        text-decoration: none;
        display: flex;
        align-items: center;
    }
    
    .navbar-brand i {
        font-size: 1.4rem;
        margin-right: 0.5rem;
    }
    
    .search-container {
        flex: 1;
        max-width: 500px;
        margin: 0 1rem;
    }
    
    .search-input {
        background-color: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        transition: all 0.3s;
        font-size: 0.9rem;
    }
    
    .search-input:focus {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
        box-shadow: 0 0 0 0.25rem rgba(58, 134, 255, 0.25);
        color: white;
    }
    
    .search-input::placeholder {
        color: rgba(255, 255, 255, 0.6);
    }
    
    .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .nav-link {
        color: rgba(255, 255, 255, 0.8) !important;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }
    
    .nav-link:hover {
        color: white !important;
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        padding: 0.5rem 0;
        min-width: 200px;
        margin-top: 0.5rem;
    }
    
    .dropdown-item {
        padding: 0.5rem 1rem;
        color: #333;
        font-weight: 400;
        font-size: 0.9rem;
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: var(--primary-color);
    }
    
    .dropdown-divider {
        margin: 0.5rem 0;
    }
    
    .badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 0.2rem 0.3rem;
    }
    
    /* Estilos para el toggle del sidebar en móvil */
    .navbar-toggler {
        border: none;
        padding: 0.4rem;
        color: white;
        background: transparent;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .navbar-toggler:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .navbar-toggler:focus {
        box-shadow: none;
        outline: none;
    }
    
    /* Responsive design */
    @media (max-width: 991.98px) {
        .navbar {
            padding: 0 0.75rem;
        }
        
        .navbar-brand {
            font-size: 1rem;
        }
        
        .navbar-brand i {
            font-size: 1.2rem;
        }
        
        .search-container {
            display: none;
        }
        
        .nav-link {
            padding: 0.4rem 0.5rem;
        }
        
        .avatar {
            width: 28px;
            height: 28px;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 576px) {
        .navbar {
            padding: 0 0.5rem;
        }
        
        .navbar-brand span {
            display: none;
        }
        
        .navbar-brand i {
            margin-right: 0;
        }
        
        .nav-link {
            padding: 0.3rem 0.4rem;
        }
        
        .dropdown-menu {
            min-width: 180px;
        }
    }
    /* Forzar visibilidad del botón de menú en tablets/iPad */
    @media (min-width: 768px) and (max-width: 1112px) {
        #sidebarToggle {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative;
            z-index: 3000;
        }
    }
    </style>
    
    <!-- JavaScript para funcionalidades del sidebar -->
    <script>
    function toggleSubmenu(submenuId) {
        const submenu = document.getElementById(submenuId);
        const navItem = submenu.parentElement;
        
        // Toggle del submenú
        submenu.classList.toggle('show');
        
        // Toggle de la clase active del nav-item
        navItem.classList.toggle('active');
        
        // Cerrar otros submenús abiertos
        const allSubmenus = document.querySelectorAll('.submenu');
        const allNavItems = document.querySelectorAll('.nav-item');
        
        allSubmenus.forEach(menu => {
            if (menu.id !== submenuId && menu.classList.contains('show')) {
                menu.classList.remove('show');
            }
        });
        
        allNavItems.forEach(item => {
            if (item !== navItem && item.classList.contains('active')) {
                item.classList.remove('active');
            }
        });
    }
    
    // Función para confirmar logout
    function confirmLogout() {
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            window.location.href = '<?php echo $base_url; ?>logout.php';
        }
    }
    </script>
</head>
<body>
    <!-- Header moderno -->
    <header class="navbar navbar-expand-md navbar-dark">
        <div class="container-fluid">
            <!-- Botón toggle para sidebar en móvil -->
            <button class="navbar-toggler d-md-none me-2" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Logo y marca -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $base_url; ?>dashboard.php">
                <i class="fas fa-id-card-alt"></i>
                <span class="ms-2 d-none d-md-inline">Sistema de Membresías</span>
            </a>
            
            <!-- Botón de búsqueda en móvil -->
            <button class="btn btn-link text-white d-md-none ms-auto me-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSearch" aria-label="Buscar">
                <i class="fas fa-search"></i>
            </button>
            
            <!-- Contenido del navbar -->
            <div class="collapse navbar-collapse" id="navbarSearch">
                <!-- Barra de búsqueda 
                <div class="search-container ms-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-search text-white-50"></i>
                        </span>
                        <input class="form-control search-input" type="text" placeholder="Buscar clientes, membresías..." aria-label="Buscar">
                    </div>
                </div>-->
                
                <!-- Menú de usuario -->
                <ul class="navbar-nav ms-auto">
                    <!-- Notificaciones 
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                0
                                <span class="visually-hidden">notificaciones sin leer</span>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notificaciones</h6></li>
                            <li><a class="dropdown-item" href="#">No hay notificaciones nuevas</a></li>
                        </ul>
                    </li>-->
                    
                    <!-- Perfil de usuario -->
                     <!--<li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                            <div class="avatar me-2">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <span class="d-none d-lg-inline"><?php echo $_SESSION['user']['username'] ?? 'Usuario'; ?></span>
                        </a>
                       <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">Sesión iniciada como</h6>
                                <div class="px-3 py-1 mb-2">
                                    <div class="fw-bold"><?php echo $_SESSION['user']['username'] ?? 'Usuario'; ?></div>
                                    <div class="text-muted small"><?php echo $_SESSION['user']['email'] ?? ''; ?></div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>profile.php">
                                    <i class="fas fa-user-circle me-2"></i> Mi perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>settings.php">
                                    <i class="fas fa-cog me-2"></i> Configuración
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo $base_url; ?>logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión
                                </a>
                            </li>
                        </ul>-->
                    </li>
                </ul>
            </div>
        </div>
    </header> 