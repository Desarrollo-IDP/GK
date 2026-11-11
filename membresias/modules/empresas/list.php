<?php
// Define current page for active menu highlighting
$current_page = 'empresas_list';

include '../../includes/db.php';

// Obtener todas las empresas de la base de datos
try {
    $sql = 'SELECT * FROM empresas ORDER BY nombre';
    $stmt = $pdo->query($sql);
    $empresas = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error al cargar las empresas: ' . $e->getMessage();
    error_log($error_message);
    $empresas = [];
}

// Obtener mensajes de la URL
$mensaje = $_GET['success'] ?? $_GET['error'] ?? '';
$tipoMensaje = isset($_GET['success']) ? 'success' : (isset($_GET['error']) ? 'danger' : '');
?>

<?php include '../../includes/header.php'; ?>
<br>
<br>
<br>
<br>
<br>
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-building me-2"></i>Empresas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Nueva Empresa
                    </a>
                </div>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Lista de Empresas
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($empresas)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay empresas registradas</h5>
                            <p class="text-muted">Comienza agregando la primera empresa al sistema.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Agregar Empresa
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Logo</th>
                                        <th>Nombre</th>
                                        <th>Razón Social</th>
                                        <th>RFC</th>
                                        <th>Ciudad</th>
                                        <th>Teléfono</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <tr>
                                            <td><?= $empresa['id'] ?></td>
                                            <td>
                                                <?php if ($empresa['logo']): ?>
                                                    <img src="<?= getImageUrl($empresa['logo']) ?>" 
                                                         alt="Logo <?= htmlspecialchars($empresa['nombre']) ?>" 
                                                         class="img-thumbnail" 
                                                         style="max-width: 50px; max-height: 50px;"
                                                         onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($empresa['nombre']) ?></strong>
                                                <?php if (!$empresa['activo']): ?>
                                                    <span class="badge bg-warning ms-1">Inactiva</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($empresa['razon_social'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($empresa['rfc'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($empresa['ciudad'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($empresa['telefono'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if ($empresa['activo']): ?>
                                                    <span class="badge bg-success">Activa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactiva</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?= $empresa['id'] ?>" 
                                                       class="btn btn-sm btn-outline-info" 
                                                       title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?= $empresa['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            title="Eliminar"
                                                            onclick="confirmarEliminacion(<?= $empresa['id'] ?>, '<?= htmlspecialchars($empresa['nombre']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function confirmarEliminacion(id, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar la empresa "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
        window.location.href = `delete.php?id=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 