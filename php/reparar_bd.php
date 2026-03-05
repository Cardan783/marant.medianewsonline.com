<?php
require_once 'conexion.php';

echo "<h2>Reparando Base de Datos...</h2>";

try {
    // 1. Agregar columna 'nombre'
    // Usamos ADD COLUMN y capturamos el error si ya existe para no detener el script
    $conn->exec("ALTER TABLE usuarios ADD COLUMN nombre VARCHAR(100) NULL AFTER id");
    echo "<p style='color:green'>✅ Columna 'nombre' agregada correctamente.</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠️ Nota sobre 'nombre': " . $e->getMessage() . "</p>";
}

try {
    // 2. Agregar columna 'identificacion'
    $conn->exec("ALTER TABLE usuarios ADD COLUMN identificacion VARCHAR(20) NULL AFTER nombre");
    echo "<p style='color:green'>✅ Columna 'identificacion' agregada correctamente.</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠️ Nota sobre 'identificacion': " . $e->getMessage() . "</p>";
}

try {
    // 3. Agregar columna 'estado' con valor por defecto 'activo'
    $conn->exec("ALTER TABLE usuarios ADD COLUMN estado VARCHAR(20) DEFAULT 'activo' AFTER password");
    echo "<p style='color:green'>✅ Columna 'estado' agregada correctamente.</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠️ Nota sobre 'estado': " . $e->getMessage() . "</p>";
}

echo "<hr><p><strong>Proceso finalizado.</strong> Ya puedes intentar registrarte o iniciar sesión.</p>";
echo "<a href='index.php'>Volver al Inicio</a>";
?>