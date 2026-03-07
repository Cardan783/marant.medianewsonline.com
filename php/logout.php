<?php
ob_start();
session_start();

// Cabeceras para evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
    require_once 'conexion.php';
    // Limpiar token de BD si existe sesión (Seguridad)
    if (isset($_SESSION['user_id']) && isset($conn)) {
        $conn->prepare("UPDATE usuarios SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
    }
} catch (Exception $e) {
    // Continuar con el logout aunque falle la BD para no dejar al usuario atrapado
}

// Borrar cookie del navegador
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Destruir sesión
session_unset();
session_destroy();

// Redirección
header("Location: ../index.php");
?>
<script>window.location.replace("../index.php");</script>
<?php
exit();
?>