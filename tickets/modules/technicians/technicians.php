<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Verificar que el técnico pertenece a la empresa del usuario antes de eliminar
    $check_query = "SELECT id FROM technicians WHERE id = ?";
    $check_params = [$id];
    $check_types = 'i';
    $check_query = addEmpresaFilter($check_query, 'id_empresa', $check_params, $check_types);
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param($check_types, ...$check_params);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // El técnico pertenece a la empresa del usuario, proceder con la eliminación
        $stmt = $conn->prepare("DELETE FROM technicians WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Técnico eliminado correctamente";
            $messageType = "success";
        } else {
            $message = "Error al eliminar el técnico: " . $conn->error;
            $messageType = "danger";
        }
        $stmt->close();
    } else {
        $message = "No tiene permisos para eliminar este técnico";
        $messageType = "danger";
    }
    $check_stmt->close();
}

$id_filter = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($id_filter !== '' && is_numeric($id_filter)) {
    $query = "SELECT t.*, t.technician_number, l.name as nivel_nombre, eu.nombre as empresa_nombre 
              FROM technicians t 
              LEFT JOIN level l ON t.nivel = l.id 
              LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
              WHERE t.id = ?";
    $params = [$id_filter];
    $types = 'i';
    $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT t.*, t.technician_number, l.name as nivel_nombre, eu.nombre as empresa_nombre 
              FROM technicians t 
              LEFT JOIN level l ON t.nivel = l.id 
              LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa";
    $params = [];
    $types = '';
    $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    $query .= " ORDER BY t.name";
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
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
                    <h1 class="h2 mb-2">
                        Gestión de Técnicos
                        <?php if ($id_filter !== ''): ?>
                            <span class="badge bg-primary ms-2">Filtrado: Técnico #<?php echo htmlspecialchars($id_filter); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <?php if ($id_filter !== ''): ?>
                            Mostrando solo el técnico #<?php echo htmlspecialchars($id_filter); ?>
                        <?php else: ?>
                            Administra los técnicos del sistema
                        <?php endif; ?>
                    </p>
                </div>
                <div class="tickets-page-actions">
                    <?php if ($id_filter !== ''): ?>
                        <a href="technicians.php" class="btn btn-outline-secondary w-100 w-md-auto">
                            <i class="fas fa-times me-2"></i>Limpiar Filtro
                        </a>
                    <?php endif; ?>
                    <a href="add.php" class="btn btn-primary w-100 w-md-auto">
                        <i class="fas fa-plus me-2"></i>Agregar Técnico
                    </a>
                </div>
            </div>
            <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Nombre</th>
                                <th>Nivel</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <!-- <th>Empresa</th> -->
                                <th>Fecha de Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="font-semibold" style="color: #202654;">#<?php echo htmlspecialchars($row['technician_number'] ?? $row['id']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nivel_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <!-- <td><?php echo getEmpresaBadge($row['id_empresa'] ?? null, $row['empresa_nombre'] ?? null); ?></td> -->
                                        <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1 action-buttons">
                                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i><span class="d-none d-md-inline ms-1">Editar</span>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash"></i><span class="d-none d-md-inline ms-1">Eliminar</span>
                                                </a>
                                            </div>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $row['id']; ?>">Confirmar eliminación</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            ¿Está seguro de que desea eliminar al técnico <strong><?php echo htmlspecialchars($row['name']); ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <a href="technicians.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger">Eliminar</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay técnicos registrados</td>
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
    
    <script>
        function confirmDelete(id) {
            if (confirm('¿Está seguro de que desea eliminar este técnico?')) {
                window.location.href = 'technicians.php?delete=' + id;
            }
        }
    </script>
</body>
</html>