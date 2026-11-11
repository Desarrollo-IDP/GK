<?php
include '../includes/db.php';
include '../includes/config.php';
include '../config/mercadopago_config.php';

initConfig();

if (!isset($_SESSION['cliente'])) {
    header('Location: login.php');
    exit;
}

$clienteId = $_SESSION['cliente']['id'];

$errores = [];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_token = $_POST['payment_token'] ?? '';
    $card_last4 = $_POST['card_last4'] ?? '';
    
    error_log('=== INICIO PROCESO GUARDAR TARJETA ===');
    error_log('Cliente ID: ' . $clienteId);
    error_log('Token recibido: ' . $payment_token);
    
    try {
        // En modo producción, solo guardamos el token de la tarjeta
        // No creamos customer porque requiere credenciales de TEST
        
        if (empty($payment_token)) {
            throw new Exception('Token de tarjeta no proporcionado');
        }
        
        // Guardar solo el token de la tarjeta en la base de datos
        // Esto permitirá hacer cargos usando el token directamente
        $upd = $pdo->prepare("UPDATE clientes SET mp_card_token = ? WHERE id = ?");
        $upd->execute([$payment_token, $clienteId]);

        $mensaje = 'Método de pago guardado correctamente.';
        error_log('Token de tarjeta guardado para cliente ' . $clienteId . ': ' . $payment_token);
        error_log('=== FIN PROCESO GUARDAR TARJETA - EXITOSO ===');
        
        // Redireccionar al dashboard después de 2 segundos
        header("Refresh: 2; URL=dashboard.php");
        
    } catch (Throwable $e) {
        $errores[] = 'Error al guardar método de pago: ' . $e->getMessage();
        error_log('Error al guardar método de pago: ' . $e->getMessage());
        error_log('=== FIN PROCESO GUARDAR TARJETA - CON ERROR ===');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guardar método de pago</title>
    <link href="../css/modern-design-system.css" rel="stylesheet">
    <link href="../css/components.css" rel="stylesheet">
    <link href="../css/responsive.css" rel="stylesheet">
    <link href="../css/membresias.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body class="body-bg">
    <div class="container" style="max-width:600px; margin-top:24px;">
        <div class="main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>Agregar método de pago
                </h2>
                <a class="btn btn-outline-secondary" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
            
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
                    <div class="mt-2">
                        <small class="text-muted">Redirigiendo al dashboard...</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <p class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Ingresa tu tarjeta para poder realizar cargos automáticos (por ejemplo, cargos por cancelación).
                </p>

                <form id="saveCardForm" method="post">
                    <div class="form-group mb-3">
                        <label for="cardNumber" class="form-label">Número de tarjeta</label>
                        <input type="text" id="cardNumber" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="expirationDate" class="form-label">Fecha de vencimiento</label>
                                <input type="text" id="expirationDate" class="form-control" placeholder="MM/YY" maxlength="5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="securityCode" class="form-label">Código de seguridad</label>
                                <input type="text" id="securityCode" class="form-control" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="cardholderName" class="form-label">Nombre del titular</label>
                        <input type="text" id="cardholderName" class="form-control" placeholder="NOMBRE APELLIDO" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="identificationType" class="form-label">Tipo de identificación</label>
                                <select id="identificationType" class="form-control">
                                    <option value="">Seleccionar...</option>
                                    <option value="DNI">DNI</option>
                                    <option value="CI">CI</option>
                                    <option value="CPF">CPF</option>
                                    <option value="CNPJ">CNPJ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="identificationNumber" class="form-label">Número de identificación</label>
                                <input type="text" id="identificationNumber" class="form-control" placeholder="12345678">
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="payment_token" name="payment_token">
                    <input type="hidden" id="card_last4" name="card_last4">

                    <div class="d-grid">
                        <button type="submit" id="submitButton" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar método de pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Cargar tipos de identificación desde Mercado Pago
        async function loadIdentificationTypes() {
            try {
                const response = await fetch('get_identification_types.php');
                const result = await response.json();
                
                if (result.success && result.types) {
                    const select = document.getElementById('identificationType');
                    // Limpiar opciones existentes excepto la primera
                    while (select.children.length > 1) {
                        select.removeChild(select.lastChild);
                    }
                    
                    result.types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.id;
                        option.textContent = type.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading identification types:', error);
                // Si falla, mantenemos las opciones por defecto que ya están en el HTML
            }
        }

        // Formatear número de tarjeta
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value;
        });

        // Formatear fecha de vencimiento
        document.getElementById('expirationDate').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Manejar envío del formulario
        document.getElementById('saveCardForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitButton = document.getElementById('submitButton');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            
            try {
                // Crear token de tarjeta
                const cardData = {
                    card_number: document.getElementById('cardNumber').value.replace(/\s/g, ''),
                    expiration_month: document.getElementById('expirationDate').value.split('/')[0],
                    expiration_year: '20' + document.getElementById('expirationDate').value.split('/')[1],
                    security_code: document.getElementById('securityCode').value,
                    cardholder: {
                        name: document.getElementById('cardholderName').value
                    }
                };
                
                // Agregar identificación solo si se proporciona
                const identificationType = document.getElementById('identificationType').value;
                const identificationNumber = document.getElementById('identificationNumber').value;
                if (identificationType && identificationNumber) {
                    cardData.cardholder.identification = {
                        type: identificationType,
                        number: identificationNumber
                    };
                }
                
                const response = await fetch('create_card_token.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(cardData)
                });
                
                const result = await response.json();
                
                if (result.success && result.token_id) {
                    // Token creado exitosamente
                    document.getElementById('payment_token').value = result.token_id;
                    document.getElementById('card_last4').value = result.last_four_digits || '';
                    document.getElementById('saveCardForm').submit();
                } else {
                    throw new Error(result.message || 'Error al crear token de tarjeta');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al procesar la tarjeta: ' + error.message);
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-save me-2"></i>Guardar método de pago';
            }
        });

        // Cargar tipos de identificación al cargar la página
        loadIdentificationTypes();
    </script>
</body>
</html>


