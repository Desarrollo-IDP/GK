<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

$message = '';
$messageType = '';


if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: technicians.php');
    exit;
}

$id = $_GET['id'];
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
        $checkEmail_query = "SELECT id FROM technicians WHERE email = ? AND id != ?";
        $checkEmail_params = [$email, $id];
        $checkEmail_types = 'si';
        
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
            // Actualizar técnico manteniendo la empresa original (no se puede cambiar)
            $stmt = $conn->prepare("UPDATE technicians SET name = ?, nivel = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sissi", $name, $nivel, $email, $phone, $id);
            
            if ($stmt->execute()) {
                $message = "Técnico actualizado correctamente";
                $messageType = "success";
            } else {
                $message = "Error al actualizar el técnico: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
}

// Obtener técnico con filtro de empresa
$query = "SELECT * FROM technicians WHERE id = ?";
$params = [$id];
$types = 'i';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: technicians.php?error=no_access');
    exit;
}

$technician = $result->fetch_assoc();
$stmt->close();
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
                    <h1 class="h2 mb-2">Editar Técnico</h1>
                    <p class="text-muted mb-0">Modifica los datos del técnico y guarda los cambios</p>
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
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($technician['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nivel" class="form-label">Nivel del técnico</label>
                                    <select class="form-select" id="nivel" name="nivel" required>
                                        <option value="">Seleccione un nivel</option>
                                        <?php foreach ($niveles as $n): ?>
                                            <option value="<?php echo $n['id']; ?>" <?php if (($technician['nivel'] ?? '') == $n['id']) echo 'selected'; ?>><?php echo htmlspecialchars($n['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($technician['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($technician['phone']); ?>">
                                </div>
                            </div>
                        </section>
                        <div class="form-actions d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
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
</body>
</html>