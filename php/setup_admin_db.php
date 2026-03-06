<?php
require_once 'conexion.php';

echo "<h2>Configurando Tabla de Administradores...</h2>";

try {
    // 1. Modificar/Crear tabla para hacerla profesional
    // Se asume que ya existe id, usuario, password. Agregamos lo que falta.
    
    // Intentamos agregar columnas. Si fallan es porque ya existen (ignoramos warnings con try/catch por bloque)
    $columnas = [
        "ADD COLUMN nombre VARCHAR(100) AFTER id",
        "ADD COLUMN email VARCHAR(150) AFTER usuario",
        "ADD COLUMN rol ENUM('superadmin', 'admin', 'editor') DEFAULT 'admin' AFTER password",
        "ADD COLUMN estado TINYINT(1) DEFAULT 1 AFTER rol", // 1: Activo, 0: Inactivo
        "ADD COLUMN last_login DATETIME NULL",
        "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columnas as $sql) {
        try {
            $conn->exec("ALTER TABLE administrador $sql");
            echo "<p style='color:green'>✅ Columna agregada/verificada.</p>";
        } catch (PDOException $e) {
            // Ignorar si la columna ya existe
        }
    }

    // 2. Crear el SUPER USUARIO por defecto
    $usuario = 'superadmin';
    $password = 'admin123'; // Contraseña temporal
    $email = 'admin@sampatv.com';
    
    // Verificar si ya existe
    $stmt = $conn->prepare("SELECT id FROM administrador WHERE usuario = ?");
    $stmt->execute([$usuario]);
    
    if ($stmt->rowCount() == 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql_insert = "INSERT INTO administrador (nombre, usuario, email, password, rol, estado) VALUES (?, ?, ?, ?, 'superadmin', 1)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->execute(['Super Administrador', $usuario, $email, $hash]);
        
        echo "<div style='background:#d4edda; padding:15px; border:1px solid #c3e6cb; margin-top:10px;'>";
        echo "<h3>¡Super Usuario Creado!</h3>";
        echo "<p><strong>Usuario:</strong> superadmin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<p><em>Por favor, cambia esta contraseña inmediatamente al ingresar.</em></p>";
        echo "</div>";
    } else {
        echo "<p style='color:blue'>ℹ️ El superusuario ya existe.</p>";
    }

    echo "<br><a href='../admin/login.php'>Ir al Login de Administración</a>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error Crítico: " . $e->getMessage() . "</p>";
}
?>