<?php
require_once 'conexion.php';

  // Define los campos que quieres seleccionar y mostrar.
  // Así es más fácil añadir o quitar campos en el futuro.
  $fields = [
    'over_run', 'alarma_car', 'alarma_sis', 'nombre_BLE','pin_BLE', 'usuario', 'contraseña',
    'wifi_ssid', 'wifi_password', 'nombre_mDNS', 'Notificacion_perdida_wifi',
    'notificacion_solo_led', 'notificacion_solo_led_alarm_temp', 'funcion_1', 'funcion_2', 'funcion_3', 'funcion_4', 'funcion_5'
  ];

  // Construye la consulta SQL seleccionando explícitamente los campos.
  // Usar LIMIT 1 es una buena práctica para tablas de configuración.
  $sql = "SELECT " . implode(', ', $fields) . " FROM config LIMIT 1";

  $stmt = $conn->query($sql);

  // Usamos mysqli_fetch_assoc y comprobamos si se encontró una fila.
  if ($mostrar = $stmt->fetch(PDO::FETCH_ASSOC)) {
      // Reconstruimos el array de valores para asegurar el orden y usamos implode.
      $valores = array_map(fn($field) => $mostrar[$field] ?? '', $fields);
      echo implode(',', $valores);
  }
$conn = null;
?>