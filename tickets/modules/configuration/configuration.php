<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';


$tables = [
    'categories' => 'Categorías',
    'statuses' => 'Estados', 
    'contact_modes' => 'Modos de Contacto',
    'levels' => 'Niveles',
    'impacts' => 'Impactos',
   // 'urgencies' => 'Urgencias',
    'priorities' => 'Prioridades',
    'classifications' => 'Clasificaciones',
    'empresas' => 'Empresas',
    'level' => 'Nivel del tecnico',
    // Nuevo catálogo para correos en copia
    'ticket_cc_emails' => 'Correos en copia',
];


// Función para verificar si una columna existe en una tabla
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

$counts = [];
$user_empresa_id = getCurrentUserEmpresaId();
foreach ($tables as $table => $label) {
        // Verificar si la tabla tiene columna id_empresa
        $has_empresa_column = columnExists($conn, $table, 'id_empresa');
        
        $query = "SELECT COUNT(*) as count FROM $table";
        $params = [];
        $types = '';
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            // Filtrar por empresa si el campo existe y el usuario tiene empresa
            $query .= " WHERE id_empresa = ?";
            $params[] = $user_empresa_id;
            $types = 'i';
        }
        // Si no tiene el campo o es admin, mostrar todo (sin WHERE adicional)
        
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $result = $conn->query($query);
        }
    
    $counts[$table] = $result->fetch_assoc()['count'];
}

$blacklist_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM blacklisted_domains");
if ($result) {
    $blacklist_count = $result->fetch_assoc()['count'];
}
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
    max-width: 1100px;
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
                    <h1 class="h2 mb-2">Configuración del Sistema</h1>
                    <p class="text-muted mb-0">Administra catálogos, listas y parámetros clave del sistema</p>
                </div>
            </div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($tables as $table => $label): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $label; ?></h5>
                            <p class="card-text">
                                <span class="badge bg-primary"><?php echo $counts[$table]; ?> registros</span>
                            </p>
                            <p class="card-text">Administre los valores disponibles para el campo "<?php echo $label; ?>".</p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="manage.php?table=<?php echo $table; ?>" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Administrar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <!-- Tarjeta para lista negra de dominios 
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-ban me-2"></i>Lista negra de dominios</h5>
                            <p class="card-text">
                                <span class="badge bg-primary"> <?php echo $blacklist_count; ?> registros</span>
                            </p>
                            <p class="card-text">Administre los dominios de correo bloqueados para evitar la creación de tickets desde esos dominios.</p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="blacklist_domains.php" class="btn btn-primary">
                                <i class="fas fa-ban"></i> Gestionar lista negra
                            </a>
                        </div>
                    </div>
                </div>-->
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>