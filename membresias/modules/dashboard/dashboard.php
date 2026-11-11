<script>
// Procesamiento automático de pagos por cancelación pendientes
document.addEventListener('DOMContentLoaded', function() {
    // Procesar automáticamente cada 5 minutos si hay pagos pendientes
    setInterval(function() {
        processPendingCancellationPayments();
    }, 5 * 60 * 1000); // 5 minutos
    
    // Procesar también al cargar la página
    setTimeout(function() {
        processPendingCancellationPayments();
    }, 2000); // Esperar 2 segundos después de cargar
});

function processPendingCancellationPayments() {
    fetch('auto_process_cancellations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.processed > 0) {
                console.log('Pagos por cancelación procesados automáticamente:', data.message);
                
                // Mostrar notificación si se procesaron pagos
                if (data.processed > 0) {
                    showNotification('Pagos Procesados', `Se procesaron ${data.processed} pagos por cancelación automáticamente`, 'success');
                }
                
                // Si hay errores, mostrarlos en consola
                if (data.errors && data.errors.length > 0) {
                    console.warn('Errores en procesamiento automático:', data.errors);
                }
            }
        } else {
            console.error('Error en procesamiento automático:', data.error);
        }
    })
    .catch(error => {
        console.error('Error en petición AJAX:', error);
    });
}

function showNotification(title, message, type = 'info') {
    // Crear notificación usando Bootstrap o librería de notificaciones
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <strong>${title}</strong><br>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Remover automáticamente después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Función para procesar manualmente (botón en el dashboard)
function processCancellationPaymentsManual() {
    const button = document.getElementById('process-cancellations-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    }
    
    fetch('auto_process_cancellations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Procesamiento Manual', data.message, 'success');
            
            // Recargar la página después de 2 segundos para mostrar cambios
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('Error', data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Error', 'Error en la petición: ' + error.message, 'error');
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt"></i> Procesar Pagos Pendientes';
        }
    });
}
</script>
