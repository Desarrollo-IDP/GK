<?php
session_start();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Componentes del Sistema de Diseño - Portal de Membresías</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/membresias-modern.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-palette"></i> Sistema de Diseño</h1>
                <p>Componentes y elementos del portal de membresías</p>
            </div>
        </div>

        <!-- Sección de Botones -->
        <div class="card">
            <h2><i class="fas fa-mouse-pointer"></i> Botones</h2>
            <div class="d-flex gap-3 mb-4">
                <button class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    Botón Primario
                </button>
                <button class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Botón Éxito
                </button>
                <button class="btn btn-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Botón Advertencia
                </button>
                <button class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Botón Peligro
                </button>
                <button class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Botón Secundario
                </button>
            </div>
            
            <h3>Estados de Botones</h3>
            <div class="d-flex gap-3">
                <button class="btn btn-primary" disabled>
                    <i class="fas fa-spinner fa-spin"></i>
                    Deshabilitado
                </button>
                <button class="btn btn-primary loading">
                    <i class="fas fa-spinner"></i>
                    Cargando
                </button>
            </div>
        </div>

        <!-- Sección de Alertas -->
        <div class="card">
            <h2><i class="fas fa-bell"></i> Alertas</h2>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>¡Éxito!</strong> La operación se completó correctamente.
                </div>
            </div>
            
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Error:</strong> Ha ocurrido un problema. Por favor, inténtalo de nuevo.
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Advertencia:</strong> Ten cuidado con esta acción.
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Información:</strong> Aquí tienes información importante.
                </div>
            </div>
        </div>

        <!-- Sección de Formularios -->
        <div class="card">
            <h2><i class="fas fa-edit"></i> Formularios</h2>
            <form>
                <div class="form-group">
                    <label class="form-label">Nombre completo</label>
                    <div class="input-container">
                        <input type="text" class="form-control" placeholder="Ingresa tu nombre">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Correo electrónico</label>
                    <div class="input-container">
                        <input type="email" class="form-control" placeholder="ejemplo@correo.com">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-container">
                        <input type="password" class="form-control" placeholder="••••••••">
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensaje</label>
                    <div class="input-container">
                        <textarea class="form-control" rows="4" placeholder="Escribe tu mensaje aquí..."></textarea>
                        <i class="fas fa-comment input-icon"></i>
                    </div>
                </div>
                
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-paper-plane"></i>
                    Enviar
                </button>
            </form>
        </div>

        <!-- Sección de Tarjetas -->
        <div class="card">
            <h2><i class="fas fa-id-card"></i> Tarjetas</h2>
            <div class="dashboard-grid">
                <div class="membership-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-star"></i>
                            Plan Premium
                        </h3>
                        <div class="card-status status-success">
                            Activo
                        </div>
                    </div>
                    <div class="card-details">
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-dollar-sign"></i>
                                Precio
                            </span>
                            <span class="detail-value">$599.00 MXN</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-calendar"></i>
                                Válido hasta
                            </span>
                            <span class="detail-value">31/12/2024</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-payment">
                            <i class="fas fa-credit-card"></i>
                            Gestionar
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <h3><i class="fas fa-chart-line"></i> Estadísticas</h3>
                    <p>Esta es una tarjeta normal con contenido informativo.</p>
                    <button class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Ver más
                    </button>
                </div>
            </div>
        </div>

        <!-- Sección de Utilidades -->
        <div class="card">
            <h2><i class="fas fa-tools"></i> Utilidades</h2>
            
            <h3>Espaciado</h3>
            <div class="d-flex gap-3 mb-4">
                <div class="p-3 bg-light rounded">mb-0</div>
                <div class="p-3 bg-light rounded">mb-1</div>
                <div class="p-3 bg-light rounded">mb-2</div>
                <div class="p-3 bg-light rounded">mb-3</div>
                <div class="p-3 bg-light rounded">mb-4</div>
            </div>
            
            <h3>Alineación de Texto</h3>
            <div class="mb-4">
                <p class="text-left">Texto alineado a la izquierda</p>
                <p class="text-center">Texto centrado</p>
                <p class="text-right">Texto alineado a la derecha</p>
            </div>
            
            <h3>Display</h3>
            <div class="d-flex gap-3 mb-4">
                <div class="p-3 bg-light rounded">d-flex</div>
                <div class="p-3 bg-light rounded">d-block</div>
                <div class="p-3 bg-light rounded">d-none</div>
            </div>
        </div>

        <!-- Sección de Animaciones -->
        <div class="card">
            <h2><i class="fas fa-magic"></i> Animaciones</h2>
            <div class="d-flex gap-3 mb-4">
                <div class="p-3 bg-light rounded hover-lift">
                    <i class="fas fa-arrow-up"></i> Hover Lift
                </div>
                <div class="p-3 bg-light rounded hover-scale">
                    <i class="fas fa-expand"></i> Hover Scale
                </div>
                <div class="p-3 bg-light rounded fade-in">
                    <i class="fas fa-eye"></i> Fade In
                </div>
            </div>
        </div>

        <!-- Botón para abrir modal -->
        <div class="card">
            <h2><i class="fas fa-window-maximize"></i> Modal</h2>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-external-link-alt"></i>
                Abrir Modal de Ejemplo
            </button>
        </div>
    </div>

    <!-- Modal de ejemplo -->
    <div class="modal-overlay" id="exampleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-info-circle"></i>
                    Modal de Ejemplo
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <p>Este es un ejemplo de modal usando el sistema de diseño.</p>
                <p>Los modales incluyen:</p>
                <ul>
                    <li>Backdrop blur</li>
                    <li>Animaciones suaves</li>
                    <li>Responsive design</li>
                    <li>Accesibilidad mejorada</li>
                </ul>
                
                <div class="btn-group">
                    <button class="btn btn-primary">
                        <i class="fas fa-check"></i>
                        Aceptar
                    </button>
                    <button class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('exampleModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('exampleModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('exampleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Efectos de hover en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar efectos de focus a los inputs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>
