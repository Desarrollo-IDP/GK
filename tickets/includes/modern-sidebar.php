<!-- Sidebar moderno responsivo -->
<nav class="sidebar">
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inicio-moderno.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>inicio-moderno.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Gestión de Tickets -->
        <div class="nav-section">
            <div class="nav-section-title">Gestión de Tickets</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/tickets.php">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Tickets Activos</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'all_tickets.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/all_tickets.php">
                    <i class="fas fa-list"></i>
                    <span>Todos los Tickets</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'closed_tickets.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/closed_tickets.php">
                    <i class="fas fa-archive"></i>
                    <span>Tickets Cerrados</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'new.php' && strpos($_SERVER['PHP_SELF'], 'modules/tickets') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/tickets/new.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nuevo Ticket</span>
                </a>
            </div>
        </div>

        <!-- Tareas -->
        <div class="nav-section">
            <div class="nav-section-title">Tareas</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/task/tasks.php">
                    <i class="fas fa-tasks"></i>
                    <span>Tareas activas</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'closed_tasks.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/task/closed_tasks.php">
                    <i class="fas fa-archive"></i>
                    <span>Tareas cerradas</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'new.php' && strpos($_SERVER['PHP_SELF'], 'modules/task') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/task/new.php">
                    <i class="fas fa-plus"></i>
                    <span>Nueva tarea</span>
                </a>
            </div>
        </div>

        <!-- Técnicos -->
        <div class="nav-section">
            <div class="nav-section-title">Técnicos</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'technicians.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/technicians/technicians.php">
                    <i class="fas fa-tools"></i>
                    <span>Técnicos</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add.php' && strpos($_SERVER['PHP_SELF'], 'technicians') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/technicians/add.php">
                    <i class="fas fa-user-cog"></i>
                    <span>Nuevo Técnico</span>
                </a>
            </div>
        </div>

        <!-- Solicitantes -->
        <div class="nav-section">
            <div class="nav-section-title">Solicitantes</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requester.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/requester/requester.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Solicitantes</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add.php' && strpos($_SERVER['PHP_SELF'], 'requester') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/requester/add.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Solicitante</span>
                </a>
            </div>
        </div>

        <!-- Empresas -->
        <div class="nav-section">
            <div class="nav-section-title">Empresas</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'empresas.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/empresas/empresas.php">
                    <i class="fas fa-building"></i>
                    <span>Empresas</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add.php' && strpos($_SERVER['PHP_SELF'], 'empresas') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/empresas/add.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nueva Empresa</span>
                </a>
            </div>
        </div>

        <!-- Reportes -->
        <div class="nav-section">
            <div class="nav-section-title">Reportes</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/reports/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </div>
        </div>

        <!-- Configuración -->
        <div class="nav-section">
            <div class="nav-section-title">Configuración</div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuration.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/configuration/configuration.php">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blacklist_domains.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>modules/configuration/blacklist_domains.php">
                    <i class="fas fa-ban"></i>
                    <span>Dominios Bloqueados</span>
                </a>
            </div>
        </div>

        <!-- Mi Cuenta -->
        <div class="nav-section">
            <div class="nav-section-title">Mi Cuenta</div>
            <div class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>logout.php" id="logoutLink">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer del sidebar -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="avatar me-2">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="sidebar-footer-text">
                    <div class="text-sm font-semibold">GK</div>
                    <!--<div class="text-xs text-muted">v2.0</div> -->
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Overlay para cerrar sidebar en móvil/tablet -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    
    // Función para abrir/cerrar sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        body.classList.toggle('sidebar-open', sidebar.classList.contains('show'));
    }
    
    // Toggle desde el botón del header
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Cerrar al hacer clic en el overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }
    
    // Cerrar al presionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            toggleSidebar();
        }
    });
    
    // Resaltar elemento activo en el sidebar
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.href && currentPath.includes(link.href.split('/').pop())) {
            link.classList.add('active');
        }
    });
});
</script>