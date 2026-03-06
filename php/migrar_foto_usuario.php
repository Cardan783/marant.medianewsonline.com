<?php
require_once 'conexion.php';

echo "<h2>Actualizando estructura de Usuarios...</h2>";

try {
    // Agregar columna 'foto' si no existe
    $conn->exec("ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) DEFAULT 'default.png' AFTER email");
    echo "<p style='color:green'>✅ Columna 'foto' agregada correctamente.</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠️ Nota: " . $e->getMessage() . "</p>";
}

// Crear carpeta de imágenes si no existe
$dir = __DIR__ . '/../img/usuarios';
if (!file_exists($dir)) {
    if (mkdir($dir, 0777, true)) {
        echo "<p style='color:green'>✅ Carpeta 'img/usuarios' creada.</p>";
    } else {
        echo "<p style='color:red'>❌ Error al crear carpeta 'img/usuarios'. Verifique permisos.</p>";
    }
} else {
    echo "<p style='color:blue'>ℹ️ La carpeta 'img/usuarios' ya existe.</p>";
}

echo "<hr><a href='../index.php'>Volver al Inicio</a>";
?>