<?php
require_once 'conexion.php';

$usuario = 'superadmin'; // El usuario a resetear
$nueva_password = 'admin123'; // La nueva contraseña

echo "<h2>Restableciendo contraseña...</h2>";

try {
    // 1. Verificar si el usuario existe
    $stmt = $conn->prepare("SELECT id FROM administrador WHERE usuario = ?");
    $stmt->execute([$usuario]);
    
    if ($stmt->rowCount() > 0) {
        // 2. Actualizar contraseña
        $hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE administrador SET password = ? WHERE usuario = ?");
        $update->execute([$hash, $usuario]);
        
        echo "<div style='color: green; border: 1px solid green; padding: 10px; background: #e9f7ef;'>";
        echo "✅ <strong>Éxito:</strong> La contraseña para el usuario '<strong>$usuario</strong>' se ha cambiado a: <strong>$nueva_password</strong>";
        echo "</div>";
    } else {
        echo "<div style='color: red; border: 1px solid red; padding: 10px; background: #fce8e6;'>";
        echo "❌ <strong>Error:</strong> El usuario '$usuario' no existe en la base de datos.<br>";
        echo "Por favor, ejecuta primero el archivo <a href='setup_admin_db.php'>setup_admin_db.php</a> para crear el usuario inicial.";
        echo "</div>";
    }
    echo "<br><a href='../admin/login.php'>Ir al Login</a>";

} catch (PDOException $e) {
    echo "Error de base de datos: " . $e->getMessage();
}
?>