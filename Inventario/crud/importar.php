<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$pageTitle = 'Importar Datos';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    if ($_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo']['tmp_name'];
        
        // Verificar si existe columna id_empresa_usuario
        $check_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
        $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
        
        if (($handle = fopen($archivo, "r")) !== FALSE) {
            $conn->begin_transaction();
            try {
                $header = fgetcsv($handle);
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Insertar con id_empresa_usuario si existe el campo
                    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
                        $stmt = $conn->prepare("INSERT INTO empresas (nombre, direccion, id_empresa_usuario) VALUES (?, ?, ?)");
                        $stmt->bind_param("ssi", $data[0], $data[1], $user_empresa_id);
                    } else {
                    $stmt = $conn->prepare("INSERT INTO empresas (nombre, direccion) VALUES (?, ?)");
                    $stmt->bind_param("ss", $data[0], $data[1]);
                    }
                    
                    if (!$stmt->execute()) {
                        if ($conn->errno === 1062) { 
                            $stmt = $conn->prepare("SELECT id_empresa FROM empresas WHERE nombre = ?");
                            $stmt->bind_param("s", $data[0]);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $empresa = $result->fetch_assoc();
                            $id_empresa = $empresa['id_empresa'];
                        } else {
                            throw new Exception("Error al insertar empresa: " . $stmt->error);
                        }
                    } else {
                        $id_empresa = $conn->insert_id;
                    }
                    if (!empty($data[2]) && !empty($data[3]) && !empty($data[4])) {
                        if (!upsertContactoEmpresa($conn, $id_empresa, $data[2], $data[3], $data[4], CONTACTO_ORIGEN_EMPRESAS)) {
                            throw new Exception("Error al insertar contacto");
                        }
                    }
                }
                
                $conn->commit();
                $mensaje = "Importaci��n completada exitosamente.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
            fclose($handle);
        }
    }
}
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4 text-gray-800">Importar Datos</h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="archivo">
                    Archivo CSV
                </label>
                <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition duration-300">
                    <input type="file" 
                           id="archivo" 
                           name="archivo" 
                           accept=".csv"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <i class="fas fa-file-csv text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-600">Arrastra tu archivo CSV aqu�� o haz clic para seleccionarlo.</p>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <i class="fas fa-upload mr-2"></i>Importar
                </button>
            </div>
        </form>
        
        <div class="mt-6">
            <h2 class="text-lg font-bold mb-2 text-gray-800">Formato del CSV:</h2>
            <p class="text-gray-600 mb-4">El archivo CSV debe tener las siguientes columnas:</p>
            <ul class="list-disc list-inside ml-4 text-gray-600">
                <li>Nombre de la Empresa</li>
                <li>Direcci��n</li>
                <li>Nombre del Contacto (opcional)</li>
                <li>Tel��fono (opcional)</li>
                <li>Correo Electr��nico (opcional)</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>