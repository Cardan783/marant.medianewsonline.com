<?php
require_once 'conexion.php';

// Define los campos que quieres seleccionar y mostrar.
// Así es más fácil añadir o quitar campos en el futuro.
$fields = [
  'id', 'equipo_id', 'usuario', 'contraseña', 'wifi_ssid', 'wifi_password', 'nombre_mDNS',
  'nombre_BLE', 'pin_BLE', 'over_run', 'alarma_sis', 'alarma_car',
  'funcion_1', 'funcion_2', 'funcion_3', 'funcion_4', 'funcion_5',
  'notificacion_solo_led', 'notificacion_solo_led_alarm_temp', 'Notificacion_perdida_wifi', 'fecha_modificacion'
];

// Recibimos la MAC del dispositivo (ESP32)
$mac_recibida = isset($_GET['mac']) ? trim($_GET['mac']) : '';

if (empty($mac_recibida)) {
    echo "ERROR: MAC_NO_RECIBIDA";
    exit();
}

try {
    // 1. Buscar el ID del equipo a partir de su MAC
    $query_equipo = "SELECT id FROM equipos WHERE mac_address = :mac_address LIMIT 1";
    $stmt_equipo = $conn->prepare($query_equipo);
    $stmt_equipo->bindParam(':mac_address', $mac_recibida, PDO::PARAM_STR);
    $stmt_equipo->execute();

    if ($stmt_equipo->rowCount() > 0) {
        $fila_equipo = $stmt_equipo->fetch();
        $equipo_id = $fila_equipo['id'];

        // 2. Con el ID, buscar la configuración específica
        $sql = "SELECT " . implode(', ', $fields) . " FROM config WHERE equipo_id = :equipo_id LIMIT 1";
        $stmt_config = $conn->prepare($sql);
        $stmt_config->bindParam(':equipo_id', $equipo_id, PDO::PARAM_INT);
        $stmt_config->execute();

        if ($config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
            // Reconstruimos el array de valores para asegurar el orden y usamos implode.
            $valores = array_map(fn($field) => $config[$field] ?? '', $fields);
            echo implode(',', $valores);
        } else {
            echo "ERROR: CONFIG_NO_ENCONTRADA";
        }
    } else {
        echo "ERROR: EQUIPO_NO_REGISTRADO";
    }
} catch (PDOException $e) {
    echo "ERROR: BD_FALLO";
}
$conn = null;
?>