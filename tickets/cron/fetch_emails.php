<?php
include '../conexion/db.php';
require_once __DIR__ . '/../includes/email_processing_helper.php';
$host = '{mail.securiti.info:993/imap/ssl}INBOX';
$user = 'oantonio@securiti.info';
$password = 'Ant0n10.2025';

$max_retries = 3;

for ($retry = 0; $retry < $max_retries; $retry++) {
    $mailbox = @imap_open($host, $user, $password);
    
    if ($mailbox) {
        break;
    }
    
    if ($retry < $max_retries - 1) {
        error_log("Intento $retry fallido. Reintentando...");
    } else {
        error_log("Error de conexión IMAP después de $max_retries intentos: " . imap_last_error());
        die("No se pudo conectar al servidor de correo después de varios intentos");
    }
}
function detectTicketId($subject) {
    $patterns = [
        '/\[Ticket #(\d+)\]/i',
        '/\[Case #(\d+)\]/i',
        '/Ticket: (\d+)/i',
        '/Case: (\d+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $subject, $matches)) {
            return (int)$matches[1];
        }
    }
    
    return null;
}

function saveAttachment($file_data, $filename, $ticket_id) {
    $upload_dir = "../uploads/tickets/$ticket_id";
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("No se pudo crear el directorio para adjuntos");
        }
    }
    
    $file_path = "$upload_dir/" . basename($filename);
    $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception("Tipo de archivo no permitido: $file_ext");
    }
    
    $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $filename);
    
    if (!file_put_contents($file_path, $file_data)) {
        throw new Exception("Error al guardar el archivo adjunto");
    }
    
    return $file_path;
}

$emails = imap_search($mailbox, 'UNSEEN');

if (!empty($emails)) {
    foreach ($emails as $email_id) {
        $header = imap_headerinfo($mailbox, $email_id);
        $subject = isset($header->subject) ? $header->subject : '(Sin asunto)';
        $from_email = isset($header->from[0]->mailbox) && isset($header->from[0]->host) ? 
                      strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host)) : '';
        $from_name = isset($header->from[0]->personal) ? (string) $header->from[0]->personal : '';
        if (function_exists('emailDecodeMimeHeaderValue')) {
            $from_name = emailDecodeMimeHeaderValue($from_name);
        } elseif (function_exists('imap_utf8') && $from_name !== '') {
            $from_name = imap_utf8($from_name);
        }
        if ($from_email === '' || (function_exists('emailLooksLikeMimeEncodedWord') && emailLooksLikeMimeEncodedWord($from_email)) || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $header_raw = @imap_fetchheader($mailbox, $email_id);
            if ($header_raw && function_exists('parseEmailAddressFromHeader')) {
                $parsed = parseEmailAddressFromHeader($header_raw);
                if ($parsed['email'] !== '') {
                    $from_email = $parsed['email'];
                    if ($parsed['name'] !== '') {
                        $from_name = $parsed['name'];
                    }
                }
            }
        }
        if ($from_name === '') {
            $from_name = $from_email;
        }
        $date = date('Y-m-d H:i:s', strtotime($header->date));
        $message_id = $header->message_id;
        $body = '';
        $structure = imap_fetchstructure($mailbox, $email_id);
        
        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                if ($part->subtype == 'PLAIN' || $part->subtype == 'HTML') {
                    $body = imap_fetchbody($mailbox, $email_id, $i+1);
                    
                    if ($part->encoding == 3) { 
                        $body = base64_decode($body);
                    } elseif ($part->encoding == 4) { 
                        $body = quoted_printable_decode($body);
                    }
                    
                    if ($part->subtype == 'HTML') {
                        break; 
                    }
                }
            }
        } else {
            $body = imap_body($mailbox, $email_id);
            if ($structure->encoding == 3) {
                $body = base64_decode($body);
            } elseif ($structure->encoding == 4) { 
                $body = quoted_printable_decode($body);
            }
        }
        $is_reply = false;
        $ticket_id = null;
        if (preg_match('/\[Ticket #(\d+)\]/', $subject, $matches)) {
            $ticket_id = $matches[1];
            $check_ticket = $conn->prepare("SELECT id FROM tickets WHERE id = ?");
            $check_ticket->bind_param("i", $ticket_id);
            $check_ticket->execute();
            $check_ticket->store_result();
            
            if ($check_ticket->num_rows > 0) {
                $is_reply = true;
            }
            $check_ticket->close();
        }

        $requester_id = null;
        $sender_type = 'requester'; 
        $check_tech = $conn->prepare("SELECT id FROM technicians WHERE email = ?");
        $check_tech->bind_param("s", $from_email);
        $check_tech->execute();
        $check_tech->store_result();
        
        if ($check_tech->num_rows > 0) {
            $check_tech->bind_result($requester_id);
            $check_tech->fetch();
            $sender_type = 'technician';
        } else {
            $check_req = $conn->prepare("SELECT id FROM requesters WHERE email = ?");
            $check_req->bind_param("s", $from_email);
            $check_req->execute();
            $check_req->store_result();
            
            if ($check_req->num_rows > 0) {
                $check_req->bind_result($requester_id);
                $check_req->fetch();
            } else {
                $insert_req = $conn->prepare("INSERT INTO requesters (name, email) VALUES (?, ?)");
                $insert_req->bind_param("ss", $from_name, $from_email);
                $insert_req->execute();
                $requester_id = $conn->insert_id;
                $insert_req->close();
            }
            $check_req->close();
        }
        $check_tech->close();
        $check_assigned_tech = $conn->prepare("SELECT technician_id FROM tickets WHERE id = ?");
        $check_assigned_tech->bind_param("i", $ticket_id);
        $check_assigned_tech->execute();
        $check_assigned_tech->store_result();
        
        if ($check_assigned_tech->num_rows > 0) {
            $check_assigned_tech->bind_result($assigned_tech_id);
            $check_assigned_tech->fetch();
            $check_tech_email = $conn->prepare("SELECT id FROM technicians WHERE id = ? AND email = ?");
            $check_tech_email->bind_param("is", $assigned_tech_id, $from_email);
            $check_tech_email->execute();
            $check_tech_email->store_result();
            
            if ($check_tech_email->num_rows > 0) {
                $sender_type = 'technician';
            }
            $check_tech_email->close();
        }
        $check_assigned_tech->close();

        if ($is_reply && $ticket_id) {
            if ($sender_type == 'technician') {
                $update_status = $conn->prepare("UPDATE tickets SET status_id = 3 WHERE id = ?"); 
                $update_status->bind_param("i", $ticket_id);
                $update_status->execute();
                $update_status->close();
            }
            
            $insert_msg = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, is_email) VALUES (?, ?, ?, ?, 1)");
            $insert_msg->bind_param("isis", $ticket_id, $sender_type, $requester_id, $body);
            
            if (!$insert_msg->execute()) {
                throw new Exception("Error al agregar respuesta: " . $conn->error);
            }
            if ($sender_type == 'requester') {
                $update_status = $conn->prepare("UPDATE tickets SET status_id = 2 WHERE id = ? AND closed_at IS NOT NULL");
                $update_status->bind_param("i", $ticket_id);
                $update_status->execute();
                $update_status->close();
            }
            
            echo "Respuesta agregada al ticket #$ticket_id<br>";
        } else {
            $stmt = $conn->prepare("INSERT INTO tickets (requester_id, description, email_subject, status_id) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("iss", $requester_id, $body, $subject);
            
            if ($stmt->execute()) {
                $new_ticket_id = $conn->insert_id;
                $insert_msg = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, is_email) VALUES (?, ?, ?, ?, 1)");
                $insert_msg->bind_param("isis", $new_ticket_id, $sender_type, $requester_id, $body);
                $insert_msg->execute();
                $insert_msg->close();
                
                echo "Nuevo ticket #$new_ticket_id creado<br>";
            } else {
                echo "Error al crear ticket: " . $conn->error . "<br>";
            }
            $stmt->close();
        }
        if (isset($structure->parts) && count($structure->parts)) {
            $attachments = array();
            function getAttachments($structure, $email_id, $part_number = '') {
                $attachments = array();
                
                if (isset($structure->parts)) {
                    foreach ($structure->parts as $count => $part) {
                        $new_part_number = empty($part_number) ? ($count + 1) : $part_number . '.' . ($count + 1);
                        
                        if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                            $attachments[] = array(
                                'part_number' => $new_part_number,
                                'filename' => isset($part->dparameters[0]->value) ? $part->dparameters[0]->value : "adjunto_" . uniqid(),
                                'encoding' => $part->encoding
                            );
                        }
                        
                        if (isset($part->parts) && count($part->parts)) {
                            $sub_attachments = getAttachments($part, $email_id, $new_part_number);
                            $attachments = array_merge($attachments, $sub_attachments);
                        }
                    }
                }
                
                return $attachments;
            }
            
            $attachments = getAttachments($structure, $email_id);
            $ticket_id_for_attachment = $is_reply ? $ticket_id : $new_ticket_id;
            
            foreach ($attachments as $attachment) {
                $filename = $attachment['filename'];
                $part_number = $attachment['part_number'];
                $encoding = $attachment['encoding'];
                
                $file_data = imap_fetchbody($mailbox, $email_id, $part_number);
                if ($encoding == 3) { 
                    $file_data = base64_decode($file_data);
                } elseif ($encoding == 4) { 
                    $file_data = quoted_printable_decode($file_data);
                }
                $upload_dir = "../uploads/tickets/$ticket_id_for_attachment";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_path = "$upload_dir/" . basename($filename);
                file_put_contents($file_path, $file_data);
                $db_file_path = "uploads/tickets/$ticket_id_for_attachment/" . basename($filename);
                $insert_file = $conn->prepare("INSERT INTO ticket_attachments (ticket_id, file_name, file_path) VALUES (?, ?, ?)");
                $insert_file->bind_param("iss", $ticket_id_for_attachment, $filename, $db_file_path);
                $insert_file->execute();
                $insert_file->close();
                
                echo "Archivo adjunto guardado: $filename<br>";
            }
        }
    }
} else { 
    echo "No hay correos nuevos.";
}


imap_close($mailbox);
?>