<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!-- Sidebar moderno -->
<nav class="sidebar sidebar-collapsed" id="sidebar" onmouseenter="sidebarExpand()" onmouseleave="sidebarCollapse()">
    <div class="sidebar-nav">
        <!-- Logo y toggle -->
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-id-card-alt"></i>
                <span class="sidebar-logo-text">Membresías</span>
            </div>
        </div>
        
        <!-- Menú principal -->
        <div class="nav-section">
            <div class="nav-section-title">Menú Principal</div>
            
            <!-- Dashboard -->
            <div class="nav-item <?= strpos($current_page, 'dashboard') !== false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>
            
            <!-- Clientes -->
            <div class="nav-item <?= strpos($current_page, 'clientes') !== false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>modules/clientes/list.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Clientes</span>
                </a>
            </div>
            
            <!-- Membresías -->
            <div class="nav-item <?= strpos($current_page, 'membresias') !== false && strpos($current_page, 'tipos_membresia') === false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>modules/membresias/cards.php" class="nav-link">
                <!--<a href="<?= $base_url ?>modules/membresias/list.php" class="nav-link">-->
                    <i class="fas fa-id-card"></i>
                    <span class="nav-text">Membresías</span>
                </a>
            </div>
            
            <!-- Tipos de Membresía -->
            <div class="nav-item <?= strpos($current_page, 'tipos_membresia') !== false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>modules/tipos_membresia/list.php" class="nav-link">
                    <i class="fas fa-tags"></i>
                    <span class="nav-text">Tipos de Membresía</span>
                </a>
            </div>
            
            <!-- Usuarios -->
            <div class="nav-item <?= strpos($current_page, 'usuarios') !== false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>modules/usuarios/list.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>
                    <span class="nav-text">Usuarios</span>
                </a>
            </div>
            
            <!-- Empresas -->
            <div class="nav-item <?= strpos($current_page, 'empresas') !== false ? 'active' : '' ?>">
                <a href="<?= $base_url ?>modules/empresas/list.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span class="nav-text">Empresas</span>
                </a>
            </div>
        </div>
        
        <!-- Menú de cuenta -->
        <div class="nav-section mt-auto">
            <div class="nav-section-title">Cuenta</div>
            <div class="nav-item">
                <a href="<?= $base_url ?>modules/usuarios/edit.php?id=<?= $_SESSION['user']['id'] ?? '' ?>" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span class="nav-text">Mi Perfil</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Configuración</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="javascript:void(0)" class="nav-link text-danger" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Cerrar Sesión</span>
                </a>
            </div>
            <!--<div class="nav-item">
                <a href="<?= $base_url ?>logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout Directo</span>
                </a>-->
            </div>
        </div>
    </div>
    
    <!-- Footer del sidebar 
    <div class="sidebar-footer p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="avatar me-2">
                    <i class="fas fa-shield-alt" style="color: #202654;"></i>
                </div>
                <div class="sidebar-footer-text">
                    <div class="text-sm font-semibold">Membresías</div>
                    <div class="text-xs text-muted">v2.0</div>
                </div>
            </div>
        </div>
    </div>-->
</nav>

<style>
/* Estilos para el sidebar moderno */
:root {
    --sidebar-width: 260px;
    --sidebar-collapsed-width: 70px;
    --header-height: 60px;
    --primary-color: #3a86ff;
    --text-color: #2d3748;
    --text-muted: #718096;
    --border-color: #e2e8f0;
    --hover-bg: #f7fafc;
    --active-bg: #ebf4ff;
    --transition-speed: 0.3s;
}

.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    top: var(--header-height);
    left: 0;
    background: white;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    z-index: 1020;
    transition: all var(--transition-speed) ease;
    overflow-y: auto;
    height: calc(100vh - var(--header-height));
}

.sidebar-collapsed {
    width: var(--sidebar-collapsed-width);
    overflow: hidden;
}

/* Header del sidebar */
.sidebar-header {
    padding: 0 1rem;
    height: var(--header-height);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.1rem;
    white-space: nowrap;
    text-decoration: none;
}

.sidebar-logo i {
    font-size: 1.5rem;
    margin-right: 0.75rem;
    min-width: 24px;
    text-align: center;
}

.sidebar-logo-text {
    transition: opacity var(--transition-speed) ease;
}

.sidebar-collapsed .sidebar-logo-text {
    opacity: 0;
    width: 0;
    display: none;
}

.sidebar-toggle {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: var(--primary-color);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    transition: all 0.2s;
    display: flex !important;
    align-items: center;
    justify-content: center;
    z-index: 3000 !important;
    position: fixed !important;
    width: 48px !important;
    height: 48px !important;
    top: 16px;
    left: 16px;
    outline: none;
}

/* Forzar visibilidad del botón y sidebar en iPad/tablet (768px - 1112px) */
@media (min-width: 768px) and (max-width: 1112px) {
    .sidebar-toggle {
        display: flex !important;
        position: fixed !important;
        top: var(--header-height);
        left: 0;
        z-index: 3000 !important;
        background: #fff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin: 0.5rem;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: all !important;
        height: 44px !important;
        width: 44px !important;
        align-items: center;
        justify-content: center;
    }
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        width: 220px !important;
        left: 0;
        top: var(--header-height);
        height: calc(100vh - var(--header-height));
        z-index: 2500 !important;
        display: block !important;
        background: #fff;
    }
    .sidebar.show {
        transform: translateX(0) !important;
        display: block !important;
    }
    .sidebar-collapsed {
        width: 220px !important;
    }
    /* Desactivar hover en iPad/tablet */
    #sidebar.sidebar-collapsed:hover,
    #sidebar.sidebar-collapsed:active,
    #sidebar.sidebar-collapsed:focus {
        width: 220px !important;
    }
}

.sidebar-toggle:hover {
    background: var(--hover-bg);
    color: var(--primary-color);
}

/* Navegación */
.sidebar-nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1rem 0;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 1.5rem;
    padding: 0 0.5rem;
}

.nav-section-title {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 0 1rem 0.5rem;
    margin-top: 0.5rem;
    letter-spacing: 0.5px;
    white-space: nowrap;
    transition: all var(--transition-speed) ease;
}

.sidebar-collapsed .nav-section-title {
    opacity: 0;
    width: 0;
    height: 0;
    padding: 0;
    margin: 0;
    overflow: hidden;
}

.nav-item {
    position: relative;
    margin: 0.125rem 0;
    border-radius: 6px;
    overflow: hidden;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.6rem 1rem;
    color: var(--text-color) !important;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
    border-radius: 6px;
}

.nav-link i {
    font-size: 1.1rem;
    width: 24px;
    text-align: center;
    margin-right: 0.75rem;
    color: var(--text-muted);
    transition: all 0.2s;
    flex-shrink: 0;
}

.nav-item:hover .nav-link {
    background-color: var(--hover-bg);
    color: var(--primary-color) !important;
}

.nav-item:hover .nav-link i {
    color: var(--primary-color);
}

.nav-item.active .nav-link {
    background-color: var(--active-bg);
    color: var(--primary-color) !important;
    font-weight: 500;
}

.nav-item.active .nav-link i {
    color: var(--primary-color);
}

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background-color: var(--primary-color);
    border-radius: 0 3px 3px 0;
}

.nav-text {
    transition: all var(--transition-speed) ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-collapsed .nav-text {
    opacity: 0;
    width: 0;
    display: none;
}

/* Footer del sidebar */
.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid var(--border-color);
    background-color: #f8fafc;
    transition: all var(--transition-speed) ease;
}

.sidebar-footer-text {
    transition: all var(--transition-speed) ease;
    white-space: nowrap;
    overflow: hidden;
}

.sidebar-collapsed .sidebar-footer-text {
    opacity: 0;
    width: 0;
    display: none;
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* Estilos para móvil */
@media (max-width: 1080px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 1040;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        top: var(--header-height);
        height: calc(100vh - var(--header-height));
        width: 240px;
        overflow-x: hidden;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .sidebar-collapsed {
        width: 240px;
    }
    .sidebar-collapsed .sidebar-logo-text,
    .sidebar-collapsed .nav-text,
    .sidebar-collapsed .nav-section-title,
    .sidebar-collapsed .sidebar-footer-text {
        opacity: 1;
        width: auto;
        display: block;
    }
    .sidebar-overlay {
        position: fixed;
        top: var(--header-height);
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1039;
        display: none;
    }
    .sidebar-overlay.show {
        display: block;
    }
    /* Desactivar hover en móvil/iPad */
    #sidebar.sidebar-collapsed:hover,
    #sidebar.sidebar-collapsed:active,
    #sidebar.sidebar-collapsed:focus {
        width: 240px !important;
    }
    .sidebar-toggle {
        display: flex !important;
        z-index: 9999 !important;
        position: relative !important;
    }
}

@media (max-width: 1024px) {
    .sidebar {
        width: 220px;
    }
    .nav-link {
        padding: 0.75rem 1rem;
    }
    .nav-text {
        font-size: 0.95rem;
    }
}

/* Transiciones suaves */
.nav-item, .nav-link, .sidebar-toggle, .sidebar-logo {
    transition: all var(--transition-speed) ease;
}

/* Estilos para el enlace de cerrar sesión */
.text-danger {
    color: #e53e3e !important;
}

.text-danger:hover {
    color: #c53030 !important;
}

/* Forzar visibilidad del botón sidebar-toggle en iPad y tablets (768px - 1112px) */
@media (min-width: 768px) and (max-width: 1112px) {
  .sidebar-header .sidebar-toggle,
  button.sidebar-toggle,
  #sidebarToggle.sidebar-toggle {
    display: flex !important;
    position: relative !important;
    z-index: 2001 !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: all !important;
    height: auto !important;
    width: auto !important;
  }
}
</style>

<script>
function sidebarExpand() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 991.98) {
        sidebar.classList.remove('sidebar-collapsed');
    }
}
function sidebarCollapse() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 991.98) {
        sidebar.classList.add('sidebar-collapsed');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body = document.body;
    let sidebarOverlay;
    
    // Crear overlay para móvil
    function createOverlay() {
        if (window.innerWidth <= 991.98) {
            if (!document.querySelector('.sidebar-overlay')) {
                sidebarOverlay = document.createElement('div');
                sidebarOverlay.className = 'sidebar-overlay';
                document.body.appendChild(sidebarOverlay);
                
                sidebarOverlay.addEventListener('click', function() {
                    closeSidebar();
                });
            }
        } else if (sidebarOverlay) {
            sidebarOverlay.remove();
            sidebarOverlay = null;
        }
    }
    
    // Abrir sidebar en móvil
    function openSidebar() {
        if (!sidebar) return;
        
        // Crear overlay si no existe
        if (window.innerWidth <= 991.98 && !document.querySelector('.sidebar-overlay')) {
            createOverlay();
        }
        
        sidebar.classList.add('show');
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('show');
        }
        document.body.style.overflow = 'hidden';
    }
    
    // Cerrar sidebar en móvil
    function closeSidebar() {
        if (!sidebar) return;
        
        sidebar.classList.remove('show');
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('show');
        }
        document.body.style.overflow = '';
    }
    
    // Toggle sidebar en desktop
    function toggleSidebar() {
        if (!sidebar) return;
        
        if (window.innerWidth > 991.98) {
            // El toggle manual ya no es necesario, pero lo dejamos para compatibilidad
            sidebar.classList.toggle('sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        } else {
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
    }
    
    // Verificar estado inicial
    function initSidebar() {
        if (!sidebar) return;
        
        // Verificar si estamos en móvil
        if (window.innerWidth <= 991.98) {
            sidebar.classList.remove('sidebar-collapsed');
            createOverlay();
        } else {
            // En desktop, siempre iniciar colapsado
            sidebar.classList.add('sidebar-collapsed');
            // Asegurarnos de que el overlay no esté presente en desktop
            const existingOverlay = document.querySelector('.sidebar-overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
        }
        // Actualizar el ícono del botón de toggle
        updateToggleIcon();
    }
    
    // Actualizar el ícono del botón de toggle
    function updateToggleIcon() {
        if (!sidebarToggle) return;
        if (window.innerWidth <= 991.98) {
            sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        } else {
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            sidebarToggle.innerHTML = isCollapsed ? 
                '<i class="fas fa-chevron-right"></i>' : 
                '<i class="fas fa-chevron-left"></i>';
        }
    }
    
    // Inicializar sidebar
    initSidebar();
    
    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
            updateToggleIcon();
        });
    }
    
    // Cerrar sidebar al hacer clic en un enlace en móvil
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1080) {
                closeSidebar();
            }
        });
    });
    
    // Manejar cambio de tamaño de ventana
    function handleResize() {
        if (window.innerWidth > 991.98) {
            if (sidebarOverlay) {
                sidebarOverlay.remove();
                sidebarOverlay = null;
            }
            sidebar.classList.remove('show');
            document.body.style.overflow = '';
            sidebar.classList.add('sidebar-collapsed');
        } else {
            if (!sidebar.classList.contains('show')) {
                closeSidebar();
            }
            sidebar.classList.remove('sidebar-collapsed');
        }
        updateToggleIcon();
    }
    window.addEventListener('resize', handleResize);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 1080 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1080 && 
            sidebar.classList.contains('show') && 
            !sidebar.contains(e.target) && 
            !e.target.matches('.sidebar-toggle, .sidebar-toggle *')) {
            closeSidebar();
        }
    });
    if (sidebar) {
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Función para confirmar logout
    window.confirmLogout = function() {
        console.log('Función confirmLogout ejecutada');
        
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            console.log('Usuario confirmó logout');
            
            // Limpiar cualquier dato en localStorage o sessionStorage
            localStorage.clear();
            sessionStorage.clear();
            
            // Calcular la ruta del logout
            var logoutUrl = '<?= $base_url ?>logout.php';
            console.log('Redirigiendo a:', logoutUrl);
            
            // Redirigir al logout
            window.location.href = logoutUrl;
        } else {
            console.log('Usuario canceló logout');
        }
    };
});
</script>