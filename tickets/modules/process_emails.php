<?php
include '../conexion/db.php';
include 'outlook_emails.php';

header('Content-Type: application/json');

try {
    // Cerrar cualquier conexión IMAP existente
    if (isset($inbox) && $inbox) {
        imap_close($inbox);
    }

    // Reiniciar la conexión IMAP
    $inbox = conectarIMAP();
    if (!$inbox) {
        throw new Exception("No se pudo establecer la conexión IMAP");
    }

    // Obtener todos los correos no procesados
    $emails = obtenerCorreos($inbox);
    
    // Procesar cada correo
    $processed = 0;
    foreach ($emails as $email) {
        if (processEmail($email['id'])) {
            $processed++;
        }
    }

    // Cerrar la conexión IMAP
    imap_close($inbox);

    echo json_encode([
        'success' => true,
        'message' => "Se procesaron $processed correos correctamente"
    ]);

} catch (Exception $e) {
    error_log("Error en process_emails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Error al procesar correos: " . $e->getMessage()
    ]);
}
?> 