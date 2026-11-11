<?php
include '../../includes/common.php';
require_once '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Agregar dominio
if (isset($_POST['add_domain'])) {
    $domain = strtolower(trim($_POST['domain']));
    if (!empty($domain)) {
        // Verificar si el dominio ya existe para esta empresa
        $check_query = "SELECT id FROM blacklisted_domains WHERE domain = ?";
        $check_params = [$domain];
        $check_types = 's';
        
        if ($user_empresa_id !== null) {
            $check_query .= " AND id_empresa = ?";
            $check_params[] = $user_empresa_id;
            $check_types .= 'i';
        } else {
            $check_query .= " AND id_empresa IS NULL";
        }
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param($check_types, ...$check_params);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // El dominio no existe para esta empresa, agregarlo
            if ($user_empresa_id !== null) {
                $stmt = $conn->prepare("INSERT INTO blacklisted_domains (domain, id_empresa) VALUES (?, ?)");
                $stmt->bind_param("si", $domain, $user_empresa_id);
            } else {
                // Admin crea dominio global (id_empresa = NULL)
                $stmt = $conn->prepare("INSERT INTO blacklisted_domains (domain, id_empresa) VALUES (?, NULL)");
                $stmt->bind_param("s", $domain);
            }
            $stmt->execute();
            $stmt->close();
        }
        $check_stmt->close();
    }
    header('Location: blacklist_domains.php');
    exit;
}

// Eliminar dominio
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($user_empresa_id !== null) {
        // Usuario normal: verificar que el dominio pertenece a su empresa antes de eliminar
        $check_query = "SELECT id FROM blacklisted_domains WHERE id = ? AND id_empresa = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $id, $user_empresa_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // El dominio pertenece a la empresa del usuario, proceder con la eliminación
            $stmt = $conn->prepare("DELETE FROM blacklisted_domains WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        // Admin: puede eliminar cualquier dominio (incluyendo globales)
        $stmt = $conn->prepare("DELETE FROM blacklisted_domains WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: blacklist_domains.php');
    exit;
}

// Obtener lista de dominios filtrados por empresa
$query = "SELECT bd.*, eu.nombre as empresa_nombre 
          FROM blacklisted_domains bd
          LEFT JOIN empresas_usuarios eu ON bd.id_empresa = eu.id_empresa";
$params = [];
$types = '';

// Filtrar por empresa del usuario (admin ve todos)
if ($user_empresa_id !== null) {
    $query .= " WHERE bd.id_empresa = ?";
    $params[] = $user_empresa_id;
    $types = 'i';
} else {
    // Admin puede ver todos, incluyendo los globales (id_empresa IS NULL)
    // No agregar filtro WHERE
}

$query .= " ORDER BY bd.domain ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$domains = [];
while ($row = $result->fetch_assoc()) {
    $domains[] = $row;
}
$stmt->close();
?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 900px;
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
@media (max-width: 767.98px) {
    .main-content-boxed {
        padding: 0.5rem 0.2rem;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">
                        Lista negra de dominios de correo
                        <?php echo getEmpresaBadge(); ?>
                    </h1>
                    <p class="text-muted mb-0">
                        Administra los dominios bloqueados para evitar tickets no deseados
                        <?php if ($user_empresa_id !== null): ?>
                            <br><small>Mostrando solo dominios de tu empresa</small>
                        <?php else: ?>
                            <br><small>Mostrando todos los dominios (admin)</small>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="tickets-page-actions">
                    <a href="configuration.php" class="btn btn-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <form method="post" class="row g-3 mb-4">
                        <div class="col-auto">
                            <input type="text" name="domain" class="form-control" placeholder="ejemplo.com" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="add_domain" class="btn btn-primary">Agregar dominio</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle tickets-list-table">
                            <thead>
                                <tr>
                                    <th>Dominio</th>
                                    <?php if ($user_empresa_id === null): ?>
                                        <th>Empresa</th>
                                    <?php endif; ?>
                                    <th>Fecha de agregado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($domains)): ?>
                                <tr>
                                    <td colspan="<?php echo $user_empresa_id === null ? '4' : '3'; ?>" class="text-center">
                                        No hay dominios en la lista negra
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($domains as $d): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($d['domain']); ?></td>
                                        <?php if ($user_empresa_id === null): ?>
                                            <td>
                                                <?php 
                                                if ($d['id_empresa'] === null) {
                                                    echo '<span class="badge bg-secondary">Global</span>';
                                                } else {
                                                    echo getEmpresaBadge($d['id_empresa'], $d['empresa_nombre'] ?? null);
                                                }
                                                ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($d['created_at']); ?></td>
                                        <td>
                                            <a href="?delete=<?php echo $d['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este dominio?');">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html> 