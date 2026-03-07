<?php
session_start();
require_once '../php/conexion.php'; // Ruta relativa para salir de 'admin' y entrar a 'php'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_input = trim($_POST['usuario']);
    $password_input = $_POST['password'];

    if (empty($usuario_input) || empty($password_input)) {
        header("Location: login.php?error=Complete todos los campos");
        exit();
    }

    try {
        // Buscar por usuario O email
        $sql = "SELECT * FROM administrador WHERE usuario = ? OR email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$usuario_input, $usuario_input]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            // 1. Verificar Estado
            if ($admin['estado'] == 0) {
                header("Location: login.php?error=Su cuenta de administrador está desactivada.");
                exit();
            }

            // 2. Verificar Contraseña
            if (password_verify($password_input, $admin['password'])) {
                // Login Exitoso
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nombre'] = $admin['nombre'];
                $_SESSION['admin_rol'] = $admin['rol']; // 'superadmin' o 'admin'
                $_SESSION['is_admin'] = true;

                // Actualizar last_login
                $update = $conn->prepare("UPDATE administrador SET last_login = NOW() WHERE id = ?");
                $update->execute([$admin['id']]);

                header("Location: index.php");
                exit();
            } else {
                header("Location: login.php?error=Credenciales incorrectas");
                exit();
            }
        } else {
            header("Location: login.php?error=Credenciales incorrectas");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: login.php?error=Error del sistema");
    }
}
?>