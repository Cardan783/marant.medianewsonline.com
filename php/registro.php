<?php
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    
    // Capturar y concatenar identificación (Ej: V-12345678)
    $tipo_id = $_POST['tipo_identificacion'] ?? '';
    $num_id = trim($_POST['identificacion'] ?? '');
    $identificacion = $tipo_id . $num_id;

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $codigo_registro = trim($_POST['codigo_registro'] ?? '');
    
    // --- SEGURIDAD: Validar contra Inventario ---
    // Verificamos si el código existe en la base de datos de equipos
    $stmt_check_code = $conn->prepare("SELECT id FROM inventario_equipos WHERE codigo_registro = ?");
    $stmt_check_code->execute([$codigo_registro]);
    $code_exists = $stmt_check_code->fetch();

    if (!$code_exists) {
        header("Location: ../index.php?status=register_error&msg=" . urlencode("Error: El código de registro ingresado no es válido. Verifique su manual de usuario."));
        exit();
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../index.php?status=register_error&msg=" . urlencode("El formato del correo electrónico no es válido."));
        exit();
    } elseif ($password !== $confirm_password) {
        header("Location: ../index.php?status=register_error&msg=" . urlencode("Las contraseñas no coinciden."));
        exit();
    } else {
        // Verificar si el usuario ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: ../index.php?status=register_error&msg=" . urlencode("El correo ya está registrado."));
            exit();
        } else {
            // Encriptar contraseña
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $sql = "INSERT INTO usuarios (nombre, identificacion, email, password, estado) VALUES (?, ?, ?, ?, 'activo')";
                $conn->prepare($sql)->execute([$nombre, $identificacion, $email, $hash]);
                header("Location: ../index.php?status=register_success");
                exit();
            } catch (PDOException $e) {
                header("Location: ../index.php?status=register_error&msg=" . urlencode("Error al registrar: " . $e->getMessage()));
                exit();
            }
        }
    }
}
else {
    header("Location: ../index.php");
    exit();
}
?>