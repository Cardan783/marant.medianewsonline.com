<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'conexion.php';

echo "<h2>Reparación de Acceso Admin</h2>";

try {
    // 1. Asegurar que la columna password tenga longitud suficiente (60+ caracteres)
    // Si la tabla se creó con varchar(50) o menos, el hash se corta y da error siempre.
    $conn->exec("ALTER TABLE administrador MODIFY COLUMN password VARCHAR(255)");
    echo "<p>✅ Columna 'password' ajustada a VARCHAR(255).</p>";

    // 2. Verificar si el usuario existe
    $usuario = 'superadmin';
    $pass_plana = 'admin123';
    
    $stmt = $conn->prepare("SELECT id FROM administrador WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $exists = $stmt->fetch();

    $hash = password_hash($pass_plana, PASSWORD_DEFAULT);

    if ($exists) {
        // Actualizar
        $update = $conn->prepare("UPDATE administrador SET password = ?, estado = 1 WHERE usuario = ?");
        $update->execute([$hash, $usuario]);
        echo "<p>✅ Contraseña actualizada para '<strong>$usuario</strong>'.</p>";
    } else {
        // Crear si no existe
        $insert = $conn->prepare("INSERT INTO administrador (usuario, password, nombre, email, rol, estado) VALUES (?, ?, 'Super Admin', 'admin@sampatv.com', 'superadmin', 1)");
        $insert->execute([$usuario, $hash]);
        echo "<p>✅ Usuario '<strong>$usuario</strong>' creado.</p>";
    }

    // 3. Prueba de verificación
    if (password_verify($pass_plana, $hash)) {
        echo "<div style='background:#d4edda; color:#155724; padding:10px; border:1px solid #c3e6cb; margin-top:10px;'>";
        echo "<strong>¡Reparación Exitosa!</strong><br>Intenta ingresar ahora con:<br>Usuario: $usuario<br>Pass: $pass_plana";
        echo "</div>";
    }

    echo "<br><a href='../admin/login.php'>Ir al Login</a>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error SQL: " . $e->getMessage() . "</p>";
}
?>