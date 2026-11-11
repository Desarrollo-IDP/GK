<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

$message = '';
$messageType = '';
$user_empresa_id = getCurrentUserEmpresaId();

// Obtener niveles según la empresa del usuario logueado
$niveles = [];
if ($user_empresa_id !== null) {
    $niveles_stmt = $conn->prepare("SELECT id, name FROM level WHERE id_empresa = ? ORDER BY name");
    $niveles_stmt->bind_param("i", $user_empresa_id);
    $niveles_stmt->execute();
    $niveles_result = $niveles_stmt->get_result();
} else {
    $niveles_result = $conn->query("SELECT id, name FROM level ORDER BY name");
}
if ($niveles_result && $niveles_result->num_rows > 0) {
    while ($nivel_row = $niveles_result->fetch_assoc()) {
        $niveles[] = $nivel_row;
    }
}
if (isset($niveles_stmt)) {
    $niveles_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $nivel = $_POST['nivel'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
 
    if (empty($name) || empty($email)) {
        $message = "Nombre y email son campos obligatorios";
        $messageType = "danger";
    } else {
 
        // Verificar email duplicado solo dentro de la misma empresa
        $user_empresa_id = getCurrentUserEmpresaId();
        $checkEmail_query = "SELECT id FROM technicians WHERE email = ?";
        $checkEmail_params = [$email];
        $checkEmail_types = 's';
        
        // Si el usuario tiene empresa, verificar solo en esa empresa
        if ($user_empresa_id !== null) {
            $checkEmail_query .= " AND id_empresa = ?";
            $checkEmail_params[] = $user_empresa_id;
            $checkEmail_types .= 'i';
        }
        
        $checkEmail = $conn->prepare($checkEmail_query);
        $checkEmail->bind_param($checkEmail_types, ...$checkEmail_params);
        $checkEmail->execute();
        $checkEmail->store_result();
        
        if ($checkEmail->num_rows > 0) {
            $message = "El email ya está registrado para otro técnico" . ($user_empresa_id !== null ? " en esta empresa" : "");
            $messageType = "danger";
        } else {
            // Obtener empresa del usuario actual
            $user_empresa_id = getCurrentUserEmpresaId();
            
            // Incluir helper de contador de technicians
            require_once '../../includes/ticket_counter_helper.php';
            
            // Obtener el siguiente número de technician para esta empresa
            $technician_number = getNextTechnicianNumber($conn, $user_empresa_id);
       
            $stmt = $conn->prepare("INSERT INTO technicians (name, nivel, email, phone, id_empresa, technician_number) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissis", $name, $nivel, $email, $phone, $user_empresa_id, $technician_number);
            
            if ($stmt->execute()) {
                $message = "Técnico agregado correctamente";
                $messageType = "success";
        
                $name = $email = $phone = '';
            } else {
                $message = "Error al agregar el técnico: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<?php
$module_ui_layout = 'form';
include '../../includes/module_ui_styles.php';
?>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Agregar Nuevo Técnico</h1>
                    <p class="text-muted mb-0">Completa el formulario para registrar un nuevo técnico</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="technicians.php" class="btn btn-secondary">
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
                        <section class="ticket-edit-panel">
                            <div class="ticket-edit-panel-header">
                                <span class="ticket-edit-panel-icon" aria-hidden="true"><i class="fas fa-user-cog"></i></span>
                                <div>
                                    <h4>Información del Técnico</h4>
                                    <p class="ticket-edit-panel-subtitle">Nombre, nivel y datos de contacto</p>
                                </div>
                            </div>
                            <div class="ticket-edit-panel-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nivel" class="form-label">Nivel</label>
                                    <select class="form-select" id="nivel" name="nivel" required>
                                        <option value="">Seleccione un nivel</option>
                                        <?php foreach ($niveles as $n): ?>
                                            <option value="<?php echo $n['id']; ?>" <?php if (($nivel ?? '') == $n['id']) echo 'selected'; ?>><?php echo htmlspecialchars($n['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                </div>
                            </div>
                        </section>
                        <div class="form-actions d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="technicians.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>