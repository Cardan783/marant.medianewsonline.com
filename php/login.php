<?php
session_start();
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiar datos de entrada
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        // Buscar usuario por email
        // NOTA: Asegúrate de que tu tabla se llame 'usuarios' y tenga columnas 'email', 'password', 'id', 'nombre'
        $stmt = $conn->prepare("SELECT id, password, nombre, apellido, estado, foto FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verificar si el usuario está activo (asumiendo valor 'activo' o 1)
            if ($user['estado'] != 'activo' && $user['estado'] != 1) {
                header("Location: ../index.php?status=login_error&msg=" . urlencode("Tu cuenta está inactiva. Contacta al administrador."));
                exit();
            }

            // Verificar contraseña
            // Si guardaste las contraseñas en texto plano (sin encriptar), usa: if ($password == $user['password'])
            // Si usaste password_hash(), usa: password_verify($password, $user['password'])
            if (password_verify($password, $user['password']) || $password == $user['password']) {
                
                // ¡Login Exitoso! Guardamos datos en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_lastname'] = $user['apellido']; // Guardamos apellido también
                $_SESSION['user_photo'] = !empty($user['foto']) ? $user['foto'] : 'default.png';
                $_SESSION['just_logged_in'] = true; // Bandera para mostrar bienvenida
                
                // Redirigir al dashboard
                header("Location: ../panel_control.php");
                exit();
            } else {
                header("Location: ../index.php?status=login_error&msg=" . urlencode("Contraseña incorrecta."));
                exit();
            }
        } else {
            header("Location: ../index.php?status=login_error&msg=" . urlencode("El usuario no existe o el email es incorrecto."));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../index.php?status=login_error&msg=" . urlencode("Error de base de datos."));
        exit();
    }
} else {
    // Redirigir si se accede directamente
    header("Location: ../index.php");
    exit();
}
?>