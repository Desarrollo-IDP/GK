<?php
/**
 * Panel de chat y pruebas WhatsApp
 */
require_once __DIR__ . '/../auth.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp — Chat y pruebas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/chat.css" rel="stylesheet">
</head>
<body>
<div class="wa-app">
    <aside class="wa-sidebar">
        <div class="wa-sidebar-header">
            <div>
                <h1><i class="fab fa-whatsapp text-success"></i> WhatsApp Lab</h1>
                <p class="mb-0 small text-muted">Pruebas e integración Meta</p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnRefresh" title="Actualizar">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>

        <div class="wa-status" id="statusPanel">
            <span class="badge bg-secondary">Cargando...</span>
        </div>

        <div class="wa-sidebar-actions">
            <button type="button" class="btn btn-primary btn-sm w-100" id="btnRunTests">
                <i class="fas fa-vial me-1"></i>Ejecutar pruebas
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="btnImport" title="Re-importar payloads guardados">
                <i class="fas fa-file-import me-1"></i>Sincronizar webhooks
            </button>
            <button type="button" class="btn btn-outline-success btn-sm w-100 mt-2" id="btnReprocessTickets" title="Crear tickets desde mensajes recibidos guardados">
                <i class="fas fa-ticket-alt me-1"></i>Generar tickets pendientes
            </button>
        </div>

        <div class="wa-conversations-title">Últimos recibidos</div>
        <div class="wa-recent-inbound" id="recentInboundList">
            <p class="text-muted small px-3 mb-0">Cargando...</p>
        </div>

        <div class="wa-conversations-title">Conversaciones</div>
        <div class="wa-conversations" id="conversationList"></div>
    </aside>

    <main class="wa-main">
        <div class="wa-main-empty" id="emptyState">
            <i class="fab fa-whatsapp"></i>
            <p>Selecciona una conversación o inicia una nueva</p>
            <div class="wa-new-chat">
                <input type="tel" class="form-control form-control-sm" id="newPhone" placeholder="5215512345678">
                <button type="button" class="btn btn-success btn-sm" id="btnNewChat">Abrir chat</button>
            </div>
            <p class="small text-muted mt-2 mb-0">Formato internacional sin + (ej. 521…)</p>
        </div>

        <div class="wa-chat d-none" id="chatPanel">
            <header class="wa-chat-header">
                <div>
                    <strong id="chatContactName">Contacto</strong>
                    <div class="small text-muted" id="chatPhone"></div>
                    <div class="small mt-1 d-none" id="chatTicketLink"></div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-secondary d-none" id="chatSendingBadge">Enviando...</span>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="btnSimulate">
                        <i class="fas fa-flask me-1"></i>Simular entrada
                    </button>
                </div>
            </header>

            <div class="wa-composer-alert d-none" id="composerAlert"></div>

            <div class="wa-messages" id="messageList"></div>

            <footer class="wa-composer" id="composerPanel">
                <div class="wa-composer-tools">
                    <label class="btn btn-light btn-sm mb-0" title="Enviar imagen">
                        <i class="fas fa-image"></i>
                        <input type="file" id="fileImage" accept="image/*" hidden>
                    </label>
                    <label class="btn btn-light btn-sm mb-0" title="Enviar audio">
                        <i class="fas fa-microphone"></i>
                        <input type="file" id="fileAudio" accept="audio/*" hidden>
                    </label>
                    <label class="btn btn-light btn-sm mb-0" title="Enviar documento">
                        <i class="fas fa-file"></i>
                        <input type="file" id="fileDocument" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,application/*" hidden>
                    </label>
                </div>
                <div class="wa-composer-input-wrap">
                    <textarea class="form-control" id="messageInput" rows="1" placeholder="Escribe un mensaje para enviar por WhatsApp..." autocomplete="off"></textarea>
                    <small class="text-muted wa-composer-hint">Enter envía · Shift+Enter nueva línea</small>
                </div>
                <button type="button" class="btn btn-success" id="btnSend" title="Enviar por WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </button>
            </footer>
        </div>
    </main>
</div>

<div class="wa-toast-container" id="toastContainer"></div>

<!-- Modal pruebas -->
<div class="modal fade" id="testsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-vial me-2"></i>Resultados de pruebas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
                    <div>
                        <label class="form-label small mb-0">URL base</label>
                        <input type="text" class="form-control form-control-sm" id="testBaseUrl" value="http://localhost:90/GK">
                    </div>
                    <div>
                        <label class="form-label small mb-0">Teléfono envío real (opcional)</label>
                        <input type="tel" class="form-control form-control-sm" id="testLivePhone" placeholder="521...">
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="testLiveSend">
                        <label class="form-check-label small" for="testLiveSend">Enviar mensaje real a Meta</label>
                    </div>
                </div>
                <div id="testsSummary" class="mb-3"></div>
                <div id="testsResults"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal simular -->
<div class="modal fade" id="simulateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Simular mensaje entrante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="simType">
                        <option value="text">Texto</option>
                        <option value="image">Imagen</option>
                        <option value="audio">Audio</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre contacto</label>
                    <input type="text" class="form-control" id="simName" value="Usuario prueba">
                </div>
                <div class="mb-2">
                    <label class="form-label">Contenido / caption</label>
                    <textarea class="form-control" id="simBody" rows="3">Hola, necesito soporte</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnConfirmSimulate">Simular recepción</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.WA_API = 'api/chat.php';
window.WA_TICKETS_VIEW = '../tickets/modules/tickets/view.php';
</script>
<script src="js/chat.js"></script>
</body>
</html>
