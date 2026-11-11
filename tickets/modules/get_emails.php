<?php
require_once('../conexion/config_imap.php');
include '../conexion/db.php';
include 'outlook_emails.php';

// Verificar si las extensiones necesarias están instaladas
if (!extension_loaded('imap')) {
    die('La extensión IMAP no está instalada en el servidor.');
}

// Obtener los correos usando la función de outlook_emails.php
$inbox = conectarIMAP();
$correos = obtenerCorreos($inbox);

// Generar el HTML de los correos
if (empty($correos)): ?>
    <div class="alert alert-info">No hay correos en la bandeja de entrada.</div>
<?php else: ?>
    <?php foreach ($correos as $correo): ?>
        <div class="card mb-3 email-item" data-email-id="<?php echo $correo['id']; ?>">
            <div class="card-body">
                <h5 class="card-title">
                    <?php if ($correo['ticket_id']): ?>
                        <span class="badge bg-primary me-2">Ticket #<?php echo htmlspecialchars($correo['ticket_id']); ?></span>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($correo['subject']); ?>
                </h5>
                <h6 class="card-subtitle mb-2 text-muted">
                    De: <?php echo htmlspecialchars($correo['from']); ?>
                    <small>(<?php echo htmlspecialchars($correo['from_email']); ?>)</small>
                </h6>
                <p class="card-text">
                    <small class="text-muted">
                        Fecha: <?php echo htmlspecialchars($correo['date']); ?>
                    </small>
                </p>
                <?php if (isset($correo['has_attachments']) && $correo['has_attachments']): ?>
                    <div class="mt-2">
                        <i class="fas fa-paperclip"></i> Tiene adjuntos
                    </div>
                <?php endif; ?>
                <div class="collapse" id="email-<?php echo $correo['id']; ?>">
                    <div class="card-text">
                        <div class="email-content">
                            <?php
                                $display_body = $correo['body'];
                                $display_contentType = $correo['contentType'];

                                if ($display_contentType === 'text/html') {
                                    // Para contenido HTML, mostrar directamente sin htmlspecialchars
                                    echo $display_body;
                                } else {
                                    // Para texto plano, aplicar nl2br y htmlspecialchars
                                    echo nl2br(htmlspecialchars($display_body, ENT_QUOTES, 'UTF-8'));
                                }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-sm btn-primary"
                            data-bs-toggle="collapse"
                            data-bs-target="#email-<?php echo $correo['id']; ?>">
                        Ver contenido
                    </button>
                    <?php if ($correo['ticket_id']): ?>
                        <a href="../tickets/view.php?id=<?php echo htmlspecialchars($correo['ticket_id']); ?>"
                           class="btn btn-sm btn-info">
                            Ver ticket
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif;

// Cerrar la conexión IMAP
if (isset($inbox) && is_resource($inbox)) {
    imap_close($inbox);
}
?> 