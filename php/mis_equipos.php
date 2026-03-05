<?php
session_start();

// Habilitar reporte de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';
$usuario_id = $_SESSION['user_id'];
$mensaje = ""; 

// --- Procesar formulario de agregar equipo ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mac_address'])) {
    $mac = trim($_POST['mac_address']);
    $nombre = trim($_POST['nombre_equipo']);

    if (!empty($mac)) {
        try {
            $conn->beginTransaction();

            // 1. Insertar en la tabla equipos vinculado al usuario actual
            $sql = "INSERT INTO equipos (usuario_id, mac_address, nombre_equipo) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$usuario_id, $mac, $nombre]);
            
            // 2. Obtener datos del usuario para registrar en desactivaciones
            $stmt_user = $conn->prepare("SELECT nombre, identificacion FROM usuarios WHERE id = ?");
            $stmt_user->execute([$usuario_id]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

            // Validamos que exista la identificación para mantener la relación lógica con la tabla usuarios
            if ($user_data && !empty($user_data['identificacion'])) {
                // Separar nombre y apellido (estimación simple)
                $parts = explode(' ', $user_data['nombre'], 2);
                $nombre_u = $parts[0];
                $apellido_u = isset($parts[1]) ? $parts[1] : '';

                // 3. Insertar en desactivaciones con flag = 1
                $sql_des = "INSERT INTO desactivaciones (nombre, apellido, identificacion, telefono, direccion, mac, flag) VALUES (?, ?, ?, '', '', ?, 1)";
                $stmt_des = $conn->prepare($sql_des);
                $stmt_des->execute([$nombre_u, $apellido_u, $user_data['identificacion'], $mac]);
            }

            $conn->commit();
            $mensaje = "Equipo agregado y activado correctamente.";
        } catch (PDOException $e) {
            $conn->rollBack();
            if ($e->getCode() == 23000) { // Código de error para duplicados
                $mensaje = "Error: Esa dirección MAC ya está registrada en el sistema.";
            } else {
                $mensaje = "Error al guardar: " . $e->getMessage();
            }
        }
    } else {
        $mensaje = "La dirección MAC es obligatoria.";
    }
}

// --- Obtener lista de equipos del usuario ---
$mis_equipos = [];
try {
    $stmt = $conn->prepare("SELECT * FROM equipos WHERE usuario_id = ? ORDER BY created_at DESC");
    $stmt->execute([$usuario_id]);
    $mis_equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si ya hay un mensaje (ej: error al insertar), lo concatenamos en lugar de sobrescribirlo
    if (empty($mensaje)) {
        $mensaje = "Error al cargar equipos: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Equipos</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .msg { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Bienvenido, <?= htmlspecialchars($_SESSION['email']) ?></h1>
    <a href="login.php?logout=1">Cerrar Sesión</a>
    
    <hr>

    <h3>Registrar Nuevo Equipo</h3>
    <?php if($mensaje): ?><p class="msg"><?= $mensaje ?></p><?php endif; ?>
    
    <form method="POST" action="">
        <label>Dirección MAC (ID del ESP32):</label><br>
        <input type="text" name="mac_address" placeholder="Ej: A1:B2:C3:D4:E5:F6" required>
        <br><br>
        <label>Nombre del Equipo (Alias):</label><br>
        <input type="text" name="nombre_equipo" placeholder="Ej: Motor Principal">
        <br><br>
        <button type="submit">Agregar Equipo</button>
    </form>

    <h3>Mis Equipos Registrados</h3>
    <table>
        <tr>
            <th>Nombre</th>
            <th>MAC Address</th>
            <th>Fecha Registro</th>
        </tr>
        <?php if (count($mis_equipos) > 0): ?>
            <?php foreach ($mis_equipos as $equipo): ?>
            <tr>
                <td><?= htmlspecialchars($equipo['nombre_equipo']) ?></td>
                <td><?= htmlspecialchars($equipo['mac_address']) ?></td>
                <td><?= $equipo['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3">No tienes equipos registrados aún.</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>