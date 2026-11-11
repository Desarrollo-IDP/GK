<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

$message = '';
$messageType = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: requester.php');
    exit;
}

$id = $_GET['id'];

// Empresas de inventario: filtrar por id_empresa_usuario del usuario logueado
$user_empresa_id = getCurrentUserEmpresaId();
$check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column_empresas && $check_column_empresas->num_rows > 0;
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $companies_stmt = $conn->prepare("SELECT id_empresa, nombre FROM empresas WHERE id_empresa_usuario = ? ORDER BY nombre");
    $companies_stmt->bind_param("i", $user_empresa_id);
    $companies_stmt->execute();
    $companies = $companies_stmt->get_result();
} else {
    $companies = $conn->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    
    if (empty($name) || empty($email)) {
        $message = "Nombre y email son campos obligatorios";
        $messageType = "danger";
    } else {
        // Verificar email duplicado solo dentro de la misma empresa
        // Esto permite que el mismo email exista en diferentes empresas
        $checkEmail_query = "SELECT id FROM requesters WHERE email = ? AND id != ?";
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
            $message = "El email ya está registrado para otro solicitante" . ($user_empresa_id !== null ? " en esta empresa" : "");
            $messageType = "danger";
        } else {
            // Actualizar solicitante manteniendo la empresa original (no se puede cambiar)
            // La empresa (id_empresa) no se actualiza, solo se mantiene la original
            $stmt = $conn->prepare("UPDATE requesters SET name = ?, email = ?, phone = ?, company_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $email, $phone, $company_id, $id);
            
            if ($stmt->execute()) {
                $message = "Solicitante actualizado correctamente";
                $messageType = "success";
            } else {
                $message = "Error al actualizar el solicitante: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
}

// Obtener solicitante con filtro de empresa
$query = "SELECT * FROM requesters WHERE id = ?";
$params = [$id];
$types = 'i';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: requester.php?error=no_access');
    exit;
}

$requester = $result->fetch_assoc();
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
                    <h1 class="h2 mb-2">Editar Solicitante</h1>
                    <p class="text-muted mb-0">Modifica los datos del solicitante y guarda los cambios</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="requester.php" class="btn btn-secondary">
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
                                <span class="ticket-edit-panel-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                                <div>
                                    <h4>Información del Solicitante</h4>
                                    <p class="ticket-edit-panel-subtitle">Datos de contacto y empresa asociada</p>
                                </div>
                            </div>
                            <div class="ticket-edit-panel-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($requester['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($requester['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($requester['phone']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="company_id" class="form-label">Empresa</label>
                                    <select class="form-select" id="company_id" name="company_id">
                                        <option value="">Seleccione una empresa</option>
                                        <?php while ($company = $companies->fetch_assoc()): ?>
                                            <option value="<?php echo $company['id_empresa']; ?>" <?php echo ($requester['company_id'] == $company['id_empresa']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </section>
                        <div class="form-actions d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                            <a href="requester.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
