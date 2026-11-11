<?php
/**
 * Punto de entrada legacy para envío de correos.
 * La configuración SMTP/IMAP se administra únicamente en:
 * tickets/modules/configuration/empresa_email_config.php → tabla empresa_email_config
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * @deprecated Usar createMailerForEmpresa() directamente.
 */
function createMailer($conn, $id_empresa = null): PHPMailer
{
    if (!$conn instanceof mysqli) {
        throw new Exception('createMailer() requiere una conexión mysqli ($conn).');
    }

    $mail = createMailerForEmpresa($id_empresa, $conn);
    if (!$mail) {
        throw new Exception(
            'No hay configuración de correo activa. Configúrela en Configuración → Email por empresa.'
        );
    }

    return $mail;
}
