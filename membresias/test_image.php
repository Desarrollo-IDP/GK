<?php
// Script de prueba para verificar que las imágenes se carguen correctamente
echo "<h1>Prueba de carga de imágenes</h1>";

// Listar algunas fotos de ejemplo
$fotos = [
    'uploads/fotos/foto_68b5db7c1ae10.png',
    'uploads/fotos/foto_68a76be60ad12.png',
    'uploads/fotos/foto_68a7522208aa1.png'
];

foreach ($fotos as $foto) {
    echo "<h3>Probando: $foto</h3>";
    
    if (file_exists($foto)) {
        echo "<p>✅ Archivo existe</p>";
        echo "<img src='$foto' alt='Test' style='width: 100px; height: 100px; object-fit: cover; border: 2px solid #333;'>";
    } else {
        echo "<p>❌ Archivo NO existe</p>";
    }
    
    echo "<hr>";
}

// Verificar directorio actual
echo "<h3>Directorio actual:</h3>";
echo "<p>" . getcwd() . "</p>";

// Verificar si existe el directorio uploads
echo "<h3>Directorio uploads:</h3>";
if (is_dir('uploads')) {
    echo "<p>✅ Directorio uploads existe</p>";
} else {
    echo "<p>❌ Directorio uploads NO existe</p>";
}

// Verificar si existe el directorio uploads/fotos
echo "<h3>Directorio uploads/fotos:</h3>";
if (is_dir('uploads/fotos')) {
    echo "<p>✅ Directorio uploads/fotos existe</p>";
} else {
    echo "<p>❌ Directorio uploads/fotos NO existe</p>";
}
?>
