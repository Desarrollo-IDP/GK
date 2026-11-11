<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

    $check_query = "SELECT id_empresa FROM empresas WHERE nombre = ?";
    $check_params = [$_POST['nombre']];
    $check_types = 's';
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $check_query .= " AND id_empresa_usuario = ?";
        $check_params[] = $user_empresa_id;
        $check_types .= 'i';
    }

    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        $message = "Error en la preparación de la consulta: " . $conn->error;
        $messageType = "danger";
    } else {
        $check_stmt->bind_param($check_types, ...$check_params);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $message = "La empresa '" . htmlspecialchars($_POST['nombre']) . "' ya existe" . ($has_empresa_usuario_column && $user_empresa_id !== null ? " en esta empresa" : "") . ".";
            $messageType = "danger";
        } else {
            if ($has_empresa_usuario_column && $user_empresa_id !== null) {
                $stmt = $conn->prepare("INSERT INTO empresas (nombre, direccion, id_empresa_usuario) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssi", $_POST['nombre'], $_POST['direccion'], $user_empresa_id);
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO empresas (nombre, direccion) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ss", $_POST['nombre'], $_POST['direccion']);
                }
            }

            if ($stmt && $stmt->execute()) {
                $id_empresa = $conn->insert_id;
                if (!empty($_POST['contacto']) || !empty($_POST['telefono']) || !empty($_POST['correo'])) {
                    upsertContactoEmpresa(
                        $conn,
                        $id_empresa,
                        $_POST['contacto'] ?? '',
                        $_POST['telefono'] ?? '',
                        $_POST['correo'] ?? '',
                        CONTACTO_ORIGEN_EMPRESAS
                    );
                }
                logActivity("Creación de empresa", "empresas", $id_empresa, "Se creó una nueva empresa: " . $_POST['nombre']);
                header('Location: empresas.php?msg=' . urlencode('Empresa agregada correctamente') . '&type=success');
                exit;
            } else {
                $message = "Error al guardar: " . ($stmt ? $stmt->error : $conn->error);
                $messageType = "danger";
            }
        }
        $check_stmt->close();
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<?php
$module_ui_layout = 'form';
$module_ui_form_max_width = '960px';
include '../../includes/module_ui_styles.php';
?>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Agregar Empresa</h1>
                    <p class="text-muted mb-0">Completa el formulario para registrar una nueva empresa</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="empresas.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="card ticket-edit-form-card">
                <div class="card-body">
                    <form method="POST" action="" class="ticket-edit-form">
                        <div class="row g-3 ticket-edit-columns">
                            <div class="col-md-6">
                                <section class="ticket-edit-panel">
                                    <div class="ticket-edit-panel-header">
                                        <span class="ticket-edit-panel-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                                        <div>
                                            <h4>Datos de la Empresa</h4>
                                            <p class="ticket-edit-panel-subtitle">Nombre y dirección</p>
                                        </div>
                                    </div>
                                    <div class="ticket-edit-panel-body">
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="direccion" class="form-label">Dirección *</label>
                                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <div class="col-md-6">
                                <section class="ticket-edit-panel">
                                    <div class="ticket-edit-panel-header">
                                        <span class="ticket-edit-panel-icon ticket-edit-panel-icon--contact" aria-hidden="true"><i class="fas fa-address-card"></i></span>
                                        <div>
                                            <h4>Contacto</h4>
                                            <p class="ticket-edit-panel-subtitle">Persona de contacto (opcional)</p>
                                        </div>
                                    </div>
                                    <div class="ticket-edit-panel-body">
                                        <div class="mb-3">
                                            <label for="contacto" class="form-label">Nombre del contacto</label>
                                            <input type="text" class="form-control" id="contacto" name="contacto" value="<?php echo htmlspecialchars($_POST['contacto'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="correo" class="form-label">Correo electrónico</label>
                                            <input type="text" class="form-control" id="correo" name="correo" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                        <div class="form-actions d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="empresas.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
