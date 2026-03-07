<?php
session_start();
require_once 'conexion.php';

$equipo_id = isset($_GET['equipo_id']) ? $_GET['equipo_id'] : null;

if ($equipo_id) {
    // SEGURIDAD: Verificar sesión y propiedad
    if (!isset($_SESSION['user_id'])) {
        exit; // Silencioso para no romper JS o devolver error simple
    }
    $stmt_check = $conn->prepare("SELECT id FROM equipos WHERE id = ? AND usuario_id = ?");
    $stmt_check->execute([$equipo_id, $_SESSION['user_id']]);
    if ($stmt_check->rowCount() === 0) exit;

    // Consulta filtrada por equipo
    $sql = "SELECT Temperatura, Temp_advertencia, Presion, Voltaje_Max, Voltaje_Min FROM alarmas WHERE equipo_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$equipo_id]);
} else {
    // Fallback (no debería usarse si el flujo es correcto)
    $stmt = null;
}

try {
    if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Imprimir datos crudos separados por coma
        echo implode(',', $row);
    }
} catch (PDOException $e) {
    // En caso de error, no devolvemos nada para no romper el JS del cliente
}
$conn = null;
?>
