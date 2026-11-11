<?php
include_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../auth.php';
checkAuth();
 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket GK - Sistema Moderno</title>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Sistema de diseño moderno -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('/tickets/css/modern-design-system.css'), ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Sidebar responsivo -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('/tickets/css/responsive-sidebar.css'), ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Responsive del módulo de tickets -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('/tickets/modules/tickets/css/tickets-responsive.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('/tickets/modules/tickets/css/tickets-theme.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('/tickets/css/notifications-panel.css'), ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Estilos específicos para la barra de búsqueda -->
    <style>
        #searchInput {
            background-color: white !important;
            color: #333 !important;
            border: 1px solid #ddd !important;
        }
        #searchInput:focus {
            background-color: white !important;
            color: #333 !important;
            border-color: #007bff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        }
        #searchInput:hover {
            background-color: white !important;
            color: #333 !important;
            border-color: #007bff !important;
        }
        #searchInput::placeholder {
            color: #6c757d !important;
        }
        .search-container {
            min-width: 250px;
        }
        
        /* Header compacto - Desktop */
        .navbar {
            height: 56px !important;
            padding: 8px 16px !important;
        }
        
        .navbar-brand {
            font-size: 1.1rem !important;
        }
        
        .navbar-brand img {
            height: 28px !important;
        }
        
        .search-container {
            max-width: 400px;
        }
        
        /* Desktop grande */
        @media (min-width: 1441px) {
            .navbar {
                height: 56px !important;
                padding: 8px 20px !important;
            }
            body {
                padding-top: 56px !important;
            }
        }
        
        /* Desktop mediano */
        @media (min-width: 1025px) and (max-width: 1440px) {
            .navbar {
                height: 56px !important;
                padding: 8px 16px !important;
            }
            body {
                padding-top: 56px !important;
            }
        }
        
        /* Tablet */
        @media (min-width: 769px) and (max-width: 1024px) {
            .navbar-toggler {
                display: block !important;
                padding: 4px 8px !important;
                margin-right: 8px !important;
            }
            .navbar {
                height: 48px !important;
                padding: 6px 12px !important;
            }
            .navbar-brand {
                font-size: 1rem !important;
            }
            .navbar-brand img {
                height: 24px !important;
            }
            .search-container {
                max-width: 300px;
                min-width: 200px;
            }
            body {
                padding-top: 48px !important;
            }
        }
        
        /* Móvil */
        @media (max-width: 768px) {
            .navbar-toggler {
                display: block !important;
                padding: 4px 8px !important;
                margin-right: 8px !important;
            }
            .navbar {
                height: 44px !important;
                padding: 4px 10px !important;
            }
            .navbar-brand {
                font-size: 0.95rem !important;
            }
            .navbar-brand img {
                height: 22px !important;
                margin-right: 6px !important;
            }
            .search-container {
                display: none !important;
            }
            .navbar-nav .nav-link {
                padding: 4px 8px !important;
            }
            body {
                padding-top: 44px !important;
            }
        }
        
        /* Móvil pequeño */
        @media (max-width: 480px) {
            .navbar {
                height: 40px !important;
                padding: 3px 8px !important;
            }
            .navbar-brand {
                font-size: 0.9rem !important;
            }
            .navbar-brand img {
                height: 20px !important;
            }
            .navbar-toggler {
                padding: 3px 6px !important;
                margin-right: 6px !important;
            }
            body {
                padding-top: 40px !important;
            }
        }
        
        /* ========================================== */
        /* ESTILOS ESPECÍFICOS PARA PERFIL DE USUARIO */
        /* ========================================== */
        
        /* Sobrescribir estilos de Bootstrap para .nav-link */
        .navbar-nav .nav-item .nav-link.user-profile-nav,
        .nav-link.user-profile-nav,
        a.user-profile-nav {
            color: #ffffff !important;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 16px !important;
            border-radius: 8px;
            text-decoration: none !important;
        }
        
        .navbar-nav .nav-item .nav-link.user-profile-nav:hover,
        .nav-link.user-profile-nav:hover,
        a.user-profile-nav:hover {
            text-decoration: none !important;
        }
        
        /* Estado normal - ICONOS en BLANCO */
        .navbar-nav .nav-item .nav-link.user-profile-nav .avatar i,
        .navbar-nav .nav-item .nav-link.user-profile-nav i,
        .nav-link.user-profile-nav .avatar i,
        .nav-link.user-profile-nav i,
        a.user-profile-nav .avatar i,
        a.user-profile-nav i,
        .user-profile-nav .fa-user-circle {
            color: #ffffff !important;
            transition: color 0.3s ease;
        }
        
        /* Estado normal - TEXTO en BLANCO */
        .navbar-nav .nav-item .nav-link.user-profile-nav span,
        .nav-link.user-profile-nav span,
        a.user-profile-nav span,
        .user-profile-nav span {
            color: #ffffff !important;
            font-weight: 500 !important;
            transition: color 0.3s ease;
            opacity: 1 !important;
        }
        
        /* Estado hover - Fondo semi-transparente blanco */
        .navbar-nav .nav-item .nav-link.user-profile-nav:hover,
        .nav-link.user-profile-nav:hover,
        a.user-profile-nav:hover {
            background-color: rgba(255, 255, 255, 0.25) !important;
            backdrop-filter: blur(4px);
            color: #000000 !important;
        }
        
        /* Estado hover - ICONOS en NEGRO */
        .navbar-nav .nav-item .nav-link.user-profile-nav:hover .avatar i,
        .navbar-nav .nav-item .nav-link.user-profile-nav:hover i,
        .nav-link.user-profile-nav:hover .avatar i,
        .nav-link.user-profile-nav:hover i,
        a.user-profile-nav:hover .avatar i,
        a.user-profile-nav:hover i,
        .user-profile-nav:hover .fa-user-circle {
            color: #000000 !important;
        }
        
        /* Estado hover - TEXTO en NEGRO */
        .navbar-nav .nav-item .nav-link.user-profile-nav:hover span,
        .nav-link.user-profile-nav:hover span,
        a.user-profile-nav:hover span,
        .user-profile-nav:hover span {
            color: #000000 !important;
        }
        
        /* Responsivo - Ajustes para pantallas pequeñas */
        @media (max-width: 768px) {
            .navbar-nav .nav-item .nav-link.user-profile-nav,
            .nav-link.user-profile-nav,
            a.user-profile-nav {
                padding: 6px 12px !important;
            }
            .user-profile-nav .avatar i,
            a.user-profile-nav .avatar i {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-nav .nav-item .nav-link.user-profile-nav,
            .nav-link.user-profile-nav,
            a.user-profile-nav {
                padding: 4px 8px !important;
            }
        }
        /* ========================================== */
    </style>
    <?php if (isset($_SESSION['user'])): ?>
    <script src="<?php echo htmlspecialchars(appUrl('/tickets/js/session-timeout.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
</head>
<body>
    <!-- Header moderno -->
    <header class="navbar navbar-dark sticky-top flex-md-nowrap p-0 shadow" style="background: linear-gradient(135deg, #202654 0%, #1a1f45 100%); position: fixed; top: 0; left: 0; right: 0; z-index: 1030;">
        <div class="d-flex align-items-center">
            <!-- Botón toggle para sidebar (visible en móvil y tablet) -->
            <button class="navbar-toggler me-3" type="button" id="sidebarToggle" style="display: none;">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Logo y marca -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo htmlspecialchars(appUrl('/direc.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars(appUrl('/tickets/includes/logoblanco.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo GK" style="height: 32px; width: auto; margin-right: 10px;">
                <span>Ticket GK</span>
            </a>
        </div>
        
        <!-- Barra de búsqueda -->
        <div class="search-container d-flex position-relative">
            <input class="search-input form-control" type="text" placeholder="Buscar tickets, usuarios, técnicos..." aria-label="Search" id="searchInput" autocomplete="off">
            <div id="searchSuggestions" class="search-suggestions position-absolute w-100" style="display: none; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto; top: 100%;">
                <!-- Las sugerencias se cargarán aquí dinámicamente -->
            </div>
        </div>
        
        <!-- Acciones del usuario -->
        <div class="navbar-nav d-flex flex-row align-items-center">
            <!-- Notificaciones -->
            <div class="nav-item me-2 me-md-3">
                <a href="#" class="notif-bell-btn" id="notifBellBtn" role="button" aria-label="Notificaciones" title="Notificaciones">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge" aria-hidden="true"></span>
                </a>
            </div>
            
            <!-- Perfil del usuario -->
            <div class="nav-item">
                <a href="<?php echo htmlspecialchars(appUrl('/perfil-usuario.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-link d-flex align-items-center user-profile-nav" style="color: #ffffff !important; text-decoration: none;">
                    <div class="avatar me-2">
                        <i class="fas fa-user-circle fa-lg" style="color: #ffffff !important;"></i>
                    </div>
                    <span class="d-none d-md-inline" style="color: #ffffff !important; font-weight: 500;"><?php echo $_SESSION['user']['username'] ?? 'Usuario'; ?></span>
                </a>
            </div>
        </div>
    </header>

    <!-- Panel lateral de notificaciones -->
    <div class="notif-overlay" id="notifOverlay" aria-hidden="true"></div>
    <aside class="notif-panel" id="notifPanel" aria-label="Notificaciones de mensajes" aria-hidden="true">
        <div class="notif-panel-header">
            <div>
                <h2 class="notif-panel-title">Notificaciones</h2>
                <p class="notif-panel-subtitle">Mensajes entrantes de tickets</p>
            </div>
            <div class="notif-panel-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="notifMarkAll" title="Marcar todas como leídas">
                    Marcar leídas
                </button>
                <button type="button" class="notif-panel-close" id="notifCloseBtn" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="notif-panel-body" id="notifList">
            <div class="notif-loading"><i class="fas fa-spinner fa-spin me-1"></i>Cargando…</div>
        </div>
    </aside>

    <script>
        window.TICKETS_NOTIF_CONFIG = {
            apiUrl: <?php echo json_encode(appUrl('/tickets/includes/api/notifications.php')); ?>,
            viewBase: <?php echo json_encode(appUrl('/tickets/modules/tickets/view.php?id=')); ?>,
            userId: <?php echo (int) ($_SESSION['user']['id'] ?? 0); ?>
        };
    </script>
    <script src="<?php echo htmlspecialchars(appUrl('/tickets/js/notifications-panel.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Funcionalidad de hover para perfil de usuario (enlace)
            const userProfile = document.querySelector('a.user-profile-nav');
            if (userProfile) {
                // Efecto hover para cambio de colores
                userProfile.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('i');
                    const span = this.querySelector('span');
                    if (icon) icon.style.color = '#000000';
                    if (span) span.style.color = '#000000';
                });
                
                userProfile.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('i');
                    const span = this.querySelector('span');
                    if (icon) icon.style.color = '#ffffff';
                    if (span) span.style.color = '#ffffff';
                });
            }
            
            // Funcionalidad de búsqueda
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');
            const searchSuggestions = document.getElementById('searchSuggestions');
            let searchTimeout;
            
            function performSearch() {
                const searchTerm = searchInput.value.trim();
                if (searchTerm) {
                    // Usar la búsqueda inteligente
                    window.location.href = <?php echo json_encode(appUrl('/tickets/includes/smart_search.php')); ?> + '?q=' + encodeURIComponent(searchTerm);
                }
            }
            
            function loadSuggestions(query) {
                if (query.length < 2) {
                    searchSuggestions.style.display = 'none';
                    return;
                }
                
                fetch(<?php echo json_encode(appUrl('/tickets/includes/search_suggestions.php')); ?> + '?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        displaySuggestions(data);
                    })
                    .catch(error => {
                        console.error('Error cargando sugerencias:', error);
                        searchSuggestions.style.display = 'none';
                    });
            }
            
            function displaySuggestions(suggestions) {
                if (suggestions.length === 0) {
                    searchSuggestions.style.display = 'none';
                    return;
                }
                
                const html = suggestions.map(item => `
                    <div class="suggestion-item p-2 border-bottom" style="cursor: pointer; display: flex; align-items: center;" data-url="${item.url}">
                        <i class="${item.icon} me-2" style="color: #6c757d;"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold">${item.name}</div>
                            <small class="text-muted">${item.email}</small>
                        </div>
                        <span class="badge bg-secondary">${item.type}</span>
                    </div>
                `).join('');
                
                searchSuggestions.innerHTML = html;
                searchSuggestions.style.display = 'block';
                
                // Agregar event listeners a las sugerencias
                searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        window.location.href = this.dataset.url;
                    });
                    
                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8f9fa';
                    });
                    
                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'white';
                    });
                });
            }
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        loadSuggestions(this.value);
                    }, 300);
                });
                
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        // Si hay sugerencias visibles, seleccionar la primera
                        const firstSuggestion = searchSuggestions.querySelector('.suggestion-item');
                        if (firstSuggestion && searchSuggestions.style.display !== 'none') {
                            window.location.href = firstSuggestion.dataset.url;
                        } else {
                            // Si no hay sugerencias, hacer búsqueda general
                            performSearch();
                        }
                    } else if (e.key === 'Escape') {
                        searchSuggestions.style.display = 'none';
                    }
                });
                
                searchInput.addEventListener('blur', function() {
                    // Delay para permitir clicks en sugerencias
                    setTimeout(() => {
                        searchSuggestions.style.display = 'none';
                    }, 200);
                });
                
                searchInput.addEventListener('focus', function() {
                    if (this.value.length >= 2) {
                        loadSuggestions(this.value);
                    }
                });
            }
            
            if (searchButton) {
                searchButton.addEventListener('click', performSearch);
            }
            
            // El toggle del sidebar ahora se maneja en modern-sidebar.php
            // Aquí solo agregamos funcionalidad adicional si es necesaria
        });
    </script>