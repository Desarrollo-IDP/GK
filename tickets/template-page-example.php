<?php
/**
 * TEMPLATE DE PÁGINA CON SIDEBAR RESPONSIVO
 * 
 * Este es un template de ejemplo que muestra cómo implementar
 * correctamente el sistema de sidebar responsivo en cualquier página.
 * 
 * Copia este archivo y modifica según tus necesidades.
 */

// Incluir archivos comunes
include 'includes/common.php';
include 'conexion/db.php';

// Tu lógica PHP aquí
// Ejemplo:
// $data = obtenerDatos();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título de la Página - Sistema de Tickets</title>
    
    <!-- ========================================
         CSS REQUERIDOS (en este orden)
         ======================================== -->
    
    <!-- 1. Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 2. Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- 3. Estilos base del sistema -->
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- 4. ⭐ IMPORTANTE: Sistema responsivo del sidebar (debe ir después de styles.css) -->
    <link rel="stylesheet" href="css/responsive-sidebar.css">
    
    <!-- 5. CSS adicionales específicos de tu página (opcional) -->
    <!-- <link rel="stylesheet" href="css/mi-pagina.css"> -->
    
    <!-- JavaScript adicional (Chart.js, etc.) -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
</head>
<body>
    <!-- ========================================
         HEADER
         Incluye el navbar con botón hamburguesa
         ======================================== -->
    <?php include 'includes/header.php'; ?>
    
    <!-- ========================================
         ESTRUCTURA PRINCIPAL
         ======================================== -->
    <div class="container-fluid">
        <div class="row">
            <!-- ========================================
                 SIDEBAR
                 Incluye el menú lateral y overlay
                 ======================================== -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- ========================================
                 CONTENIDO PRINCIPAL
                 IMPORTANTE: Mantener las clases de Bootstrap
                 - col-md-9: Ancho en tablets
                 - ms-sm-auto: Margin left automático
                 - col-lg-10: Ancho en desktop
                 - px-md-4: Padding horizontal
                 ======================================== -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ========================================
                     ENCABEZADO DE PÁGINA
                     ======================================== -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Título de la Página</h1>
                    
                    <!-- Botones de acción (opcional) -->
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    </div>
                </div>
                
                <!-- ========================================
                     TU CONTENIDO AQUÍ
                     ======================================== -->
                
                <!-- Ejemplo: Cards informativos -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Card 1</h5>
                                <p class="card-text">Contenido de ejemplo</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Card 2</h5>
                                <p class="card-text">Contenido de ejemplo</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Card 3</h5>
                                <p class="card-text">Contenido de ejemplo</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ejemplo: Tabla -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Tabla de Datos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td>Ejemplo 1</td>
                                        <td><span class="badge bg-success">Activo</span></td>
                                        <td>08/10/2025</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Más filas aquí -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </main>
        </div>
    </div>
    
    <!-- ========================================
         FOOTER (Opcional)
         ======================================== -->
    <?php //include 'includes/footer.php'; ?>
    
    <!-- ========================================
         JAVASCRIPT
         ======================================== -->
    
    <!-- Bootstrap Bundle JS (Incluye Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado de tu página -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tu código JavaScript aquí
        
        console.log('✅ Página cargada correctamente');
        console.log('✅ Sidebar responsivo activo');
        
        // Ejemplo: Actualizar datos
        // document.getElementById('btnActualizar').addEventListener('click', function() {
        //     location.reload();
        // });
    });
    </script>
    
</body>
</html>

