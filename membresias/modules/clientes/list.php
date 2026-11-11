<?php
include '../../includes/db.php';
include '../../includes/config.php';
include '../../includes/header.php';

// Obtener la empresa del usuario logueado
$empresa_id = getCurrentUserEmpresaIdOrDefault();

// Obtener todos los clientes de la empresa del usuario
try {
    $sql = 'SELECT * FROM clientes WHERE empresa_id = ? ORDER BY nombre';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error al cargar los clientes: ' . $e->getMessage();
    error_log($error_message);
    $clientes = [];
}

// Función para mostrar el sexo en formato legible
function mostrarSexo($sexo) {
    switch ($sexo) {
        case 'M':
            return 'Masculino';
        case 'F':
            return 'Femenino';
        case 'Otro':
            return 'Otro';
        default:
            return $sexo;
    }
}

// Obtener mensajes de la URL
$mensaje = $_GET['success'] ?? $_GET['error'] ?? '';
$tipoMensaje = isset($_GET['success']) ? 'success' : (isset($_GET['error']) ? 'danger' : '');
?>

<?php include '../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Lista de Clientes</h1>
        <div>
            <!--<button id="refreshBtn" class="btn btn-outline-primary btn-sm me-2">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>-->
            

        </div>
    </div>
    
    <!-- Mostrar mensajes de éxito/error -->
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-1"></i>
                Clientes Registrados
                <span id="lastUpdate" class="text-muted ms-2"></span>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo Cliente
            </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($clientes)): ?>
                <div class="alert alert-info">No hay clientes registrados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="clientesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Apellido Paterno</th>
                            <th>Apellido Materno</th>
                            <th>Edad</th>
                            <th>Sexo</th>
                            <th>CURP</th>
                            <th>Teléfono</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                <td><?= htmlspecialchars($cliente['apellido_paterno']) ?></td>
                                <td><?= htmlspecialchars($cliente['apellido_materno'] ?? '') ?></td>
                                <td><?= htmlspecialchars($cliente['edad']) ?></td>
                                <td><?= htmlspecialchars(mostrarSexo($cliente['sexo'])) ?></td>
                                <td><?= htmlspecialchars($cliente['curp']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $cliente['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <!--<a href="delete.php?id=<?= $cliente['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar este cliente?')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </a>-->
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<!-- DataTables JavaScript -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- Inicialización de DataTable -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var table = $('#clientesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-MX.json'
            },
            responsive: true,
            order: [[0, 'asc']], // Ordenar por nombre por defecto
            pageLength: 25,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]]
        });
        
        // Función para actualizar la última actualización
        function updateLastUpdate() {
            var now = new Date();
            var timeString = now.toLocaleTimeString('es-MX');
            document.getElementById('lastUpdate').textContent = 'Última actualización: ' + timeString;
        }
        
        // Actualizar al cargar
        updateLastUpdate();
        
        // Botón de refresh manual
        document.getElementById('refreshBtn').addEventListener('click', function() {
            location.reload();
        });
        
        // Auto-refresh cada 60 segundos
        setInterval(function() {
            location.reload();
        }, 60000);
    });
</script>

<?php include '../../includes/footer.php'; ?> 