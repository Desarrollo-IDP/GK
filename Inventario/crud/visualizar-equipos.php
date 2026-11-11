<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php'; 
checkAuth();
$pageTitle = 'Visualizar Equipos';
require_once 'includes/header.php';
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_empresa_id = getCurrentUserEmpresaId();

// Filtrar clientes por empresa del usuario
// Nota: equipos tiene id_empresa que apunta a tabla empresas (inventario)
// Necesitamos agregar campo id_empresa_usuario para relacionar con empresas_usuarios
$clients_query = "SELECT DISTINCT e.nombre_cliente 
                 FROM equipos e";
$clients_params = [];
$clients_types = '';

// Verificar si existe columna id_empresa_usuario en equipos
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    // Filtrar por id_empresa_usuario si existe
    $clients_query .= " WHERE e.id_empresa_usuario = ?";
    $clients_params[] = $user_empresa_id;
    $clients_types = 'i';
} elseif ($user_empresa_id !== null) {
    // Si no existe el campo, por ahora no filtramos (requiere migración SQL)
    // TODO: Agregar campo id_empresa_usuario a tabla equipos
}

$clients_query .= " ORDER BY e.nombre_cliente";

if (!empty($clients_params)) {
    $clients_stmt = $conn->prepare($clients_query);
    $clients_stmt->bind_param($clients_types, ...$clients_params);
    $clients_stmt->execute();
    $clients_result = $clients_stmt->get_result();
} else {
$clients_result = $conn->query($clients_query);
}

$client_names = [];
while($row = $clients_result->fetch_assoc()) {
    $client_names[] = $row['nombre_cliente'];
}


$cliente_filter = isset($_GET['cliente']) ? htmlspecialchars($_GET['cliente']) : '';

$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

$where_conditions = [];
$params = [];
$types = "";
$query = "SELECT e.*, emp.nombre as empresa_nombre,
          e.id_equipo,
          e.equipo_number,
          COALESCE(e.equipo_number, e.id_equipo) as display_id
          FROM equipos e 
          LEFT JOIN empresas_equipos emp ON e.id_empresa = emp.id_empresa";

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Agregar filtro por empresa del usuario
if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    // Filtrar por id_empresa_usuario si existe
    $where_conditions[] = "e.id_empresa_usuario = ?";
    $params[] = $user_empresa_id;
    $types .= 'i';
} elseif ($user_empresa_id !== null) {
    // Si no existe el campo, mostrar mensaje o no filtrar
    // TODO: Agregar campo id_empresa_usuario a tabla equipos
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(e.nombre_cliente LIKE ? OR 
                          e.nombre_equipo LIKE ? OR
                          e.responsable LIKE ? OR
                          e.sistema_operativo LIKE ? OR 
                          e.direccion_mac LIKE ? OR 
                          e.tipo LIKE ? OR
                          e.fabricante LIKE ? OR
                          e.modelo LIKE ? OR
                          e.numero_serie LIKE ? OR
                          e.observaciones LIKE ? OR
                          e.ubicacion LIKE ? OR
                          e.activo_equipo LIKE ?)";
    $params = array_merge($params, array_fill(0, 12, $search));
    $types .= str_repeat('s', 12);
}

if (!empty($cliente_filter)) {
    $where_conditions[] = "e.nombre_cliente LIKE ?";
    $params[] = "%$cliente_filter%";
    $types .= 's';
}

if (!empty($fecha_desde)) {
    $where_conditions[] = "DATE(e.fecha_registro) >= ?";
    $params[] = $fecha_desde;
    $types .= 's';
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "DATE(e.fecha_registro) <= ?";
    $params[] = $fecha_hasta;
    $types .= 's';
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " ORDER BY e.fecha_registro DESC";



try {
    
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Error getting result: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("Ha ocurrido un error al cargar los equipos.");
}
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <!-- Encabezado de página -->
    <div class="inicio-header mb-8">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                    <i class="fas fa-boxes-stacked text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Visualizar equipos</h1>
                    <p class="inicio-header-subtitle">Consulta y gestiona el inventario de equipos</p>
                </div>
            </div>
        </div>
       
    </div>

    <!-- Tarjeta de filtros -->
    <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-6 mb-8">
        <div class="flex items-center gap-2 mb-5">
            <i class="fas fa-filter inv-brand-text text-lg"></i>
            <h2 class="text-lg font-semibold text-gray-800">Filtros de búsqueda</h2>
        </div>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-building w-4 mr-1 text-gray-400"></i> Cliente
                </label>
                <select name="cliente" class="inventory-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                    <option value="">Todos los clientes</option>
                    <?php foreach($client_names as $client): ?>
                        <option value="<?php echo htmlspecialchars($client); ?>" 
                                <?php echo $cliente_filter === $client ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-search w-4 mr-1 text-gray-400"></i> Buscar
                </label>
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                       placeholder="Cliente, equipo, MAC, modelo..." 
                       class="inventory-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none placeholder-gray-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-calendar-alt w-4 mr-1 text-gray-400"></i> Desde
                </label>
                <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde ?? ''); ?>" 
                       class="inventory-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-calendar-alt w-4 mr-1 text-gray-400"></i> Hasta
                </label>
                <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta ?? ''); ?>" 
                       class="inventory-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" class="btn-inventory-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <?php if (isAdmin()): ?>
                <a href="agregar-equipo.php" class="btn-inventory-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                    <i class="fas fa-plus"></i> Agregar Equipo
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>










    <!-- Tabla de inventario -->
    <div class="inventory-table-wrap bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="table-container overflow-x-auto overflow-y-auto max-h-[520px]">
            <table class="inventory-table min-w-full">
                <thead>
                    <tr>
                        <th class="inv-th">ID Equipo</th>
                        <th class="inv-th">Cliente</th>
                        <th class="inv-th">Equipo</th>
                        <th class="inv-th">Responsable</th>
                        <th class="inv-th">S.O.</th>
                        <th class="inv-th">MAC</th>
                        <th class="inv-th">Tipo</th>
                        <th class="inv-th">Fabricante</th>
                        <th class="inv-th">Modelo</th>
                        <th class="inv-th">Nº Serie</th>
                        <th class="inv-th">RAM</th>
                        <th class="inv-th">Disco</th>
                        <th class="inv-th">Procesador</th>
                        <th class="inv-th">Ubicación</th>
                        <th class="inv-th">Activo</th>
                        <th class="inv-th">Observaciones</th>
                        <th class="inv-th">Documentos</th>
                        <th class="inv-th">Fecha</th>
                        <?php if (isAdmin()): ?>
                        <th class="inv-th inv-th-actions">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowIndex = 0; while ($row = $result->fetch_assoc()): $rowIndex++; ?>
                    <tr class="inv-tr <?php echo $rowIndex % 2 === 0 ? 'inv-tr-even' : ''; ?>">
                        <td class="inv-td font-mono text-xs inv-brand-text font-medium"><?php echo htmlspecialchars($row['equipo_number'] ?? ($row['id_equipo'] ?? '')); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['nombre_cliente'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['nombre_equipo'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['responsable'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['sistema_operativo'] ?? ''); ?></td>
                        <td class="inv-td font-mono text-xs"><?php echo htmlspecialchars($row['direccion_mac'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['tipo'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['fabricante'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['modelo'] ?? ''); ?></td>
                        <td class="inv-td font-mono text-xs"><?php echo htmlspecialchars($row['numero_serie'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['ram'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['disco_duro'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['procesador'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['ubicacion'] ?? ''); ?></td>
                        <td class="inv-td"><?php echo htmlspecialchars($row['activo_equipo'] ?? ''); ?></td>
                        <td class="inv-td inv-td-notes" title="<?php echo htmlspecialchars($row['observaciones'] ?? ''); ?>"><?php echo htmlspecialchars($row['observaciones'] ?? ''); ?></td>
                        <td class="inv-td inv-td-docs">
                            <?php 
                            if(!empty($row['documentos'])) {
                                $documentos = json_decode($row['documentos'], true);
                                if(is_array($documentos)) {
                                    echo '<div class="inv-docs-wrap">';
                                    foreach($documentos as $index => $doc) {
                                        $url = 'ver-documento.php?id=' . $row['id_equipo'] . '&doc=' . $index;
                                        $nombre = htmlspecialchars($doc['nombre'] ?? 'Documento');
                                        echo '<a href="' . $url . '" class="inv-doc-item" target="_blank" rel="noopener" title="Abrir: ' . $nombre . '">';
                                        echo '<i class="fas fa-file-alt inv-doc-icon"></i>';
                                        echo '<span class="inv-doc-name">' . $nombre . '</span>';
                                        echo '<i class="fas fa-external-link-alt inv-doc-external"></i>';
                                        echo '</a>';
                                    }
                                    echo '</div>';
                                }
                            } else {
                                echo '<span class="inv-doc-empty"><i class="fas fa-folder-open"></i> Sin documentos</span>';
                            }
                            ?>
                        </td>
                        <td class="inv-td text-gray-600 text-sm"><?php echo htmlspecialchars($row['fecha_registro'] ?? ''); ?></td>
                        <?php if (isAdmin()): ?>
                        <td class="inv-td inv-td-actions">
                            <a href="editar-equipo.php?id=<?php echo $row['id_equipo']; ?>" 
                               class="inv-btn inv-btn-edit"><i class="fas fa-pen mr-1"></i> Editar</a>
                            <button type="button" onclick="deleteEquipo(<?php echo $row['id_equipo']; ?>)" 
                                    class="inv-btn inv-btn-delete"><i class="fas fa-trash-alt mr-1"></i> Eliminar</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteEquipo(id) {
    showConfirm('¿Está seguro de eliminar este equipo?', function() {
        fetch('api/delete-equipo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAlert('Error al eliminar el equipo: ' + (data.error || 'Error desconocido'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error al eliminar el equipo: ' + error.message, 'error');
        });
    }, { title: 'Eliminar equipo', okText: 'Eliminar' });
}


if (window.performance && window.performance.navigation.type === window.performance.navigation.TYPE_BACK_FORWARD) {
    location.reload();
}
</script>
<style>
/* Utilidades tema inventario (alineado con navbar #1E2A3B) */
.inv-page-wrap { max-width: 1600px; }
.inv-brand-bg { background-color: #1E2A3B; }
.inv-brand-text { color: #1E2A3B; }
.inicio-header {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.inicio-header-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.9rem;
    color: #6b7280;
}
.inicio-header-date {
    color: #6b7280;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.inicio-header-date i {
    color: #1E2A3B;
}
@media (max-width: 768px) {
    .inicio-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
.btn-inventory-primary:hover { filter: brightness(1.08); }
/* ---- Inventario: inputs y botones ---- */
.inventory-input:hover { border-color: #cbd5e1; }
/* ---- Campo Observaciones ---- */
.inv-td-notes {
    min-width: 220px;
    max-width: 220px;
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}
/* ---- Campo Documentos ---- */
.inv-td-docs { min-width: 140px; max-width: 220px; }
.inv-docs-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.inv-doc-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.6rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    color: #1E2A3B;
    font-size: 0.8125rem;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    width: fit-content;
    max-width: 100%;
}
.inv-doc-item:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
    box-shadow: 0 2px 8px rgba(29, 78, 216, 0.12);
}
.inv-doc-icon {
    font-size: 0.75rem;
    color: #64748b;
    flex-shrink: 0;
}
.inv-doc-item:hover .inv-doc-icon { color: #2563eb; }
.inv-doc-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.inv-doc-external {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-left: auto;
    flex-shrink: 0;
}
.inv-doc-item:hover .inv-doc-external { color: #3b82f6; }
.inv-doc-empty {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8125rem;
    color: #94a3b8;
}
.inv-doc-empty i { font-size: 0.9rem; opacity: 0.8; }

.inv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    background-color: #ffffff;
    text-decoration: none;
    transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    flex-shrink: 0;
}
.inv-td-actions .inv-btn {
    margin-right: 0.3rem;
    width: 95px;
    min-width: 95px;
    max-width: 95px;
    box-sizing: border-box;
}
.inv-td-actions .inv-btn:last-child {
    margin-right: 0;
}
.inv-btn i { font-size: 0.7rem; }
.inv-btn-edit {
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.inv-btn-edit:hover {
    background-color: #eff6ff;
    border-color: #60a5fa;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
}
.inv-btn-delete {
    color: #b91c1c;
    border-color: #fecaca;
}
.inv-btn-delete:hover {
    background-color: #fef2f2;
    border-color: #f97373;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
}

/* ---- Tabla inventario ---- */
.inventory-table-wrap { min-height: 200px; }
.table-container {
    overflow-y: auto;
    overflow-x: auto;
    max-height: 520px;
    -webkit-overflow-scrolling: touch;
    width: 100%;
}
.inventory-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    min-width: 1200px;
    font-size: 0.875rem;
}
.inventory-table thead { position: sticky; top: 0; z-index: 10; }
.inv-th {
    background: #1E2A3B;
    color: #fff;
    font-weight: 600;
    text-align: left;
    padding: 0.875rem 1rem;
    white-space: nowrap;
    border-bottom: 2px solid #2d3a50;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.inv-th:not(:last-child) { border-right: 1px solid rgba(255,255,255,0.08); }
.inv-th-actions { text-align: center; min-width: 140px; }
.inv-tr { transition: background-color 0.12s ease; }
.inv-tr:hover { background-color: #f8fafc; }
.inv-tr-even { background-color: #fafbfc; }
.inv-tr-even:hover { background-color: #f1f5f9; }
.inv-td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    vertical-align: middle;
}
.inv-td-actions {
    white-space: nowrap;
    text-align: center;
    position: sticky;
    right: 0;
    background-color: #ffffff;
    box-shadow: -8px 0 12px -4px rgba(0,0,0,0.04);
}
.inv-tr-even .inv-td-actions {
    background-color: #fafbfc;
}
.inv-tr:hover .inv-td-actions {
    background-color: #f8fafc;
}
.inv-tr-even:hover .inv-td-actions {
    background-color: #f1f5f9;
}

/* Responsive tabla */
@media (max-width: 768px) {
    .table-container { max-height: 420px; }
    .inventory-table { min-width: 1100px; font-size: 0.8125rem; }
    .inv-th, .inv-td { padding: 0.5rem 0.65rem; font-size: 0.75rem; }
    .inv-th { font-size: 0.7rem; }
    .inv-th-actions { min-width: 200px; }
    .inv-td-actions .inv-btn {
        margin-right: 0.35rem;
        width: 95px;
        min-width: 95px;
        max-width: 95px;
    }
}
@media (min-width: 769px) and (max-width: 1024px) {
    .table-container { max-height: 480px; }
}
</style>
<?php
require_once 'includes/footer.php';
?>