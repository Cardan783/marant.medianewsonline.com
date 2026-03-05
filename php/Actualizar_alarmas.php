<?php
require_once 'conexion.php';

// Asegurar que la respuesta sea siempre JSON para que el JS la pueda leer
header('Content-Type: application/json');

$response = array();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir los datos del formulario
    $equipo_id = $_POST['equipo_id'] ?? null;
    $temperatura = $_POST['temperatura'] ?? null;
    $temp_advertencia = $_POST['temp_advertencia'] ?? null;
    $presion = $_POST['presion'] ?? null;
    $voltaje_max = $_POST['voltaje_max'] ?? null;
    $voltaje_min = $_POST['voltaje_min'] ?? null;

    // Validar que los datos no estén vacíos
    if ($equipo_id !== null && $temperatura !== null && $temp_advertencia !== null && $presion !== null && $voltaje_max !== null && $voltaje_min !== null) {
        try {
            // Verificar si ya existe un registro para este equipo
            $stmt = $conn->prepare("SELECT id FROM alarmas WHERE equipo_id = ?");
            $stmt->execute([$equipo_id]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Actualizar el registro existente
                $sql = "UPDATE alarmas SET Temperatura = ?, Temp_advertencia = ?, Presion = ?, Voltaje_Max = ?, Voltaje_Min = ?, cambio_pendiente = 1 WHERE equipo_id = ?";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$temperatura, $temp_advertencia, $presion, $voltaje_max, $voltaje_min, $equipo_id]);
            } else {
                // Insertar un nuevo registro vinculado al equipo
                $sql = "INSERT INTO alarmas (equipo_id, Temperatura, Temp_advertencia, Presion, Voltaje_Max, Voltaje_Min, cambio_pendiente) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$equipo_id, $temperatura, $temp_advertencia, $presion, $voltaje_max, $voltaje_min]);
            }

            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Alarmas actualizadas correctamente.';
                // Devolver los datos para que el frontend pueda actualizar los inputs si es necesario
                $response['data'] = [
                    'temperatura' => $temperatura,
                    'temp_advertencia' => $temp_advertencia,
                    'presion' => $presion,
                    'voltaje_max' => $voltaje_max,
                    'voltaje_min' => $voltaje_min
                ];
            } else {
                $response['success'] = false;
                $response['error'] = 'No se pudo actualizar la base de datos.';
            }

        } catch (PDOException $e) {
            $response['success'] = false;
            $response['error'] = 'Error de base de datos: ' . $e->getMessage();
        }
    } else {
        $response['success'] = false;
        $response['error'] = 'Faltan datos obligatorios.';
    }
} else {
    $response['success'] = false;
    $response['error'] = 'Método de solicitud no válido.';
}

echo json_encode($response);
$conn = null;
?>
