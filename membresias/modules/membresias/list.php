<?php
//include '../../includes/common.php';
include '../../includes/db.php';
include '../../includes/config.php';
include '../../includes/header.php';

// Manejar mensajes de éxito y error desde add.php
if (isset($_GET['success']) && !empty($_GET['success'])) {
    $message = urldecode($_GET['success']);
    $messageType = "success";
} elseif (isset($_GET['error']) && !empty($_GET['error'])) {
    $message = urldecode($_GET['error']);
    $messageType = "danger";
}

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM membresias WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Membresía eliminada correctamente";
        $messageType = "success";
    } else {
        $message = "Error al eliminar la membresía: " . $conn->error;
        $messageType = "danger";
    }
    $stmt->close();
}

try {
    // Debug: Mostrar información de la conexión
    echo '<!-- Debug: Intentando conectar a la base de datos -->';
    
    // Verificar si las tablas existen
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<!-- Tablas en la base de datos: ' . implode(', ', $tablas) . ' -->';
    
    // Obtener la empresa del usuario logueado
    $empresa_id = getCurrentUserEmpresaIdOrDefault();
    
    // Consulta SQL para verificar datos en las tablas
    $sql = "SELECT 
                m.id, 
                CONCAT(COALESCE(c.nombre, ''), ' ', 
                       COALESCE(c.apellido_paterno, ''), ' ', 
                       COALESCE(c.apellido_materno, '')) AS nombre_completo,
                t.nombre AS tipo_membresia,
                m.fecha_inicio, 
                m.fecha_fin, 
                m.metodo_pago, 
                m.firma_digital,
                c.id AS cliente_id,
                m.mp_status,
                m.mp_preapproval_id,
                m.mp_cancellation_fee,
                t.monto_cancelacion
            FROM membresias m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            LEFT JOIN tipos_membresia t ON m.tipo_membresia_id = t.id
            WHERE m.empresa_id = ?
            ORDER BY m.fecha_inicio DESC";
    
    // Debug: Mostrar la consulta SQL
    echo '<!-- Consulta SQL: ' . htmlspecialchars($sql) . ' -->';
    
    // Ejecutar consulta
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Mostrar número de resultados
    echo '<!-- Número de membresías encontradas: ' . count($membresias) . ' -->';
    
    // Debug: Mostrar los primeros 3 registros (si existen)
    if (count($membresias) > 0) {
        echo '<!-- Primeros registros: ' . htmlspecialchars(print_r(array_slice($membresias, 0, 3), true)) . ' -->';
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error al cargar las membresías: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $membresias = [];
}
?>


<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
    margin-top: 2rem;
    margin-bottom: 2rem;
}
@media (max-width: 991.98px) {
    .main-content-boxed {
        padding: 1rem 0.5rem;
        border-radius: 0.75rem;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }
}
.table-container {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 2px 8px 0 rgba(32,38,84,0.04);
    margin-bottom: 2rem;
    padding: 0.5rem;
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Gestión de Membresías</h1>
                    <p class="text-muted mb-0">Administra las membresías del sistema</p>
                </div>
                <!--<div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="add.php" class="btn btn-primary w-100 w-md-auto">
                        <i class="fas fa-plus me-2"></i>Agregar Membresía
                    </a>
                </div>-->
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
    <h1 class="mt-4">Lista de Membresías</h1>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Membresías Registradas
            <a href="add.php" class="btn btn-primary btn-sm float-end">
                <i class="fas fa-plus"></i> Nueva Membresía
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($membresias)): ?>
                <div class="alert alert-info">No hay membresías registradas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="membresiasTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Tipo de Membresía</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado MP</th>
                                <th>Método de Pago</th>
                                <th>Firma Digital</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membresias as $m): ?>
                                <tr>
                                    <td>
                                        <a href="../clientes/edit.php?id=<?= $m['cliente_id'] ?>">
                                            <?= htmlspecialchars(trim($m['nombre_completo'])) ?: 'Cliente no especificado' ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($m['tipo_membresia']) ?></td>
                                    <td><?= htmlspecialchars($m['fecha_inicio']) ?></td>
                                    <td><?= htmlspecialchars($m['fecha_fin']) ?></td>
                                    <td>
                                        <?php if ($m['mp_status']): ?>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($m['mp_status']) {
                                                case 'authorized':
                                                    $statusClass = 'bg-success';
                                                    $statusText = 'Activa';
                                                    break;
                                                case 'pending':
                                                    $statusClass = 'bg-warning';
                                                    $statusText = 'Pendiente';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-danger';
                                                    $statusText = 'Cancelada';
                                                    break;
                                                case 'paused':
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = 'Pausada';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-info';
                                                    $statusText = $m['mp_status'];
                                            }
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                            <?php if ($m['mp_cancellation_fee'] > 0): ?>
                                                <br><small class="text-muted">Cargo: $<?= number_format($m['mp_cancellation_fee'], 2) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Sin MP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($m['metodo_pago']) ?></td>
                                    <td>
                                        <?php if (!empty($m['firma_digital'])): ?>
                                            <a href="../../<?= htmlspecialchars($m['firma_digital']) ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-signature"></i> Ver Firma
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($m['mp_status'] === 'authorized' && $m['mp_preapproval_id']): ?>
                                            <a href="cancel.php?id=<?= $m['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Cancelar Suscripción">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="delete.php?id=<?= $m['id'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Eliminar"
                                           onclick="return confirm('¿Seguro que deseas eliminar esta membresía?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php
// Include footer with proper path
//include dirname(dirname(__DIR__)) . '/includes/footer.php';
?>

<!-- Initialize DataTable -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // The DataTable initialization is now handled in footer.php
        // Any custom DataTable configuration specific to this page can go here
        if ($.fn.DataTable.isDataTable('#membresiasTable')) {
            $('#membresiasTable').DataTable({
                order: [[2, 'desc']], // Ordenar por fecha de inicio por defecto (columna 2)
                // Add any page-specific configuration here
            });
        }
    });
</script>