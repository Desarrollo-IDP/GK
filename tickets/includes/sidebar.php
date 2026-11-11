<?php
include_once __DIR__ . '/common.php';
$base_url = ticketsRelativeBaseUrl();
?>

<!-- Overlay para móvil/tablet -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inicio.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>inicio.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/tickets') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/tickets.php">
                    <i class="fas fa-ticket-alt"></i>
                    Tickets Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'closed_tickets.php') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/closed_tickets.php">
                    <i class="fas fa-archive"></i>
                    Tickets Cerrados
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1">
            <span>Configuración</span>
            <a class="link-secondary" href="#" aria-label="Toggle configuration menu" id="toggleConfig">
                <i class="fas fa-chevron-down"></i>
            </a>
        </h6>
        <ul class="nav flex-column mb-2" id="configMenu">            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/requester') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/requester/requester.php">
                    <i class="fas fa-users"></i>
                    Solicitantes
                </a>
            </li> 
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/technicians') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/technicians/technicians.php">
                    <i class="fas fa-user-cog"></i>
                    Técnicos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/reports') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/reports/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Reportes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/users/users.php">
                    <i class="fas fa-user-shield"></i>
                    Usuarios
                </a>
            </li> 
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/configuration') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/configuration/configuration.php">
                    <i class="fas fa-cogs"></i>
                    Configuración
                </a>
            </li>
        </ul>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========================================
    // GESTIÓN DEL MENÚ DE CONFIGURACIÓN
    // ========================================
    const toggleConfig = document.getElementById('toggleConfig');
    const configMenu = document.getElementById('configMenu');
    const icon = toggleConfig.querySelector('i');
    
    const isConfigHidden = localStorage.getItem('configMenuHidden') === 'true';
    
    if (isConfigHidden) {
        configMenu.style.display = 'none';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    }
    
    toggleConfig.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (configMenu.style.display === 'none') {
            configMenu.style.display = 'block';
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            localStorage.setItem('configMenuHidden', 'false');
        } else {
            configMenu.style.display = 'none';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            localStorage.setItem('configMenuHidden', 'true');
        }
    });
    
    // ========================================
    // SIDEBAR RESPONSIVO
    // ========================================
    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    const navbarToggler = document.querySelector('.navbar-toggler');
    
    // Función para cerrar el sidebar
    function closeSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    // Función para abrir el sidebar
    function openSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            // Prevenir scroll en móvil cuando el menú está abierto
            if (window.innerWidth <= 1024) {
                document.body.style.overflow = 'hidden';
            }
        }
    }
    
    // Click en el overlay para cerrar
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Click en el botón hamburguesa
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }
    
    // Cerrar sidebar al hacer click en un enlace (solo en móvil/tablet)
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                closeSidebar();
            }
        });
    });
    
    // Cerrar sidebar al redimensionar a desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        }, 250);
    });
    
    // Prevenir propagación de clicks dentro del sidebar
    if (sidebar) {
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // ========================================
    // DETECCIÓN DE PÁGINA ACTIVA
    // ========================================
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href)) {
            link.classList.add('active');
        }
    });
    
    // ========================================
    // SOPORTE PARA ESC PARA CERRAR SIDEBAR
    // ========================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // ========================================
    // PREVENIR SCROLL HORIZONTAL
    // ========================================
    document.body.style.overflowX = 'hidden';
});
</script>



