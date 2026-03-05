<?php
// Permitir el acceso desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'conexion.php';

// Nombre de la tabla de destino 
$tableName = "Ajax_2seg"; 

// --- 1. RECEPCIÓN DE DATOS ---

// Usar $_POST para recepción
$temperatura = isset($_POST['temperatura']) ? $_POST['temperatura'] : null;
$presion     = isset($_POST['presion']) ? $_POST['presion'] : null;
$voltaje     = isset($_POST['voltaje']) ? $_POST['voltaje'] : null;

// --- 2. VALIDACIÓN DE DATOS ---
if ($temperatura === null || $presion === null || $voltaje === null) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false, 
        "error" => "Faltan parámetros. Se esperan 'temp', 'pres' y 'volt'."
    ]);
    die();
}


// --- 4. PREPARAR Y EJECUTAR LA CONSULTA UPDATE ---

// **CORRECCIÓN FINAL:** La consulta SOLO actualiza las columnas de datos.
// Se asume que esta tabla tiene una única fila que debe ser actualizada.
$stmt = $conn->prepare("UPDATE " . $tableName . " SET Temperatura = ?, Presion = ?, Voltaje = ?");

// 'ddd' significa: double, double, double (para los parámetros Temp, Pres, Volt).
$stmt->bind_param("ddd", $temperatura, $presion, $voltaje);

if ($stmt->execute()) {
    // Éxito al actualizar
    http_response_code(200);
    echo json_encode([
        "success" => true, 
        "message" => "Datos de sensores actualizados correctamente en la tabla " . $tableName,
        "updated_rows" => $stmt->affected_rows
    ]);
} else {
    // Error en la consulta
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => "Error al ejecutar la consulta de actualización: " . $stmt->error
    ]);
}

// Cerrar la sentencia y la conexión
$stmt->close();
$conn->close();

?>