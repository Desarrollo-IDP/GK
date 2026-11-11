<?php
include_once __DIR__ . '/common.php';
$base_url = ticketsRelativeBaseUrl();
?>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
            </div>
        </div>
    </div>
</footer>

<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">Manual de Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Gestión de Tickets</h6>
                <p>Este sistema permite gestionar tickets de soporte técnico de manera eficiente.</p>
                
                <h6>Funcionalidades principales:</h6>
                <ul>
                    <li>Crear, editar y eliminar tickets</li>
                    <li>Asignar tickets a técnicos</li>
                    <li>Seguimiento del estado de los tickets</li>
                    <li>Gestión de solicitantes y técnicos</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Contactar Soporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supportForm">
                    <div class="mb-3">
                        <label for="supportSubject" class="form-label">Asunto</label>
                        <input type="text" class="form-control" id="supportSubject" required>
                    </div>
                    <div class="mb-3">
                        <label for="supportMessage" class="form-label">Mensaje</label>
                        <textarea class="form-control" id="supportMessage" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="sendSupportBtn">Enviar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopBtn.style.display = 'inline-block';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });

        backToTopBtn.style.display = 'none';
    }

    const toggleDarkMode = document.getElementById('toggleDarkMode');
    const darkModeText = document.getElementById('darkModeText');
    const body = document.body;

    if (toggleDarkMode && darkModeText) {
        const isDarkMode = localStorage.getItem('darkMode') === 'true';

        if (isDarkMode) {
            body.classList.add('dark-mode');
            darkModeText.textContent = 'Modo claro';
        }

        toggleDarkMode.addEventListener('click', function() {
            if (body.classList.contains('dark-mode')) {
                body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
                darkModeText.textContent = 'Modo oscuro';
            } else {
                body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
                darkModeText.textContent = 'Modo claro';
            }
        });
    }
    
    const sendSupportBtn = document.getElementById('sendSupportBtn');
    if (sendSupportBtn) {
        sendSupportBtn.addEventListener('click', function() {
            const subject = document.getElementById('supportSubject').value;
            const message = document.getElementById('supportMessage').value;
            
            if (subject && message) {
                alert('Mensaje enviado correctamente. Nos pondremos en contacto pronto.');
                document.getElementById('supportForm').reset();
                $('#contactModal').modal('hide');
            } else {
                alert('Por favor complete todos los campos.');
            }
        });
    }
});
</script>

<style>
.dark-mode {
    background-color: #222;
    color: #f8f9fa;
}

.dark-mode .card {
    background-color: #333;
    color: #f8f9fa;
}

.dark-mode .table {
    color: #f8f9fa;
}

.dark-mode .bg-light {
    background-color: #333 !important;
}

.dark-mode .text-muted {
    color: #adb5bd !important;
}

.dark-mode .modal-content {
    background-color: #333;
    color: #f8f9fa;
}

.dark-mode .form-control {
    background-color: #444;
    color: #f8f9fa;
    border-color: #666;
}

.dark-mode .nav-link {
    color: #f8f9fa;
}

.dark-mode .nav-link.active {
    color: #fff;
    background-color: #0d6efd;
}

.nav-link {
    transition: all 0.3s ease;
}

.btn {
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

#backToTop {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

#backToTop:hover {
    background-color: #0d6efd;
    color: white;
}
</style>