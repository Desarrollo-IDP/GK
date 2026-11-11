<?php
require_once('../vendor/autoload.php');
require_once('../conexion/config_mailer.php');
require_once('../conexion/get_empresa_email_config.php');
require_once('../conexion/db.php');

function enviarRespuestaTicket($ticket_id, $mensaje, $adjuntos = []) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT t.*, r.email as requester_email, r.name as requester_name,
                   tm.message_id as original_message_id
            FROM tickets t
            JOIN requesters r ON t.requester_id = r.id
            JOIN ticket_messages tm ON t.id = tm.ticket_id
            WHERE t.id = ? AND tm.parent_message_id IS NULL
        ");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();
        $stmt->close();
        
        if (!$ticket) {
            throw new Exception("Ticket no encontrado");
        }

        $id_empresa = isset($ticket['id_empresa']) ? (int)$ticket['id_empresa'] : null;
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }
        
        require_once('outlook_emails.php');
        $mensaje_limpio = cleanEmailContent($mensaje);
        
        $body = "
            <html>
            <body>
                <p>Estimado/a {$ticket['requester_name']},</p>
                <p>Se ha recibido una respuesta a su ticket #{$ticket_id}:</p>
                <div style='background-color: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff;'>
                    {$mensaje_limpio}
                </div>

                <p>Saludos cordiales,<br>Sistema de Tickets</p>
            </body>
            </html>
        ";
        
        $subject = "Re: [Ticket #{$ticket_id}] {$ticket['email_subject']}";
        
        $mail = createMailerForEmpresa($id_empresa, $conn);
        if (!$mail) {
            throw new Exception('No se pudo inicializar el servidor de correo.');
        }

        $mail->addAddress($ticket['requester_email'], $ticket['requester_name']);
        
        if ($ticket['original_message_id']) {
            $mail->addCustomHeader('In-Reply-To', $ticket['original_message_id']);
            $mail->addCustomHeader('References', $ticket['original_message_id']);
        }
        
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->addReplyTo($empresa_config['smtp_from_email'], $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        foreach ($adjuntos as $adjunto) {
            if (file_exists($adjunto)) {
                $mail->addAttachment($adjunto);
            }
        }
        
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Respuesta al ticket #{$ticket_id} enviada exitosamente");
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar respuesta al ticket #{$ticket_id}: " . $e->getMessage());
        return false;
    }
}

function enviarNotificacionNuevoTicket($ticket_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT t.*, r.email as requester_email, r.name as requester_name,
                   tm.message_id as original_message_id
            FROM tickets t
            JOIN requesters r ON t.requester_id = r.id
            JOIN ticket_messages tm ON t.id = tm.ticket_id
            WHERE t.id = ? AND tm.parent_message_id IS NULL
        ");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();
        $stmt->close();
        
        if (!$ticket) {
            throw new Exception("Ticket no encontrado");
        }

        $id_empresa = isset($ticket['id_empresa']) ? (int)$ticket['id_empresa'] : null;
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }
        
        $body = "
            <html>
            <body>
                <p>Estimado/a {$ticket['requester_name']},</p>
                <p>Se ha creado un nuevo ticket con el número #{$ticket_id}.</p>
                <p>Detalles del ticket:</p>
                <div style='background-color: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745;'>
                    <p><strong>Asunto:</strong> {$ticket['email_subject']}</p>
                    <p><strong>Descripción del problema:</strong><br>{$ticket['description']}</p>
                </div>
               <p>Saludos cordiales,<br>Sistema de Tickets</p>
            </body>
            </html>
        ";
        
        $subject = "[Ticket #{$ticket_id}] {$ticket['email_subject']}";
        
        $mail = createMailerForEmpresa($id_empresa, $conn);
        if (!$mail) {
            throw new Exception('No se pudo inicializar el servidor de correo.');
        }

        $mail->addAddress($ticket['requester_email'], $ticket['requester_name']);
        
        if ($ticket['original_message_id']) {
            $mail->addCustomHeader('In-Reply-To', $ticket['original_message_id']);
            $mail->addCustomHeader('References', $ticket['original_message_id']);
        }
        
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->addReplyTo($empresa_config['smtp_from_email'], $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Notificación de nuevo ticket #{$ticket_id} enviada exitosamente");
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar notificación de nuevo ticket #{$ticket_id}: " . $e->getMessage());
        return false;
    }
}
