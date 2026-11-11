/**
 * Sistema de Gestión de Almacén - JavaScript Principal
 * Funcionalidades responsivas e interactividad
 */

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeModals();
    initializeForms();
    initializeDataTables();
});

/**
 * Inicializar funcionalidad del Sidebar
 */
function initializeSidebar() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    if (!hamburgerBtn || !sidebar) return;

    // Toggle sidebar
    hamburgerBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
    });

    // Cerrar sidebar con overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Cerrar sidebar al hacer clic en enlaces (móviles)
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                sidebar.classList.remove('active');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
            }
        });
    });
}

/**
 * Inicializar Modales
 */
function initializeModals() {
    // Cerrar modales con botón close
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-close') || 
            e.target.classList.contains('modal-overlay')) {
            closeAllModals();
        }
    });

    // Cerrar modales con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Cerrar todos los modales
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.classList.remove('active');
    });
}

/**
 * Abrir modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

/**
 * Cerrar modal específico
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Inicializar funcionalidades de formularios
 */
function initializeForms() {
    // Validación en tiempo real
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                showAlert('Por favor, complete todos los campos requeridos', 'error');
            }
        });
    });

    // Auto-guardar en localStorage
    const autoSaveForms = document.querySelectorAll('form[data-autosave]');
    autoSaveForms.forEach(form => {
        const formId = form.id;
        if (!formId) return;

        // Cargar datos guardados
        loadFormData(formId);

        // Guardar al cambiar
        form.addEventListener('input', function() {
            saveFormData(formId);
        });
    });
}

/**
 * Validar formulario
 */
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });

    return isValid;
}

/**
 * Guardar datos del formulario en localStorage
 */
function saveFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const formData = {};
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (input.name && input.type !== 'password') {
            formData[input.name] = input.value;
        }
    });

    localStorage.setItem(`form_${formId}`, JSON.stringify(formData));
}

/**
 * Cargar datos del formulario desde localStorage
 */
function loadFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const savedData = localStorage.getItem(`form_${formId}`);
    if (!savedData) return;

    try {
        const formData = JSON.parse(savedData);
        Object.keys(formData).forEach(name => {
            const input = form.querySelector(`[name="${name}"]`);
            if (input && input.type !== 'password') {
                input.value = formData[name];
            }
        });
    } catch (e) {
        console.error('Error al cargar datos del formulario:', e);
    }
}

/**
 * Limpiar datos guardados del formulario
 */
function clearFormData(formId) {
    localStorage.removeItem(`form_${formId}`);
}

/**
 * Inicializar tablas de datos
 */
function initializeDataTables() {
    // Búsqueda en tablas
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const tableId = input.dataset.tableSearch;
        const table = document.getElementById(tableId);
        
        if (!table) return;

        input.addEventListener('input', function() {
            filterTable(table, this.value);
        });
    });

    // Ordenar columnas
    const sortableHeaders = document.querySelectorAll('[data-sortable]');
    sortableHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            sortTable(this);
        });
    });
}

/**
 * Filtrar tabla
 */
function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

/**
 * Ordenar tabla
 */
function sortTable(header) {
    const table = header.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    const isAscending = header.classList.contains('sort-asc');

    rows.sort((a, b) => {
        const aValue = a.children[columnIndex].textContent.trim();
        const bValue = b.children[columnIndex].textContent.trim();
        
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        }
        
        return isAscending ? 
            bValue.localeCompare(aValue) : 
            aValue.localeCompare(bValue);
    });

    // Actualizar clases
    table.querySelectorAll('th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');

    // Reordenar filas
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        const container = document.createElement('div');
        container.id = 'alert-container';
        container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
    }

    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = 'margin-bottom: 10px; animation: slideInRight 0.3s ease;';
    alert.textContent = message;

    const closeBtn = document.createElement('span');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'float: right; font-size: 20px; font-weight: bold; cursor: pointer; margin-left: 10px;';
    closeBtn.onclick = function() {
        alert.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    };

    alert.insertBefore(closeBtn, alert.firstChild);
    document.getElementById('alert-container').appendChild(alert);

    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

/**
 * Confirmar acción
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Eliminar elemento (AJAX)
 */
function deleteItem(id, type, url) {
    confirmAction('¿Está seguro de que desea eliminar este elemento?', function() {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id, type: type, action: 'delete' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Elemento eliminado correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('Error al eliminar: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error en la conexión: ' + error.message, 'error');
        });
    });
}

/**
 * Formato de número
 */
function formatNumber(number, decimals = 2) {
    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

/**
 * Formato de moneda
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

/**
 * Formato de fecha
 */
function formatDate(date) {
    return new Intl.DateFormat('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

// Añadir estilos para animaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    .form-control.error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }
    
    th[data-sortable]::after {
        content: ' ⇅';
        opacity: 0.3;
    }
    
    th.sort-asc::after {
        content: ' ↑';
        opacity: 1;
    }
    
    th.sort-desc::after {
        content: ' ↓';
        opacity: 1;
    }
`;
document.head.appendChild(style);

