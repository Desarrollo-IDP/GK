<?php
 require_once __DIR__ . '/auth.php';
 checkAuth();
 ?> 
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Selección de Opciones</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <style>
            :root {
                --primary-color: #1E2A3B;
                --secondary-color: #2C3E50;
                --white: #ffffff;
            }
    
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Poppins', sans-serif;
            }
    
            body {
                min-height: 100vh;
                background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color));
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 1rem;
            }
    
            .container {
                text-align: center;
                padding: 2rem;
                width: 100%;
                max-width: 1400px;
            }
    
            .options-container {
                display: flex;
                justify-content: center;
                gap: 2.5rem;
                flex-wrap: nowrap;
                margin-top: 2rem;
            }
            
            h1 {
                color: var(--white);
                margin-bottom: 2rem;
                font-size: 2.5rem;
                font-weight: 700;
            }

            .user-management-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--white);
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .user-management-btn-profile {
                position: fixed;
                top: 100px;
                right: 20px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--white);
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .user-management-btn-logout {
                position: fixed;
                top: 180px;
                right: 20px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--white);
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .user-management-btn:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }

            .user-management-btn i {
                font-size: 1.5rem;
                color: var(--primary-color);
            }

            .user-management-btn-profile:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }

            .user-management-btn-profile i {
                font-size: 1.5rem;
                color: var(--primary-color);
            }

            .user-management-btn-logout:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }

            .user-management-btn-logout i {
                font-size: 1.5rem;
                color: var(--primary-color);
            }
    
            .option-card {
                cursor: pointer;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                width: 280px;
                flex-shrink: 0;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }

            .option-icon-area {
                min-height: clamp(100px, 24vw, 200px);
                display: grid;
                place-items: center;
                padding: clamp(1rem, 4vw, 2rem);
                background: #1E2A3B;
                color: var(--white);
            }

            .option-icon-area i {
                font-size: clamp(3rem, 10vw, 6rem);
                line-height: 1;
            }
    
            .option-card:hover {
                transform: scale(1.05);
                box-shadow: 0 15px 30px rgba(0,0,0,0.3);
            }
    
            .option-image {
                width: 100%;
                height: 200px;
                object-fit: contain;
                background: #1E2A3B;
                display: block;
                margin: 0 auto;
                padding: 20px;
            }
    
            .option-text {
                padding: 1.5rem;
                background: var(--white);
                color: var(--primary-color);
                font-weight: bold;
                font-size: 1.2rem;
            }
    
            footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(30, 42, 59, 0.9);
                backdrop-filter: blur(10px);
                padding: 1rem;
                text-align: center;
                color: var(--white);
                font-size: 0.9rem;
                z-index: 999;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
    
            footer .version {
                font-weight: 500;
                opacity: 0.9;
            }
    
            /* Responsive para desktop grande */
            @media (min-width: 1441px) {
                h1 {
                    font-size: 3rem;
                }
                
                .container {
                    padding: 3rem;
                }
                
                .options-container {
                    gap: 4rem;
                }
                
                .option-card {
                    width: 350px;
                }
                
                .option-image {
                    height: 240px;
                }

                .option-icon-area {
                    min-height: 240px;
                }
                
                .option-text {
                    font-size: 1.3rem;
                    padding: 1.75rem;
                }
            }
            
            /* Responsive para tablet */
            @media (max-width: 1024px) {
                h1 {
                    font-size: 2rem;
                }
                
                .container {
                    padding: 1.5rem;
                }
                
                .options-container {
                    gap: 2rem;
                }
                
                .option-card {
                    width: 280px;
                }
            }
            
            /* Responsive para tablet pequeña */
            @media (max-width: 768px) {
                body {
                    padding: 0.75rem;
                    padding-bottom: 60px;
                }
                
                h1 {
                    font-size: 1.75rem;
                    margin-bottom: 1.5rem;
                }
                
                .container {
                    padding: 1rem;
                }
                
                .options-container {
                    flex-direction: column;
                    gap: 2rem;
                    align-items: center;
                }
                
                .option-card {
                    width: 100%;
                    max-width: 350px;
                }

                .user-management-btn {
                    top: 15px;
                    right: 15px;
                    width: 50px;
                    height: 50px;
                }

                .user-management-btn i {
                    font-size: 1.2rem;
                }

                .user-management-btn-profile {
                    top: 80px;
                    right: 15px;
                    width: 50px;
                    height: 50px;
                }

                .user-management-btn-profile i {
                    font-size: 1.2rem;
                }

                .user-management-btn-logout {
                    top: 145px;
                    right: 15px;
                    width: 50px;
                    height: 50px;
                }

                .user-management-btn-logout i {
                    font-size: 1.2rem;
                }

                footer {
                    padding: 0.75rem;
                    font-size: 0.85rem;
                }
            }
            
            /* Responsive para móvil */
            @media (max-width: 480px) {
                body {
                    padding: 0.5rem;
                    padding-bottom: 55px;
                }
                
                h1 {
                    font-size: 1.5rem;
                    margin-bottom: 1rem;
                }
                
                .container {
                    padding: 0.75rem;
                }
                
                .options-container {
                    gap: 1.5rem;
                }
                
                .option-card {
                    max-width: 100%;
                }
                
                .option-image {
                    height: 160px;
                }

                .option-icon-area {
                    min-height: 140px;
                }
                
                .option-text {
                    font-size: 1rem;
                    padding: 1rem;
                }
                
                .user-management-btn,
                .user-management-btn-profile,
                .user-management-btn-logout {
                    width: 45px;
                    height: 45px;
                }
                
                .user-management-btn {
                    top: 10px;
                    right: 10px;
                }
                
                .user-management-btn-profile {
                    top: 65px;
                    right: 10px;
                }
                
                .user-management-btn-logout {
                    top: 120px;
                    right: 10px;
                }
                
                .user-management-btn i,
                .user-management-btn-profile i,
                .user-management-btn-logout i {
                    font-size: 1rem;
                }

                footer {
                    padding: 0.6rem;
                    font-size: 0.8rem;
                }
            }
            
            /* Responsive para móvil muy pequeño */
            @media (max-width: 320px) {
                body {
                    padding-bottom: 50px;
                }
                
                h1 {
                    font-size: 1.25rem;
                }
                
                .option-image {
                    height: 140px;
                }

                .option-icon-area {
                    min-height: 120px;
                }
                
                .option-text {
                    font-size: 0.9rem;
                    padding: 0.75rem;
                }

                footer {
                    padding: 0.5rem;
                    font-size: 0.75rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Selecciona una opción </h1>
            
            
            <div class="options-container">
                <!--<div class="option-card" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/almacen/modules/inicio/inicio.php'), ENT_QUOTES, 'UTF-8'); ?>')">
                    <img src="Inventario/img/logoblanco.png" alt="Opción 1" class="option-image">
                    <div class="option-text">almacén</div>
                </div>-->
            <?php if (isAdmin()): ?>
                <div class="option-card" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/tickets/inicio-moderno.php'), ENT_QUOTES, 'UTF-8'); ?>')">
                    <div class="option-icon-area" aria-hidden="true"><i class="fas fa-ticket-alt"></i></div>
                    <div class="option-text">Ticket</div>
                </div>
            <?php endif; ?>
                <!--<div class="option-card" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/Inventario/crud/inicio.php'), ENT_QUOTES, 'UTF-8'); ?>')">
                    <img src="Inventario/img/logoblanco.png" alt="Opción 3" class="option-image">
                    <div class="option-text">Inventario</div>
                </div>-->
            </div>
        </div>

        <?php if (function_exists('canManageUsers') && canManageUsers()): ?>
            <button class="user-management-btn" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/gestion-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>')" title="Gestión de Usuarios">
                <i class="fas fa-user-cog"></i>
            </button>
        <?php endif; ?>
        
        
        <button class="user-management-btn-profile" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/perfil-usuario.php'), ENT_QUOTES, 'UTF-8'); ?>')" title="Mi Perfil">
            <i class="fas fa-user"></i>
        </button>
        
        <button class="user-management-btn-logout" onclick="redirectTo('<?php echo htmlspecialchars(appUrl('/logout.php'), ENT_QUOTES, 'UTF-8'); ?>')" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt"></i>
        </button>  

        <footer>
            <div class="version">Versión 5.3.1</div>
        </footer>

        <script>
            function redirectTo(route) {
                window.location.href = route;
            }
        </script>
    </body>
    </html>