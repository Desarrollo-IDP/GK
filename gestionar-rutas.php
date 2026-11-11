<?php
require_once __DIR__ . '/auth.php';
checkAdmin();

$configFile = __DIR__ . '/route_permissions.php';

// Cargar configuración actual
$roleToAllowedRoutes = [
    'administrador' => ['*'],
    'supervisor' => ['/Inventario/', '/tickets/'],
    'visualizador' => ['/Inventario/']
];

if (file_exists($configFile)) {
    $loaded = include $configFile;
    if (is_array($loaded)) {
        $roleToAllowedRoutes = $loaded;
    }
}

$message = '';
$messageType = '';

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supervisorInventario = isset($_POST['supervisor_inventario']);
    $supervisorTickets = isset($_POST['supervisor_tickets']);
    $visualizadorInventario = isset($_POST['visualizador_inventario']);
    $visualizadorTickets = isset($_POST['visualizador_tickets']);

    $newConfig = [
        'administrador' => ['*'],
        'supervisor' => array_values(array_filter([
            $supervisorInventario ? '/Inventario/' : null,
            $supervisorTickets ? '/tickets/' : null,
        ])),
        'visualizador' => array_values(array_filter([
            $visualizadorInventario ? '/Inventario/' : null,
            $visualizadorTickets ? '/tickets/' : null,
        ])),
    ];

    // Construir contenido PHP del archivo de configuración
    $export = var_export($newConfig, true);
    $fileContents = "<?php\n// Configuración editada desde gestionar-rutas.php\nreturn " . $export . ";\n?>\n";

    $tmpFile = $configFile . '.tmp';
    $written = @file_put_contents($tmpFile, $fileContents, LOCK_EX);
    if ($written !== false) {
        if (@rename($tmpFile, $configFile)) {
            $roleToAllowedRoutes = $newConfig;
            $message = 'Permisos de rutas actualizados correctamente.';
            $messageType = 'success';
        } else {
            @unlink($tmpFile);
            $message = 'No se pudieron guardar los cambios (permiso de escritura).';
            $messageType = 'error';
        }
    } else {
        $message = 'Error al escribir el archivo temporal. Verifique permisos de carpeta.';
        $messageType = 'error';
    }
}

function isRouteAllowed(array $config, string $role, string $routePrefix): bool {
    $allowed = $config[$role] ?? [];
    if ($allowed === '*') return true;
    if (is_array($allowed) && in_array('*', $allowed, true)) return true;
    if (!is_array($allowed)) return false;
    foreach ($allowed as $prefix) {
        if (is_string($prefix) && strpos($routePrefix, $prefix) === 0 || strpos($prefix, $routePrefix) === 0) {
            return true;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Rutas por Rol</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="bg-gray-800 text-white py-4 mb-6">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-route mr-2"></i>
                Gestión de Rutas por Rol
            </h1>
            <div class="space-x-2">
                <a href="<?php echo htmlspecialchars(appUrl('/gestion-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">
                    <i class="fas fa-users mr-2"></i> Gestión de Usuarios
                </a>
                <a href="<?php echo htmlspecialchars(appUrl('/direc.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4">
        <?php if ($message): ?>
            <div class="<?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200'; ?> border rounded p-4 mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Permisos por Rol</h2>
            <form method="POST">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">/Inventario/</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">/tickets/</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notas</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="py-4 px-6 whitespace-nowrap font-medium">Administrador</td>
                            <td class="py-4 px-6 whitespace-nowrap">Permitido (total)</td>
                            <td class="py-4 px-6 whitespace-nowrap">Permitido (total)</td>
                            <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500">Acceso completo, no editable</td>
                        </tr>
                        <tr>
                            <td class="py-4 px-6 whitespace-nowrap font-medium">Supervisor</td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <input type="checkbox" name="supervisor_inventario" <?php echo isRouteAllowed($roleToAllowedRoutes, 'supervisor', '/Inventario/') ? 'checked' : ''; ?> />
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <input type="checkbox" name="supervisor_tickets" <?php echo isRouteAllowed($roleToAllowedRoutes, 'supervisor', '/tickets/') ? 'checked' : ''; ?> />
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500">Selecciona secciones permitidas</td>
                        </tr>
                        <tr>
                            <td class="py-4 px-6 whitespace-nowrap font-medium">Visualizador</td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <input type="checkbox" name="visualizador_inventario" <?php echo isRouteAllowed($roleToAllowedRoutes, 'visualizador', '/Inventario/') ? 'checked' : ''; ?> />
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <input type="checkbox" name="visualizador_tickets" <?php echo isRouteAllowed($roleToAllowedRoutes, 'visualizador', '/tickets/') ? 'checked' : ''; ?> />
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500">Selecciona secciones permitidas</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex justify-end space-x-3 mt-6">
                    <a href="<?php echo htmlspecialchars(appUrl('/gestion-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Cancelar</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

