<?php
require_once 'conexion.php';

// --- Lógica para consulta desde ESP32 (GET) ---
// El ESP32 debe llamar a: ingreso_nuevo_cliente.php?mac=DIRECCION_MAC
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['mac'])) {
    $mac = $_GET['mac'];
    
    // Seleccionamos solo el campo 'flag'
    $sql = "SELECT flag FROM desactivaciones WHERE mac = ? LIMIT 1";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mac]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Imprime solo el valor del flag (ej: 1 o 0) para que el ESP32 lo lea
            echo $row['flag'];
        } else {
            echo "0"; // Si no existe el cliente, devolvemos 0 (desactivado) por seguridad
        }
    } catch (PDOException $e) { echo "0"; }
    $conn = null;
    exit; // Detener ejecución para no procesar nada más del script
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Identificar qué acción se está realizando
    $accion = $_POST['accion'] ?? '';

    if ($accion == 'crear') {
        // --- Lógica para Ingresar Nuevo Cliente ---
        $nombre = $_POST['nombre'] ?? '';
        $apellido = $_POST['apellido'] ?? '';
        $identificacion = $_POST['identificacion'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $mac = $_POST['mac'] ?? '';
        $flag = $_POST['flag'] ?? '1'; // Por defecto 1 (Activo)

        $sql = "INSERT INTO desactivaciones (nombre, apellido, identificacion, telefono, direccion, mac, flag) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $apellido, $identificacion, $telefono, $direccion, $mac, $flag]);
            header('Location: nuevo_cliente.php?status=success');
        } catch (PDOException $e) {
            header('Location: nuevo_cliente.php?status=error&msg=' . urlencode($e->getMessage()));
        }

    } elseif ($accion == 'actualizar') {
        // --- Lógica para Actualizar Estado (Desactivaciones) ---
        $identificacion = $_POST['identificacion'] ?? '';
        $mac = $_POST['mac'] ?? ''; // Recibimos la MAC específica
        $flag = $_POST['flag'] ?? '0';

        // Actualizamos SOLO la MAC seleccionada
        $sql = "UPDATE desactivaciones SET flag=? WHERE mac=?";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$flag, $mac]);
            // Redirigir manteniendo la búsqueda para ver el cambio
            header('Location: desactivaciones.php?status=updated&busqueda=' . urlencode($identificacion));
        } catch (PDOException $e) {
            header('Location: desactivaciones.php?status=error&msg=' . urlencode($e->getMessage()));
        }
    }
}
$conn = null;
?>