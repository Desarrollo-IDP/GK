<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

if ($id <= 0) {
    header('Location: empresas.php');
    exit;
}

// Cargar empresa y verificar permisos
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $stmt = $conn->prepare("SELECT * FROM empresas WHERE id_empresa = ? AND id_empresa_usuario = ?");
    $stmt->bind_param("ii", $id, $user_empresa_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM empresas WHERE id_empresa = ?");
    $stmt->bind_param("i", $id);
}
$stmt->execute();
$empresa_original = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empresa_original) {
    header('Location: empresas.php?msg=' . urlencode('No tiene permiso para editar esta empresa') . '&type=danger');
    exit;
}

if ($user_empresa_id !== null && $has_empresa_usuario_column && $empresa_original['id_empresa_usuario'] != $user_empresa_id) {
    header('Location: empresas.php?msg=' . urlencode('No tiene permiso para editar esta empresa') . '&type=danger');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $verify_stmt = $conn->prepare("SELECT id_empresa FROM empresas WHERE id_empresa = ? AND id_empresa_usuario = ?");
        $verify_stmt->bind_param("ii", $id, $user_empresa_id);
        $verify_stmt->execute();
        if ($verify_stmt->get_result()->num_rows === 0) {
            header('Location: empresas.php?msg=' . urlencode('No tiene permiso') . '&type=danger');
            exit;
        }
        $verify_stmt->close();
    }

    $stmt = $conn->prepare("UPDATE empresas SET nombre = ?, direccion = ? WHERE id_empresa = ?");
    $stmt->bind_param("ssi", $_POST['nombre'], $_POST['direccion'], $id);

    if ($stmt->execute()) {
        $cambios = [];
        if (($empresa_original['nombre'] ?? '') != $_POST['nombre']) {
            $cambios[] = "Nombre: " . ($empresa_original['nombre'] ?? '') . " → " . $_POST['nombre'];
        }
        if (($empresa_original['direccion'] ?? '') != $_POST['direccion']) {
            $cambios[] = "Dirección actualizada";
        }

        $contacto_original = getContactoEmpresa($conn, $id, CONTACTO_ORIGEN_EMPRESAS);

        if ($contacto_original || !empty($_POST['contacto']) || !empty($_POST['telefono']) || !empty($_POST['correo'])) {
            upsertContactoEmpresa(
                $conn,
                $id,
                $_POST['contacto'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['correo'] ?? '',
                CONTACTO_ORIGEN_EMPRESAS
            );
            if ($contacto_original) {
                if (($contacto_original['contacto'] ?? '') != ($_POST['contacto'] ?? '')) $cambios[] = "Contacto actualizado";
                if (($contacto_original['telefono'] ?? '') != ($_POST['telefono'] ?? '')) $cambios[] = "Teléfono actualizado";
                if (($contacto_original['correo'] ?? '') != ($_POST['correo'] ?? '')) $cambios[] = "Correo actualizado";
            } else {
                $cambios[] = "Se agregó información de contacto";
            }
        }

        logActivity(
            "Actualización de empresa",
            "empresas",
            $id,
            "Se actualizó la empresa: " . $_POST['nombre'] . "\n" . implode("\n", $cambios)
        );

        header('Location: empresas.php?msg=' . urlencode('Empresa actualizada correctamente') . '&type=success');
        exit;
    } else {
        $message = "Error al actualizar: " . $stmt->error;
        $messageType = "danger";
    }
}

// Cargar datos para el formulario
$stmt = $conn->prepare("
    SELECT e.*, c.id_contacto, c.contacto, c.telefono, c.correo
    FROM empresas e
    " . contactosEmpresaLeftJoinSql($conn, CONTACTO_ORIGEN_EMPRESAS) . "
    WHERE e.id_empresa = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$empresa = $result->fetch_assoc();
$stmt->close();

if (!$empresa) {
    header('Location: empresas.php');
    exit;
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
                    <h1 class="h2 mb-2">Editar Empresa</h1>
                    <p class="text-muted mb-0">Modifica los datos de la empresa y guarda los cambios</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="empresas.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="card ticket-edit-form-card">
                <div class="card-body">
                    <form method="POST" action="" class="ticket-edit-form">
                        <input type="hidden" name="id_empresa" value="<?php echo (int)$empresa['id_empresa']; ?>">
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
                                            <label for="nombre" class="form-label">Nombre de la empresa *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($empresa['nombre']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="direccion" class="form-label">Dirección *</label>
                                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?>" required>
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
                                            <p class="ticket-edit-panel-subtitle">Persona de contacto</p>
                                        </div>
                                    </div>
                                    <div class="ticket-edit-panel-body">
                                        <div class="mb-3">
                                            <label for="contacto" class="form-label">Nombre del contacto</label>
                                            <input type="text" class="form-control" id="contacto" name="contacto" value="<?php echo htmlspecialchars($empresa['contacto'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="correo" class="form-label">Correo electrónico</label>
                                            <input type="text" class="form-control" id="correo" name="correo" value="<?php echo htmlspecialchars($empresa['correo'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                        <div class="form-actions d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
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
