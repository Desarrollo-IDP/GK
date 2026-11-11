<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$isAdmin = isAdmin();
$message = '';
$messageType = '';

// Eliminar empresa (solo si viene ?delete=id)
if (isset($_GET['delete']) && !empty($_GET['delete']) && $isAdmin) {
    $id = (int)$_GET['delete'];
    $check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

    $check_query = "SELECT id_empresa, nombre FROM empresas WHERE id_empresa = ?";
    $check_params = [$id];
    $check_types = 'i';
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $check_query .= " AND id_empresa_usuario = ?";
        $check_params[] = $user_empresa_id;
        $check_types .= 'i';
    }
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param($check_types, ...$check_params);
    $check_stmt->execute();
    $empresa_row = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($empresa_row) {
        // No permitir eliminar si algún requester tiene esta empresa en company_id
        $req_check = $conn->prepare("SELECT COUNT(*) as total FROM requesters WHERE company_id = ?");
        $req_check->bind_param("i", $id);
        $req_check->execute();
        $req_row = $req_check->get_result()->fetch_assoc();
        $req_check->close();
        if ($req_row && (int)$req_row['total'] > 0) {
            $message = "No se puede eliminar la empresa porque " . $req_row['total'] . " solicitante(s) la tienen asignada. Reasigne o edite los solicitantes primero.";
            $messageType = "danger";
            header('Location: empresas.php?msg=' . urlencode($message) . '&type=' . $messageType);
            exit;
        }

        $conn->begin_transaction();
        try {
            deleteContactosEmpresaSafe($conn, $id, CONTACTO_ORIGEN_EMPRESAS, 'empresas_equipos');
            $stmt = $conn->prepare("DELETE FROM empresas WHERE id_empresa = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logActivity("Eliminación de empresa", "empresas", $id, "Se eliminó la empresa: " . $empresa_row['nombre']);
                $conn->commit();
                $message = "Empresa eliminada correctamente";
                $messageType = "success";
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error al eliminar: " . (strpos($e->getMessage(), 'foreign key') !== false ? 'La empresa tiene equipos o registros asociados.' : $e->getMessage());
            $messageType = "danger";
        }
    } else {
        $message = "No tiene permisos para eliminar esta empresa";
        $messageType = "danger";
    }
    header('Location: empresas.php?msg=' . urlencode($message) . '&type=' . $messageType);
    exit;
}

// Mensaje por GET (después de redirect)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'];
}

// Verificar si existe columna id_empresa_usuario en empresas
$check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT e.*, c.contacto, c.telefono, c.correo
          FROM empresas e
          " . contactosEmpresaLeftJoinSql($conn, CONTACTO_ORIGEN_EMPRESAS);
$params = [];
$types = '';

if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $query .= " WHERE e.id_empresa_usuario = ?";
    $params[] = $user_empresa_id;
    $types = 'i';
}
$query .= " ORDER BY e.nombre";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}
?>
<?php include '../../includes/modern-header.php'; ?>
<?php
$module_ui_layout = 'list';
include '../../includes/module_ui_styles.php';
?>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Listado de Empresas</h1>
                    <p class="text-muted mb-0">Administra las empresas del sistema</p>
                </div>
                <?php if ($isAdmin): ?>
                <div class="tickets-page-actions">
                    <a href="add.php" class="btn btn-primary w-100 w-md-auto">
                        <i class="fas fa-plus me-2"></i>Agregar Nueva Empresa
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Dirección</th>
                                <th>Fecha Registro</th>
                                <th>Contactos</th>
                                <?php if ($isAdmin): ?>
                                <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($empresa = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="align-middle"><?php echo htmlspecialchars($empresa['nombre']); ?></td>
                                    <td class="align-middle"><?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?></td>
                                    <td class="align-middle"><?php echo isset($empresa['fecha_registro']) ? date('d/m/Y', strtotime($empresa['fecha_registro'])) : '-'; ?></td>
                                    <td class="align-middle">
                                        <?php if (!empty($empresa['contacto']) || !empty($empresa['telefono']) || !empty($empresa['correo'])): ?>
                                            <div><strong>Contacto:</strong> <?php echo htmlspecialchars($empresa['contacto'] ?? '-'); ?></div>
                                            <div><strong>Tel:</strong> <?php echo htmlspecialchars($empresa['telefono'] ?? '-'); ?></div>
                                            <div><strong>Email:</strong> <?php echo htmlspecialchars($empresa['correo'] ?? '-'); ?></div>
                                        <?php else: ?>
                                            <em class="text-muted">Sin contactos registrados</em>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                    <td class="align-middle">
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="edit.php?id=<?php echo (int)$empresa['id_empresa']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i><span class="d-none d-md-inline ms-1">Editar</span>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo (int)$empresa['id_empresa']; ?>">
                                                <i class="fas fa-trash"></i><span class="d-none d-md-inline ms-1">Eliminar</span>
                                            </a>
                                        </div>
                                        <div class="modal fade" id="deleteModal<?php echo (int)$empresa['id_empresa']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo (int)$empresa['id_empresa']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo (int)$empresa['id_empresa']; ?>">Confirmar eliminación</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        ¿Está seguro de que desea eliminar la empresa <strong><?php echo htmlspecialchars($empresa['nombre']); ?></strong>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <a href="empresas.php?delete=<?php echo (int)$empresa['id_empresa']; ?>" class="btn btn-danger">Eliminar</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $isAdmin ? 5 : 4; ?>" class="text-center text-muted">No hay empresas registradas</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
