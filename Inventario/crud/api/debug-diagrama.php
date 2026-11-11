<?php
require_once '../includes/db.php';
require_once '../../../auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

echo "<h2>Debug Diagrama ID: $id</h2>";

if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, archivo_url, archivo_binario, mime_type FROM empresa_memoria_tecnica WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo "<h3>Datos en BD:</h3>";
        echo "<ul>";
        echo "<li>ID: " . $row['id'] . "</li>";
        echo "<li>archivo_url: " . htmlspecialchars($row['archivo_url']) . "</li>";
        echo "<li>tiene archivo_binario: " . (!empty($row['archivo_binario']) ? "SÍ" : "NO") . "</li>";
        echo "<li>mime_type: " . htmlspecialchars($row['mime_type']) . "</li>";
        echo "</ul>";
        
        if (!empty($row['archivo_url'])) {
            $filePath = "../" . $row['archivo_url'];
            echo "<h3>Verificación de Archivo:</h3>";
            echo "<ul>";
            echo "<li>Ruta relativa calculada: $filePath</li>";
            echo "<li>Ruta absoluta: " . realpath('../' . $row['archivo_url']) . "</li>";
            echo "<li>¿Existe archivo?: " . (file_exists($filePath) ? "SÍ ✓" : "NO ✗") . "</li>";
            
            if (file_exists($filePath)) {
                echo "<li>Tamaño: " . filesize($filePath) . " bytes</li>";
                echo "<li>Permisos: " . substr(sprintf('%o', fileperms($filePath)), -4) . "</li>";
            }
            echo "</ul>";
        }
        
        echo "<h3>Diagnóstico:</h3>";
        if (!empty($row['archivo_url']) && file_exists("../" . $row['archivo_url'])) {
            echo "<p style='color: green'>✓ Archivo encontrado correctamente</p>";
        } else if (!empty($row['archivo_binario'])) {
            echo "<p style='color: orange'>⚠ Archivo no existe en disco, pero hay datos binarios en BD</p>";
        } else {
            echo "<p style='color: red'>✗ Error: No hay archivo físico ni datos binarios</p>";
        }
        
    } else {
        echo "<p style='color: red'>✗ No se encontró registro con ID: $id</p>";
    }
} else {
    echo "<p style='color: red'>✗ ID no válido</p>";
}

?>
