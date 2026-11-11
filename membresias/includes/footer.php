<?php
// footer.php - Pie de página común
?>
    </div> <!-- Cierre de main-content -->
    
    <footer class="main-footer bg-light py-3 mt-4 border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="footer-copyright text-muted">
                        &copy; <?= date('Y') ?> Sistema de Membresías - Todos los derechos reservados
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-version text-muted">
                  
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Toast Notifications -->
    <?php if (isset($_SESSION['mensaje'])): ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header <?= $_SESSION['tipo_mensaje'] === 'error' ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                <strong class="me-auto">
                    <?= $_SESSION['tipo_mensaje'] === 'error' ? 'Error' : 'Éxito' ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
        </div>
    </div>
    <?php 
    // Clear the message after displaying
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
    endif; 
    ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (si es necesario) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Scripts personalizados -->
    <script>
    // Funciones globales para el sistema
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Inicializar popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Función para mostrar alertas
        window.showAlert = function(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container') || createAlertContainer();
            const alertId = 'alert-' + Date.now();
            
            const alertHtml = `
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto-dismiss después de 5 segundos
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        };
        
        // Función para crear contenedor de alertas si no existe
        function createAlertContainer() {
            const container = document.createElement('div');
            container.id = 'alert-container';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1050';
            document.body.appendChild(container);
            return container;
        }
        
        // Función para confirmar acciones
        window.confirmAction = function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        };
        
        // Función para mostrar loading
        window.showLoading = function(element) {
            if (element) {
                element.classList.add('loading');
                element.disabled = true;
            }
        };
        
        // Función para ocultar loading
        window.hideLoading = function(element) {
            if (element) {
                element.classList.remove('loading');
                element.disabled = false;
            }
        };
        
        // Función para formatear fechas
        window.formatDate = function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };
        
        // Función para formatear moneda
        window.formatCurrency = function(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(amount);
        };
        
        // Función para validar formularios
        window.validateForm = function(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        };
        
        // Función para limpiar formularios
        window.resetForm = function(formId) {
            const form = document.getElementById(formId);
            if (form) {
                form.reset();
                form.querySelectorAll('.is-invalid').forEach(input => {
                    input.classList.remove('is-invalid');
                });
            }
        };
        
        // Función para hacer peticiones AJAX
        window.ajaxRequest = function(url, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                ...options
            };
            
            return fetch(url, defaultOptions)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error en petición AJAX:', error);
                    showAlert('Error en la petición: ' + error.message, 'danger');
                    throw error;
                });
        };
        
        // Función para actualizar tablas dinámicamente
        window.updateTable = function(tableId, data) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            data.forEach(row => {
                const tr = document.createElement('tr');
                Object.values(row).forEach(value => {
                    const td = document.createElement('td');
                    td.textContent = value;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        };
        
        // Función para exportar datos
        window.exportData = function(data, filename, type = 'csv') {
            let content = '';
            let mimeType = '';
            
            if (type === 'csv') {
                content = data.map(row => Object.values(row).join(',')).join('\n');
                mimeType = 'text/csv';
            } else if (type === 'json') {
                content = JSON.stringify(data, null, 2);
                mimeType = 'application/json';
            }
            
            const blob = new Blob([content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        };
        
        // Función para imprimir página
        window.printPage = function() {
            window.print();
        };
        
        // Función para copiar al portapapeles
        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showAlert('Texto copiado al portapapeles', 'success');
            }).catch(err => {
                console.error('Error al copiar:', err);
                showAlert('Error al copiar al portapapeles', 'danger');
            });
        };
        
        // Función para generar PDF (placeholder)
        window.generatePDF = function(elementId, filename) {
            showAlert('Función de generación de PDF en desarrollo', 'info');
        };
        
        // Función para generar Excel (placeholder)
        window.generateExcel = function(data, filename) {
            showAlert('Función de generación de Excel en desarrollo', 'info');
        };
        
        // Event listeners globales
        document.addEventListener('click', function(e) {
            // Manejar botones de eliminar
            if (e.target.matches('.btn-delete')) {
                e.preventDefault();
                const message = e.target.dataset.confirm || '¿Estás seguro de que quieres eliminar este elemento?';
                const url = e.target.href;
                
                confirmAction(message, () => {
                    window.location.href = url;
                });
            }
            
            // Manejar botones de copiar
            if (e.target.matches('.btn-copy')) {
                e.preventDefault();
                const text = e.target.dataset.text || e.target.textContent;
                copyToClipboard(text);
            }
        });
        
        // Manejar formularios con validación
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this.id)) {
                    e.preventDefault();
                    showAlert('Por favor, completa todos los campos requeridos', 'warning');
                }
            });
        });
        
        // Auto-hide alerts después de 5 segundos
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
        
        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });
    
    // Función para manejar errores globales
    window.addEventListener('error', function(e) {
        console.error('Error global:', e.error);
        showAlert('Ha ocurrido un error inesperado', 'danger');
    });
    
    // Función para manejar errores de red
    window.addEventListener('offline', function() {
        showAlert('Sin conexión a internet', 'warning');
    });
    
    window.addEventListener('online', function() {
        showAlert('Conexión restaurada', 'success');
    });
    </script>
    
    <!-- Scripts específicos de la página -->
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Scripts inline específicos de la página -->
    <?php if (isset($page_inline_scripts)): ?>
        <script>
            <?php echo $page_inline_scripts; ?>
        </script>
    <?php endif; ?>
</body>
</html>