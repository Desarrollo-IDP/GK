<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$isAdmin = isset($_SESSION['user']['rol']) && $_SESSION['user']['rol'] === 'administrador';
$hasSupervisorAccess = function_exists('canViewInventoryHistory') ? canViewInventoryHistory() : false;


if (!$isAdmin && !$hasSupervisorAccess) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Historial de Actividades';
require_once 'includes/header.php';

$usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Verificar si existe columna id_empresa en historial_actividades
$check_column = $conn->query("SHOW COLUMNS FROM historial_actividades LIKE 'id_empresa'");
$has_empresa_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT h.* 
          FROM historial_actividades h 
          WHERE 1=1";

$params = [];
$types = "";

// Agregar filtro por empresa del usuario
if ($has_empresa_column && $user_empresa_id !== null) {
    $query .= " AND h.id_empresa = ?";
    $params[] = $user_empresa_id;
    $types .= "i";
}

if (!empty($usuario)) {
    $query .= " AND h.nombre_usuario LIKE ?";
    $params[] = "%$usuario%";
    $types .= "s";
}

if (!empty($accion)) {
    $query .= " AND h.accion LIKE ?";
    $params[] = "%$accion%";
    $types .= "s";
}

if (!empty($tabla)) {
    $query .= " AND h.tabla_afectada = ?";
    $params[] = $tabla;
    $types .= "s";
}

if (!empty($fecha_desde)) {
    $query .= " AND DATE(h.fecha_hora) >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}

if (!empty($fecha_hasta)) {
    $query .= " AND DATE(h.fecha_hora) <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}

$query .= " ORDER BY h.fecha_hora DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

try {
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Error obteniendo resultados: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Error en la base de datos: " . $e->getMessage());
    die("Ha ocurrido un error al cargar el historial. Por favor, contacte al administrador.");
}

$tablas_query = "SELECT DISTINCT tabla_afectada FROM historial_actividades ORDER BY tabla_afectada";
$tablas_result = $conn->query($tablas_query);
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                <i class="fas fa-history text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Historial de actividades</h1>
                <p class="inicio-header-subtitle">Consulta y filtra todas las acciones registradas en el sistema.</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-6">
        <!--<div class="px-5 pt-5 pb-3 lg:px-6 lg:pt-6 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600">
                    <i class="fas fa-sliders-h text-sm"></i>
                </span>
                Filtros
            </h2>
            <p class="text-sm text-gray-500 mt-1 ml-10">Refina los resultados por usuario, acción, tabla o rango de fechas.</p>
        </div>-->
        <form method="GET" class="p-5 lg:p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 mb-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-user w-4 mr-1.5 text-gray-400"></i> Usuario
                    </label>
                    <input
                        type="text"
                        name="usuario"
                        value="<?php echo htmlspecialchars($usuario); ?>"
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                        placeholder="Nombre de usuario"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-bolt w-4 mr-1.5 text-gray-400"></i> Acción
                    </label>
                    <input
                        type="text"
                        name="accion"
                        value="<?php echo htmlspecialchars($accion); ?>"
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                        placeholder="Creación, Actualización, Eliminación..."
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-table w-4 mr-1.5 text-gray-400"></i> Tabla
                    </label>
                    <select
                        name="tabla"
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                    >
                        <option value="">Todas</option>
                        <?php while ($tablas_result && $tabla_row = $tablas_result->fetch_assoc()): ?>
                            <option
                                value="<?php echo htmlspecialchars($tabla_row['tabla_afectada']); ?>"
                                <?php echo ($tabla == $tabla_row['tabla_afectada']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($tabla_row['tabla_afectada']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pt-1">
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-5 min-w-0 sm:max-w-md">
                    <div class="flex-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fas fa-calendar-day w-4 mr-1.5 text-gray-400"></i> Desde
                        </label>
                        <input
                            type="date"
                            name="fecha_desde"
                            value="<?php echo htmlspecialchars($fecha_desde); ?>"
                            class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                        >
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fas fa-calendar-check w-4 mr-1.5 text-gray-400"></i> Hasta
                        </label>
                        <input
                            type="date"
                            name="fecha_hasta"
                            value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                            class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                        >
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 sm:shrink-0">
                    <button
                        type="submit"
                        class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all"
                    >
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                    <a
                        href="historial-actividades.php"
                        class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all"
                    >
                        <i class="fas fa-undo"></i>
                        Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
            <div class="text-sm text-gray-600">
                <?php if ($result->num_rows > 0): ?>
                    <span class="font-semibold text-gray-800"><?php echo $result->num_rows; ?></span> registros encontrados
                <?php else: ?>
                    No se encontraron registros para los filtros seleccionados.
                <?php endif; ?>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Acción</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tabla</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Detalles</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha y hora</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($actividad = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 flex-shrink-0">
                                        <i class="fas fa-user text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($actividad['nombre_usuario']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                    $accionTexto = $actividad['accion'];
                                    $accionColor = 'bg-gray-100 text-gray-800';
                                    if (stripos($accionTexto, 'Creación') !== false) {
                                        $accionColor = 'bg-green-100 text-green-800';
                                    } elseif (stripos($accionTexto, 'Actualización') !== false) {
                                        $accionColor = 'bg-blue-100 text-blue-800';
                                    } elseif (stripos($accionTexto, 'Eliminación') !== false) {
                                        $accionColor = 'bg-red-100 text-red-800';
                                    }
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $accionColor; ?>">
                                    <?php echo htmlspecialchars($accionTexto); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    <i class="fas fa-table mr-1.5 text-[11px]"></i>
                                    <?php echo htmlspecialchars($actividad['tabla_afectada']); ?>
                                </span>
                                <?php if (!empty($actividad['id_registro'])): ?>
                                    <div class="text-xs text-gray-400 mt-1">
                                        ID registro: <?php echo htmlspecialchars($actividad['id_registro']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 align-top whitespace-normal break-words hist-detalles">
                            <?php 
                            $detalles = $actividad['detalles'];
                           
                            if ($actividad['tabla_afectada'] == 'equipos' && strpos($actividad['accion'], 'Actualización') !== false) {
                                if (preg_match('/ID: (\d+)/', $detalles, $matches)) {
                                    $equipo_id = $matches[1];
                                    
                                    $stmt_equipo = $conn->prepare("SELECT nombre_equipo FROM equipos WHERE id_equipo = ?");
                                    $stmt_equipo->bind_param("i", $equipo_id);
                                    $stmt_equipo->execute();
                                    $result_equipo = $stmt_equipo->get_result();
                                    
                                    if ($equipo = $result_equipo->fetch_assoc()) {
                                        $detalles = str_replace(
                                            "Se actualizó el equipo ID: $equipo_id", 
                                            "Se actualizó el equipo: " . htmlspecialchars($equipo['nombre_equipo']) . " (ID: $equipo_id)", 
                                            $detalles
                                        );
                                    }
                                }
                            }
                                if ($actividad['tabla_afectada'] == 'usuarios') {
                                    if (preg_match('/ID: (\d+)/', $detalles, $matches)) {
                                        $usuario_id = $matches[1];
                                        if (strpos($actividad['accion'], 'Creación') !== false) {
                                            $stmt_usuario = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?");
                                            $stmt_usuario->bind_param("i", $usuario_id);
                                            $stmt_usuario->execute();
                                            $result_usuario = $stmt_usuario->get_result();
                                            if ($usuario_data = $result_usuario->fetch_assoc()) {
                                                $detalles = preg_replace(
                                                    '/Se creó el usuario.*?ID: (\d+)/', 
                                                    "Se creó el usuario: " . htmlspecialchars($usuario_data['nombre_usuario']) . 
                                                    " (ID: $usuario_id)", 
                                                    $detalles
                                                );
                                            }
                                        }
                
                                        if (strpos($actividad['accion'], 'Actualización') !== false) {
                                            $stmt_usuario = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?");
                                            $stmt_usuario->bind_param("i", $usuario_id);
                                            $stmt_usuario->execute();
                                            $result_usuario = $stmt_usuario->get_result();
                                            
                                            if ($usuario_data = $result_usuario->fetch_assoc()) {
                                                $detalles = str_replace(
                                                    "Se actualizó el usuario: " . $usuario_id, 
                                                    "Se actualizó el usuario: " . htmlspecialchars($usuario_data['nombre_usuario']) . 
                                                    " (ID: $usuario_id)", 
                                                    $detalles
                                                );
                                            }
                                        }
                                    }
                                }
                                
                                if (strpos($actividad['accion'], 'Eliminación') !== false) {
                                    if (preg_match('/usuario:?\s*(.*?)\s*(?:\(ID:?\s*(\d+)\))?/', $detalles, $matches)) {
                                        $nombre_usuario = $matches[1];
                                        $usuario_id = isset($matches[2]) ? $matches[2] : '';
                                        
                                        if (!empty($usuario_id)) {
                                            $detalles = "Se eliminó el usuario: " . htmlspecialchars($nombre_usuario) . " (ID: $usuario_id)";
                                        } else {
                                            $detalles = "Se eliminó el usuario: " . htmlspecialchars($nombre_usuario);
                                        }
                                    }
                                }
                            
                            
                            echo nl2br(htmlspecialchars($detalles)); 
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="text-xs font-mono bg-gray-50 inline-flex px-2.5 py-1 rounded-md text-gray-700">
                                <?php echo date('d/m/Y H:i:s', strtotime($actividad['fecha_hora'])); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 text-sm">
                            No se encontraron registros de actividad para los filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .inv-page-wrap { max-width: 1600px; }
    .inv-brand-bg { background-color: #1E2A3B; }
    .inv-input { min-height: 42px; }
    .inv-input:hover { border-color: #cbd5e1; }
    .inv-btn-primary:hover { filter: brightness(1.08); }
    .inicio-header {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
    }
    .inicio-header-subtitle {
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
        color: #6b7280;
    }
    .hist-detalles {
        max-width: 520px;
    }
    @media (max-width: 768px) {
        .inicio-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .inicio-header h1 {
            font-size: 1.25rem;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>