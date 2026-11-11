<?php
require_once __DIR__ . '/../../../auth.php';
 
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Sistema de Gestión'; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding-left: 80px;
            padding-top: 70px;
            transition: padding-left 0.25s ease;
            color: #111827;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #0f172a;
            padding: 10px 24px;
            color: #f9fafb;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1001;
            height: 70px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
            border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        }

        .navbar .logo img {
            max-width: 130px;
            height: auto;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.25));
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 10px 15px;
            }

            .navbar .logo img {
                max-width: 100px;
            }
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            z-index: 1002;
        }

        .menu-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            color: inherit;
        }

        .user-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .user-menu a:hover img {
            transform: scale(1.05);
        }

        .user-menu .user-name {
            color: #e5e7eb;
            font-weight: 500;
            font-size: 14px;
            white-space: nowrap;
            display: none;
        }

        @media screen and (min-width: 768px) {
            .user-menu .user-name {
                display: block;
            }
        }

        .sidebar {
            width: 80px;
            height: calc(100vh - 70px);
            background: #020617;
            color: #e5e7eb;
            padding: 10px 0;
            position: fixed;
            top: 70px;
            left: 0;
            z-index: 999;
            transition: transform 0.25s ease, width 0.25s ease, box-shadow 0.25s ease;
            overflow-x: hidden;
            overflow-y: auto;
            border-right: 1px solid rgba(15, 23, 42, 0.85);
        }

        .sidebar:hover {
            width: 190px;
            box-shadow: 8px 0 25px rgba(15, 23, 42, 0.65);
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            padding: 10px 16px;
            transition: background-color 0.2s ease, transform 0.15s ease;
            margin: 2px 8px;
            border-radius: 10px;
        }

        .sidebar ul li:hover {
            background-color: rgba(31, 41, 55, 0.85);
            transform: translateX(2px);
        }

        .sidebar ul li a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            font-size: 14px;
            letter-spacing: 0.01em;
        }

        .sidebar ul li a i {
            font-size: 20px;
            min-width: 24px;
            margin-right: 6px;
        }

        .sidebar ul li a span {
            display: none;
            transition: opacity 0.2s ease;
        }

        .sidebar:hover ul li a span {
            display: inline;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: calc(100vh - 70px);
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .overlay.active {
            display: block;
            opacity: 1;
        }

        /* Responsive: Mobile (320px - 768px) */
        @media (max-width: 768px) {
            body {
                padding-left: 0;
                padding-top: 70px;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                top: 70px;
                height: calc(100vh - 70px);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar:hover {
                width: 250px;
            }

            .sidebar ul li a span {
                display: inline;
            }

            .overlay.active {
                display: block;
            }
        }

        /* Responsive: Tablet (769px - 1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            body {
                padding-left: 0;
                padding-top: 70px;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                top: 70px;
                height: calc(100vh - 70px);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar:hover {
                width: 250px;
            }

            .sidebar ul li a span {
                display: inline;
            }

            .overlay.active {
                display: block;
            }
        }

        /* Responsive: Desktop (1025px - 1440px) */
        @media (min-width: 1025px) and (max-width: 1440px) {
            body {
                padding-left: 80px;
                padding-top: 70px;
            }

            .menu-toggle {
                display: none;
            }

            .sidebar {
                width: 80px;
                transform: translateX(0);
            }

            .overlay {
                display: none !important;
            }
        }

        /* Responsive: Large Screens (1441px+) */
        @media (min-width: 1441px) {
            body {
                padding-left: 80px;
                padding-top: 70px;
            }

            .menu-toggle {
                display: none;
            }

            .sidebar {
                width: 80px;
                transform: translateX(0);
            }

            .overlay {
                display: none !important;
            }
        }

        /* Responsive Tables */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 768px) {
            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 8px 4px;
            }

            .table-container {
                max-width: 100%;
            }
        }

        /* Responsive Forms */
        @media (max-width: 768px) {

            .form-container,
            form {
                width: 100%;
                padding: 10px;
            }

            input[type="text"],
            input[type="date"],
            input[type="email"],
            input[type="password"],
            select,
            textarea {
                width: 100%;
                font-size: 16px;
                /* Evita zoom en iOS */
            }

            .grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Responsive Buttons */
        @media (max-width: 768px) {

            button,
            .btn,
            a[class*="bg-"] {
                width: 100%;
                margin-bottom: 10px;
                padding: 12px;
                font-size: 16px;
            }

            .flex {
                flex-direction: column;
            }

            .flex button,
            .flex a {
                width: 100%;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }

        /* Responsive Cards and Containers */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .grid {
                grid-template-columns: 1fr !important;
                gap: 10px;
            }

            .max-w-5xl {
                max-width: 100%;
                padding: 15px;
            }

            .flex.gap-4,
            .flex.items-end,
            .flex.justify-between {
                flex-direction: column;
                gap: 10px;
            }

            .flex.gap-4>*,
            .flex.items-end>*,
            .flex.justify-between>* {
                width: 100%;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .flex.gap-4 {
                flex-wrap: wrap;
            }
        }

        /* Responsive Form Actions */
        @media (max-width: 768px) {
            .flex.items-center.justify-between {
                flex-direction: column;
                gap: 10px;
            }

            .flex.items-center.justify-between>* {
                width: 100%;
            }
        }

        /* Responsive Select and Input Groups */
        @media (max-width: 768px) {
            .flex-1 {
                width: 100%;
            }

            select,
            input[type="text"],
            input[type="date"],
            input[type="file"] {
                width: 100%;
            }
        }

        /* Responsive Button Groups */
        @media (max-width: 768px) {
            .flex.gap-2 {
                flex-direction: column;
                width: 100%;
            }

            .flex.gap-2>* {
                width: 100%;
            }
        }

        /* Modal de notificación profesional */
        .app-alert-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: appAlertFadeIn 0.2s ease;
        }

        .app-alert-overlay.is-visible {
            display: flex;
        }

        @keyframes appAlertFadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .app-alert-modal {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 420px;
            width: 100%;
            overflow: hidden;
            animation: appAlertSlideIn 0.25s ease;
        }

        @keyframes appAlertSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .app-alert-icon-wrap {
            padding: 24px 24px 8px;
            text-align: center;
        }

        .app-alert-icon-wrap .fa-circle-exclamation {
            color: #dc2626;
            font-size: 48px;
        }

        .app-alert-icon-wrap .fa-circle-check {
            color: #16a34a;
            font-size: 48px;
        }

        .app-alert-icon-wrap .fa-triangle-exclamation {
            color: #d97706;
            font-size: 48px;
        }

        .app-alert-title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            padding: 0 24px 8px;
        }

        .app-alert-message {
            text-align: center;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
            padding: 0 24px 24px;
            max-height: 200px;
            overflow-y: auto;
        }

        .app-alert-actions {
            padding: 0 24px 24px;
            display: flex;
            justify-content: center;
        }

        .app-alert-actions .app-alert-btn {
            min-width: 120px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .app-alert-actions .app-alert-btn-primary {
            background: #1E2A3B;
            color: #fff;
        }

        .app-alert-actions .app-alert-btn-primary:hover {
            background: #2d3a50;
        }

        /* Modal de confirmación */
        .app-confirm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: appAlertFadeIn 0.2s ease;
        }

        .app-confirm-overlay.is-visible {
            display: flex;
        }

        .app-confirm-modal {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
            animation: appAlertSlideIn 0.25s ease;
        }

        .app-confirm-icon-wrap {
            padding: 24px 24px 8px;
            text-align: center;
        }

        .app-confirm-icon-wrap i {
            color: #1E2A3B;
            font-size: 44px;
        }

        .app-confirm-title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            padding: 0 24px 8px;
        }

        .app-confirm-message {
            text-align: center;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
            padding: 0 24px 20px;
        }

        .app-confirm-actions {
            padding: 0 24px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .app-confirm-actions button {
            min-width: 110px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .app-confirm-btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .app-confirm-btn-cancel:hover {
            background: #e5e7eb;
        }

        .app-confirm-btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .app-confirm-btn-danger:hover {
            background: #b91c1c;
        }
    </style>
    <?php if (isset($_SESSION['user'])): ?>
        <script src="../js/session-timeout.js"></script>
    <?php endif; ?>
</head>

<body class="bg-gray-200 text-gray-800">
    <nav class="navbar">
        <div style="display: flex; align-items: center; gap: 15px;">
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <a href="../../../direc.php">
                    <img src="../img/logolargoblanco.png" alt="Logo" width="120">
                </a>
            </div>
        </div>
        <div class="user-menu">
            <a href="../../../perfil-usuario.php" title="Ver mi perfil">
                <img src="../img/usuario.png" alt="Usuario">
                <span
                    class="user-name"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario'); ?></span>
            </a>
        </div>
    </nav>

    <div class="overlay" id="overlay"></div>

    <div class="sidebar" id="sidebar">
        <ul>
            <br>
            <li><a href="./inicio.php"><i class="fas fa-home"></i> <span>Inicio</span></a></li><br>
            <li><a href="./visualizar-equipos.php"><i class="fas fa-desktop"></i> <span>Equipos</span></a></li><br>

            <?php if (isAdmin()): ?>
                <li><a href="./visualizar-empresas.php"><i class="fas fa-building"></i> <span>Empresas</span></a></li><br>
                <li><a href="./importar-equipos.php"><i class="fas fa-file-import"></i> <span>Importar</span></a></li><br>
            <?php endif; ?>
            <li><a href="./reporte.php"><i class="fas fa-file-export"></i> <span>Exportar</span></a></li><br>
            <?php if (isAdmin()): ?>
                <li><a href="./memoria-tecnica.php"><i class="fa-solid fa-clipboard"></i> <span>Memoria Técnica</span></a>
                </li>
                <br>
            <?php endif; ?>

            <?php if (function_exists('canViewInventoryHistory') && canViewInventoryHistory()): ?>
                <li><a href="./historial-actividades.php"><i class="fas fa-history"></i> <span>Historial</span></a></li><br>
            <?php endif; ?>
            <li><a href="./configuracion-inventario.php"><i class="fas fa-cog"></i> <span>Configuración</span></a></li>
            <br>
            <?php /* Removing this duplicate entry that shows "Usuarios" for all logged-in users
<?php if (isset($_SESSION['user'])):?>
<li><a href="../gestion-usuarios.php"><i class="fas fa-users"></i> <span>Usuarios</span></a></li><br>
<?php endif;?>
*/ ?>
            <li><a href="./logout.php"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a></li><br>
        </ul>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            const overlay = document.getElementById('overlay');
            const isMobile = window.innerWidth <= 1024;

            // Funcionalidad del sidebar en desktop
            if (!isMobile) {
                sidebar.addEventListener('mouseenter', () => {
                    document.body.style.paddingLeft = '180px';
                });
                sidebar.addEventListener('mouseleave', () => {
                    document.body.style.paddingLeft = '80px';
                });
            }

            // Funcionalidad del menú móvil/tablet
            function toggleMenu() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');

                // Cambiar icono del botón
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }

            // Toggle del menú
            if (menuToggle) {
                menuToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleMenu();
                });
            }

            // Cerrar menú al hacer clic en overlay
            if (overlay) {
                overlay.addEventListener('click', function () {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                });
            }

            // Cerrar menú al hacer clic en un enlace del sidebar (móvil/tablet)
            if (isMobile) {
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function () {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                        const icon = menuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    });
                });
            }

            // Manejar resize de ventana
            let resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    const currentIsMobile = window.innerWidth <= 1024;
                    if (currentIsMobile !== isMobile) {
                        location.reload();
                    }
                }, 250);
            });
        });
    </script>

    <div class="container mx-auto p-4" style="margin-top: 0;">