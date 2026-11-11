<?php
// Cargar PHPMailer
require_once __DIR__ . '/tickets/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// =====================
// ENVÍO DEL FORMULARIO
// =====================
$mensaje_exito = "";
$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $remitente = trim($_POST["remitente"]);
    $destinatario = trim($_POST["destinatario"]);
    $asunto = trim($_POST["asunto"]);
    $mensaje = trim($_POST["mensaje"]);
    $cantidad = (int)$_POST["cantidad"];
    
    // Configuración SMTP (opcional, si no se proporciona usa valores por defecto)
    $smtp_host = !empty($_POST["smtp_host"]) ? trim($_POST["smtp_host"]) : 'mail.securiti.info';
    $smtp_port = !empty($_POST["smtp_port"]) ? (int)$_POST["smtp_port"] : 587;
    $smtp_usuario = !empty($_POST["smtp_usuario"]) ? trim($_POST["smtp_usuario"]) : '';
    $smtp_password = !empty($_POST["smtp_password"]) ? trim($_POST["smtp_password"]) : '';
    $smtp_seguro = isset($_POST["smtp_seguro"]) ? $_POST["smtp_seguro"] : 'tls';

    // Validaciones
    if (empty($remitente) || !filter_var($remitente, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "Por favor, ingresa un correo remitente válido.";
    } elseif (empty($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "Por favor, ingresa un correo destinatario válido.";
    } elseif (empty($asunto)) {
        $mensaje_error = "Por favor, ingresa un asunto.";
    } elseif (empty($mensaje)) {
        $mensaje_error = "Por favor, ingresa un mensaje.";
    } elseif ($cantidad < 1) {
        $mensaje_error = "La cantidad de correos debe ser al menos 1.";
    } elseif (empty($smtp_usuario) || empty($smtp_password)) {
        $mensaje_error = "Por favor, ingresa el usuario y contraseña SMTP.";
    } else {
        $enviados = 0;
        $fallidos = 0;
        $errores_detalle = [];

        // Enviar correos
        for ($i = 1; $i <= $cantidad; $i++) {
            try {
                $mail = new PHPMailer(true);
                
                // Configuración del servidor SMTP
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->Port = $smtp_port;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_usuario;
                $mail->Password = $smtp_password;
                
                // Configuración de seguridad
                if ($smtp_seguro === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($smtp_seguro === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    // Sin encriptar
                    $mail->SMTPSecure = '';
                }
                
                // Configuración adicional
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->isHTML(false); // Texto plano
                $mail->SMTPDebug = SMTP::DEBUG_OFF;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                $mail->Timeout = 60;
                
                // Configuración del correo
                $mail->setFrom($remitente);
                $mail->addAddress($destinatario);
                $mail->Subject = $asunto;
                $mail->Body = $mensaje;
                $mail->AltBody = strip_tags($mensaje);
                
                if ($mail->send()) {
                    $enviados++;
                } else {
                    $fallidos++;
                    $errores_detalle[] = "Intento $i: " . $mail->ErrorInfo;
                }
            } catch (Exception $e) {
                $fallidos++;
                $errores_detalle[] = "Intento $i: " . $mail->ErrorInfo;
            }
        }

        if ($enviados > 0) {
            $mensaje_exito = "Se enviaron $enviados de $cantidad correo(s) correctamente.";
            if ($fallidos > 0) {
                $mensaje_exito .= " Fallaron $fallidos correo(s).";
            }
        } else {
            $mensaje_error = "No se pudo enviar ningún correo. ";
            if (!empty($errores_detalle)) {
                $mensaje_error .= "Detalles: " . implode(" | ", array_slice($errores_detalle, 0, 3));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Envío de Correos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-top: 0;
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="email"],
        input[type="text"],
        input[type="number"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        select {
            cursor: pointer;
        }
        h3 {
            margin-top: 30px;
            margin-bottom: 15px;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover {
            background-color: #45a049;
        }
        .mensaje-exito {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .mensaje-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Formulario de Envío de Correos</h2>

    <?php if (!empty($mensaje_exito)): ?>
        <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
    <?php endif; ?>

    <?php if (!empty($mensaje_error)): ?>
        <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
    <?php endif; ?>

    <form method="post">
        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;">Configuración del Correo</h3>
        
        <label>Correo Remitente (desde el que saldrán los correos):</label>
        <input type="email" name="remitente" value="<?php echo isset($_POST['remitente']) ? htmlspecialchars($_POST['remitente']) : ''; ?>" placeholder="ejemplo@dominio.com" required>

        <label>Correo Destinatario:</label>
        <input type="email" name="destinatario" value="<?php echo isset($_POST['destinatario']) ? htmlspecialchars($_POST['destinatario']) : ''; ?>" placeholder="destinatario@dominio.com" required>

        <label>Asunto:</label>
        <input type="text" name="asunto" value="<?php echo isset($_POST['asunto']) ? htmlspecialchars($_POST['asunto']) : ''; ?>" placeholder="Asunto del correo" required>

        <label>Cuerpo del Mensaje:</label>
        <textarea name="mensaje" placeholder="Escribe aquí el contenido del correo..." required><?php echo isset($_POST['mensaje']) ? htmlspecialchars($_POST['mensaje']) : ''; ?></textarea>

        <label>Cantidad de correos a enviar:</label>
        <input type="number" name="cantidad" min="1" value="<?php echo isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1; ?>" required>

        <h3 style="margin-top: 30px; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;">Configuración SMTP</h3>
        
        <label>Servidor SMTP:</label>
        <input type="text" name="smtp_host" value="<?php echo isset($_POST['smtp_host']) ? htmlspecialchars($_POST['smtp_host']) : 'mail.securiti.info'; ?>" placeholder="mail.dominio.com" required>

        <label>Puerto SMTP:</label>
        <select name="smtp_port" required>
            <option value="587" <?php echo (isset($_POST['smtp_port']) && $_POST['smtp_port'] == '587') || !isset($_POST['smtp_port']) ? 'selected' : ''; ?>>587 (TLS)</option>
            <option value="465" <?php echo isset($_POST['smtp_port']) && $_POST['smtp_port'] == '465' ? 'selected' : ''; ?>>465 (SSL)</option>
            <option value="25" <?php echo isset($_POST['smtp_port']) && $_POST['smtp_port'] == '25' ? 'selected' : ''; ?>>25 (Sin encriptar)</option>
        </select>

        <label>Tipo de Seguridad:</label>
        <select name="smtp_seguro" required>
            <option value="tls" <?php echo (isset($_POST['smtp_seguro']) && $_POST['smtp_seguro'] == 'tls') || !isset($_POST['smtp_seguro']) ? 'selected' : ''; ?>>TLS (Recomendado)</option>
            <option value="ssl" <?php echo isset($_POST['smtp_seguro']) && $_POST['smtp_seguro'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
            <option value="none" <?php echo isset($_POST['smtp_seguro']) && $_POST['smtp_seguro'] == 'none' ? 'selected' : ''; ?>>Sin encriptar</option>
        </select>

        <label>Usuario SMTP:</label>
        <input type="text" name="smtp_usuario" value="<?php echo isset($_POST['smtp_usuario']) ? htmlspecialchars($_POST['smtp_usuario']) : ''; ?>" placeholder="usuario@dominio.com" required>

        <label>Contraseña SMTP:</label>
        <input type="password" name="smtp_password" value="<?php echo isset($_POST['smtp_password']) ? htmlspecialchars($_POST['smtp_password']) : ''; ?>" placeholder="Contraseña del servidor SMTP" required>

        <button type="submit">Enviar Correos</button>
    </form>
</div>

</body>
</html>
