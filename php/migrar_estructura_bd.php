<?php
require_once 'conexion.php';

echo "<h2>Iniciando reestructuración de Base de Datos...</h2>";

try {
    // 1. Agregar columnas faltantes a la tabla USUARIOS
    echo "<h3>1. Actualizando tabla USUARIOS...</h3>";
    
    $cols_usuarios = [
        "apellido VARCHAR(100) AFTER nombre",
        "telefono VARCHAR(50) AFTER identificacion",
        "direccion TEXT AFTER telefono"
    ];

    foreach ($cols_usuarios as $col) {
        try {
            $conn->exec("ALTER TABLE usuarios ADD COLUMN $col");
            echo "<p style='color:green'>✅ Columna agregada: " . explode(' ', $col)[0] . "</p>";
        } catch (PDOException $e) {
            echo "<p style='color:orange'>⚠️ La columna ya existía o error menor: " . $e->getMessage() . "</p>";
        }
    }

    // 2. Agregar equipo_id a DESACTIVACIONES
    echo "<h3>2. Actualizando tabla DESACTIVACIONES...</h3>";
    try {
        $conn->exec("ALTER TABLE desactivaciones ADD COLUMN equipo_id INT(11) NULL AFTER id");
        $conn->exec("ALTER TABLE desactivaciones ADD INDEX (equipo_id)");
        echo "<p style='color:green'>✅ Columna equipo_id agregada.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠️ Nota: " . $e->getMessage() . "</p>";
    }

    // 3. MIGRACIÓN DE DATOS (Crucial antes de borrar columnas)
    echo "<h3>3. Migrando datos existentes...</h3>";
    
    // A. Vincular equipo_id en desactivaciones basado en la MAC antigua
    $sql_update_ids = "UPDATE desactivaciones d 
                       JOIN equipos e ON d.mac = e.mac_address 
                       SET d.equipo_id = e.id 
                       WHERE d.equipo_id IS NULL";
    $stmt = $conn->prepare($sql_update_ids);
    $stmt->execute();
    echo "<p>🔄 IDs de equipos vinculados en desactivaciones: " . $stmt->rowCount() . " registros.</p>";

    // B. Mover datos personales de desactivaciones a usuarios
    // Usamos la relación: desactivaciones -> equipos -> usuarios
    $sql_migrate_info = "UPDATE usuarios u
                         JOIN equipos e ON u.id = e.usuario_id
                         JOIN desactivaciones d ON e.mac_address = d.mac
                         SET 
                            u.nombre = COALESCE(NULLIF(d.nombre, ''), u.nombre),
                            u.apellido = COALESCE(NULLIF(d.apellido, ''), u.apellido),
                            u.identificacion = COALESCE(NULLIF(d.identificacion, ''), u.identificacion),
                            u.telefono = COALESCE(NULLIF(d.telefono, ''), u.telefono),
                            u.direccion = COALESCE(NULLIF(d.direccion, ''), u.direccion)
                         WHERE d.nombre IS NOT NULL AND d.nombre != ''";
    
    $stmt_mig = $conn->prepare($sql_migrate_info);
    $stmt_mig->execute();
    echo "<p>🔄 Datos personales migrados a usuarios: " . $stmt_mig->rowCount() . " registros.</p>";

    // 4. LIMPIEZA (Borrar columnas antiguas)
    echo "<h3>4. Limpiando columnas obsoletas en DESACTIVACIONES...</h3>";
    
    $cols_borrar = ['nombre', 'apellido', 'identificacion', 'telefono', 'direccion', 'mac'];
    
    foreach ($cols_borrar as $col) {
        try {
            // Verificamos si existe antes de intentar borrar para evitar errores fatales
            $check = $conn->query("SHOW COLUMNS FROM desactivaciones LIKE '$col'");
            if ($check->rowCount() > 0) {
                $conn->exec("ALTER TABLE desactivaciones DROP COLUMN $col");
                echo "<p style='color:red'>❌ Columna eliminada: $col</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color:red'>Error al borrar $col: " . $e->getMessage() . "</p>";
        }
    }

    echo "<hr><h1>✅ Proceso Terminado Exitosamente</h1>";
    echo "<p>La estructura ahora es:</p>";
    echo "<ul>";
    echo "<li><strong>Usuarios:</strong> id, nombre, apellido, identificacion, telefono, direccion, email, password, estado, created_at</li>";
    echo "<li><strong>Desactivaciones:</strong> id, equipo_id, flag</li>";
    echo "</ul>";
    echo "<a href='gestion_equipos.php'>Ir a Gestión de Equipos</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error Crítico</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>