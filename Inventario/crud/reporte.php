<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$pageTitle = 'Generar Reporte';
require_once 'includes/header.php';

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

// Filtrar clientes por empresa del usuario
$clientQuery = "SELECT DISTINCT nombre_cliente FROM equipos";
$client_params = [];
$client_types = '';

if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $clientQuery .= " WHERE id_empresa_usuario = ?";
    $client_params[] = $user_empresa_id;
    $client_types = 'i';
}

$clientQuery .= " ORDER BY nombre_cliente";

if (!empty($client_params)) {
    $client_stmt = $conn->prepare($clientQuery);
    $client_stmt->bind_param($client_types, ...$client_params);
    $client_stmt->execute();
    $clientResult = $client_stmt->get_result();
} else {
    $clientResult = $conn->query($clientQuery);
}

$selectedClient = $_GET['cliente'] ?? '';
$searchTerm = $_GET['busqueda'] ?? '';

$equiposQuery = "SELECT *, id_equipo, equipo_number, COALESCE(equipo_number, id_equipo) as display_id FROM equipos";
$conditions = [];

// Agregar filtro por empresa del usuario
if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $conditions[] = "id_empresa_usuario = " . intval($user_empresa_id);
}

if (!empty($selectedClient)) {
    $conditions[] = "nombre_cliente = '" . $conn->real_escape_string($selectedClient) . "'";
}

if (!empty($searchTerm)) {
    $searchEscaped = $conn->real_escape_string($searchTerm);
    $searchConditions = [
        "nombre_cliente LIKE '%" . $searchEscaped . "%'",
        "nombre_equipo LIKE '%" . $searchEscaped . "%'",
        "responsable LIKE '%" . $searchEscaped . "%'",
        "sistema_operativo LIKE '%" . $searchEscaped . "%'",
        "direccion_mac LIKE '%" . $searchEscaped . "%'",
        "tipo LIKE '%" . $searchEscaped . "%'",
        "fabricante LIKE '%" . $searchEscaped . "%'",
        "modelo LIKE '%" . $searchEscaped . "%'",
        "numero_serie LIKE '%" . $searchEscaped . "%'",
        "ram LIKE '%" . $searchEscaped . "%'",
        "disco_duro LIKE '%" . $searchEscaped . "%'",
        "procesador LIKE '%" . $searchEscaped . "%'",
        "observaciones LIKE '%" . $searchEscaped . "%'"
    ];
    $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
}

if (!empty($conditions)) {
    $equiposQuery .= " WHERE " . implode(" AND ", $conditions);
}

$equiposResult = $conn->query($equiposQuery);
?>

<style>
    .inv-page-wrap { max-width: 1600px; }
    .inv-brand-bg { background-color: #1E2A3B; }
    .inv-input { min-height: 42px; border-radius: 0.75rem; }
    .inv-input:hover { border-color: #cbd5e1; }
    .inv-btn-primary:hover { filter: brightness(1.08); }
    .inv-action-btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
    }
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
    @media (max-width: 768px) {
        .inicio-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .inicio-header h1 { font-size: 1.25rem; }
    }
</style>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                <i class="fas fa-file-alt text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Generar reporte</h1>
                <p class="inicio-header-subtitle">Configura el tipo de reporte, filtros y equipos antes de generar el documento.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-5 lg:p-6 mb-6">
        <form method="get" class="space-y-4">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 lg:gap-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 flex-1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fas fa-layer-group w-4 mr-1.5 text-gray-400"></i> Tipo de reporte
                        </label>
                        <select name="tipo_reporte" class="inv-input w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" required>
                            <option value="">Seleccione un tipo</option>
                            <option value="general" <?php echo ($_GET['tipo_reporte'] ?? '') === 'general' ? 'selected' : ''; ?>>Reporte general</option>
                            <option value="carta_responsiva" <?php echo ($_GET['tipo_reporte'] ?? '') === 'carta_responsiva' ? 'selected' : ''; ?>>Carta responsiva</option>
                            <option value="carta_entrega" <?php echo ($_GET['tipo_reporte'] ?? '') === 'carta_entrega' ? 'selected' : ''; ?>>Carta entrega</option>
                            <option value="carta_prestamo" <?php echo ($_GET['tipo_reporte'] ?? '') === 'carta_prestamo' ? 'selected' : ''; ?>>Carta de préstamo</option>
                            <option value="carta_mantenimiento" <?php echo ($_GET['tipo_reporte'] ?? '') === 'carta_mantenimiento' ? 'selected' : ''; ?>>Carta de mantenimiento</option>
                        </select>
                    </div>

                    <div id="mantenimientoSubtypeContainer" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fas fa-tools w-4 mr-1.5 text-gray-400"></i> Categoría de mantenimiento
                        </label>
                        <select id="mantenimientoSubtype" class="inv-input w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none">
                            <option value="Laptop">Laptop</option>
                            <option value="PC">PC (Escritorio)</option>
                            <option value="Impresora">Impresora</option>
                            <option value="Mantenimiento Preventivo">Mantenimiento preventivo</option>
                            <option value="Salida a Laboratorio">Salida a laboratorio</option>
                        </select>
                    </div>

                    <div id="filterEmpresaContainer">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fas fa-building w-4 mr-1.5 text-gray-400"></i> Filtrar por empresa
                        </label>
                        <select name="cliente" class="inv-input w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none">
                            <option value="">Todas las empresas</option>
                            <?php while ($client = $clientResult->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($client['nombre_cliente']); ?>"
                                        <?php echo $selectedClient === $client['nombre_cliente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['nombre_cliente']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-2 md:mt-0 md:self-end">
                    <a
                        href="carta-mail.php"
                        class="inv-btn-secondary inv-action-btn gap-2 px-4 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all text-center"
                    >
                        <i class="fas fa-envelope"></i>
                        Cartas de correo electrónico
                    </a>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4" id="searchFilterRow">
                <div class="flex-1" id="searchFilterContainer">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-search w-4 mr-1.5 text-gray-400"></i> Búsqueda general
                    </label>
                    <input
                        type="text"
                        name="busqueda"
                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                        placeholder="Buscar en nombre de equipo, responsable, sistema operativo..."
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                    >
                    <p class="text-xs text-gray-500 mt-1">Se buscará en múltiples campos del inventario.</p>
                </div>
                <div id="searchActions" class="flex flex-wrap gap-3 sm:shrink-0">
                    <button
                        type="submit"
                        class="inv-btn-primary inv-action-btn gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all"
                    >
                        <i class="fas fa-filter"></i>
                        Buscar
                    </button>
                    <?php if (!empty($searchTerm) || !empty($selectedClient)): ?>
                    <a
                        href="reporte.php?tipo_reporte=<?php echo htmlspecialchars($_GET['tipo_reporte'] ?? ''); ?>"
                        class="inv-btn-secondary inv-action-btn gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all text-center"
                    >
                        <i class="fas fa-undo"></i>
                        Limpiar
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

   <form method="post" action="generar_reporte.php" id="reportForm" target="_blank">
    <?php $unique_report_id = bin2hex(random_bytes(16)); ?>
    <input type="hidden" name="report_id" value="<?php echo $unique_report_id; ?>">
    <input type="hidden" name="tipo_reporte" value="<?php echo htmlspecialchars($_GET['tipo_reporte'] ?? 'general'); ?>">
    <input type="hidden" name="mantenimiento_categoria" id="hiddenCategoria" value="Laptop">
    <input type="hidden" name="empresa_nombre" value="<?php echo htmlspecialchars($selectedClient); ?>">
        
        <div id="cartaEntradaFields" class="bg-white shadow-md rounded-lg p-6 mb-6 border-t-4 border-blue-600" style="display: none;">
            <div class="flex justify-between items-center mb-6 border-b pb-2">
                <h2 id="cartaEntradaTitle" class="text-xl font-bold text-gray-800 uppercase">Datos de Carta de Mantenimiento (Laptop)</h2>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded uppercase">Manual</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Marca</label>
                    <input type="text" name="entrada_marca" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Empresa / Cliente</label>
                    <input type="text" name="entrada_cliente" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Modelo</label>
                    <input type="text" name="entrada_modelo" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Reporta (Responsable)</label>
                    <input type="text" name="entrada_responsable" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Número de Serie</label>
                    <input type="text" name="entrada_serie" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1" id="field_nombre_equipo" style="display: none;">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Nombre de Equipo</label>
                    <input type="text" name="entrada_nombre_equipo" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="space-y-1" id="field_contrasena">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Contraseña</label>
                    <input type="text" name="entrada_contrasena" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-3 flex items-center gap-6 bg-gray-50 p-3 rounded border" id="field_restauracion">
                    <label class="text-sm font-bold text-gray-700 uppercase">¿Autoriza Restauración?</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="entrada_restauracion" value="SI" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm font-medium text-gray-900">SI</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="entrada_restauracion" value="NO" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm font-medium text-gray-900">NO</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-sm font-bold text-blue-700 mb-4 border-l-4 border-blue-700 pl-2 uppercase">Equipo en General y Aditamentos</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6" id="laptopAditamentos">
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Cargador</label>
                        <div class="flex justify-center gap-4">
                            <label class="inline-flex items-center"><input type="radio" name="entrada_cargador" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center"><input type="radio" name="entrada_cargador" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Batería</label>
                        <div class="flex justify-center gap-4">
                            <label class="inline-flex items-center"><input type="radio" name="entrada_bateria" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center"><input type="radio" name="entrada_bateria" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Cable AC</label>
                        <div class="flex justify-center gap-4">
                            <label class="inline-flex items-center"><input type="radio" name="entrada_cable_ac" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center"><input type="radio" name="entrada_cable_ac" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                </div>

                <!-- PC Aditamentos -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 hidden" id="pcAditamentos">
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Monitor</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_monitor" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_monitor" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Teclado</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_teclado" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_teclado" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mouse</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_mouse" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_mouse" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Equipo All-in-one</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_aio" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_aio" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cable AC PC</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_ac_pc" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_ac_pc" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cable de Video</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_video" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_video" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">¿Lleva Impresora?</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="adit_impresora" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="adit_impresora" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                </div>

                <!-- Printer Aditamentos -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 hidden" id="printerAditamentos">
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cable USB</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_usb" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_usb" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cartucho Toner</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_toner" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_toner" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cartucho Tinta</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_tinta" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_tinta" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded border text-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cable AC Impresora</label>
                        <div class="flex justify-center gap-3">
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_ac_imp" value="SI" class="mr-1"> SI</label>
                            <label class="inline-flex items-center text-xs"><input type="radio" name="entrada_cable_ac_imp" value="NO" class="mr-1"> NO</label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6" id="commonAditamentos">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Disco Duro</label>
                        <input type="text" name="entrada_disco_duro" class="w-full p-2 border rounded outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Memoria RAM</label>
                        <input type="text" name="entrada_memoria_ram" class="w-full p-2 border rounded outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Falla Reportada</label>
                        <input type="text" name="falla_reportada" class="w-full p-2 border rounded outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Otros Aditamentos</label>
                        <input type="text" name="entrada_otros" class="w-full p-2 border rounded outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-sm font-bold text-blue-700 mb-4 border-l-4 border-blue-700 pl-2 uppercase">Revisión de Condiciones de Equipo</h3>
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <!-- Laptop Checklist -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1" id="laptopChecklist">
                        <?php 
                        $laptop_conds = [
                            'cond_rayado' => 'Equipo Rayado o Maltratado',
                            'cond_golpeado' => 'Equipo Golpeado',
                            'cond_tornillos_l' => 'Tornillos Incompletos',
                            'cond_gomas' => 'Gomas de Display Completas',
                            'cond_respaldo_l' => 'Respaldo de la Información',
                            'cond_liquidos_l' => 'Muestra Derrame de Líquidos',
                            'cond_teclado_l' => 'Teclado Completo',
                            'cond_cable_ac_l' => 'Cable AC Maltratado o Roto',
                            'cond_cargador_l' => 'Cargador Maltratado o Roto',
                            'cond_intervencion_l' => 'Muestra Intervención del Equipo'
                        ];
                        foreach ($laptop_conds as $name => $label): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-700"><?php echo $label; ?></span>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="SI" class="mr-1"> SI</label>
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="NO" class="mr-1"> NO</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PC Checklist -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 hidden" id="pcChecklist">
                        <?php 
                        $pc_conds = [
                            'cond_rayado_pc' => 'Equipo Rayado o Maltratado',
                            'cond_cargador_pc' => 'Cargador Golpeado',
                            'cond_tornillos_pc' => 'Tornillos Completos',
                            'cond_monitor_pc' => 'Monitor Rayado o Maltratado',
                            'cond_tapas_pc' => 'Tapas o Protecciones',
                            'cond_liquidos_pc' => 'Muestra Derrame de Líquidos',
                            'cond_respaldo_pc' => 'Respaldo de la Información',
                            'cond_cable_ac_pc' => 'Cable AC Maltratado o Roto',
                            'cond_cable_video_pc' => 'Cable de Video Maltratado o Roto'
                        ];
                        foreach ($pc_conds as $name => $label): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-700"><?php echo $label; ?></span>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="SI" class="mr-1"> SI</label>
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="NO" class="mr-1"> NO</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Printer Checklist -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 hidden" id="printerChecklist">
                        <?php 
                        $printer_conds = [
                            'cond_rayado_imp' => 'Equipo Rayado o Maltratado',
                            'cond_golpeado_imp' => 'Equipo Golpeado',
                            'cond_tornillos_imp' => 'Tornillos Incompletos',
                            'cond_intervencion_imp' => 'Muestra Intervención del Equipo',
                            'cond_tapas_imp' => 'Tapas o Protecciones',
                            'cond_liquidos_imp' => 'Muestra Derrame de Líquidos',
                            'cond_cable_ac_imp_br' => 'Cable AC Maltratado o Roto',
                            'cond_usb_br' => 'Cable USB Maltratado o Roto'
                        ];
                        foreach ($printer_conds as $name => $label): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-700"><?php echo $label; ?></span>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="SI" class="mr-1"> SI</label>
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="NO" class="mr-1"> NO</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Laboratorio Checklist -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 hidden" id="labChecklist">
                        <?php 
                        $lab_conds = [
                            'cond_rayado_lab' => 'Equipo Rayado o Maltratado',
                            'cond_pantalla_lab' => 'Pantalla Rayada',
                            'cond_tornillos_lab' => 'Tornillos Incompletos',
                            'cond_monitor_lab' => 'Monitor Rayado o Maltratado',
                            'cond_tapas_lab' => 'Tapas o Protecciones',
                            'cond_liquidos_lab' => 'Muestra Derrame de Líquidos'
                        ];
                        foreach ($lab_conds as $name => $label): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="text-xs font-medium text-gray-700"><?php echo $label; ?></span>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="SI" class="mr-1"> SI</label>
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="NO" class="mr-1"> NO</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Observaciones de Revisión</label>
                        <textarea name="entrada_observaciones" rows="3" class="w-full p-3 border rounded outline-none focus:ring-1 focus:ring-blue-500" placeholder="Detalle cualquier observación adicional de la revisión..."></textarea>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8" id="section_reparacion_y_salida">
                <div>
                    <h3 class="text-sm font-bold text-blue-700 mb-4 border-l-4 border-blue-700 pl-2 uppercase" id="label_reparacion">Reparación Realizada</h3>
                    <textarea name="reparacion_realizada" rows="6" class="w-full p-3 border rounded outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="Describa el trabajo técnico efectuado o motivo de traslado..."></textarea>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold text-blue-700 mb-4 border-l-4 border-blue-700 pl-2 uppercase">Salida de Equipo (Validación)</h3>
                    <div class="bg-gray-50 p-4 rounded-lg border space-y-1">
                        <?php 
                        $salida_labels = [
                            'salida_armado' => 'Equipo Bien Armado',
                            'salida_limpieza' => 'Limpieza de Equipo',
                            'salida_antivirus' => 'Instalación de Antivirus',
                            'salida_envuelto' => 'Equipo Envuelto / Cerrado',
                            'salida_office' => 'Instalación de Office / Configuración',
                            'salida_video' => 'Prueba de Video / Sensores',
                            'salida_informacion' => 'Paso / Respaldo de Información (PC)',
                            'salida_accesorios' => 'Accesorios Ordenados'
                        ];
                        foreach ($salida_labels as $name => $label): ?>
                            <div class="flex justify-between items-center py-1 border-b border-gray-100 italic last:border-0">
                                <span class="text-xs text-gray-600"><?php echo $label; ?></span>
                                <div class="flex gap-3">
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="SI" class="mr-1"> SI</label>
                                    <label class="inline-flex items-center text-xs cursor-pointer"><input type="radio" name="<?php echo $name; ?>" value="NO" class="mr-1"> NO</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t" id="section_diagnostico_final">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase">¿Qué Diagnostica?</label>
                    <input type="text" name="diagnostico" class="w-full p-2 border rounded bg-gray-50">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase">Ingeniero que Repara</label>
                    <input type="text" name="ing_repara" class="w-full p-2 border rounded bg-gray-50">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase">Persona que Recibe</label>
                    <input type="text" name="persona_recibe" class="w-full p-2 border rounded bg-gray-50">
                </div>
            </div>
        </div>

        <div class="mt-6 mb-2" id="reportActions">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        id="selectAll"
                        class="inv-btn-secondary inv-action-btn gap-2 px-4 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all"
                    >
                        <i class="fas fa-check-square"></i>
                        Seleccionar todos
                    </button>
                    <button
                        type="button"
                        id="deselectAll"
                        class="inv-btn-secondary inv-action-btn gap-2 px-4 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all"
                    >
                        <i class="far fa-square"></i>
                        Deseleccionar todos
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        id="exportExcelBtn"
                        class="inv-btn-secondary inv-action-btn gap-2 px-4 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all text-center"
                        style="display: none;"
                    >
                        <i class="fas fa-file-excel"></i>
                        Exportar Excel
                    </button>
                    <button
                        type="submit"
                        class="inv-btn-primary inv-action-btn gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all text-center"
                    >
                        <i class="fas fa-file-export"></i>
                        Generar reporte
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden my-4" id="equiposTable">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300">
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Equipo</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Equipo</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número de Serie</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($equipo = $equiposResult->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" name="equipos[]" value="<?php echo $equipo['id_equipo']; ?>" 
                                   class="equipo-checkbox rounded border-gray-300">
                        </td>
                        <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['id_equipo'] ?? ''); ?>
                        </td> -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['equipo_number'] ?? ($equipo['id_equipo'] ?? '')); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['nombre_cliente']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['nombre_equipo']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['numero_serie'] ?? ''); ?></td>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['tipo'] ?? ''); ?></td>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($equipo['modelo'] ?? ''); ?></td>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </form>
    
</div>

<script>
    function handleReportTypeChange() {
        const reportType = this.value;
        const isCartaType = reportType.includes('carta');
        const isCartaMantenimiento = reportType === 'carta_mantenimiento';
        const isCartaEntrada = reportType === 'carta_entrega';
        const checkboxes = document.querySelectorAll('.equipo-checkbox');
        const exportExcelBtn = document.getElementById('exportExcelBtn');
        const equiposTable = document.getElementById('equiposTable');
        const cartaEntradaFields = document.getElementById('cartaEntradaFields');
        const selectAllBtn = document.getElementById('selectAll');
        const deselectAllBtn = document.getElementById('deselectAll');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const subContainer = document.getElementById('mantenimientoSubtypeContainer');
        const filterEmpresa = document.getElementById('filterEmpresaContainer');
        const searchRow = document.getElementById('searchFilterRow');
        
        // Reset visibility
        equiposTable.style.display = 'block';
        cartaEntradaFields.style.display = 'none';
        subContainer.style.display = isCartaMantenimiento ? 'block' : 'none';
        filterEmpresa.style.display = isCartaMantenimiento ? 'none' : 'block';
        searchRow.style.display = isCartaMantenimiento ? 'none' : 'flex';
        
        // Mostrar u ocultar botón de exportar Excel
        if (reportType === 'general') {
            exportExcelBtn.style.display = 'block';
        } else {
            exportExcelBtn.style.display = 'none';
        }

        if (isCartaMantenimiento) {
            equiposTable.style.display = 'none';
            cartaEntradaFields.style.display = 'block';
            selectAllBtn.style.display = 'none';
            deselectAllBtn.style.display = 'none';
        } else {
            selectAllBtn.style.display = 'inline-block';
            deselectAllBtn.style.display = 'inline-block';
            cartaEntradaFields.style.display = 'none';
        }
        
        // Siempre permitir selección múltiple para todos los reportes, incluyendo cartas responsivas y de préstamo
        checkboxes.forEach(checkbox => {
            checkbox.removeEventListener('change', enforceSingleSelection);
        });
        
        selectAllBtn.disabled = false;
        deselectAllBtn.disabled = false;
        selectAllCheckbox.disabled = false;
    }
    
    function enforceSingleSelection(e) {
        const checkboxes = document.querySelectorAll('.equipo-checkbox');
        if (e.target.checked) {
            checkboxes.forEach(checkbox => {
                if (checkbox !== e.target) {
                    checkbox.checked = false;
                }
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const reportTypeSelect = document.querySelector('select[name="tipo_reporte"]');
        const maintenanceSubSelect = document.getElementById('mantenimientoSubtype');
        const hiddenCategoria = document.getElementById('hiddenCategoria');
        const titleH2 = document.getElementById('cartaEntradaTitle');

        reportTypeSelect.addEventListener('change', handleReportTypeChange);
        
        maintenanceSubSelect.addEventListener('change', function() {
            syncMaintenanceFields.call(this);
        });

        function syncMaintenanceFields() {
            const val = this.value;
            hiddenCategoria.value = val;
            titleH2.textContent = `Datos de Carta de Mantenimiento (${val})`;
            
            // Toggle specific fields
            const laptopAditamentos = document.getElementById('laptopAditamentos');
            const pcAditamentos = document.getElementById('pcAditamentos');
            const printerAditamentos = document.getElementById('printerAditamentos');
            const laptopChecklist = document.getElementById('laptopChecklist');
            const pcChecklist = document.getElementById('pcChecklist');
            const printerChecklist = document.getElementById('printerChecklist');
            const labChecklist = document.getElementById('labChecklist');
            const printerPruebas = document.getElementById('printerPruebas');
            
            const fieldNombreEquipo = document.getElementById('field_nombre_equipo');
            const fieldContrasena = document.getElementById('field_contrasena');
            const fieldRestauracion = document.getElementById('field_restauracion');
            const sectionRevision = laptopChecklist?.parentElement?.parentElement || null;
            const sectionReparacion = document.getElementById('section_reparacion_y_salida');
            const sectionDiagnostico = document.getElementById('section_diagnostico_final');
            const labelReparacion = document.getElementById('label_reparacion');
            const commonAditamentos = document.getElementById('commonAditamentos'); // Disco duro, RAM, etc.

            // Reset visibility (con protecciones para evitar que rompa el JS si falta algún elemento)
            const clsAddHidden = (el) => { if (el && el.classList) el.classList.add('hidden'); };
            const clsRemoveHidden = (el) => { if (el && el.classList) el.classList.remove('hidden'); };
            const styleDisplay = (el, value) => { if (el && el.style) el.style.display = value; };
            const setText = (el, text) => { if (el) el.textContent = text; };

            clsAddHidden(laptopAditamentos);
            clsAddHidden(pcAditamentos);
            clsAddHidden(printerAditamentos);
            clsAddHidden(laptopChecklist);
            clsAddHidden(pcChecklist);
            clsAddHidden(printerChecklist);
            clsAddHidden(labChecklist);
            clsAddHidden(printerPruebas);
            
            styleDisplay(fieldNombreEquipo, 'none');
            styleDisplay(fieldContrasena, 'block');
            styleDisplay(fieldRestauracion, 'flex');
            styleDisplay(sectionRevision, 'block');
            styleDisplay(sectionReparacion, 'grid');
            styleDisplay(sectionDiagnostico, 'grid');
            styleDisplay(commonAditamentos, 'grid');
            setText(labelReparacion, 'Reparación Realizada');

            if (val === 'PC') {
                clsRemoveHidden(pcAditamentos);
                clsRemoveHidden(pcChecklist);
            } else if (val === 'Impresora') {
                clsRemoveHidden(printerAditamentos);
                clsRemoveHidden(printerChecklist);
                clsRemoveHidden(printerPruebas);
            } else if (val === 'Mantenimiento Preventivo') {
                clsRemoveHidden(pcAditamentos); // Uses PC aditamentos logic
                styleDisplay(fieldNombreEquipo, 'block');
                styleDisplay(fieldContrasena, 'none');
                styleDisplay(fieldRestauracion, 'none');
                styleDisplay(sectionRevision, 'none'); // Simplified version
                styleDisplay(sectionReparacion, 'none');
                styleDisplay(sectionDiagnostico, 'none');
                styleDisplay(commonAditamentos, 'none');
            } else if (val === 'Salida a Laboratorio') {
                clsRemoveHidden(pcAditamentos);
                clsRemoveHidden(labChecklist);
                setText(labelReparacion, 'Motivo de Traslado');
                styleDisplay(sectionDiagnostico, 'none');
                styleDisplay(commonAditamentos, 'none');
                // Hide output validation for lab exit (second child of reparacion section)
                if (sectionReparacion && sectionReparacion.children[1]) sectionReparacion.children[1].style.display = 'none';
                if (sectionReparacion && sectionReparacion.classList) sectionReparacion.classList.replace('md:grid-cols-2', 'md:grid-cols-1');
            } else {
                // Laptop
                clsRemoveHidden(laptopAditamentos);
                clsRemoveHidden(laptopChecklist);
                // Ensure layout is 2 columns for others
                if (sectionReparacion && sectionReparacion.classList) sectionReparacion.classList.replace('md:grid-cols-1', 'md:grid-cols-2');
                if (sectionReparacion && sectionReparacion.children[1]) sectionReparacion.children[1].style.display = 'block';
            }
        }

        // Sync on load
        handleReportTypeChange.call(reportTypeSelect);
        syncMaintenanceFields.call(maintenanceSubSelect);
        document.querySelector('input[name="tipo_reporte"]').value = reportTypeSelect.value;
        hiddenCategoria.value = maintenanceSubSelect.value;
        
        document.getElementById('selectAll').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.equipo-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
            document.getElementById('selectAllCheckbox').checked = true;
        });
        
        document.getElementById('deselectAll').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.equipo-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
        });
        
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.equipo-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Removido el auto-submit del filtro de cliente para permitir búsqueda combinada
        
        document.querySelector('select[name="tipo_reporte"]').addEventListener('change', function() {
            document.querySelector('input[name="tipo_reporte"]').value = this.value;
        });

        // Validación antes de enviar
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const reportType = document.querySelector('select[name="tipo_reporte"]').value;
            // Asegurar que el backend reciba el tipo correcto (hidden input).
            // Evita casos donde por un error JS el hidden queda con 'general'.
            document.querySelector('input[name="tipo_reporte"]').value = reportType;
            
            if (reportType === 'carta_mantenimiento') {
                // Para carta de mantenimiento no necesitamos equipos seleccionados
                return true;
            }

            const checkboxes = document.querySelectorAll('.equipo-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Por favor, seleccione al menos un equipo para generar el reporte.');
            }
        });
        
        // Funcionalidad del botón Exportar Excel
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.equipo-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Por favor, seleccione al menos un equipo para exportar.');
                return;
            }
            
            // Crear un formulario temporal para enviar a exportar_excel.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'exportar_excel.php';
            form.target = '_blank';
            
            // Agregar equipos seleccionados
            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'equipos[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
            
            // Agregar nombre de empresa
            const empresaInput = document.createElement('input');
            empresaInput.type = 'hidden';
            empresaInput.name = 'empresa_nombre';
            empresaInput.value = '<?php echo htmlspecialchars($selectedClient); ?>';
            form.appendChild(empresaInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    });
</script>

<!-- <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 60px 0 0 150px; 
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #1E2A3B;
            padding: 10px 20px;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            height: 60px; 
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
        }
        .nav-links {
            list-style: none;
            display: flex;
            gap: 15px;
        }
        .nav-links li {
            display: inline;
        }
        .nav-links a {
            text-decoration: none;
            color: white;
            padding: 10px;
        }
        .nav-links a:hover {
            background-color: #575757;
            border-radius: 5px;
        }
        .user-menu {
            position: relative;
        }
        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
        }
        .dropdown {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            width: 150px;
            top: 50px;
            z-index: 1000;
        }
        .dropdown a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: black;
        }
        .dropdown a:hover {
            background-color: #f1f1f1;
        }
        .sidebar {
            width: 150px;
            height: 100vh;
            background: #1E2A3B;
            color: white;
            padding: 20px;
            position: fixed;
            top: 60px; 
            left: 0;
            z-index: 999;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            padding: 10px;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .sidebar ul li a i {
            margin-right: 10px;
        }
        .content {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .btn-open-modal {
            background-color: #000;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .btn-open-modal:hover {
            background-color: #333;
        }
        .equipo-checkbox:checked {
            background-color: #4F46E5;
            border-color: #4F46E5;
        }
        
        .equipo-checkbox {
            cursor: pointer;
        }
        
        table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f9fafb;
        } -->
</style>
<?php require_once 'includes/footer.php'; ?>